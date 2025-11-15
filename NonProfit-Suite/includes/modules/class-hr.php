<?php
/**
 * Human Resources Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_HR {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'HR module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_employee( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage employees' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_employees';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'person_id' => absint( $data['person_id'] ),
				'employee_type' => isset( $data['employee_type'] ) ? sanitize_text_field( $data['employee_type'] ) : 'full_time',
				'hire_date' => sanitize_text_field( $data['hire_date'] ),
				'salary' => isset( $data['salary'] ) ? floatval( $data['salary'] ) : 0,
				'position' => isset( $data['position'] ) ? sanitize_text_field( $data['position'] ) : null,
				'status' => 'active',
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'time_off_requests' );
		return $wpdb->insert_id;
	}

	public static function terminate_employee( $employee_id, $termination_date = null ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage employees' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_employees';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		if ( ! $termination_date ) {
			$termination_date = current_time( 'mysql' );
		}

		return $wpdb->update(
			$table,
			array(
				'termination_date' => $termination_date,
				'status' => 'terminated',
			),
			array( 'id' => $employee_id ),
			array( '%s', '%s' ),
			array( '%d' )
		) !== false;
	}

	public static function request_time_off( $data ) {
		// Check permissions FIRST - employees can request their own time off
		$permission_check = NonprofitSuite_Security::check_capability( 'read', 'request time off' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_off';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Calculate days
		$start = new DateTime( $data['start_date'] );
		$end = new DateTime( $data['end_date'] );
		$days = $end->diff( $start )->days + 1;

		$wpdb->insert(
			$table,
			array(
				'employee_id' => absint( $data['employee_id'] ),
				'time_off_type' => sanitize_text_field( $data['time_off_type'] ),
				'start_date' => sanitize_text_field( $data['start_date'] ),
				'end_date' => sanitize_text_field( $data['end_date'] ),
				'days' => $days,
				'status' => 'pending',
				'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'time_off_requests' );
		return $wpdb->insert_id;
	}

	public static function approve_time_off( $time_off_id ) {
		// Check permissions FIRST - only managers/admins can approve
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'approve time off' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_off';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array( 'status' => 'approved' ),
			array( 'id' => $time_off_id ),
			array( '%s' ),
			array( '%d' )
		) !== false;
	}

	public static function deny_time_off( $time_off_id ) {
		// Check permissions FIRST - only managers/admins can deny
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'deny time off' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_off';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array( 'status' => 'denied' ),
			array( 'id' => $time_off_id ),
			array( '%s' ),
			array( '%d' )
		) !== false;
	}

	public static function get_employees( $status = 'active' ) {
		global $wpdb;
		$employees_table = $wpdb->prefix . 'ns_employees';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$where = $status ? $wpdb->prepare( "WHERE e.status = %s", $status ) : "WHERE 1=1";

		return $wpdb->get_results(
			"SELECT e.*, p.first_name, p.last_name, p.email, p.phone
			FROM {$employees_table} e
			JOIN {$people_table} p ON e.person_id = p.id
			{$where}
			ORDER BY p.last_name ASC, p.first_name ASC"
		);
	}

	public static function get_time_off_requests( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_off';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array(
			'employee_id' => null,
			'status' => null,
		);
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";
		if ( $args['employee_id'] ) {
			$where .= $wpdb->prepare( " AND employee_id = %d", $args['employee_id'] );
		}
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		// Use caching for time off requests
		$cache_key = NonprofitSuite_Cache::list_key( 'time_off_requests', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, employee_id, time_off_type, start_date, end_date, days,
			               status, notes, created_at
			        FROM {$table} {$where}
			        ORDER BY start_date DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_employee_time_off_balance( $employee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_off';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use date range instead of YEAR() to allow index usage
		$year_start = date( 'Y-01-01 00:00:00' );
		$year_end = date( 'Y-12-31 23:59:59' );

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT
				SUM(CASE WHEN time_off_type = 'vacation' AND status = 'approved' THEN days ELSE 0 END) as vacation_used,
				SUM(CASE WHEN time_off_type = 'sick' AND status = 'approved' THEN days ELSE 0 END) as sick_used,
				SUM(CASE WHEN time_off_type = 'personal' AND status = 'approved' THEN days ELSE 0 END) as personal_used
			FROM {$table}
			WHERE employee_id = %d
			AND start_date >= %s AND start_date <= %s",
			$employee_id,
			$year_start,
			$year_end
		) );
	}

	public static function get_employee_types() {
		return array(
			'full_time' => __( 'Full-Time', 'nonprofitsuite' ),
			'part_time' => __( 'Part-Time', 'nonprofitsuite' ),
			'contract' => __( 'Contract', 'nonprofitsuite' ),
			'intern' => __( 'Intern', 'nonprofitsuite' ),
		);
	}

	public static function get_time_off_types() {
		return array(
			'vacation' => __( 'Vacation', 'nonprofitsuite' ),
			'sick' => __( 'Sick Leave', 'nonprofitsuite' ),
			'personal' => __( 'Personal Day', 'nonprofitsuite' ),
			'unpaid' => __( 'Unpaid Leave', 'nonprofitsuite' ),
		);
	}
}
