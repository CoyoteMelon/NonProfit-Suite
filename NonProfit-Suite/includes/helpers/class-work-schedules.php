<?php
/**
 * Work Schedules & Volunteer Shift Management
 *
 * Manages employee work schedules, volunteer shifts, availability blocks,
 * and shift signup functionality.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Work_Schedules Class
 *
 * Handles work schedule and volunteer shift operations.
 */
class NonprofitSuite_Work_Schedules {

	/**
	 * Create a work schedule or volunteer shift.
	 *
	 * @param array $schedule_data Schedule data.
	 * @return int|WP_Error Schedule ID on success, WP_Error on failure.
	 */
	public static function create_schedule( $schedule_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_work_schedules';

		$data = array(
			'user_id'         => isset( $schedule_data['user_id'] ) ? $schedule_data['user_id'] : 0,
			'schedule_type'   => isset( $schedule_data['schedule_type'] ) ? $schedule_data['schedule_type'] : 'shift',
			'shift_name'      => isset( $schedule_data['shift_name'] ) ? $schedule_data['shift_name'] : null,
			'role'            => isset( $schedule_data['role'] ) ? $schedule_data['role'] : null,
			'event_id'        => isset( $schedule_data['event_id'] ) ? $schedule_data['event_id'] : null,
			'day_of_week'     => isset( $schedule_data['day_of_week'] ) ? $schedule_data['day_of_week'] : null,
			'start_date'      => isset( $schedule_data['start_date'] ) ? $schedule_data['start_date'] : null,
			'end_date'        => isset( $schedule_data['end_date'] ) ? $schedule_data['end_date'] : null,
			'start_time'      => $schedule_data['start_time'],
			'end_time'        => $schedule_data['end_time'],
			'is_recurring'    => isset( $schedule_data['is_recurring'] ) ? $schedule_data['is_recurring'] : 0,
			'recurrence_pattern' => isset( $schedule_data['recurrence_pattern'] ) ? wp_json_encode( $schedule_data['recurrence_pattern'] ) : null,
			'positions_needed' => isset( $schedule_data['positions_needed'] ) ? $schedule_data['positions_needed'] : 1,
			'positions_filled' => isset( $schedule_data['positions_filled'] ) ? $schedule_data['positions_filled'] : 0,
			'is_published'    => isset( $schedule_data['is_published'] ) ? $schedule_data['is_published'] : 1,
			'notes'           => isset( $schedule_data['notes'] ) ? $schedule_data['notes'] : null,
			'created_by'      => isset( $schedule_data['created_by'] ) ? $schedule_data['created_by'] : get_current_user_id(),
		);

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create schedule', 'nonprofitsuite' ) );
		}

		$schedule_id = $wpdb->insert_id;

		/**
		 * Fires after a work schedule is created.
		 *
		 * @param int   $schedule_id   Schedule ID.
		 * @param array $schedule_data Schedule data.
		 */
		do_action( 'ns_work_schedule_created', $schedule_id, $data );

		return $schedule_id;
	}

	/**
	 * Create volunteer shift for an event.
	 *
	 * @param int   $event_id      Event ID.
	 * @param array $shift_data    Shift configuration.
	 * @return int|WP_Error Shift ID on success.
	 */
	public static function create_volunteer_shift( $event_id, $shift_data ) {
		$schedule_data = array_merge( $shift_data, array(
			'schedule_type' => 'volunteer_shift',
			'event_id'      => $event_id,
			'user_id'       => 0, // No specific user initially
		) );

		return self::create_schedule( $schedule_data );
	}

	/**
	 * Create multiple volunteer shifts for an event.
	 *
	 * Example: 3-day booth event, 2-hour shifts, need 3 people per shift.
	 *
	 * @param int   $event_id        Event ID.
	 * @param array $shift_template  Shift template configuration.
	 * @return array Array of created shift IDs.
	 */
	public static function create_event_shifts( $event_id, $shift_template ) {
		$defaults = array(
			'start_date'       => null,
			'end_date'         => null,
			'shift_duration'   => 120, // minutes
			'positions_needed' => 3,
			'role'             => 'Volunteer',
			'start_hour'       => 9,
			'end_hour'         => 17,
		);

		$config = array_merge( $defaults, $shift_template );
		$shift_ids = array();

		$current_date = strtotime( $config['start_date'] );
		$end_date = strtotime( $config['end_date'] );

		while ( $current_date <= $end_date ) {
			$day_shifts = self::generate_day_shifts( $current_date, $config );

			foreach ( $day_shifts as $shift ) {
				$shift_data = array(
					'shift_name'       => $shift['shift_name'],
					'role'             => $config['role'],
					'start_date'       => gmdate( 'Y-m-d', $current_date ),
					'start_time'       => $shift['start_time'],
					'end_time'         => $shift['end_time'],
					'positions_needed' => $config['positions_needed'],
					'is_published'     => 1,
				);

				$shift_id = self::create_volunteer_shift( $event_id, $shift_data );

				if ( ! is_wp_error( $shift_id ) ) {
					$shift_ids[] = $shift_id;
				}
			}

			$current_date = strtotime( '+1 day', $current_date );
		}

		return $shift_ids;
	}

	/**
	 * Generate shifts for a single day.
	 *
	 * @param int   $date   Date timestamp.
	 * @param array $config Configuration.
	 * @return array Array of shift data.
	 */
	private static function generate_day_shifts( $date, $config ) {
		$shifts = array();
		$shift_duration = $config['shift_duration'];
		$start_hour = $config['start_hour'];
		$end_hour = $config['end_hour'];

		$current_hour = $start_hour;

		while ( $current_hour < $end_hour ) {
			$start_minutes = $current_hour * 60;
			$end_minutes = $start_minutes + $shift_duration;

			$end_hour_calc = floor( $end_minutes / 60 );

			if ( $end_hour_calc > $end_hour ) {
				break;
			}

			$start_time = sprintf( '%02d:%02d:00', floor( $start_minutes / 60 ), $start_minutes % 60 );
			$end_time = sprintf( '%02d:%02d:00', floor( $end_minutes / 60 ), $end_minutes % 60 );

			$shifts[] = array(
				'shift_name'  => sprintf(
					'%s - %s',
					gmdate( 'g:i A', strtotime( $start_time ) ),
					gmdate( 'g:i A', strtotime( $end_time ) )
				),
				'start_time' => $start_time,
				'end_time'   => $end_time,
			);

			$current_hour += ( $shift_duration / 60 );
		}

		return $shifts;
	}

	/**
	 * Sign up volunteer for a shift.
	 *
	 * @param int $shift_id Shift ID.
	 * @param int $user_id  User ID.
	 * @return int|WP_Error Assignment schedule ID on success.
	 */
	public static function signup_for_shift( $shift_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_work_schedules';

		// Get shift details
		$shift = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$shift_id
		), ARRAY_A );

		if ( ! $shift ) {
			return new WP_Error( 'shift_not_found', __( 'Shift not found', 'nonprofitsuite' ) );
		}

		// Check if shift is full
		if ( $shift['positions_filled'] >= $shift['positions_needed'] ) {
			return new WP_Error( 'shift_full', __( 'This shift is already full', 'nonprofitsuite' ) );
		}

		// Check if user already signed up
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table}
			WHERE user_id = %d
			AND event_id = %d
			AND start_date = %s
			AND start_time = %s
			AND schedule_type = 'volunteer_shift'",
			$user_id,
			$shift['event_id'],
			$shift['start_date'],
			$shift['start_time']
		) );

		if ( $existing ) {
			return new WP_Error( 'already_signed_up', __( 'You are already signed up for this shift', 'nonprofitsuite' ) );
		}

		// Check for conflicts
		$conflicts = NonprofitSuite_Calendar_Conflicts::check_conflicts(
			array( $user_id ),
			$shift['start_date'] . ' ' . $shift['start_time'],
			$shift['start_date'] . ' ' . $shift['end_time']
		);

		if ( ! empty( $conflicts ) ) {
			return new WP_Error( 'schedule_conflict', __( 'You have a scheduling conflict at this time', 'nonprofitsuite' ) );
		}

		// Create assignment schedule entry
		$assignment_data = array(
			'user_id'          => $user_id,
			'schedule_type'    => 'volunteer_shift',
			'shift_name'       => $shift['shift_name'],
			'role'             => $shift['role'],
			'event_id'         => $shift['event_id'],
			'start_date'       => $shift['start_date'],
			'start_time'       => $shift['start_time'],
			'end_time'         => $shift['end_time'],
			'positions_needed' => 1,
			'positions_filled' => 1,
			'is_published'     => 0, // Personal assignment, not published
		);

		$result = $wpdb->insert( $table, $assignment_data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to sign up for shift', 'nonprofitsuite' ) );
		}

		$assignment_id = $wpdb->insert_id;

		// Increment positions_filled on the main shift
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET positions_filled = positions_filled + 1 WHERE id = %d",
			$shift_id
		) );

		// Create calendar event for the volunteer
		self::create_calendar_event_for_shift( $assignment_id, $user_id, $shift );

		/**
		 * Fires after volunteer signs up for shift.
		 *
		 * @param int   $assignment_id Assignment ID.
		 * @param int   $shift_id      Shift ID.
		 * @param int   $user_id       User ID.
		 * @param array $shift         Shift data.
		 */
		do_action( 'ns_volunteer_shift_signup', $assignment_id, $shift_id, $user_id, $shift );

		return $assignment_id;
	}

	/**
	 * Cancel shift signup.
	 *
	 * @param int $assignment_id Assignment schedule ID.
	 * @param int $user_id       User ID (for verification).
	 * @return bool|WP_Error True on success.
	 */
	public static function cancel_shift_signup( $assignment_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_work_schedules';

		// Get assignment
		$assignment = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
			$assignment_id,
			$user_id
		), ARRAY_A );

		if ( ! $assignment ) {
			return new WP_Error( 'not_found', __( 'Assignment not found', 'nonprofitsuite' ) );
		}

		// Find the main shift to decrement positions_filled
		$main_shift = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table}
			WHERE event_id = %d
			AND start_date = %s
			AND start_time = %s
			AND user_id = 0
			AND schedule_type = 'volunteer_shift'
			LIMIT 1",
			$assignment['event_id'],
			$assignment['start_date'],
			$assignment['start_time']
		), ARRAY_A );

		// Delete assignment
		$wpdb->delete( $table, array( 'id' => $assignment_id ), array( '%d' ) );

		// Decrement positions_filled
		if ( $main_shift ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET positions_filled = GREATEST(positions_filled - 1, 0) WHERE id = %d",
				$main_shift['id']
			) );
		}

		// Delete associated calendar event
		if ( $assignment['event_id'] ) {
			$event_table = $wpdb->prefix . 'ns_calendar_events';
			$wpdb->delete( $event_table, array(
				'entity_type' => 'work_schedule',
				'entity_id'   => $assignment_id,
			), array( '%s', '%d' ) );
		}

		do_action( 'ns_volunteer_shift_cancelled', $assignment_id, $user_id );

		return true;
	}

	/**
	 * Get available volunteer shifts.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of available shifts.
	 */
	public static function get_available_shifts( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_work_schedules';

		$defaults = array(
			'start_date'  => gmdate( 'Y-m-d' ),
			'end_date'    => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
			'event_id'    => null,
			'role'        => null,
			'only_unfilled' => false,
		);

		$args = array_merge( $defaults, $args );

		$where = array();
		$where[] = "schedule_type = 'volunteer_shift'";
		$where[] = "is_published = 1";
		$where[] = "user_id = 0"; // Only main shift entries, not assignments

		if ( $args['start_date'] ) {
			$where[] = $wpdb->prepare( "start_date >= %s", $args['start_date'] );
		}

		if ( $args['end_date'] ) {
			$where[] = $wpdb->prepare( "start_date <= %s", $args['end_date'] );
		}

		if ( $args['event_id'] ) {
			$where[] = $wpdb->prepare( "event_id = %d", $args['event_id'] );
		}

		if ( $args['role'] ) {
			$where[] = $wpdb->prepare( "role = %s", $args['role'] );
		}

		if ( $args['only_unfilled'] ) {
			$where[] = "positions_filled < positions_needed";
		}

		$where_clause = implode( ' AND ', $where );

		$shifts = $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE {$where_clause}
			ORDER BY start_date ASC, start_time ASC",
			ARRAY_A
		);

		return $shifts;
	}

	/**
	 * Get shifts for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Query arguments.
	 * @return array Array of user's shifts.
	 */
	public static function get_user_shifts( $user_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_work_schedules';

		$defaults = array(
			'start_date' => null,
			'end_date'   => null,
			'type'       => null,
		);

		$args = array_merge( $defaults, $args );

		$where = array();
		$where[] = $wpdb->prepare( "user_id = %d", $user_id );

		if ( $args['start_date'] ) {
			$where[] = $wpdb->prepare( "start_date >= %s", $args['start_date'] );
		}

		if ( $args['end_date'] ) {
			$where[] = $wpdb->prepare( "start_date <= %s", $args['end_date'] );
		}

		if ( $args['type'] ) {
			$where[] = $wpdb->prepare( "schedule_type = %s", $args['type'] );
		}

		$where_clause = implode( ' AND ', $where );

		$shifts = $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE {$where_clause}
			ORDER BY start_date ASC, start_time ASC",
			ARRAY_A
		);

		return $shifts;
	}

	/**
	 * Find volunteers who are available for a shift.
	 *
	 * @param array $shift_details Shift details (start_date, start_time, end_time).
	 * @param array $volunteer_pool Optional array of user IDs to check. If empty, checks all volunteers.
	 * @return array Array of available volunteer user IDs with details.
	 */
	public static function find_available_volunteers( $shift_details, $volunteer_pool = array() ) {
		// If no pool specified, get all users with volunteer capability
		if ( empty( $volunteer_pool ) ) {
			$volunteer_pool = get_users( array(
				'role__in' => array( 'volunteer', 'subscriber', 'contributor' ),
				'fields'   => 'ID',
			) );
		}

		$available_volunteers = array();

		$start_datetime = $shift_details['start_date'] . ' ' . $shift_details['start_time'];
		$end_datetime = $shift_details['start_date'] . ' ' . $shift_details['end_time'];

		foreach ( $volunteer_pool as $user_id ) {
			// Check if volunteer has conflicts
			$is_available = NonprofitSuite_Calendar_Conflicts::is_user_available(
				$user_id,
				$start_datetime,
				$end_datetime
			);

			if ( $is_available ) {
				$user = get_userdata( $user_id );
				$available_volunteers[] = array(
					'user_id'    => $user_id,
					'name'       => $user->display_name,
					'email'      => $user->user_email,
				);
			}
		}

		return $available_volunteers;
	}

	/**
	 * Create calendar event for a work schedule/shift.
	 *
	 * @param int   $schedule_id Schedule ID.
	 * @param int   $user_id     User ID.
	 * @param array $shift       Shift data.
	 * @return int|null Calendar event ID or null.
	 */
	private static function create_calendar_event_for_shift( $schedule_id, $user_id, $shift ) {
		$event_data = array(
			'entity_type'      => 'work_schedule',
			'entity_id'        => $schedule_id,
			'calendar_category' => 'personal',
			'title'            => $shift['shift_name'] ? $shift['shift_name'] : __( 'Volunteer Shift', 'nonprofitsuite' ),
			'description'      => $shift['role'] ? sprintf( __( 'Role: %s', 'nonprofitsuite' ), $shift['role'] ) : '',
			'start_datetime'   => $shift['start_date'] . ' ' . $shift['start_time'],
			'end_datetime'     => $shift['start_date'] . ' ' . $shift['end_time'],
			'all_day'          => 0,
			'created_by'       => $user_id,
			'assignee_id'      => $user_id,
		);

		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		// Calculate visibility
		$visible_calendars = NonprofitSuite_Calendar_Visibility::calculate_visibility( $event_data );
		$event_data['visible_on_calendars'] = wp_json_encode( $visible_calendars );

		$wpdb->insert( $table, $event_data );

		return $wpdb->insert_id;
	}

	/**
	 * Get shift statistics.
	 *
	 * @param int $event_id Event ID.
	 * @return array Statistics array.
	 */
	public static function get_shift_statistics( $event_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_work_schedules';

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as total_shifts,
				SUM(positions_needed) as total_positions,
				SUM(positions_filled) as filled_positions,
				SUM(CASE WHEN positions_filled >= positions_needed THEN 1 ELSE 0 END) as fully_staffed_shifts
			FROM {$table}
			WHERE event_id = %d
			AND user_id = 0
			AND schedule_type = 'volunteer_shift'",
			$event_id
		), ARRAY_A );

		$stats['unfilled_positions'] = $stats['total_positions'] - $stats['filled_positions'];
		$stats['coverage_percentage'] = $stats['total_positions'] > 0
			? round( ( $stats['filled_positions'] / $stats['total_positions'] ) * 100, 1 )
			: 0;

		return $stats;
	}
}
