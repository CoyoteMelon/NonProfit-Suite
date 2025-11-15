<?php
/**
 * Programs/Operations Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Programs {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Programs Module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_program( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage programs' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_programs';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'program_name' => sanitize_text_field( $data['program_name'] ),
				'program_type' => isset( $data['program_type'] ) ? sanitize_text_field( $data['program_type'] ) : null,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'goals' => isset( $data['goals'] ) ? sanitize_textarea_field( $data['goals'] ) : null,
				'budget' => isset( $data['budget'] ) ? floatval( $data['budget'] ) : 0,
				'start_date' => isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null,
				'status' => 'active',
				'manager_id' => isset( $data['manager_id'] ) ? absint( $data['manager_id'] ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d' )
		);

		NonprofitSuite_Cache::invalidate_module( 'programs' );
		return $wpdb->insert_id;
	}

	public static function get_programs( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_programs';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'status' => 'active' );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = $wpdb->prepare( "WHERE status = %s", $args['status'] );

		// Use caching for program lists
		$cache_key = NonprofitSuite_Cache::list_key( 'programs', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, program_name, program_type, description, goals, budget,
			               start_date, end_date, status, manager_id, participant_count, created_at
			        FROM {$table} {$where}
			        ORDER BY program_name ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function enroll_participant( $program_id, $person_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_program_participants';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'program_id' => $program_id,
				'person_id' => $person_id,
				'enrollment_date' => current_time( 'mysql' ),
				'status' => 'active',
			),
			array( '%d', '%d', '%s', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'program_participants' );
		return $wpdb->insert_id;
	}

	public static function get_program_participants( $program_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_program_participants';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Use caching for program participants
		$cache_key = NonprofitSuite_Cache::list_key( 'program_participants', array_merge( $args, array( 'program_id' => $program_id ) ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $program_id, $args ) {
			$sql = $wpdb->prepare(
				"SELECT id, program_id, person_id, enrollment_date, completion_date, status, notes
				 FROM {$table}
				 WHERE program_id = %d AND status = 'active'
				 ORDER BY enrollment_date DESC
				 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
				$program_id
			);

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function create_activity( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage programs' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_activities';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'program_id' => absint( $data['program_id'] ),
				'activity_name' => sanitize_text_field( $data['activity_name'] ),
				'activity_date' => sanitize_text_field( $data['activity_date'] ),
				'location' => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : null,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'capacity' => isset( $data['capacity'] ) ? absint( $data['capacity'] ) : null,
				'status' => 'scheduled',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'program_activities' );
		return $wpdb->insert_id;
	}

	public static function get_program_activities( $program_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_activities';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Use caching for program activities
		$cache_key = NonprofitSuite_Cache::list_key( 'program_activities', array_merge( $args, array( 'program_id' => $program_id ) ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $program_id, $args ) {
			$sql = $wpdb->prepare(
				"SELECT id, program_id, activity_name, activity_date, location, description,
				        capacity, participant_count, status, created_at
				 FROM {$table}
				 WHERE program_id = %d
				 ORDER BY activity_date DESC
				 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
				$program_id
			);

			return $wpdb->get_results( $sql );
		}, 300 );
	}
}
