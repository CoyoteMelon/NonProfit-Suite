<?php
/**
 * Volunteer Management Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Volunteers {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Volunteer Management requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_volunteer( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_volunteers();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_volunteers';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Validate required fields
		if ( empty( $data['person_id'] ) || ! is_numeric( $data['person_id'] ) || $data['person_id'] <= 0 ) {
			return new WP_Error( 'invalid_person_id', __( 'Valid person ID is required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'person_id' => absint( $data['person_id'] ),
				'application_date' => current_time( 'mysql' ),
				'application_status' => 'pending',
				'volunteer_status' => 'applicant',
				'skills' => isset( $data['skills'] ) ? sanitize_textarea_field( $data['skills'] ) : null,
				'interests' => isset( $data['interests'] ) ? sanitize_textarea_field( $data['interests'] ) : null,
				'availability' => isset( $data['availability'] ) ? sanitize_textarea_field( $data['availability'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to create volunteer - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to create volunteer.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'volunteers' );
		return $wpdb->insert_id;
	}

	public static function log_hours( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_volunteer_hours';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Validate required fields
		if ( empty( $data['volunteer_id'] ) || ! is_numeric( $data['volunteer_id'] ) || $data['volunteer_id'] <= 0 ) {
			return new WP_Error( 'invalid_volunteer_id', __( 'Valid volunteer ID is required.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['hours'] ) || ! is_numeric( $data['hours'] ) || $data['hours'] <= 0 ) {
			return new WP_Error( 'invalid_hours', __( 'Hours must be greater than zero.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['activity_date'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Activity date is required.', 'nonprofitsuite' ) );
		}

		// Validate date format
		if ( ! strtotime( $data['activity_date'] ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid activity date format.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['description'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Activity description is required.', 'nonprofitsuite' ) );
		}

		// Start transaction for data consistency
		$wpdb->query( 'START TRANSACTION' );

		$result = $wpdb->insert(
			$table,
			array(
				'volunteer_id' => absint( $data['volunteer_id'] ),
				'activity_date' => sanitize_text_field( $data['activity_date'] ),
				'hours' => floatval( $data['hours'] ),
				'program_id' => isset( $data['program_id'] ) ? absint( $data['program_id'] ) : null,
				'description' => sanitize_textarea_field( $data['description'] ),
			),
			array( '%d', '%s', '%f', '%d', '%s' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'NonprofitSuite: Failed to log volunteer hours - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to log volunteer hours.', 'nonprofitsuite' ) );
		}

		$hours_id = $wpdb->insert_id;

		// Update volunteer total hours
		$update_result = self::update_volunteer_totals( $data['volunteer_id'] );
		if ( is_wp_error( $update_result ) ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'NonprofitSuite: Failed to update volunteer totals - ' . $update_result->get_error_message() );
			return new WP_Error( 'db_error', __( 'Failed to update volunteer totals.', 'nonprofitsuite' ) );
		}

		// Commit transaction
		$wpdb->query( 'COMMIT' );

		NonprofitSuite_Cache::invalidate_module( 'volunteers' );
		NonprofitSuite_Cache::invalidate_module( 'volunteer_hours' );
		return $hours_id;
	}

	public static function approve_hours( $hours_id, $approver_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_volunteer_hours';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$result = $wpdb->update(
			$table,
			array(
				'approved' => 1,
				'approved_by' => $approver_id,
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $hours_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to approve volunteer hours - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to approve volunteer hours.', 'nonprofitsuite' ) );
		}

		return true;
	}

	public static function get_volunteers( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_volunteers';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'status' => 'active' );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = $wpdb->prepare( "WHERE volunteer_status = %s", $args['status'] );

		// Use caching for volunteer lists
		$cache_key = NonprofitSuite_Cache::list_key( 'volunteers', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, person_id, application_date, application_status, volunteer_status,
			               skills, interests, availability, total_hours, last_activity_date,
			               orientation_date, background_check_date, created_at
			        FROM {$table} {$where}
			        ORDER BY total_hours DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_volunteer_hours( $volunteer_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_volunteer_hours';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'approved_only' => false );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = $wpdb->prepare( "WHERE volunteer_id = %d", $volunteer_id );
		if ( $args['approved_only'] ) {
			$where .= " AND approved = 1";
		}

		// Use caching for volunteer hours
		$cache_key = NonprofitSuite_Cache::list_key( 'volunteer_hours', array_merge( $args, array( 'volunteer_id' => $volunteer_id ) ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, volunteer_id, activity_date, hours, program_id, description,
			               approved, approved_by, approved_at, created_at
			        FROM {$table} {$where}
			        ORDER BY activity_date DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	private static function update_volunteer_totals( $volunteer_id ) {
		global $wpdb;
		$volunteers_table = $wpdb->prefix . 'ns_volunteers';
		$hours_table = $wpdb->prefix . 'ns_volunteer_hours';

		$total_hours = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(hours) FROM {$hours_table} WHERE volunteer_id = %d AND approved = 1",
			$volunteer_id
		) );

		if ( null === $total_hours && $wpdb->last_error ) {
			error_log( 'NonprofitSuite: Failed to calculate volunteer hours - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to calculate volunteer hours.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$volunteers_table,
			array( 'total_hours' => $total_hours ),
			array( 'id' => $volunteer_id ),
			array( '%f' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to update volunteer totals - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to update volunteer totals.', 'nonprofitsuite' ) );
		}

		return true;
	}

	public static function get_volunteer_statuses() {
		return array(
			'applicant' => __( 'Applicant', 'nonprofitsuite' ),
			'active' => __( 'Active', 'nonprofitsuite' ),
			'inactive' => __( 'Inactive', 'nonprofitsuite' ),
			'suspended' => __( 'Suspended', 'nonprofitsuite' ),
		);
	}
}
