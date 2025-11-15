<?php
/**
 * Automatic Calendar Event Creation
 *
 * Automatically creates calendar events when tasks, meetings, filings,
 * and other entities with deadlines are created or updated.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Calendar_Auto_Events Class
 *
 * Handles automatic calendar event generation.
 */
class NonprofitSuite_Calendar_Auto_Events {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Task hooks
		add_action( 'ns_task_created', array( __CLASS__, 'on_task_created' ), 10, 2 );
		add_action( 'ns_task_updated', array( __CLASS__, 'on_task_updated' ), 10, 2 );
		add_action( 'ns_task_deleted', array( __CLASS__, 'on_task_deleted' ), 10, 1 );

		// Meeting hooks
		add_action( 'ns_meeting_created', array( __CLASS__, 'on_meeting_created' ), 10, 2 );
		add_action( 'ns_meeting_updated', array( __CLASS__, 'on_meeting_updated' ), 10, 2 );
		add_action( 'ns_meeting_deleted', array( __CLASS__, 'on_meeting_deleted' ), 10, 1 );

		// Filing hooks
		add_action( 'ns_filing_created', array( __CLASS__, 'on_filing_created' ), 10, 2 );
		add_action( 'ns_filing_updated', array( __CLASS__, 'on_filing_updated' ), 10, 2 );
		add_action( 'ns_filing_deleted', array( __CLASS__, 'on_filing_deleted' ), 10, 1 );

		// Project hooks
		add_action( 'ns_project_created', array( __CLASS__, 'on_project_created' ), 10, 2 );
		add_action( 'ns_project_updated', array( __CLASS__, 'on_project_updated' ), 10, 2 );
		add_action( 'ns_project_deleted', array( __CLASS__, 'on_project_deleted' ), 10, 1 );
	}

	/**
	 * Handle task creation.
	 *
	 * @param int   $task_id   Task ID.
	 * @param array $task_data Task data.
	 */
	public static function on_task_created( $task_id, $task_data ) {
		// Only create calendar event if task has a due date
		if ( empty( $task_data['due_date'] ) ) {
			return;
		}

		$event_data = array(
			'entity_type'         => 'task',
			'entity_id'           => $task_id,
			'calendar_category'   => self::determine_task_category( $task_data ),
			'title'               => ! empty( $task_data['title'] ) ? $task_data['title'] : __( 'Task', 'nonprofitsuite' ),
			'description'         => ! empty( $task_data['description'] ) ? $task_data['description'] : '',
			'start_datetime'      => $task_data['due_date'],
			'end_datetime'        => $task_data['due_date'],
			'all_day'             => 1,
			'due_date'            => $task_data['due_date'],
			'assigned_date'       => ! empty( $task_data['created_at'] ) ? $task_data['created_at'] : current_time( 'mysql' ),
			'created_by'          => ! empty( $task_data['created_by'] ) ? $task_data['created_by'] : get_current_user_id(),
			'assignee_id'         => ! empty( $task_data['assigned_to'] ) ? $task_data['assigned_to'] : null,
			'assigner_id'         => ! empty( $task_data['created_by'] ) ? $task_data['created_by'] : get_current_user_id(),
			'source_committee_id' => ! empty( $task_data['committee_id'] ) ? $task_data['committee_id'] : null,
		);

		$event_id = self::create_or_update_calendar_event( null, $event_data );

		if ( $event_id ) {
			// Link event ID to task
			global $wpdb;
			$task_table = $wpdb->prefix . 'ns_tasks';
			$wpdb->update(
				$task_table,
				array( 'calendar_event_id' => $event_id ),
				array( 'id' => $task_id ),
				array( '%d' ),
				array( '%d' )
			);

			/**
			 * Fires after task calendar event is created.
			 *
			 * @param int $event_id Event ID.
			 * @param int $task_id  Task ID.
			 */
			do_action( 'ns_task_calendar_event_created', $event_id, $task_id );
		}
	}

	/**
	 * Handle task update.
	 *
	 * @param int   $task_id   Task ID.
	 * @param array $task_data Task data.
	 */
	public static function on_task_updated( $task_id, $task_data ) {
		global $wpdb;
		$task_table = $wpdb->prefix . 'ns_tasks';

		// Get existing calendar event ID if any
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$task_table} WHERE id = %d",
			$task_id
		), ARRAY_A );

		$existing_event_id = ! empty( $task['calendar_event_id'] ) ? $task['calendar_event_id'] : null;

		// If task is completed, update calendar event
		if ( ! empty( $task_data['status'] ) && $task_data['status'] === 'completed' ) {
			if ( $existing_event_id ) {
				self::mark_event_completed( $existing_event_id );
			}
			return;
		}

		// If due date is removed, delete calendar event
		if ( empty( $task_data['due_date'] ) && $existing_event_id ) {
			self::delete_calendar_event( $existing_event_id );
			return;
		}

		// If due date exists, create or update event
		if ( ! empty( $task_data['due_date'] ) ) {
			$event_data = array(
				'entity_type'         => 'task',
				'entity_id'           => $task_id,
				'calendar_category'   => self::determine_task_category( $task_data ),
				'title'               => ! empty( $task_data['title'] ) ? $task_data['title'] : __( 'Task', 'nonprofitsuite' ),
				'description'         => ! empty( $task_data['description'] ) ? $task_data['description'] : '',
				'start_datetime'      => $task_data['due_date'],
				'end_datetime'        => $task_data['due_date'],
				'all_day'             => 1,
				'due_date'            => $task_data['due_date'],
				'assignee_id'         => ! empty( $task_data['assigned_to'] ) ? $task_data['assigned_to'] : null,
				'source_committee_id' => ! empty( $task_data['committee_id'] ) ? $task_data['committee_id'] : null,
			);

			$event_id = self::create_or_update_calendar_event( $existing_event_id, $event_data );

			// Link event ID if newly created
			if ( $event_id && ! $existing_event_id ) {
				$wpdb->update(
					$task_table,
					array( 'calendar_event_id' => $event_id ),
					array( 'id' => $task_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Handle task deletion.
	 *
	 * @param int $task_id Task ID.
	 */
	public static function on_task_deleted( $task_id ) {
		global $wpdb;
		$task_table = $wpdb->prefix . 'ns_tasks';

		// Get calendar event ID
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT calendar_event_id FROM {$task_table} WHERE id = %d",
			$task_id
		), ARRAY_A );

		if ( ! empty( $task['calendar_event_id'] ) ) {
			self::delete_calendar_event( $task['calendar_event_id'] );
		}
	}

	/**
	 * Handle meeting creation.
	 *
	 * @param int   $meeting_id   Meeting ID.
	 * @param array $meeting_data Meeting data.
	 */
	public static function on_meeting_created( $meeting_id, $meeting_data ) {
		if ( empty( $meeting_data['meeting_date'] ) ) {
			return;
		}

		$event_data = array(
			'entity_type'       => 'meeting',
			'entity_id'         => $meeting_id,
			'calendar_category' => self::determine_meeting_category( $meeting_data ),
			'title'             => ! empty( $meeting_data['title'] ) ? $meeting_data['title'] : __( 'Meeting', 'nonprofitsuite' ),
			'description'       => ! empty( $meeting_data['description'] ) ? $meeting_data['description'] : '',
			'location'          => ! empty( $meeting_data['location'] ) ? $meeting_data['location'] : '',
			'start_datetime'    => $meeting_data['meeting_date'],
			'end_datetime'      => ! empty( $meeting_data['end_time'] ) ? $meeting_data['end_time'] : $meeting_data['meeting_date'],
			'all_day'           => 0,
			'created_by'        => ! empty( $meeting_data['created_by'] ) ? $meeting_data['created_by'] : get_current_user_id(),
			'source_committee_id' => ! empty( $meeting_data['committee_id'] ) ? $meeting_data['committee_id'] : null,
		);

		// Add attendees from committee members
		if ( ! empty( $meeting_data['committee_id'] ) ) {
			$members = self::get_committee_members( $meeting_data['committee_id'] );
			if ( ! empty( $members ) ) {
				$event_data['related_users'] = $members;
			}
		}

		$event_id = self::create_or_update_calendar_event( null, $event_data );

		if ( $event_id ) {
			global $wpdb;
			$meeting_table = $wpdb->prefix . 'ns_meetings';
			$wpdb->update(
				$meeting_table,
				array( 'calendar_event_id' => $event_id ),
				array( 'id' => $meeting_id ),
				array( '%d' ),
				array( '%d' )
			);

			do_action( 'ns_meeting_calendar_event_created', $event_id, $meeting_id );
		}
	}

	/**
	 * Handle meeting update.
	 *
	 * @param int   $meeting_id   Meeting ID.
	 * @param array $meeting_data Meeting data.
	 */
	public static function on_meeting_updated( $meeting_id, $meeting_data ) {
		global $wpdb;
		$meeting_table = $wpdb->prefix . 'ns_meetings';

		$meeting = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$meeting_table} WHERE id = %d",
			$meeting_id
		), ARRAY_A );

		$existing_event_id = ! empty( $meeting['calendar_event_id'] ) ? $meeting['calendar_event_id'] : null;

		if ( empty( $meeting_data['meeting_date'] ) && $existing_event_id ) {
			self::delete_calendar_event( $existing_event_id );
			return;
		}

		if ( ! empty( $meeting_data['meeting_date'] ) ) {
			$event_data = array(
				'entity_type'         => 'meeting',
				'entity_id'           => $meeting_id,
				'calendar_category'   => self::determine_meeting_category( $meeting_data ),
				'title'               => ! empty( $meeting_data['title'] ) ? $meeting_data['title'] : __( 'Meeting', 'nonprofitsuite' ),
				'description'         => ! empty( $meeting_data['description'] ) ? $meeting_data['description'] : '',
				'location'            => ! empty( $meeting_data['location'] ) ? $meeting_data['location'] : '',
				'start_datetime'      => $meeting_data['meeting_date'],
				'end_datetime'        => ! empty( $meeting_data['end_time'] ) ? $meeting_data['end_time'] : $meeting_data['meeting_date'],
				'all_day'             => 0,
				'source_committee_id' => ! empty( $meeting_data['committee_id'] ) ? $meeting_data['committee_id'] : null,
			);

			if ( ! empty( $meeting_data['committee_id'] ) ) {
				$members = self::get_committee_members( $meeting_data['committee_id'] );
				if ( ! empty( $members ) ) {
					$event_data['related_users'] = $members;
				}
			}

			$event_id = self::create_or_update_calendar_event( $existing_event_id, $event_data );

			if ( $event_id && ! $existing_event_id ) {
				$wpdb->update(
					$meeting_table,
					array( 'calendar_event_id' => $event_id ),
					array( 'id' => $meeting_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Handle meeting deletion.
	 *
	 * @param int $meeting_id Meeting ID.
	 */
	public static function on_meeting_deleted( $meeting_id ) {
		global $wpdb;
		$meeting_table = $wpdb->prefix . 'ns_meetings';

		$meeting = $wpdb->get_row( $wpdb->prepare(
			"SELECT calendar_event_id FROM {$meeting_table} WHERE id = %d",
			$meeting_id
		), ARRAY_A );

		if ( ! empty( $meeting['calendar_event_id'] ) ) {
			self::delete_calendar_event( $meeting['calendar_event_id'] );
		}
	}

	/**
	 * Handle filing creation (placeholder for future).
	 *
	 * @param int   $filing_id   Filing ID.
	 * @param array $filing_data Filing data.
	 */
	public static function on_filing_created( $filing_id, $filing_data ) {
		// TODO: Implement when filing system is added
	}

	/**
	 * Handle filing update (placeholder for future).
	 *
	 * @param int   $filing_id   Filing ID.
	 * @param array $filing_data Filing data.
	 */
	public static function on_filing_updated( $filing_id, $filing_data ) {
		// TODO: Implement when filing system is added
	}

	/**
	 * Handle filing deletion (placeholder for future).
	 *
	 * @param int $filing_id Filing ID.
	 */
	public static function on_filing_deleted( $filing_id ) {
		// TODO: Implement when filing system is added
	}

	/**
	 * Handle project creation (placeholder for future).
	 *
	 * @param int   $project_id   Project ID.
	 * @param array $project_data Project data.
	 */
	public static function on_project_created( $project_id, $project_data ) {
		// TODO: Implement when project system is added
	}

	/**
	 * Handle project update (placeholder for future).
	 *
	 * @param int   $project_id   Project ID.
	 * @param array $project_data Project data.
	 */
	public static function on_project_updated( $project_id, $project_data ) {
		// TODO: Implement when project system is added
	}

	/**
	 * Handle project deletion (placeholder for future).
	 *
	 * @param int $project_id Project ID.
	 */
	public static function on_project_deleted( $project_id ) {
		// TODO: Implement when project system is added
	}

	/**
	 * Create or update a calendar event.
	 *
	 * @param int|null $event_id   Existing event ID or null to create new.
	 * @param array    $event_data Event data.
	 * @return int|null Event ID on success, null on failure.
	 */
	private static function create_or_update_calendar_event( $event_id, $event_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		// Calculate visibility
		$visible_calendars = NonprofitSuite_Calendar_Visibility::calculate_visibility( $event_data );
		$event_data['visible_on_calendars'] = wp_json_encode( $visible_calendars );

		// Encode JSON fields
		if ( isset( $event_data['related_users'] ) && is_array( $event_data['related_users'] ) ) {
			$event_data['related_users'] = wp_json_encode( $event_data['related_users'] );
		}

		if ( $event_id ) {
			// Update existing event
			$wpdb->update(
				$table,
				$event_data,
				array( 'id' => $event_id ),
				null,
				array( '%d' )
			);

			// Push to external providers
			NonprofitSuite_Calendar_Push::push_event( $event_id );

			return $event_id;
		} else {
			// Create new event
			$event_data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $event_data );

			$new_event_id = $wpdb->insert_id;

			if ( $new_event_id ) {
				// Push to external providers
				NonprofitSuite_Calendar_Push::push_event( $new_event_id );
			}

			return $new_event_id;
		}
	}

	/**
	 * Delete a calendar event.
	 *
	 * @param int $event_id Event ID.
	 */
	private static function delete_calendar_event( $event_id ) {
		// Delete from external providers first
		NonprofitSuite_Calendar_Push::delete_event_from_providers( $event_id );

		// Delete from database
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';
		$wpdb->delete( $table, array( 'id' => $event_id ), array( '%d' ) );
	}

	/**
	 * Mark an event as completed.
	 *
	 * @param int $event_id Event ID.
	 */
	private static function mark_event_completed( $event_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		$wpdb->update(
			$table,
			array( 'completed_date' => current_time( 'mysql' ) ),
			array( 'id' => $event_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Re-push to update external calendars
		NonprofitSuite_Calendar_Push::push_event( $event_id );
	}

	/**
	 * Determine calendar category for a task.
	 *
	 * @param array $task_data Task data.
	 * @return string Calendar category.
	 */
	private static function determine_task_category( $task_data ) {
		if ( ! empty( $task_data['committee_id'] ) ) {
			return 'committee_' . $task_data['committee_id'];
		}

		return 'general';
	}

	/**
	 * Determine calendar category for a meeting.
	 *
	 * @param array $meeting_data Meeting data.
	 * @return string Calendar category.
	 */
	private static function determine_meeting_category( $meeting_data ) {
		if ( ! empty( $meeting_data['committee_id'] ) ) {
			return 'committee_' . $meeting_data['committee_id'];
		}

		if ( ! empty( $meeting_data['is_board_meeting'] ) ) {
			return 'board';
		}

		return 'general';
	}

	/**
	 * Get committee members.
	 *
	 * @param int $committee_id Committee ID.
	 * @return array Array of user IDs.
	 */
	private static function get_committee_members( $committee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_committee_members';

		$member_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM {$table} WHERE committee_id = %d AND status = 'active'",
			$committee_id
		) );

		return $member_ids;
	}
}

// Initialize hooks
NonprofitSuite_Calendar_Auto_Events::init();
