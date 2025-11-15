<?php
/**
 * Calendar Conflict Detection & Smart Scheduling
 *
 * Detects scheduling conflicts and suggests available time slots
 * for meetings across multiple attendees' calendars.
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
 * NonprofitSuite_Calendar_Conflicts Class
 *
 * Handles conflict detection and smart scheduling.
 */
class NonprofitSuite_Calendar_Conflicts {

	/**
	 * Check for scheduling conflicts.
	 *
	 * @param array  $user_ids       Array of user IDs to check.
	 * @param string $start_datetime Start datetime (MySQL format).
	 * @param string $end_datetime   End datetime (MySQL format).
	 * @param int    $exclude_event_id Optional event ID to exclude (for updates).
	 * @return array Array of conflicts with user details.
	 */
	public static function check_conflicts( $user_ids, $start_datetime, $end_datetime, $exclude_event_id = null ) {
		if ( empty( $user_ids ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		$conflicts = array();

		foreach ( $user_ids as $user_id ) {
			// Get user's accessible calendars
			$user_calendars = NonprofitSuite_Calendar_Visibility::get_user_calendars( $user_id );

			if ( empty( $user_calendars ) ) {
				continue;
			}

			// Build calendar conditions
			$calendar_conditions = array();
			foreach ( $user_calendars as $calendar_id ) {
				$calendar_conditions[] = $wpdb->prepare(
					"visible_on_calendars LIKE %s",
					'%"' . $wpdb->esc_like( $calendar_id ) . '"%'
				);
			}

			$calendar_where = '(' . implode( ' OR ', $calendar_conditions ) . ')';

			// Build time overlap query
			$time_where = $wpdb->prepare(
				"(
					(start_datetime < %s AND end_datetime > %s) OR
					(start_datetime >= %s AND start_datetime < %s) OR
					(end_datetime > %s AND end_datetime <= %s)
				)",
				$end_datetime,    // Event starts before new event ends
				$start_datetime,  // Event ends after new event starts
				$start_datetime,  // Event starts within new event
				$end_datetime,
				$start_datetime,  // Event ends within new event
				$end_datetime
			);

			// Build full query
			$where = "{$calendar_where} AND {$time_where}";

			if ( $exclude_event_id ) {
				$where .= $wpdb->prepare( ' AND id != %d', $exclude_event_id );
			}

			$conflicting_events = $wpdb->get_results(
				"SELECT * FROM {$table} WHERE {$where}",
				ARRAY_A
			);

			if ( ! empty( $conflicting_events ) ) {
				$user = get_userdata( $user_id );
				$conflicts[] = array(
					'user_id'     => $user_id,
					'user_name'   => $user ? $user->display_name : __( 'Unknown User', 'nonprofitsuite' ),
					'user_email'  => $user ? $user->user_email : '',
					'events'      => $conflicting_events,
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Find available time slots for multiple users.
	 *
	 * @param array  $user_ids        Array of user IDs.
	 * @param int    $duration_minutes Duration in minutes.
	 * @param string $start_date      Start date for search (Y-m-d).
	 * @param string $end_date        End date for search (Y-m-d).
	 * @param array  $business_hours  Business hours array (optional).
	 * @return array Array of available time slots.
	 */
	public static function find_available_slots( $user_ids, $duration_minutes, $start_date, $end_date, $business_hours = null ) {
		if ( empty( $user_ids ) ) {
			return array();
		}

		// Default business hours: Mon-Fri 9AM-5PM
		if ( null === $business_hours ) {
			$business_hours = array(
				'monday'    => array( '09:00', '17:00' ),
				'tuesday'   => array( '09:00', '17:00' ),
				'wednesday' => array( '09:00', '17:00' ),
				'thursday'  => array( '09:00', '17:00' ),
				'friday'    => array( '09:00', '17:00' ),
			);
		}

		// Get all events for all users in date range
		$all_busy_times = self::get_busy_times_for_users( $user_ids, $start_date, $end_date );

		// Generate potential time slots
		$potential_slots = self::generate_potential_slots( $start_date, $end_date, $duration_minutes, $business_hours );

		// Filter out conflicting slots
		$available_slots = array();

		foreach ( $potential_slots as $slot ) {
			$has_conflict = false;

			// Check if this slot conflicts with any user's busy time
			foreach ( $all_busy_times as $user_id => $busy_times ) {
				foreach ( $busy_times as $busy_period ) {
					if ( self::time_periods_overlap( $slot, $busy_period ) ) {
						$has_conflict = true;
						break 2;
					}
				}
			}

			if ( ! $has_conflict ) {
				$available_slots[] = $slot;
			}
		}

		return $available_slots;
	}

	/**
	 * Suggest best meeting times.
	 *
	 * @param array  $user_ids         Array of user IDs.
	 * @param int    $duration_minutes Duration in minutes.
	 * @param string $start_date       Start date for search.
	 * @param string $end_date         End date for search.
	 * @param int    $max_suggestions  Maximum number of suggestions.
	 * @return array Ranked array of suggested time slots.
	 */
	public static function suggest_meeting_times( $user_ids, $duration_minutes, $start_date, $end_date, $max_suggestions = 5 ) {
		$available_slots = self::find_available_slots( $user_ids, $duration_minutes, $start_date, $end_date );

		if ( empty( $available_slots ) ) {
			return array();
		}

		// Score each slot based on various factors
		$scored_slots = array();

		foreach ( $available_slots as $slot ) {
			$score = self::score_time_slot( $slot, $user_ids );

			$scored_slots[] = array(
				'slot'  => $slot,
				'score' => $score,
			);
		}

		// Sort by score (highest first)
		usort( $scored_slots, function( $a, $b ) {
			return $b['score'] - $a['score'];
		} );

		// Return top suggestions
		$suggestions = array_slice( $scored_slots, 0, $max_suggestions );

		return array_map( function( $item ) {
			return $item['slot'];
		}, $suggestions );
	}

	/**
	 * Get busy times for multiple users.
	 *
	 * @param array  $user_ids   Array of user IDs.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Array of busy times indexed by user_id.
	 */
	private static function get_busy_times_for_users( $user_ids, $start_date, $end_date ) {
		$busy_times = array();

		foreach ( $user_ids as $user_id ) {
			$events = NonprofitSuite_Calendar_Visibility::get_user_events( $user_id, array(
				'start_date' => $start_date . ' 00:00:00',
				'end_date'   => $end_date . ' 23:59:59',
				'limit'      => 1000,
			) );

			$busy_periods = array();
			foreach ( $events as $event ) {
				$busy_periods[] = array(
					'start' => $event['start_datetime'],
					'end'   => $event['end_datetime'],
					'title' => $event['title'],
				);
			}

			$busy_times[ $user_id ] = $busy_periods;
		}

		return $busy_times;
	}

	/**
	 * Generate potential time slots within business hours.
	 *
	 * @param string $start_date      Start date.
	 * @param string $end_date        End date.
	 * @param int    $duration_minutes Duration in minutes.
	 * @param array  $business_hours  Business hours configuration.
	 * @return array Array of potential time slots.
	 */
	private static function generate_potential_slots( $start_date, $end_date, $duration_minutes, $business_hours ) {
		$slots = array();
		$current_date = strtotime( $start_date );
		$end_timestamp = strtotime( $end_date );

		$slot_interval = 30; // Generate slots every 30 minutes

		while ( $current_date <= $end_timestamp ) {
			$day_name = strtolower( gmdate( 'l', $current_date ) );

			// Skip if no business hours defined for this day
			if ( ! isset( $business_hours[ $day_name ] ) ) {
				$current_date = strtotime( '+1 day', $current_date );
				continue;
			}

			$hours = $business_hours[ $day_name ];
			$day_start = strtotime( gmdate( 'Y-m-d', $current_date ) . ' ' . $hours[0] );
			$day_end = strtotime( gmdate( 'Y-m-d', $current_date ) . ' ' . $hours[1] );

			// Generate slots for this day
			$slot_start = $day_start;

			while ( $slot_start + ( $duration_minutes * 60 ) <= $day_end ) {
				$slot_end = $slot_start + ( $duration_minutes * 60 );

				$slots[] = array(
					'start' => gmdate( 'Y-m-d H:i:s', $slot_start ),
					'end'   => gmdate( 'Y-m-d H:i:s', $slot_end ),
				);

				$slot_start += ( $slot_interval * 60 );
			}

			$current_date = strtotime( '+1 day', $current_date );
		}

		return $slots;
	}

	/**
	 * Check if two time periods overlap.
	 *
	 * @param array $period1 First period with 'start' and 'end'.
	 * @param array $period2 Second period with 'start' and 'end'.
	 * @return bool True if they overlap.
	 */
	private static function time_periods_overlap( $period1, $period2 ) {
		$start1 = strtotime( $period1['start'] );
		$end1 = strtotime( $period1['end'] );
		$start2 = strtotime( $period2['start'] );
		$end2 = strtotime( $period2['end'] );

		return (
			( $start1 < $end2 && $end1 > $start2 ) ||
			( $start1 >= $start2 && $start1 < $end2 ) ||
			( $end1 > $start2 && $end1 <= $end2 )
		);
	}

	/**
	 * Score a time slot based on various factors.
	 *
	 * Higher scores are better. Factors considered:
	 * - Time of day (prefer mid-morning or early afternoon)
	 * - Day of week (prefer Tuesday-Thursday)
	 * - How far in the future (prefer sooner)
	 *
	 * @param array $slot     Time slot array.
	 * @param array $user_ids User IDs (for future preference checking).
	 * @return int Score (0-100).
	 */
	private static function score_time_slot( $slot, $user_ids ) {
		$score = 50; // Base score

		$start_timestamp = strtotime( $slot['start'] );
		$hour = (int) gmdate( 'H', $start_timestamp );
		$day_of_week = (int) gmdate( 'N', $start_timestamp ); // 1=Monday, 7=Sunday

		// Time of day preference
		if ( $hour >= 10 && $hour <= 11 ) {
			// Mid-morning (10-11 AM) is ideal
			$score += 20;
		} elseif ( $hour >= 14 && $hour <= 15 ) {
			// Early afternoon (2-3 PM) is good
			$score += 15;
		} elseif ( $hour >= 9 && $hour <= 16 ) {
			// Rest of business hours is okay
			$score += 10;
		}

		// Day of week preference
		if ( $day_of_week >= 2 && $day_of_week <= 4 ) {
			// Tuesday-Thursday is ideal
			$score += 15;
		} elseif ( $day_of_week === 1 || $day_of_week === 5 ) {
			// Monday and Friday are okay
			$score += 5;
		}

		// Proximity preference (prefer sooner dates)
		$days_from_now = floor( ( $start_timestamp - time() ) / 86400 );
		if ( $days_from_now <= 3 ) {
			$score += 10;
		} elseif ( $days_from_now <= 7 ) {
			$score += 5;
		}

		/**
		 * Filter the time slot score.
		 *
		 * @param int   $score    Calculated score.
		 * @param array $slot     Time slot.
		 * @param array $user_ids User IDs.
		 */
		return apply_filters( 'ns_calendar_slot_score', $score, $slot, $user_ids );
	}

	/**
	 * Check user availability at a specific time.
	 *
	 * @param int    $user_id        User ID.
	 * @param string $start_datetime Start datetime.
	 * @param string $end_datetime   End datetime.
	 * @return bool True if available, false if busy.
	 */
	public static function is_user_available( $user_id, $start_datetime, $end_datetime ) {
		$conflicts = self::check_conflicts( array( $user_id ), $start_datetime, $end_datetime );
		return empty( $conflicts );
	}

	/**
	 * Get user's schedule for a date range.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Schedule with busy and free periods.
	 */
	public static function get_user_schedule( $user_id, $start_date, $end_date ) {
		$events = NonprofitSuite_Calendar_Visibility::get_user_events( $user_id, array(
			'start_date' => $start_date . ' 00:00:00',
			'end_date'   => $end_date . ' 23:59:59',
			'limit'      => 1000,
		) );

		$schedule = array(
			'busy_periods' => array(),
			'events'       => array(),
		);

		foreach ( $events as $event ) {
			$schedule['busy_periods'][] = array(
				'start' => $event['start_datetime'],
				'end'   => $event['end_datetime'],
			);

			$schedule['events'][] = array(
				'id'    => $event['id'],
				'title' => $event['title'],
				'start' => $event['start_datetime'],
				'end'   => $event['end_datetime'],
				'type'  => $event['entity_type'],
			);
		}

		return $schedule;
	}

	/**
	 * Format conflict information for display.
	 *
	 * @param array $conflicts Conflicts array from check_conflicts().
	 * @return string HTML formatted conflict information.
	 */
	public static function format_conflicts_html( $conflicts ) {
		if ( empty( $conflicts ) ) {
			return '<p class="ns-no-conflicts">' . esc_html__( 'No scheduling conflicts detected.', 'nonprofitsuite' ) . '</p>';
		}

		$html = '<div class="ns-conflicts-list">';
		$html .= '<p class="ns-conflicts-warning">';
		$html .= '<span class="dashicons dashicons-warning"></span> ';
		$html .= sprintf(
			_n(
				'%d person has a scheduling conflict:',
				'%d people have scheduling conflicts:',
				count( $conflicts ),
				'nonprofitsuite'
			),
			count( $conflicts )
		);
		$html .= '</p>';

		foreach ( $conflicts as $conflict ) {
			$html .= '<div class="ns-conflict-item">';
			$html .= '<strong>' . esc_html( $conflict['user_name'] ) . '</strong>';
			$html .= '<ul>';

			foreach ( $conflict['events'] as $event ) {
				$html .= '<li>';
				$html .= esc_html( $event['title'] ) . ' ';
				$html .= '<span class="ns-conflict-time">';
				$html .= '(' . esc_html( wp_date( get_option( 'time_format' ), strtotime( $event['start_datetime'] ) ) );
				$html .= ' - ' . esc_html( wp_date( get_option( 'time_format' ), strtotime( $event['end_datetime'] ) ) ) . ')';
				$html .= '</span>';
				$html .= '</li>';
			}

			$html .= '</ul>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}
}
