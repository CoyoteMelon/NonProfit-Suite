<?php
/**
 * Chair Module - Board Leadership Hub
 *
 * Executive dashboard for Board Chair including strategic oversight,
 * agenda planning, leadership tools, and comprehensive metrics.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Chair {

	/**
	 * Create a private chair note
	 *
	 * @param array $data Note data
	 * @return int|WP_Error Note ID or error
	 */
	public static function create_note( $data ) {
		// Check permissions FIRST - chair role functionality
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage chair notes' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'note_type' => '',
			'related_id' => null,
			'related_type' => null,
			'title' => null,
			'content' => '',
			'is_private' => 1,
			'meeting_id' => null,
			'person_id' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['note_type'] ) || empty( $data['content'] ) ) {
			return new WP_Error( 'missing_required', __( 'Note type and content are required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_chair_notes',
			array(
				'note_type' => sanitize_text_field( $data['note_type'] ),
				'related_id' => absint( $data['related_id'] ),
				'related_type' => sanitize_text_field( $data['related_type'] ),
				'title' => sanitize_text_field( $data['title'] ),
				'content' => sanitize_textarea_field( $data['content'] ),
				'is_private' => absint( $data['is_private'] ),
				'meeting_id' => absint( $data['meeting_id'] ),
				'person_id' => absint( $data['person_id'] ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create note.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'chair_notes' );
		return $wpdb->insert_id;
	}

	/**
	 * Get chair notes with filters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_notes( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'note_type' => null,
			'related_id' => null,
			'related_type' => null,
			'meeting_id' => null,
			'person_id' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['note_type'] ) ) {
			$where[] = 'note_type = %s';
			$params[] = $args['note_type'];
		}

		if ( ! empty( $args['related_id'] ) ) {
			$where[] = 'related_id = %d';
			$params[] = $args['related_id'];
		}

		if ( ! empty( $args['related_type'] ) ) {
			$where[] = 'related_type = %s';
			$params[] = $args['related_type'];
		}

		if ( ! empty( $args['meeting_id'] ) ) {
			$where[] = 'meeting_id = %d';
			$params[] = $args['meeting_id'];
		}

		if ( ! empty( $args['person_id'] ) ) {
			$where[] = 'person_id = %d';
			$params[] = $args['person_id'];
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for chair notes
		$cache_key = NonprofitSuite_Cache::list_key( 'chair_notes', $args );
		$results = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $params, $args ) {
			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			$sql = "SELECT id, note_type, related_id, related_type, title, content, is_private,
			               meeting_id, person_id, created_at
			        FROM {$wpdb->prefix}ns_chair_notes
					WHERE $where_clause
					ORDER BY $orderby
					" . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare( $sql, $params );
			}

			return $wpdb->get_results( $sql );
		}, 300 );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch notes.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Get meeting prep notes for a specific meeting
	 *
	 * @param int $meeting_id Meeting ID
	 * @return array|WP_Error
	 */
	public static function get_meeting_prep_notes( $meeting_id ) {
		return self::get_notes( array(
			'note_type' => 'meeting_prep',
			'meeting_id' => $meeting_id,
		) );
	}

	/**
	 * Get all notes about a board member
	 *
	 * @param int $person_id Person ID
	 * @return array|WP_Error
	 */
	public static function get_board_member_notes( $person_id ) {
		return self::get_notes( array(
			'person_id' => $person_id,
		) );
	}

	/**
	 * Get comprehensive dashboard metrics
	 *
	 * @return array
	 */
	public static function get_dashboard_metrics() {
		global $wpdb;

		$metrics = array(
			'governance' => array(
				'attendance_rate' => 0,
				'quorum_status' => 0,
				'minutes_approval_rate' => 0,
				'committee_meetings' => 0,
			),
			'financial' => array(
				'cash_position' => 0,
				'budget_variance' => 0,
				'fundraising_vs_goal' => 0,
				'major_gifts_pending' => 0,
			),
			'strategic' => array(
				'active_projects' => 0,
				'projects_on_schedule' => 0,
				'strategic_completion' => 0,
				'board_prospects' => 0,
			),
			'priorities' => array(),
		);

		// Governance: Board attendance rate (last 3 meetings)
		$attendance = $wpdb->get_row(
			"SELECT
				COUNT(DISTINCT ma.meeting_id) as meetings_count,
				COUNT(ma.id) as total_attendances,
				(SELECT COUNT(*) FROM {$wpdb->prefix}ns_people WHERE board_member = 1 AND status = 'active') as board_size
			FROM {$wpdb->prefix}ns_meeting_attendance ma
			INNER JOIN {$wpdb->prefix}ns_meetings m ON ma.meeting_id = m.id
			WHERE m.meeting_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
			AND ma.attended = 1"
		);

		if ( $attendance && $attendance->meetings_count > 0 && $attendance->board_size > 0 ) {
			$expected_attendances = $attendance->meetings_count * $attendance->board_size;
			$metrics['governance']['attendance_rate'] = round( ( $attendance->total_attendances / $expected_attendances ) * 100 );
		}

		// Governance: Minutes approval rate
		$minutes_stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_meetings,
				SUM(CASE WHEN minutes_status = 'approved' THEN 1 ELSE 0 END) as approved_count
			FROM {$wpdb->prefix}ns_meetings
			WHERE meeting_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
		);

		if ( $minutes_stats && $minutes_stats->total_meetings > 0 ) {
			$metrics['governance']['minutes_approval_rate'] = round( ( $minutes_stats->approved_count / $minutes_stats->total_meetings ) * 100 );
		}

		// Governance: Committee meetings this quarter
		$committee_meetings = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_meetings
			WHERE meeting_type = 'committee'
			AND meeting_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
		);
		$metrics['governance']['committee_meetings'] = (int) $committee_meetings;

		// Financial: Cash position (from treasury)
		$cash_position = $wpdb->get_var(
			"SELECT SUM(amount) FROM {$wpdb->prefix}ns_transactions
			WHERE account_type IN ('checking', 'savings')"
		);
		$metrics['financial']['cash_position'] = $cash_position ? (float) $cash_position : 0;

		// Strategic: Active projects
		$active_projects = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_projects
			WHERE status IN ('planning', 'active')"
		);
		$metrics['strategic']['active_projects'] = (int) $active_projects;

		// Strategic: Projects on schedule
		$on_schedule = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_projects
			WHERE status = 'active'
			AND target_end_date >= CURDATE()"
		);
		$metrics['strategic']['projects_on_schedule'] = (int) $on_schedule;

		// Strategic: Board prospects
		$prospects = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_board_prospects
			WHERE stage NOT IN ('declined', 'joined')"
		);
		$metrics['strategic']['board_prospects'] = (int) $prospects;

		return $metrics;
	}

	/**
	 * Get committee status report
	 *
	 * @return array|WP_Error
	 */
	public static function get_committee_status_report() {
		global $wpdb;

		// Get all committees with their last meeting date
		$sql = "SELECT
					c.id,
					c.committee_name,
					c.status,
					MAX(m.meeting_date) as last_meeting
				FROM {$wpdb->prefix}ns_committees c
				LEFT JOIN {$wpdb->prefix}ns_meetings m
					ON m.meeting_type = 'committee' AND m.related_id = c.id
				GROUP BY c.id
				ORDER BY c.committee_name ASC";

		$committees = $wpdb->get_results( $sql );

		if ( null === $committees ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch committee status.', 'nonprofitsuite' ) );
		}

		// Add status indicators
		$three_months_ago = date( 'Y-m-d', strtotime( '-3 months' ) );

		foreach ( $committees as &$committee ) {
			$committee->activity_status = 'active';

			if ( empty( $committee->last_meeting ) ) {
				$committee->activity_status = 'no_meetings';
			} elseif ( $committee->last_meeting < $three_months_ago ) {
				$committee->activity_status = 'inactive';
			}
		}

		return $committees;
	}

	/**
	 * Get upcoming board actions
	 *
	 * @return array
	 */
	public static function get_upcoming_board_actions() {
		global $wpdb;

		$actions = array();

		// Pending minutes approvals
		$pending_minutes = $wpdb->get_results(
			"SELECT id, title, meeting_date
			FROM {$wpdb->prefix}ns_meetings
			WHERE minutes_status = 'draft'
			ORDER BY meeting_date DESC
			LIMIT 5"
		);

		foreach ( $pending_minutes as $meeting ) {
			$actions[] = array(
				'type' => 'minutes_approval',
				'title' => sprintf( __( 'Approve %s Minutes', 'nonprofitsuite' ), $meeting->title ),
				'date' => $meeting->meeting_date,
				'priority' => 'high',
				'related_id' => $meeting->id,
			);
		}

		// Policy reviews due
		$policy_reviews = $wpdb->get_results(
			"SELECT id, title, next_review_date
			FROM {$wpdb->prefix}ns_policies
			WHERE next_review_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
			AND status = 'approved'
			ORDER BY next_review_date ASC
			LIMIT 5"
		);

		foreach ( $policy_reviews as $policy ) {
			$actions[] = array(
				'type' => 'policy_review',
				'title' => sprintf( __( 'Review %s Policy', 'nonprofitsuite' ), $policy->title ),
				'date' => $policy->next_review_date,
				'priority' => 'medium',
				'related_id' => $policy->id,
			);
		}

		// Board term expirations
		$term_expirations = $wpdb->get_results(
			"SELECT bt.id, p.first_name, p.last_name, bt.end_date
			FROM {$wpdb->prefix}ns_board_terms bt
			INNER JOIN {$wpdb->prefix}ns_people p ON bt.person_id = p.id
			WHERE bt.end_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
			AND bt.end_date >= CURDATE()
			ORDER BY bt.end_date ASC
			LIMIT 5"
		);

		foreach ( $term_expirations as $term ) {
			$actions[] = array(
				'type' => 'term_expiration',
				'title' => sprintf( __( '%s %s Term Expiring', 'nonprofitsuite' ), $term->first_name, $term->last_name ),
				'date' => $term->end_date,
				'priority' => 'high',
				'related_id' => $term->id,
			);
		}

		// Sort by date
		usort( $actions, function( $a, $b ) {
			return strtotime( $a['date'] ) - strtotime( $b['date'] );
		} );

		return array_slice( $actions, 0, 10 );
	}

	/**
	 * Get strategic priorities with progress
	 *
	 * @return array|WP_Error
	 */
	public static function get_strategic_priorities() {
		global $wpdb;

		// Get active projects as strategic priorities
		$sql = "SELECT
					id,
					name as title,
					description,
					status,
					start_date,
					target_end_date
				FROM {$wpdb->prefix}ns_projects
				WHERE status IN ('planning', 'active')
				AND priority IN ('high', 'urgent')
				ORDER BY priority DESC, start_date ASC
				LIMIT 10";

		$priorities = $wpdb->get_results( $sql );

		if ( null === $priorities ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch strategic priorities.', 'nonprofitsuite' ) );
		}

		// Add progress for each priority
		foreach ( $priorities as &$priority ) {
			$priority->progress = NonprofitSuite_Projects::calculate_progress( $priority->id );
		}

		return $priorities;
	}

	/**
	 * Create meeting from template
	 *
	 * @param string $template_name Template type
	 * @return int|WP_Error Meeting ID or error
	 */
	public static function create_meeting_from_template( $template_name ) {
		// Check permissions FIRST - chair role functionality
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage chair notes' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$templates = array(
			'regular_board' => array(
				'title' => __( 'Regular Board Meeting', 'nonprofitsuite' ),
				'meeting_type' => 'board',
				'agenda_template' => "1. Call to Order\n2. Approval of Minutes\n3. Financial Report\n4. Executive Director Report\n5. Committee Reports\n6. Old Business\n7. New Business\n8. Adjournment",
			),
			'annual_meeting' => array(
				'title' => __( 'Annual Meeting', 'nonprofitsuite' ),
				'meeting_type' => 'board',
				'agenda_template' => "1. Call to Order\n2. Annual Report\n3. Financial Review\n4. Election of Officers\n5. Strategic Planning Review\n6. Other Business\n7. Adjournment",
			),
			'executive_session' => array(
				'title' => __( 'Executive Session', 'nonprofitsuite' ),
				'meeting_type' => 'board',
				'agenda_template' => "1. Call to Order\n2. Executive Session (Board Members Only)\n   - Personnel matters\n   - Legal matters\n   - Confidential business\n3. Return to Open Session\n4. Adjournment",
			),
			'special_meeting' => array(
				'title' => __( 'Special Board Meeting', 'nonprofitsuite' ),
				'meeting_type' => 'board',
				'agenda_template' => "1. Call to Order\n2. Special Business Item\n3. Discussion and Vote\n4. Adjournment",
			),
		);

		if ( ! isset( $templates[ $template_name ] ) ) {
			return new WP_Error( 'invalid_template', __( 'Invalid template name.', 'nonprofitsuite' ) );
		}

		$template = $templates[ $template_name ];

		// Create meeting using the Meetings module
		if ( class_exists( 'NonprofitSuite_Meetings' ) ) {
			$meeting_data = array(
				'title' => $template['title'],
				'meeting_type' => $template['meeting_type'],
				'meeting_date' => date( 'Y-m-d' ),
				'start_time' => '18:00:00',
				'agenda' => $template['agenda_template'],
				'status' => 'scheduled',
			);

			return NonprofitSuite_Meetings::create_meeting( $meeting_data );
		}

		return new WP_Error( 'meetings_module_missing', __( 'Meetings module not available.', 'nonprofitsuite' ) );
	}

	/**
	 * Send agenda request for input to board members
	 *
	 * @param int $meeting_id Meeting ID
	 * @param array $board_member_ids Array of person IDs
	 * @return bool|WP_Error
	 */
	public static function send_agenda_for_input( $meeting_id, $board_member_ids ) {
		// This would integrate with the Communications module
		// For now, return a placeholder

		if ( empty( $board_member_ids ) ) {
			return new WP_Error( 'no_recipients', __( 'No board members specified.', 'nonprofitsuite' ) );
		}

		// Create a note about this request
		$note_data = array(
			'note_type' => 'meeting_prep',
			'meeting_id' => $meeting_id,
			'title' => __( 'Agenda Input Requested', 'nonprofitsuite' ),
			'content' => sprintf(
				__( 'Requested agenda input from %d board members on %s', 'nonprofitsuite' ),
				count( $board_member_ids ),
				current_time( 'mysql' )
			),
			'is_private' => 1,
		);

		$note_id = self::create_note( $note_data );

		if ( is_wp_error( $note_id ) ) {
			return $note_id;
		}

		return true;
	}
}
