<?php
/**
 * Policy Management Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */

defined( 'ABSPATH' ) or exit;

class NonprofitSuite_Policy {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Policy Management module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_policy( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage policies' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'title' => sanitize_text_field( $data['title'] ),
				'policy_number' => isset( $data['policy_number'] ) ? sanitize_text_field( $data['policy_number'] ) : null,
				'category' => sanitize_text_field( $data['category'] ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'content' => wp_kses_post( $data['content'] ),
				'status' => 'draft',
				'version' => '1.0',
				'review_frequency' => isset( $data['review_frequency'] ) ? sanitize_text_field( $data['review_frequency'] ) : null,
				'responsible_person_id' => isset( $data['responsible_person_id'] ) ? absint( $data['responsible_person_id'] ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		NonprofitSuite_Cache::invalidate_module( 'policies' );
		return $wpdb->insert_id;
	}

	public static function get_policy( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for individual policies
		$cache_key = NonprofitSuite_Cache::item_key( 'policy', $id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, title, policy_number, category, description, content, status, version,
				        review_frequency, responsible_person_id, approved_by, approved_date,
				        effective_date, next_review_date, created_at
				 FROM {$table}
				 WHERE id = %d",
				$id
			) );
		}, 300 );
	}

	public static function get_policies( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$defaults = array(
			'category' => null,
			'status' => null,
			'due_for_review' => false,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";

		if ( $args['category'] ) {
			$where .= $wpdb->prepare( " AND category = %s", $args['category'] );
		}

		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		if ( $args['due_for_review'] ) {
			$where .= " AND next_review_date <= CURDATE() AND next_review_date IS NOT NULL AND status = 'active'";
		}

		// Use caching for policy lists
		$cache_key = NonprofitSuite_Cache::list_key( 'policies', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, title, policy_number, category, description, content, status, version,
			               review_frequency, responsible_person_id, approved_by, approved_date,
			               effective_date, next_review_date, created_at
			        FROM {$table} {$where}
			        ORDER BY created_at DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function update_policy( $id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage policies' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$update_data = array();
		$update_format = array();

		if ( isset( $data['title'] ) ) {
			$update_data['title'] = sanitize_text_field( $data['title'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['policy_number'] ) ) {
			$update_data['policy_number'] = sanitize_text_field( $data['policy_number'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['category'] ) ) {
			$update_data['category'] = sanitize_text_field( $data['category'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['content'] ) ) {
			$update_data['content'] = wp_kses_post( $data['content'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['review_frequency'] ) ) {
			$update_data['review_frequency'] = sanitize_text_field( $data['review_frequency'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['responsible_person_id'] ) ) {
			$update_data['responsible_person_id'] = absint( $data['responsible_person_id'] );
			$update_format[] = '%d';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'policies' );
		}

		return $result;
	}

	public static function approve_policy( $id, $approver_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Get policy to set next review date
		$policy = self::get_policy( $id );
		if ( ! $policy ) {
			return new WP_Error( 'policy_not_found', __( 'Policy not found.', 'nonprofitsuite' ) );
		}

		$next_review_date = null;
		if ( $policy->review_frequency ) {
			$next_review_date = self::calculate_next_review_date( $policy->review_frequency );
		}

		$result = $wpdb->update(
			$table,
			array(
				'status' => 'active',
				'approved_by' => absint( $approver_id ),
				'approved_date' => current_time( 'mysql' ),
				'effective_date' => current_time( 'mysql', false ),
				'next_review_date' => $next_review_date,
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'policies' );
		}

		return $result;
	}

	public static function get_policies_due_for_review( $days_ahead = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$date = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );

		// Use caching for policies due for review
		$cache_key = NonprofitSuite_Cache::list_key( 'policies_due_review', array( 'days_ahead' => $days_ahead ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $date ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, title, policy_number, category, description, status, version,
				        review_frequency, responsible_person_id, next_review_date, created_at
				 FROM {$table}
				 WHERE status = 'active'
				 AND next_review_date IS NOT NULL
				 AND next_review_date >= CURDATE()
				 AND next_review_date <= %s
				 ORDER BY next_review_date ASC",
				$date
			) );
		}, 300 );
	}

	public static function increment_version( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$policy = self::get_policy( $id );
		if ( ! $policy ) {
			return new WP_Error( 'policy_not_found', __( 'Policy not found.', 'nonprofitsuite' ) );
		}

		// Parse version (e.g., "1.2" becomes 1.3, "2.0" becomes 2.1)
		$version_parts = explode( '.', $policy->version );
		$major = isset( $version_parts[0] ) ? intval( $version_parts[0] ) : 1;
		$minor = isset( $version_parts[1] ) ? intval( $version_parts[1] ) : 0;
		$minor++;
		$new_version = "{$major}.{$minor}";

		$result = $wpdb->update(
			$table,
			array( 'version' => $new_version ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'policies' );
		}

		return $result;
	}

	public static function archive_policy( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_policies';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$result = $wpdb->update(
			$table,
			array( 'status' => 'archived' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'policies' );
		}

		return $result;
	}

	private static function calculate_next_review_date( $frequency ) {
		$date = new DateTime();

		switch ( $frequency ) {
			case 'annually':
				$date->modify( '+1 year' );
				break;
			case 'biannually':
				$date->modify( '+6 months' );
				break;
			case '3_years':
				$date->modify( '+3 years' );
				break;
			case '5_years':
				$date->modify( '+5 years' );
				break;
			default:
				return null;
		}

		return $date->format( 'Y-m-d' );
	}

	public static function get_categories() {
		return array(
			'financial' => __( 'Financial', 'nonprofitsuite' ),
			'governance' => __( 'Governance', 'nonprofitsuite' ),
			'hr' => __( 'Human Resources', 'nonprofitsuite' ),
			'program' => __( 'Program', 'nonprofitsuite' ),
			'safety' => __( 'Safety', 'nonprofitsuite' ),
			'legal' => __( 'Legal', 'nonprofitsuite' ),
			'it' => __( 'Information Technology', 'nonprofitsuite' ),
			'volunteer' => __( 'Volunteer', 'nonprofitsuite' ),
			'fundraising' => __( 'Fundraising', 'nonprofitsuite' ),
			'ethics' => __( 'Ethics', 'nonprofitsuite' ),
		);
	}

	public static function get_review_frequencies() {
		return array(
			'annually' => __( 'Annually', 'nonprofitsuite' ),
			'biannually' => __( 'Biannually', 'nonprofitsuite' ),
			'3_years' => __( 'Every 3 Years', 'nonprofitsuite' ),
			'5_years' => __( 'Every 5 Years', 'nonprofitsuite' ),
			'as_needed' => __( 'As Needed', 'nonprofitsuite' ),
		);
	}
}
