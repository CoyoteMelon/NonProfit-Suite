<?php
/**
 * In-Kind Donations Module
 *
 * Track non-cash donations, manage fair market value calculations,
 * handle appraisals, and generate tax receipts.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_InKind_Donations {

	/**
	 * Record an in-kind donation
	 *
	 * @param array $data Donation data
	 * @return int|WP_Error Donation ID or error
	 */
	public static function record_donation( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'donor_id' => null,
			'donation_date' => current_time( 'Y-m-d' ),
			'category' => '',
			'description' => '',
			'quantity' => 1,
			'unit' => null,
			'fair_market_value' => 0,
			'valuation_method' => null,
			'appraised' => 0,
			'appraiser_name' => null,
			'appraisal_date' => null,
			'condition_rating' => null,
			'location' => null,
			'restricted' => 0,
			'restriction_terms' => null,
			'intended_use' => null,
			'tax_receipt_issued' => 0,
			'receipt_number' => null,
			'notes' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['category'] ) || empty( $data['description'] ) ) {
			return new WP_Error( 'missing_required', __( 'Category and description are required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_in_kind_donations',
			array(
				'donor_id' => absint( $data['donor_id'] ),
				'donation_date' => $data['donation_date'],
				'category' => sanitize_text_field( $data['category'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'quantity' => $data['quantity'],
				'unit' => sanitize_text_field( $data['unit'] ),
				'fair_market_value' => $data['fair_market_value'],
				'valuation_method' => sanitize_text_field( $data['valuation_method'] ),
				'appraised' => absint( $data['appraised'] ),
				'appraiser_name' => sanitize_text_field( $data['appraiser_name'] ),
				'appraisal_date' => $data['appraisal_date'],
				'condition_rating' => sanitize_text_field( $data['condition_rating'] ),
				'location' => sanitize_text_field( $data['location'] ),
				'restricted' => absint( $data['restricted'] ),
				'restriction_terms' => sanitize_textarea_field( $data['restriction_terms'] ),
				'intended_use' => sanitize_textarea_field( $data['intended_use'] ),
				'tax_receipt_issued' => absint( $data['tax_receipt_issued'] ),
				'receipt_number' => sanitize_text_field( $data['receipt_number'] ),
				'notes' => sanitize_textarea_field( $data['notes'] ),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to record in-kind donation - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to record donation.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'in_kind_donations' );
		return $wpdb->insert_id;
	}

	/**
	 * Get donations with filters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_donations( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'category' => null,
			'donor_id' => null,
			'date_from' => null,
			'date_to' => null,
			'min_value' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['category'] ) ) {
			$where[] = 'category = %s';
			$params[] = $args['category'];
		}

		if ( ! empty( $args['donor_id'] ) ) {
			$where[] = 'donor_id = %d';
			$params[] = $args['donor_id'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'donation_date >= %s';
			$params[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'donation_date <= %s';
			$params[] = $args['date_to'];
		}

		if ( ! empty( $args['min_value'] ) ) {
			$where[] = 'fair_market_value >= %f';
			$params[] = $args['min_value'];
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for donation lists
		$cache_key = NonprofitSuite_Cache::list_key( 'in_kind_donations', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $params, $args ) {
			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare(
					"SELECT id, donor_id, donation_date, category, description, quantity, unit,
					        fair_market_value, valuation_method, appraised, appraiser_name, appraisal_date,
					        condition_rating, location, restricted, restriction_terms, intended_use,
					        tax_receipt_issued, receipt_number, notes, created_at
					 FROM {$wpdb->prefix}ns_in_kind_donations
					 WHERE $where_clause
					 ORDER BY $orderby
					 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
					$params
				);
			} else {
				$sql = "SELECT id, donor_id, donation_date, category, description, quantity, unit,
				        fair_market_value, valuation_method, appraised, appraiser_name, appraisal_date,
				        condition_rating, location, restricted, restriction_terms, intended_use,
				        tax_receipt_issued, receipt_number, notes, created_at
				        FROM {$wpdb->prefix}ns_in_kind_donations
				        WHERE $where_clause
				        ORDER BY $orderby
				        " . NonprofitSuite_Utilities::build_limit_clause( $args );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Calculate annual in-kind value
	 *
	 * @param int $year Year
	 * @return float
	 */
	public static function calculate_annual_in_kind_value( $year ) {
		global $wpdb;

		// Use date range instead of YEAR() to allow index usage
		$year_start = $year . '-01-01 00:00:00';
		$year_end = $year . '-12-31 23:59:59';

		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(fair_market_value)
			FROM {$wpdb->prefix}ns_in_kind_donations
			WHERE donation_date >= %s AND donation_date <= %s",
			$year_start,
			$year_end
		) );

		return $value ? (float) $value : 0;
	}

	/**
	 * Generate tax receipt (acknowledgment letter)
	 *
	 * @param int $id Donation ID
	 * @return string|WP_Error Receipt content or error
	 */
	public static function generate_tax_receipt( $id ) {
		global $wpdb;

		$donation = $wpdb->get_row( $wpdb->prepare(
			"SELECT d.*, p.first_name, p.last_name, p.email
			FROM {$wpdb->prefix}ns_in_kind_donations d
			LEFT JOIN {$wpdb->prefix}ns_people p ON d.donor_id = p.id
			WHERE d.id = %d",
			$id
		) );

		if ( ! $donation ) {
			return new WP_Error( 'not_found', __( 'Donation not found.', 'nonprofitsuite' ) );
		}

		$year = date( 'Y', strtotime( $donation->donation_date ) );

		// Generate receipt content
		$receipt = sprintf(
			__( "Thank you for your generous in-kind donation of %s with a fair market value of $%s on %s.\n\nNo goods or services were provided in exchange for this contribution.\n\nPlease consult your tax advisor regarding the deductibility of this contribution.", 'nonprofitsuite' ),
			$donation->description,
			number_format( $donation->fair_market_value, 2 ),
			date( 'F j, Y', strtotime( $donation->donation_date ) )
		);

		// Mark receipt as issued
		$result = $wpdb->update(
			$wpdb->prefix . 'ns_in_kind_donations',
			array(
				'tax_receipt_issued' => 1,
				'receipt_number' => 'IK-' . $year . '-' . str_pad( $id, 5, '0', STR_PAD_LEFT ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to mark receipt as issued - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to mark receipt as issued.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'in_kind_donations' );
		return $receipt;
	}

	/**
	 * Get donations by category for year (Form 990)
	 *
	 * @param int $year Year
	 * @return array
	 */
	public static function get_donations_by_category( $year ) {
		global $wpdb;

		// Use date range instead of YEAR() to allow index usage
		$year_start = $year . '-01-01 00:00:00';
		$year_end = $year . '-12-31 23:59:59';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT category, SUM(fair_market_value) as total_value, COUNT(*) as count
			FROM {$wpdb->prefix}ns_in_kind_donations
			WHERE donation_date >= %s AND donation_date <= %s
			GROUP BY category
			ORDER BY total_value DESC",
			$year_start,
			$year_end
		) );

		return $results ? $results : array();
	}

	/**
	 * Flag items needing appraisal (>$5000)
	 *
	 * @param int $id Donation ID
	 * @return bool|WP_Error
	 */
	public static function flag_for_appraisal( $id ) {
		global $wpdb;

		$donation = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, fair_market_value, appraised, notes
			 FROM {$wpdb->prefix}ns_in_kind_donations
			 WHERE id = %d",
			$id
		) );

		if ( ! $donation ) {
			return new WP_Error( 'not_found', __( 'Donation not found.', 'nonprofitsuite' ) );
		}

		if ( $donation->fair_market_value >= 5000 && ! $donation->appraised ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'ns_in_kind_donations',
				array( 'notes' => $donation->notes . "\n" . __( '[FLAGGED: Requires professional appraisal per IRS rules]', 'nonprofitsuite' ) ),
				array( 'id' => $id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false === $result ) {
				error_log( 'NonprofitSuite: Failed to flag donation for appraisal - ' . $wpdb->last_error );
				return new WP_Error( 'db_error', __( 'Failed to flag donation for appraisal.', 'nonprofitsuite' ) );
			}

			NonprofitSuite_Cache::invalidate_module( 'in_kind_donations' );
			return true;
		}

		return false;
	}

	/**
	 * Get Form 990 Schedule M data (non-cash contributions)
	 *
	 * @param int $year Year
	 * @return array
	 */
	public static function get_form_990_schedule_m_data( $year ) {
		return self::get_donations_by_category( $year );
	}
}
