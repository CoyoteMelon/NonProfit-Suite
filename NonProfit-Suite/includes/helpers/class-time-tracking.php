<?php
/**
 * Time Tracking & Clock In/Out System
 *
 * Manages time entries for employees and volunteers, including clock in/out,
 * time card generation, approval workflows, and payroll integration.
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
 * NonprofitSuite_Time_Tracking Class
 *
 * Handles time entry operations and time card management.
 */
class NonprofitSuite_Time_Tracking {

	/**
	 * Clock in a user.
	 *
	 * @param int   $user_id    User ID.
	 * @param array $entry_data Optional additional data.
	 * @return int|WP_Error Time entry ID on success.
	 */
	public static function clock_in( $user_id, $entry_data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		// Check if user is already clocked in
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table}
			WHERE user_id = %d
			AND end_datetime IS NULL
			LIMIT 1",
			$user_id
		) );

		if ( $existing ) {
			return new WP_Error( 'already_clocked_in', __( 'You are already clocked in', 'nonprofitsuite' ) );
		}

		$data = array_merge( array(
			'user_id'        => $user_id,
			'entry_type'     => 'work',
			'start_datetime' => current_time( 'mysql' ),
			'status'         => 'draft',
		), $entry_data );

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to clock in', 'nonprofitsuite' ) );
		}

		$entry_id = $wpdb->insert_id;

		/**
		 * Fires after user clocks in.
		 *
		 * @param int   $entry_id Entry ID.
		 * @param int   $user_id  User ID.
		 * @param array $data     Entry data.
		 */
		do_action( 'ns_time_clock_in', $entry_id, $user_id, $data );

		return $entry_id;
	}

	/**
	 * Clock out a user.
	 *
	 * @param int   $user_id       User ID.
	 * @param array $additional_data Optional data (break_minutes, description, etc.).
	 * @return int|WP_Error Time entry ID on success.
	 */
	public static function clock_out( $user_id, $additional_data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		// Find active time entry
		$entry = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE user_id = %d
			AND end_datetime IS NULL
			LIMIT 1",
			$user_id
		), ARRAY_A );

		if ( ! $entry ) {
			return new WP_Error( 'not_clocked_in', __( 'You are not currently clocked in', 'nonprofitsuite' ) );
		}

		$end_datetime = current_time( 'mysql' );
		$break_minutes = isset( $additional_data['break_minutes'] ) ? (int) $additional_data['break_minutes'] : 0;

		// Calculate duration
		$start_timestamp = strtotime( $entry['start_datetime'] );
		$end_timestamp = strtotime( $end_datetime );
		$total_minutes = floor( ( $end_timestamp - $start_timestamp ) / 60 );
		$duration_minutes = $total_minutes - $break_minutes;

		$update_data = array(
			'end_datetime'     => $end_datetime,
			'duration_minutes' => $duration_minutes,
			'break_minutes'    => $break_minutes,
		);

		// Add optional fields
		if ( isset( $additional_data['description'] ) ) {
			$update_data['description'] = $additional_data['description'];
		}

		if ( isset( $additional_data['location'] ) ) {
			$update_data['location'] = $additional_data['location'];
		}

		// Calculate total amount if hourly rate is set
		if ( ! empty( $entry['hourly_rate'] ) ) {
			$hours = $duration_minutes / 60;
			$update_data['total_amount'] = round( $hours * $entry['hourly_rate'], 2 );
		}

		$wpdb->update(
			$table,
			$update_data,
			array( 'id' => $entry['id'] ),
			null,
			array( '%d' )
		);

		do_action( 'ns_time_clock_out', $entry['id'], $user_id, $update_data );

		return $entry['id'];
	}

	/**
	 * Create time entry manually.
	 *
	 * @param array $entry_data Entry data.
	 * @return int|WP_Error Entry ID on success.
	 */
	public static function create_entry( $entry_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		// Calculate duration if both start and end are provided
		if ( ! empty( $entry_data['start_datetime'] ) && ! empty( $entry_data['end_datetime'] ) ) {
			$start = strtotime( $entry_data['start_datetime'] );
			$end = strtotime( $entry_data['end_datetime'] );
			$total_minutes = floor( ( $end - $start ) / 60 );
			$break_minutes = isset( $entry_data['break_minutes'] ) ? (int) $entry_data['break_minutes'] : 0;
			$entry_data['duration_minutes'] = $total_minutes - $break_minutes;

			// Calculate total amount
			if ( ! empty( $entry_data['hourly_rate'] ) ) {
				$hours = $entry_data['duration_minutes'] / 60;
				$entry_data['total_amount'] = round( $hours * $entry_data['hourly_rate'], 2 );
			}
		}

		// Set default status
		if ( ! isset( $entry_data['status'] ) ) {
			$entry_data['status'] = 'draft';
		}

		$result = $wpdb->insert( $table, $entry_data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create time entry', 'nonprofitsuite' ) );
		}

		$entry_id = $wpdb->insert_id;

		do_action( 'ns_time_entry_created', $entry_id, $entry_data );

		return $entry_id;
	}

	/**
	 * Auto-create time entry from calendar event attendance.
	 *
	 * @param int $event_id  Event ID.
	 * @param int $user_id   User ID.
	 * @param array $attendance_data Attendance data.
	 * @return int|WP_Error Entry ID on success.
	 */
	public static function create_from_event( $event_id, $user_id, $attendance_data = array() ) {
		global $wpdb;
		$events_table = $wpdb->prefix . 'ns_calendar_events';

		$event = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$events_table} WHERE id = %d",
			$event_id
		), ARRAY_A );

		if ( ! $event ) {
			return new WP_Error( 'event_not_found', __( 'Event not found', 'nonprofitsuite' ) );
		}

		// Determine entry type based on event entity type
		$entry_type = 'meeting';
		if ( $event['entity_type'] === 'work_schedule' ) {
			$entry_type = 'volunteer';
		}

		$entry_data = array(
			'user_id'           => $user_id,
			'entry_type'        => $entry_type,
			'calendar_event_id' => $event_id,
			'start_datetime'    => $event['start_datetime'],
			'end_datetime'      => $event['end_datetime'],
			'description'       => $event['title'],
			'location'          => $event['location'],
		);

		// Merge with any additional attendance data
		$entry_data = array_merge( $entry_data, $attendance_data );

		return self::create_entry( $entry_data );
	}

	/**
	 * Submit time entries for approval.
	 *
	 * @param array $entry_ids Array of entry IDs.
	 * @param int   $user_id   User ID (for verification).
	 * @return bool|WP_Error True on success.
	 */
	public static function submit_for_approval( $entry_ids, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		foreach ( $entry_ids as $entry_id ) {
			// Verify ownership
			$owner = $wpdb->get_var( $wpdb->prepare(
				"SELECT user_id FROM {$table} WHERE id = %d",
				$entry_id
			) );

			if ( (int) $owner !== (int) $user_id ) {
				continue;
			}

			$wpdb->update(
				$table,
				array(
					'status'       => 'submitted',
					'submitted_at' => current_time( 'mysql' ),
				),
				array( 'id' => $entry_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		do_action( 'ns_time_entries_submitted', $entry_ids, $user_id );

		return true;
	}

	/**
	 * Approve time entries.
	 *
	 * @param array $entry_ids   Array of entry IDs.
	 * @param int   $approver_id Approver user ID.
	 * @return bool|WP_Error True on success.
	 */
	public static function approve_entries( $entry_ids, $approver_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		foreach ( $entry_ids as $entry_id ) {
			$wpdb->update(
				$table,
				array(
					'status'      => 'approved',
					'approved_by' => $approver_id,
					'approved_at' => current_time( 'mysql' ),
				),
				array( 'id' => $entry_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		do_action( 'ns_time_entries_approved', $entry_ids, $approver_id );

		return true;
	}

	/**
	 * Reject time entries.
	 *
	 * @param array  $entry_ids Array of entry IDs.
	 * @param int    $approver_id Approver user ID.
	 * @param string $reason    Rejection reason.
	 * @return bool True on success.
	 */
	public static function reject_entries( $entry_ids, $approver_id, $reason ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		foreach ( $entry_ids as $entry_id ) {
			$wpdb->update(
				$table,
				array(
					'status'           => 'rejected',
					'approved_by'      => $approver_id,
					'approved_at'      => current_time( 'mysql' ),
					'rejection_reason' => $reason,
				),
				array( 'id' => $entry_id ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		do_action( 'ns_time_entries_rejected', $entry_ids, $approver_id, $reason );

		return true;
	}

	/**
	 * Get user's time card.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Time card data.
	 */
	public static function get_time_card( $user_id, $start_date, $end_date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		$entries = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE user_id = %d
			AND DATE(start_datetime) >= %s
			AND DATE(start_datetime) <= %s
			ORDER BY start_datetime ASC",
			$user_id,
			$start_date,
			$end_date
		), ARRAY_A );

		// Calculate totals
		$total_hours = 0;
		$total_amount = 0;
		$billable_hours = 0;

		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['duration_minutes'] ) ) {
				$hours = $entry['duration_minutes'] / 60;
				$total_hours += $hours;

				if ( ! empty( $entry['is_billable'] ) ) {
					$billable_hours += $hours;
				}

				if ( ! empty( $entry['total_amount'] ) ) {
					$total_amount += $entry['total_amount'];
				}
			}
		}

		return array(
			'entries'        => $entries,
			'total_hours'    => round( $total_hours, 2 ),
			'billable_hours' => round( $billable_hours, 2 ),
			'total_amount'   => round( $total_amount, 2 ),
			'start_date'     => $start_date,
			'end_date'       => $end_date,
		);
	}

	/**
	 * Get volunteer hours summary.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Summary data.
	 */
	public static function get_volunteer_hours( $user_id, $start_date = null, $end_date = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		$where = array();
		$where[] = $wpdb->prepare( "user_id = %d", $user_id );
		$where[] = "entry_type = 'volunteer'";

		if ( $start_date ) {
			$where[] = $wpdb->prepare( "DATE(start_datetime) >= %s", $start_date );
		}

		if ( $end_date ) {
			$where[] = $wpdb->prepare( "DATE(start_datetime) <= %s", $end_date );
		}

		$where_clause = implode( ' AND ', $where );

		$summary = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_entries,
				SUM(duration_minutes) as total_minutes,
				MIN(start_datetime) as first_entry,
				MAX(start_datetime) as last_entry
			FROM {$table}
			WHERE {$where_clause}",
			ARRAY_A
		);

		$summary['total_hours'] = $summary['total_minutes'] ? round( $summary['total_minutes'] / 60, 2 ) : 0;

		return $summary;
	}

	/**
	 * Get pending approvals for a manager.
	 *
	 * @param int $manager_id Manager user ID (optional, shows all if admin).
	 * @return array Array of entries pending approval.
	 */
	public static function get_pending_approvals( $manager_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		$query = "SELECT e.*, u.display_name, u.user_email
				FROM {$table} e
				LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
				WHERE e.status = 'submitted'
				ORDER BY e.submitted_at ASC";

		$entries = $wpdb->get_results( $query, ARRAY_A );

		// Group by user
		$grouped = array();
		foreach ( $entries as $entry ) {
			$user_id = $entry['user_id'];
			if ( ! isset( $grouped[ $user_id ] ) ) {
				$grouped[ $user_id ] = array(
					'user_id'      => $user_id,
					'user_name'    => $entry['display_name'],
					'user_email'   => $entry['user_email'],
					'entries'      => array(),
					'total_hours'  => 0,
					'total_amount' => 0,
				);
			}

			$grouped[ $user_id ]['entries'][] = $entry;

			if ( ! empty( $entry['duration_minutes'] ) ) {
				$grouped[ $user_id ]['total_hours'] += $entry['duration_minutes'] / 60;
			}

			if ( ! empty( $entry['total_amount'] ) ) {
				$grouped[ $user_id ]['total_amount'] += $entry['total_amount'];
			}
		}

		return array_values( $grouped );
	}

	/**
	 * Export time entries to CSV.
	 *
	 * @param array $args Query arguments.
	 * @return string CSV content.
	 */
	public static function export_to_csv( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		$where = array( '1=1' );

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( "user_id = %d", $args['user_id'] );
		}

		if ( ! empty( $args['start_date'] ) ) {
			$where[] = $wpdb->prepare( "DATE(start_datetime) >= %s", $args['start_date'] );
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[] = $wpdb->prepare( "DATE(start_datetime) <= %s", $args['end_date'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( "status = %s", $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		$entries = $wpdb->get_results(
			"SELECT e.*, u.display_name, u.user_email
			FROM {$table} e
			LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
			WHERE {$where_clause}
			ORDER BY start_datetime ASC",
			ARRAY_A
		);

		// Build CSV
		$csv = array();
		$csv[] = array(
			'User',
			'Email',
			'Entry Type',
			'Start Date/Time',
			'End Date/Time',
			'Duration (Hours)',
			'Break (Minutes)',
			'Description',
			'Location',
			'Hourly Rate',
			'Total Amount',
			'Status',
		);

		foreach ( $entries as $entry ) {
			$csv[] = array(
				$entry['display_name'],
				$entry['user_email'],
				ucfirst( $entry['entry_type'] ),
				$entry['start_datetime'],
				$entry['end_datetime'],
				$entry['duration_minutes'] ? round( $entry['duration_minutes'] / 60, 2 ) : '',
				$entry['break_minutes'],
				$entry['description'],
				$entry['location'],
				$entry['hourly_rate'],
				$entry['total_amount'],
				ucfirst( $entry['status'] ),
			);
		}

		// Convert to CSV format
		$output = '';
		foreach ( $csv as $row ) {
			$output .= implode( ',', array_map( function( $field ) {
				return '"' . str_replace( '"', '""', $field ) . '"';
			}, $row ) ) . "\n";
		}

		return $output;
	}

	/**
	 * Check if user is currently clocked in.
	 *
	 * @param int $user_id User ID.
	 * @return array|false Active entry or false.
	 */
	public static function is_clocked_in( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		$entry = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE user_id = %d
			AND end_datetime IS NULL
			LIMIT 1",
			$user_id
		), ARRAY_A );

		return $entry ? $entry : false;
	}

	/**
	 * Get active time entry for a user.
	 *
	 * Alias for is_clocked_in() for semantic clarity.
	 *
	 * @param int $user_id User ID.
	 * @return array|null Active entry or null.
	 */
	public static function get_active_entry( $user_id ) {
		return self::is_clocked_in( $user_id );
	}
}
