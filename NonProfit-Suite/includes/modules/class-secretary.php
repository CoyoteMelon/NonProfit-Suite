<?php
/**
 * Secretary Module - Governance Authority Dashboard
 *
 * Centralized oversight for the Secretary role including meeting management,
 * official records, document authority, and automated task creation.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Secretary {

	/**
	 * Create a secretary task
	 *
	 * @param array $data Task data
	 * @return int|WP_Error Task ID or error
	 */
	public static function create_task( $data ) {
		// Check permissions FIRST - secretary role functionality
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage secretary tasks' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'task_type' => '',
			'related_id' => null,
			'related_type' => null,
			'title' => '',
			'description' => null,
			'due_date' => null,
			'status' => 'pending',
			'priority' => 'medium',
			'completed_date' => null,
			'notes' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['task_type'] ) || empty( $data['title'] ) ) {
			return new WP_Error( 'missing_required', __( 'Task type and title are required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_secretary_tasks',
			array(
				'task_type' => sanitize_text_field( $data['task_type'] ),
				'related_id' => absint( $data['related_id'] ),
				'related_type' => sanitize_text_field( $data['related_type'] ),
				'title' => sanitize_text_field( $data['title'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'due_date' => $data['due_date'],
				'status' => sanitize_text_field( $data['status'] ),
				'priority' => sanitize_text_field( $data['priority'] ),
				'completed_date' => $data['completed_date'],
				'notes' => sanitize_textarea_field( $data['notes'] ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create task.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'secretary_tasks' );
		return $wpdb->insert_id;
	}

	/**
	 * Get secretary tasks with filters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_tasks( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'task_type' => null,
			'status' => null,
			'priority' => null,
			'related_id' => null,
			'related_type' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['task_type'] ) ) {
			$where[] = 'task_type = %s';
			$params[] = $args['task_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['priority'] ) ) {
			$where[] = 'priority = %s';
			$params[] = $args['priority'];
		}

		if ( ! empty( $args['related_id'] ) ) {
			$where[] = 'related_id = %d';
			$params[] = $args['related_id'];
		}

		if ( ! empty( $args['related_type'] ) ) {
			$where[] = 'related_type = %s';
			$params[] = $args['related_type'];
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for secretary tasks
		$cache_key = NonprofitSuite_Cache::list_key( 'secretary_tasks', $args );
		$results = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $params, $args ) {
			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			$sql = "SELECT id, task_type, related_id, related_type, title, description, due_date,
			               status, priority, completed_date, notes, created_at
			        FROM {$wpdb->prefix}ns_secretary_tasks
					WHERE $where_clause
					ORDER BY $orderby
					" . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare( $sql, $params );
			}

			return $wpdb->get_results( $sql );
		}, 300 );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch tasks.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Get overdue tasks
	 *
	 * @return array|WP_Error
	 */
	public static function get_overdue_tasks() {
		global $wpdb;

		// Use caching for overdue tasks
		$cache_key = NonprofitSuite_Cache::item_key( 'secretary_overdue_tasks', date( 'Y-m-d' ) );
		$results = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb ) {
			$sql = $wpdb->prepare(
				"SELECT id, task_type, related_id, related_type, title, description, due_date,
				        status, priority, completed_date, notes, created_at
				 FROM {$wpdb->prefix}ns_secretary_tasks
				WHERE status NOT IN ('completed')
				AND due_date < %s
				ORDER BY due_date ASC",
				current_time( 'mysql', false )
			);

			return $wpdb->get_results( $sql );
		}, 300 );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch overdue tasks.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Complete a task
	 *
	 * @param int $id Task ID
	 * @param string $notes Optional completion notes
	 * @return bool|WP_Error
	 */
	public static function complete_task( $id, $notes = '' ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_secretary_tasks',
			array(
				'status' => 'completed',
				'completed_date' => current_time( 'mysql', false ),
				'notes' => sanitize_textarea_field( $notes ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to complete task.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'secretary_tasks' );
		return true;
	}

	/**
	 * Get dashboard summary with counts
	 *
	 * @return array
	 */
	public static function get_dashboard_summary() {
		global $wpdb;

		$summary = array(
			'pending' => 0,
			'in_progress' => 0,
			'completed' => 0,
			'overdue' => 0,
			'this_week' => 0,
			'by_type' => array(),
		);

		// Count by status
		$status_counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$wpdb->prefix}ns_secretary_tasks
			GROUP BY status"
		);

		foreach ( $status_counts as $row ) {
			$summary[ $row->status ] = (int) $row->count;
		}

		// Count overdue
		$overdue = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_secretary_tasks
			WHERE status NOT IN ('completed')
			AND due_date < %s",
			current_time( 'mysql', false )
		) );
		$summary['overdue'] = (int) $overdue;

		// Count this week
		$week_end = date( 'Y-m-d', strtotime( '+7 days' ) );
		$this_week = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_secretary_tasks
			WHERE status NOT IN ('completed')
			AND due_date <= %s",
			$week_end
		) );
		$summary['this_week'] = (int) $this_week;

		// Count by type
		$type_counts = $wpdb->get_results(
			"SELECT task_type, COUNT(*) as count
			FROM {$wpdb->prefix}ns_secretary_tasks
			WHERE status NOT IN ('completed')
			GROUP BY task_type"
		);

		foreach ( $type_counts as $row ) {
			$summary['by_type'][ $row->task_type ] = (int) $row->count;
		}

		return $summary;
	}

	/**
	 * Get pending minutes reviews
	 *
	 * @return array|WP_Error
	 */
	public static function get_pending_minutes_reviews() {
		global $wpdb;

		// Use caching for pending minutes
		$cache_key = NonprofitSuite_Cache::item_key( 'secretary_pending_minutes', 'list' );
		$results = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb ) {
			// Get all meetings with draft minutes (status = 'draft')
			$sql = "SELECT m.id, m.title, m.meeting_date, m.meeting_time, m.location, m.meeting_type,
			               m.agenda, m.attendees, m.minutes, m.minutes_status, m.notes, m.created_at, t.id as task_id
					FROM {$wpdb->prefix}ns_meetings m
					LEFT JOIN {$wpdb->prefix}ns_secretary_tasks t
						ON t.related_id = m.id AND t.related_type = 'meeting' AND t.task_type = 'minutes_review'
					WHERE m.minutes_status = 'draft'
					ORDER BY m.meeting_date DESC
					LIMIT 20";

			return $wpdb->get_results( $sql );
		}, 300 );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch pending minutes.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Get upcoming meetings needing preparation
	 *
	 * @param int $days_ahead Number of days to look ahead
	 * @return array|WP_Error
	 */
	public static function get_upcoming_meetings( $days_ahead = 14 ) {
		global $wpdb;

		$date_limit = date( 'Y-m-d', strtotime( "+$days_ahead days" ) );

		// Use caching for upcoming meetings
		$cache_key = NonprofitSuite_Cache::item_key( 'secretary_upcoming_meetings', $days_ahead );
		$results = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $date_limit ) {
			$sql = $wpdb->prepare(
				"SELECT id, title, meeting_date, meeting_time, location, meeting_type, agenda,
				        attendees, minutes, minutes_status, notes, created_at
				 FROM {$wpdb->prefix}ns_meetings
				WHERE meeting_date >= %s
				AND meeting_date <= %s
				ORDER BY meeting_date ASC
				LIMIT 10",
				current_time( 'Y-m-d' ),
				$date_limit
			);

			return $wpdb->get_results( $sql );
		}, 300 );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch upcoming meetings.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Auto-create tasks based on events
	 * Call this from meeting creation, document upload, etc.
	 *
	 * @param string $trigger_type Type of trigger
	 * @param int $related_id Related item ID
	 * @param array $data Additional data
	 * @return int|WP_Error Task ID or error
	 */
	public static function auto_create_tasks( $trigger_type, $related_id, $data = array() ) {
		$task_data = array();

		switch ( $trigger_type ) {
			case 'meeting_created':
				// Create "Prepare Meeting Materials" task 3 days before meeting
				$meeting_date = isset( $data['meeting_date'] ) ? $data['meeting_date'] : '';
				if ( $meeting_date ) {
					$due_date = date( 'Y-m-d', strtotime( $meeting_date . ' -3 days' ) );
					$task_data = array(
						'task_type' => 'meeting_preparation',
						'related_id' => $related_id,
						'related_type' => 'meeting',
						'title' => __( 'Prepare Meeting Materials', 'nonprofitsuite' ) . ' - ' . ( $data['title'] ?? __( 'Meeting', 'nonprofitsuite' ) ),
						'description' => __( 'Prepare agenda, supporting documents, and distribute materials to board members.', 'nonprofitsuite' ),
						'due_date' => $due_date,
						'priority' => 'high',
					);
				}
				break;

			case 'minutes_draft_saved':
				// Create "Review and Approve Minutes" task
				$task_data = array(
					'task_type' => 'minutes_review',
					'related_id' => $related_id,
					'related_type' => 'meeting',
					'title' => __( 'Review and Approve Minutes', 'nonprofitsuite' ) . ' - ' . ( $data['title'] ?? __( 'Meeting', 'nonprofitsuite' ) ),
					'description' => __( 'Review draft minutes for accuracy and completeness before board approval.', 'nonprofitsuite' ),
					'due_date' => date( 'Y-m-d', strtotime( '+7 days' ) ),
					'priority' => 'high',
				);
				break;

			case 'policy_approved':
				// Create "File Official Policy" task
				$task_data = array(
					'task_type' => 'document_filing',
					'related_id' => $related_id,
					'related_type' => 'policy',
					'title' => __( 'File Official Policy', 'nonprofitsuite' ) . ' - ' . ( $data['title'] ?? __( 'Policy', 'nonprofitsuite' ) ),
					'description' => __( 'File approved policy in official records and distribute to relevant parties.', 'nonprofitsuite' ),
					'due_date' => date( 'Y-m-d', strtotime( '+3 days' ) ),
					'priority' => 'medium',
				);
				break;

			case 'bylaw_amendment_proposed':
				// Create "Process Bylaw Amendment" task
				$task_data = array(
					'task_type' => 'bylaw_amendment',
					'related_id' => $related_id,
					'related_type' => 'policy',
					'title' => __( 'Process Bylaw Amendment', 'nonprofitsuite' ) . ' - ' . ( $data['title'] ?? __( 'Amendment', 'nonprofitsuite' ) ),
					'description' => __( 'Review amendment, schedule board vote, and file with state if approved.', 'nonprofitsuite' ),
					'due_date' => date( 'Y-m-d', strtotime( '+30 days' ) ),
					'priority' => 'high',
				);
				break;

			case 'annual_filing_due':
				// Create annual filing task
				$task_data = array(
					'task_type' => 'annual_filing',
					'related_id' => $related_id,
					'related_type' => 'compliance_item',
					'title' => __( 'Annual Corporate Report Due', 'nonprofitsuite' ),
					'description' => __( 'Prepare and file annual report with state corporate filing office.', 'nonprofitsuite' ),
					'due_date' => isset( $data['due_date'] ) ? $data['due_date'] : date( 'Y-m-d', strtotime( '+30 days' ) ),
					'priority' => 'urgent',
				);
				break;
		}

		if ( ! empty( $task_data ) ) {
			return self::create_task( $task_data );
		}

		return new WP_Error( 'invalid_trigger', __( 'Invalid trigger type.', 'nonprofitsuite' ) );
	}

	/**
	 * Certify a board action
	 *
	 * @param int $meeting_id Meeting ID
	 * @param string $action_description Description of action
	 * @return int|WP_Error Task ID or error
	 */
	public static function certify_board_action( $meeting_id, $action_description ) {
		return self::create_task( array(
			'task_type' => 'certification',
			'related_id' => $meeting_id,
			'related_type' => 'meeting',
			'title' => __( 'Certify Board Action', 'nonprofitsuite' ),
			'description' => sanitize_textarea_field( $action_description ),
			'due_date' => date( 'Y-m-d', strtotime( '+3 days' ) ),
			'priority' => 'high',
		) );
	}

	/**
	 * Get records request log
	 *
	 * @return array|WP_Error
	 */
	public static function get_records_request_log() {
		// Get tasks of type 'records_request'
		return self::get_tasks( array(
			'task_type' => 'records_request',
			'orderby' => 'created_at',
			'order' => 'DESC',
			'limit' => 50,
		) );
	}
}
