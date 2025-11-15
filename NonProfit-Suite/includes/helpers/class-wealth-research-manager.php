<?php
/**
 * Wealth Research Manager
 *
 * Coordinates wealth research operations across multiple providers,
 * manages caching, tracks costs, and integrates with contact records.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 * @since 1.18.0
 */

namespace NonprofitSuite\Helpers;

class NS_Wealth_Research_Manager {

	/**
	 * Database instance
	 *
	 * @var \wpdb
	 */
	private $db;

	/**
	 * Settings table name
	 *
	 * @var string
	 */
	private $settings_table;

	/**
	 * Reports table name
	 *
	 * @var string
	 */
	private $reports_table;

	/**
	 * Cache duration (30 days default)
	 *
	 * @var int
	 */
	private $cache_duration = 2592000; // 30 days in seconds

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->db             = $wpdb;
		$this->settings_table = $wpdb->prefix . 'ns_wealth_research_settings';
		$this->reports_table  = $wpdb->prefix . 'ns_wealth_research_reports';
	}

	/**
	 * Research a contact
	 *
	 * Main entry point for wealth research operations.
	 *
	 * @param int    $contact_id     Contact ID to research
	 * @param string $depth          Research depth (basic, standard, comprehensive)
	 * @param bool   $force_refresh  Force new research (bypass cache)
	 * @return array Research results
	 */
	public function research_contact( $contact_id, $depth = 'basic', $force_refresh = false ) {
		// Get contact information
		$contact = $this->get_contact_info( $contact_id );
		if ( ! $contact ) {
			return array(
				'success' => false,
				'error'   => 'Contact not found',
			);
		}

		// Check for cached report if not forcing refresh
		if ( ! $force_refresh ) {
			$cached = $this->get_cached_report( $contact_id, $depth );
			if ( $cached ) {
				return array(
					'success'    => true,
					'cached'     => true,
					'report'     => $cached,
					'contact_id' => $contact_id,
				);
			}
		}

		// Get active provider
		$provider = $this->get_active_provider( $contact['organization_id'] );
		if ( ! $provider ) {
			return array(
				'success' => false,
				'error'   => 'No active wealth research provider configured',
			);
		}

		// Check usage limits
		$usage_check = $this->check_usage_limits( $provider );
		if ( ! $usage_check['allowed'] ) {
			return array(
				'success' => false,
				'error'   => $usage_check['message'],
			);
		}

		// Get adapter instance
		$adapter = $this->get_adapter( $provider );
		if ( ! $adapter ) {
			return array(
				'success' => false,
				'error'   => 'Provider adapter not available',
			);
		}

		// Perform research based on depth
		$result = $this->perform_research( $adapter, $contact, $depth );

		if ( $result['success'] ) {
			// Save report to database
			$report_id = $this->save_report( $contact_id, $provider, $result, $depth );

			// Update contact with capacity rating
			$this->update_contact_capacity( $contact_id, $result );

			// Update usage tracking
			$this->track_usage( $provider, $result['cost'] ?? 0 );

			return array(
				'success'    => true,
				'cached'     => false,
				'report_id'  => $report_id,
				'report'     => $result,
				'contact_id' => $contact_id,
			);
		}

		return $result;
	}

	/**
	 * Get giving capacity for a contact
	 *
	 * @param int $contact_id Contact ID
	 * @return array Capacity information
	 */
	public function get_giving_capacity( $contact_id ) {
		// Check for recent capacity report
		$report = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->reports_table}
				WHERE contact_id = %d
				AND report_type IN ('screening', 'capacity_rating')
				AND expires_at > NOW()
				ORDER BY researched_at DESC
				LIMIT 1",
				$contact_id
			)
		);

		if ( $report ) {
			return array(
				'success'          => true,
				'capacity_rating'  => $report->giving_capacity_rating,
				'capacity_range'   => $this->get_capacity_range( $report->giving_capacity_rating ),
				'confidence_score' => $report->confidence_score,
				'researched_at'    => $report->researched_at,
			);
		}

		// No cached data - perform capacity research
		return $this->research_contact( $contact_id, 'capacity' );
	}

	/**
	 * Find major gift prospects
	 *
	 * Search contacts for high-capacity donors.
	 *
	 * @param array $criteria Search criteria
	 * @return array List of prospects
	 */
	public function find_major_gift_prospects( $criteria = array() ) {
		$defaults = array(
			'min_capacity'       => 'B', // Minimum B rating
			'organization_id'    => 0,
			'limit'              => 50,
			'exclude_researched' => false, // Exclude already researched?
		);

		$criteria = wp_parse_args( $criteria, $defaults );

		// Build query
		$sql = "SELECT c.*, r.giving_capacity_rating, r.estimated_income_range, r.estimated_net_worth_range
				FROM {$this->db->prefix}ns_contacts c
				LEFT JOIN {$this->reports_table} r ON c.id = r.contact_id AND r.expires_at > NOW()
				WHERE c.organization_id = %d";

		$params = array( $criteria['organization_id'] );

		if ( $criteria['exclude_researched'] ) {
			$sql .= " AND r.id IS NULL";
		} else {
			// Filter by capacity rating
			$capacity_values = array( 'A+', 'A', 'B', 'C', 'D' );
			$min_index       = array_search( $criteria['min_capacity'], $capacity_values, true );
			if ( false !== $min_index ) {
				$allowed_ratings = array_slice( $capacity_values, 0, $min_index + 1 );
				$placeholders    = implode( ',', array_fill( 0, count( $allowed_ratings ), '%s' ) );
				$sql            .= " AND r.giving_capacity_rating IN ($placeholders)";
				$params          = array_merge( $params, $allowed_ratings );
			}
		}

		$sql .= " ORDER BY FIELD(r.giving_capacity_rating, 'A+', 'A', 'B', 'C', 'D') LIMIT %d";
		$params[] = $criteria['limit'];

		$prospects = $this->db->get_results( $this->db->prepare( $sql, $params ) );

		return array(
			'success'   => true,
			'prospects' => $prospects,
			'count'     => count( $prospects ),
		);
	}

	/**
	 * Batch screen contacts
	 *
	 * Screen multiple contacts at once.
	 *
	 * @param array $contact_ids Array of contact IDs
	 * @return array Screening results
	 */
	public function batch_screen_contacts( $contact_ids ) {
		$results = array(
			'success'   => array(),
			'failed'    => array(),
			'skipped'   => array(),
			'total_cost' => 0,
		);

		foreach ( $contact_ids as $contact_id ) {
			// Check if already has recent screening
			$existing = $this->get_cached_report( $contact_id, 'basic' );
			if ( $existing ) {
				$results['skipped'][] = $contact_id;
				continue;
			}

			// Perform screening
			$result = $this->research_contact( $contact_id, 'basic' );

			if ( $result['success'] ) {
				$results['success'][]  = $contact_id;
				$results['total_cost'] += $result['report']['cost'] ?? 0;
			} else {
				$results['failed'][] = array(
					'contact_id' => $contact_id,
					'error'      => $result['error'] ?? 'Unknown error',
				);
			}

			// Rate limiting - sleep between requests
			sleep( 1 );
		}

		$results['summary'] = sprintf(
			'Screened %d contacts. Success: %d, Failed: %d, Skipped: %d. Total cost: $%.2f',
			count( $contact_ids ),
			count( $results['success'] ),
			count( $results['failed'] ),
			count( $results['skipped'] ),
			$results['total_cost']
		);

		return $results;
	}

	/**
	 * Get research summary for a contact
	 *
	 * @param int $contact_id Contact ID
	 * @return array Research summary
	 */
	public function get_research_summary( $contact_id ) {
		$reports = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->reports_table}
				WHERE contact_id = %d
				ORDER BY researched_at DESC",
				$contact_id
			)
		);

		if ( empty( $reports ) ) {
			return array(
				'researched'    => false,
				'report_count'  => 0,
				'last_research' => null,
			);
		}

		$latest = $reports[0];

		return array(
			'researched'           => true,
			'report_count'         => count( $reports ),
			'last_research'        => $latest->researched_at,
			'capacity_rating'      => $latest->giving_capacity_rating,
			'income_range'         => $latest->estimated_income_range,
			'net_worth_range'      => $latest->estimated_net_worth_range,
			'philanthropic_score'  => $this->calculate_philanthropic_score( $latest ),
			'is_current'           => strtotime( $latest->expires_at ) > time(),
		);
	}

	/**
	 * Update contact with research data
	 *
	 * @param int $contact_id Contact ID
	 * @param int $report_id  Report ID
	 * @return bool Success
	 */
	public function update_contact_with_research( $contact_id, $report_id ) {
		$report = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->reports_table} WHERE id = %d",
				$report_id
			)
		);

		if ( ! $report ) {
			return false;
		}

		// Update contact custom fields
		$this->db->update(
			$this->db->prefix . 'ns_contacts',
			array(
				'wealth_capacity_rating' => $report->giving_capacity_rating,
				'estimated_income'       => $report->estimated_income_range,
				'estimated_net_worth'    => $report->estimated_net_worth_range,
			),
			array( 'id' => $contact_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get active provider for organization
	 *
	 * @param int $organization_id Organization ID
	 * @return object|null Provider settings
	 */
	private function get_active_provider( $organization_id ) {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->settings_table}
				WHERE organization_id = %d AND is_active = 1
				LIMIT 1",
				$organization_id
			)
		);
	}

	/**
	 * Get adapter instance for provider
	 *
	 * @param object $provider Provider settings
	 * @return object|null Adapter instance
	 */
	private function get_adapter( $provider ) {
		$config = array(
			'api_key'         => $provider->api_key,
			'api_secret'      => $provider->api_secret,
			'api_endpoint'    => $provider->api_endpoint,
			'organization_id' => $provider->organization_id,
		);

		// Parse additional settings
		if ( ! empty( $provider->settings ) ) {
			$additional = json_decode( $provider->settings, true );
			if ( is_array( $additional ) ) {
				$config = array_merge( $config, $additional );
			}
		}

		switch ( $provider->provider ) {
			case 'wealthengine':
				require_once plugin_dir_path( __FILE__ ) . '../integrations/adapters/class-wealthengine-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_WealthEngine_Adapter( $config );

			case 'donorsearch':
				require_once plugin_dir_path( __FILE__ ) . '../integrations/adapters/class-donorsearch-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_DonorSearch_Adapter( $config );

			case 'blackbaud':
				require_once plugin_dir_path( __FILE__ ) . '../integrations/adapters/class-blackbaud-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_Blackbaud_Adapter( $config );

			default:
				return null;
		}
	}

	/**
	 * Perform research operation
	 *
	 * @param object $adapter Adapter instance
	 * @param array  $contact Contact information
	 * @param string $depth   Research depth
	 * @return array Research results
	 */
	private function perform_research( $adapter, $contact, $depth ) {
		$params = array(
			'first_name' => $contact['first_name'],
			'last_name'  => $contact['last_name'],
			'email'      => $contact['email'],
			'address'    => $contact['address'],
			'city'       => $contact['city'],
			'state'      => $contact['state'],
			'zip'        => $contact['zip'],
		);

		switch ( $depth ) {
			case 'basic':
			case 'screening':
				return $adapter->screen_individual( $params );

			case 'capacity':
			case 'capacity_rating':
				return $adapter->get_capacity_rating( $params );

			case 'philanthropy':
			case 'philanthropic':
				return $adapter->get_philanthropic_history( $params );

			case 'comprehensive':
			case 'full':
				return $adapter->get_profile( $params );

			default:
				return $adapter->screen_individual( $params );
		}
	}

	/**
	 * Get cached report
	 *
	 * @param int    $contact_id Contact ID
	 * @param string $report_type Report type
	 * @return object|null Cached report
	 */
	private function get_cached_report( $contact_id, $report_type ) {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->reports_table}
				WHERE contact_id = %d
				AND report_type = %s
				AND expires_at > NOW()
				ORDER BY researched_at DESC
				LIMIT 1",
				$contact_id,
				$report_type
			)
		);
	}

	/**
	 * Save research report
	 *
	 * @param int    $contact_id Contact ID
	 * @param object $provider   Provider settings
	 * @param array  $result     Research results
	 * @param string $depth      Research depth
	 * @return int Report ID
	 */
	private function save_report( $contact_id, $provider, $result, $depth ) {
		$data = array(
			'organization_id'          => $provider->organization_id,
			'contact_id'               => $contact_id,
			'provider'                 => $provider->provider,
			'report_type'              => $depth,
			'estimated_income_range'   => $result['income_range'] ?? null,
			'estimated_net_worth_range' => $result['net_worth_range'] ?? null,
			'giving_capacity_rating'   => $result['giving_capacity'] ?? $result['capacity_rating'] ?? null,
			'confidence_score'         => $result['confidence_score'] ?? null,
			'raw_response'             => wp_json_encode( $result ),
			'researched_at'            => current_time( 'mysql' ),
			'expires_at'               => date( 'Y-m-d H:i:s', time() + $this->cache_duration ),
			'cost'                     => $result['cost'] ?? 0,
			'created_by'               => get_current_user_id(),
		);

		// Add depth-specific data
		if ( isset( $result['wealth_indicators'] ) ) {
			$data['real_estate_value']     = $result['wealth_indicators']['real_estate_value'] ?? null;
			$data['business_affiliations'] = wp_json_encode( $result['wealth_indicators']['business_affiliations'] ?? array() );
			$data['stock_holdings']        = wp_json_encode( $result['wealth_indicators']['stock_holdings'] ?? array() );
		}

		if ( isset( $result['donations'] ) ) {
			$data['philanthropic_history'] = wp_json_encode( $result['donations'] ?? array() );
		}

		if ( isset( $result['board_affiliations'] ) ) {
			$data['board_affiliations'] = wp_json_encode( $result['board_affiliations'] ?? array() );
		}

		$this->db->insert( $this->reports_table, $data );

		return $this->db->insert_id;
	}

	/**
	 * Get contact information
	 *
	 * @param int $contact_id Contact ID
	 * @return array|null Contact data
	 */
	private function get_contact_info( $contact_id ) {
		$contact = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}ns_contacts WHERE id = %d",
				$contact_id
			),
			ARRAY_A
		);

		return $contact;
	}

	/**
	 * Update contact capacity rating
	 *
	 * @param int   $contact_id Contact ID
	 * @param array $result     Research results
	 */
	private function update_contact_capacity( $contact_id, $result ) {
		$capacity = $result['giving_capacity'] ?? $result['capacity_rating'] ?? null;

		if ( $capacity ) {
			$this->db->update(
				$this->db->prefix . 'ns_contacts',
				array( 'wealth_capacity_rating' => $capacity ),
				array( 'id' => $contact_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Check usage limits
	 *
	 * @param object $provider Provider settings
	 * @return array Usage check result
	 */
	private function check_usage_limits( $provider ) {
		if ( empty( $provider->monthly_limit ) ) {
			return array( 'allowed' => true );
		}

		if ( $provider->current_month_usage >= $provider->monthly_limit ) {
			return array(
				'allowed' => false,
				'message' => 'Monthly API limit reached',
			);
		}

		return array( 'allowed' => true );
	}

	/**
	 * Track API usage
	 *
	 * @param object $provider Provider settings
	 * @param float  $cost     Operation cost
	 */
	private function track_usage( $provider, $cost ) {
		$this->db->query(
			$this->db->prepare(
				"UPDATE {$this->settings_table}
				SET current_month_usage = current_month_usage + 1
				WHERE id = %d",
				$provider->id
			)
		);
	}

	/**
	 * Calculate philanthropic score
	 *
	 * @param object $report Report object
	 * @return int Score (0-100)
	 */
	private function calculate_philanthropic_score( $report ) {
		$score = 0;

		// Capacity rating contributes 40%
		$capacity_scores = array(
			'A+' => 40,
			'A'  => 32,
			'B'  => 24,
			'C'  => 16,
			'D'  => 8,
		);
		$score += $capacity_scores[ $report->giving_capacity_rating ] ?? 0;

		// Board affiliations contribute 30%
		if ( ! empty( $report->board_affiliations ) ) {
			$boards = json_decode( $report->board_affiliations, true );
			$score += min( 30, count( $boards ) * 10 );
		}

		// Political contributions contribute 15%
		if ( $report->political_contributions > 0 ) {
			$score += min( 15, ( $report->political_contributions / 10000 ) * 5 );
		}

		// Confidence score contributes 15%
		if ( $report->confidence_score > 0 ) {
			$score += ( $report->confidence_score / 100 ) * 15;
		}

		return min( 100, round( $score ) );
	}

	/**
	 * Get capacity range for rating
	 *
	 * @param string $rating Capacity rating
	 * @return string Giving capacity range
	 */
	private function get_capacity_range( $rating ) {
		$ranges = array(
			'A+' => '$100K+',
			'A'  => '$50K-$100K',
			'B'  => '$10K-$50K',
			'C'  => '$1K-$10K',
			'D'  => 'Under $1K',
		);

		return $ranges[ $rating ] ?? 'Unknown';
	}
}
