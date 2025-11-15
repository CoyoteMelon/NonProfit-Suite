<?php
/**
 * Calendar Visibility Calculator
 *
 * Determines which calendars should display each event based on
 * entity relationships, assignments, and organizational structure.
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
 * NonprofitSuite_Calendar_Visibility Class
 *
 * Intelligently calculates visibility for calendar events.
 */
class NonprofitSuite_Calendar_Visibility {

	/**
	 * Calculate which calendars should display an event.
	 *
	 * @param array $event_data Event data with relationship fields.
	 * @return array Array of calendar identifiers (e.g., ['committee_3', 'user_5', 'user_8']).
	 */
	public static function calculate_visibility( $event_data ) {
		$calendars = array();

		// Add calendar category (e.g., 'public', 'board', 'committee_3')
		if ( ! empty( $event_data['calendar_category'] ) ) {
			$category = $event_data['calendar_category'];

			// Handle committee-specific categories
			if ( strpos( $category, 'committee_' ) === 0 ) {
				$calendars[] = $category;
			} else {
				// General categories (public, board, general, etc.)
				$calendars[] = $category;
			}
		}

		// Add source committee calendar
		if ( ! empty( $event_data['source_committee_id'] ) ) {
			$calendars[] = 'committee_' . $event_data['source_committee_id'];
		}

		// Add target committee calendar
		if ( ! empty( $event_data['target_committee_id'] ) ) {
			$calendars[] = 'committee_' . $event_data['target_committee_id'];
		}

		// Add assigner's personal calendar
		if ( ! empty( $event_data['assigner_id'] ) ) {
			$calendars[] = 'user_' . $event_data['assigner_id'];
		}

		// Add assignee's personal calendar
		if ( ! empty( $event_data['assignee_id'] ) ) {
			$calendars[] = 'user_' . $event_data['assignee_id'];
		}

		// Add all related users' calendars
		if ( ! empty( $event_data['related_users'] ) ) {
			$related_users = is_string( $event_data['related_users'] )
				? json_decode( $event_data['related_users'], true )
				: $event_data['related_users'];

			if ( is_array( $related_users ) ) {
				foreach ( $related_users as $user_id ) {
					$calendars[] = 'user_' . $user_id;
				}
			}
		}

		// Add creator's calendar
		if ( ! empty( $event_data['created_by'] ) ) {
			$calendars[] = 'user_' . $event_data['created_by'];
		}

		// Add attendees to related users if provided
		if ( ! empty( $event_data['attendees'] ) ) {
			$attendees = is_string( $event_data['attendees'] )
				? json_decode( $event_data['attendees'], true )
				: $event_data['attendees'];

			if ( is_array( $attendees ) ) {
				foreach ( $attendees as $attendee ) {
					// If attendee is a user ID
					if ( is_numeric( $attendee ) ) {
						$calendars[] = 'user_' . $attendee;
					}
					// If attendee is an email, try to find user
					elseif ( is_email( $attendee ) ) {
						$user = get_user_by( 'email', $attendee );
						if ( $user ) {
							$calendars[] = 'user_' . $user->ID;
						}
					}
				}
			}
		}

		// Remove duplicates and return
		$calendars = array_unique( $calendars );

		/**
		 * Filter the calculated calendar visibility.
		 *
		 * @param array $calendars   Array of calendar identifiers.
		 * @param array $event_data  Event data.
		 */
		return apply_filters( 'ns_calendar_event_visibility', $calendars, $event_data );
	}

	/**
	 * Get calendars visible to a specific user.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of calendar identifiers the user can see.
	 */
	public static function get_user_calendars( $user_id ) {
		$calendars = array();

		// User's personal calendar
		$calendars[] = 'user_' . $user_id;

		// Public calendar (everyone can see)
		$calendars[] = 'public';

		// Get user's committee memberships
		global $wpdb;
		$committee_table = $wpdb->prefix . 'ns_committees';
		$member_table = $wpdb->prefix . 'ns_committee_members';

		$committees = $wpdb->get_col( $wpdb->prepare(
			"SELECT committee_id FROM {$member_table} WHERE user_id = %d AND status = 'active'",
			$user_id
		) );

		foreach ( $committees as $committee_id ) {
			$calendars[] = 'committee_' . $committee_id;
		}

		// Check if user is on the board
		$user = get_userdata( $user_id );
		if ( $user && ( in_array( 'administrator', $user->roles ) ||
		               in_array( 'board_member', $user->roles ) ||
		               user_can( $user_id, 'ns_manage_meetings' ) ) ) {
			$calendars[] = 'board';
		}

		/**
		 * Filter calendars visible to user.
		 *
		 * @param array $calendars Array of calendar identifiers.
		 * @param int   $user_id   User ID.
		 */
		return apply_filters( 'ns_user_visible_calendars', $calendars, $user_id );
	}

	/**
	 * Check if a user can see a specific event.
	 *
	 * @param int   $user_id    User ID.
	 * @param array $event_data Event data or event ID.
	 * @return bool True if user can see the event.
	 */
	public static function can_user_see_event( $user_id, $event_data ) {
		// If event_data is an ID, fetch the event
		if ( is_numeric( $event_data ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ns_calendar_events';
			$event_data = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$event_data
			), ARRAY_A );

			if ( ! $event_data ) {
				return false;
			}
		}

		// Get event's visible calendars
		$event_calendars = ! empty( $event_data['visible_on_calendars'] )
			? json_decode( $event_data['visible_on_calendars'], true )
			: self::calculate_visibility( $event_data );

		// Get user's accessible calendars
		$user_calendars = self::get_user_calendars( $user_id );

		// Check for intersection
		$can_see = ! empty( array_intersect( $event_calendars, $user_calendars ) );

		/**
		 * Filter whether user can see event.
		 *
		 * @param bool  $can_see    Whether user can see event.
		 * @param int   $user_id    User ID.
		 * @param array $event_data Event data.
		 */
		return apply_filters( 'ns_user_can_see_calendar_event', $can_see, $user_id, $event_data );
	}

	/**
	 * Update event visibility in database.
	 *
	 * @param int   $event_id   Event ID.
	 * @param array $event_data Event data (optional, will fetch if not provided).
	 * @return bool True on success.
	 */
	public static function update_event_visibility( $event_id, $event_data = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		// Fetch event data if not provided
		if ( null === $event_data ) {
			$event_data = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$event_id
			), ARRAY_A );

			if ( ! $event_data ) {
				return false;
			}
		}

		// Calculate visibility
		$calendars = self::calculate_visibility( $event_data );

		// Update database
		$result = $wpdb->update(
			$table,
			array( 'visible_on_calendars' => wp_json_encode( $calendars ) ),
			array( 'id' => $event_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get events for a specific calendar.
	 *
	 * @param string $calendar_id Calendar identifier (e.g., 'committee_3', 'user_5').
	 * @param array  $args        Query arguments (start_date, end_date, limit, etc.).
	 * @return array Array of events.
	 */
	public static function get_calendar_events( $calendar_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		$defaults = array(
			'start_date' => null,
			'end_date'   => null,
			'limit'      => 100,
			'offset'     => 0,
			'order'      => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query
		$where = array();
		$where[] = $wpdb->prepare(
			"(visible_on_calendars LIKE %s OR visible_on_calendars IS NULL)",
			'%"' . $wpdb->esc_like( $calendar_id ) . '"%'
		);

		if ( $args['start_date'] ) {
			$where[] = $wpdb->prepare( "start_datetime >= %s", $args['start_date'] );
		}

		if ( $args['end_date'] ) {
			$where[] = $wpdb->prepare( "end_datetime <= %s", $args['end_date'] );
		}

		$where_clause = implode( ' AND ', $where );
		$order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$table}
		        WHERE {$where_clause}
		        ORDER BY start_datetime {$order}
		        LIMIT %d OFFSET %d";

		$events = $wpdb->get_results( $wpdb->prepare(
			$sql,
			$args['limit'],
			$args['offset']
		), ARRAY_A );

		return $events;
	}

	/**
	 * Get events for a user based on their accessible calendars.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Query arguments.
	 * @return array Array of events.
	 */
	public static function get_user_events( $user_id, $args = array() ) {
		$user_calendars = self::get_user_calendars( $user_id );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		$defaults = array(
			'start_date' => null,
			'end_date'   => null,
			'limit'      => 100,
			'offset'     => 0,
			'order'      => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build LIKE conditions for each calendar
		$calendar_conditions = array();
		foreach ( $user_calendars as $calendar_id ) {
			$calendar_conditions[] = $wpdb->prepare(
				"visible_on_calendars LIKE %s",
				'%"' . $wpdb->esc_like( $calendar_id ) . '"%'
			);
		}

		$where = array();
		$where[] = '(' . implode( ' OR ', $calendar_conditions ) . ')';

		if ( $args['start_date'] ) {
			$where[] = $wpdb->prepare( "start_datetime >= %s", $args['start_date'] );
		}

		if ( $args['end_date'] ) {
			$where[] = $wpdb->prepare( "end_datetime <= %s", $args['end_date'] );
		}

		$where_clause = implode( ' AND ', $where );
		$order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$table}
		        WHERE {$where_clause}
		        ORDER BY start_datetime {$order}
		        LIMIT %d OFFSET %d";

		$events = $wpdb->get_results( $wpdb->prepare(
			$sql,
			$args['limit'],
			$args['offset']
		), ARRAY_A );

		return $events;
	}
}
