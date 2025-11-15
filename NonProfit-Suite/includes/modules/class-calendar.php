<?php
/**
 * Calendar Integration Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Calendar {

	/**
	 * Create calendar item
	 *
	 * @param array $data Calendar item data
	 * @return int|WP_Error Item ID or error
	 */
	public static function create_item( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage calendar' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_calendar_items',
			array(
				'title' => sanitize_text_field( $data['title'] ),
				'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
				'item_type' => sanitize_text_field( $data['item_type'] ),
				'source_module' => sanitize_text_field( $data['source_module'] ),
				'source_id' => isset( $data['source_id'] ) ? absint( $data['source_id'] ) : null,
				'start_date' => sanitize_text_field( $data['start_date'] ),
				'end_date' => isset( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
				'all_day' => isset( $data['all_day'] ) ? absint( $data['all_day'] ) : 0,
				'recurrence' => isset( $data['recurrence'] ) ? sanitize_text_field( $data['recurrence'] ) : null,
				'location' => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : null,
				'attendees' => isset( $data['attendees'] ) ? wp_json_encode( $data['attendees'] ) : null,
				'color' => isset( $data['color'] ) ? sanitize_text_field( $data['color'] ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create calendar item', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'calendar' );
		return $wpdb->insert_id;
	}

	/**
	 * Get calendar items for date range
	 *
	 * @param string $start_date Start date (Y-m-d)
	 * @param string $end_date End date (Y-m-d)
	 * @param array  $filters Additional filters
	 * @return array|WP_Error Array of calendar items or error
	 */
	public static function get_items( $start_date, $end_date, $filters = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$where = array( '1=1' );
		$values = array();

		// Date range
		$where[] = 'start_date <= %s';
		$where[] = '(end_date IS NULL OR end_date >= %s)';
		$values[] = sanitize_text_field( $end_date ) . ' 23:59:59';
		$values[] = sanitize_text_field( $start_date ) . ' 00:00:00';

		// Item type filter
		if ( ! empty( $filters['item_type'] ) ) {
			$where[] = 'item_type = %s';
			$values[] = sanitize_text_field( $filters['item_type'] );
		}

		// Source module filter
		if ( ! empty( $filters['source_module'] ) ) {
			$where[] = 'source_module = %s';
			$values[] = sanitize_text_field( $filters['source_module'] );
		}

		$sql = "SELECT id, title, description, item_type, source_module, source_id,
		               start_date, end_date, all_day, recurrence, location, attendees, color, created_at
		        FROM {$wpdb->prefix}ns_calendar_items
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY start_date ASC";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$items = $wpdb->get_results( $sql );

		// Decode attendees JSON
		foreach ( $items as $item ) {
			if ( ! empty( $item->attendees ) ) {
				$item->attendees = json_decode( $item->attendees, true );
			}
		}

		return $items;
	}

	/**
	 * Sync from other modules
	 *
	 * @return int Number of items synced
	 */
	public static function sync_all() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return 0;
		}

		$synced = 0;

		// Sync meetings
		$synced += self::sync_meetings();

		// Sync compliance due dates
		$synced += self::sync_compliance();

		// Sync events
		$synced += self::sync_events();

		return $synced;
	}

	/**
	 * Sync meetings to calendar
	 *
	 * @return int Number synced
	 */
	private static function sync_meetings() {
		global $wpdb;

		$meetings = $wpdb->get_results(
			"SELECT id, title AS meeting_name, meeting_date, meeting_time, location
			 FROM {$wpdb->prefix}ns_meetings
			 WHERE meeting_date >= CURDATE()"
		);

		$count = 0;
		foreach ( $meetings as $meeting ) {
			// Check if already synced
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ns_calendar_items WHERE source_module = 'meetings' AND source_id = %d",
				$meeting->id
			) );

			if ( ! $exists ) {
				self::create_item( array(
					'title' => $meeting->meeting_name,
					'item_type' => 'meeting',
					'source_module' => 'meetings',
					'source_id' => $meeting->id,
					'start_date' => $meeting->meeting_date . ' ' . ( $meeting->meeting_time ?? '00:00:00' ),
					'location' => $meeting->location ?? null,
					'color' => '#2563eb',
				) );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Sync compliance items to calendar
	 *
	 * @return int Number synced
	 */
	private static function sync_compliance() {
		global $wpdb;

		$items = $wpdb->get_results(
			"SELECT id, title, description, due_date, status, compliance_type
			 FROM {$wpdb->prefix}ns_compliance_items
			 WHERE due_date >= CURDATE() AND status != 'completed'"
		);

		$count = 0;
		foreach ( $items as $item ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ns_calendar_items WHERE source_module = 'compliance' AND source_id = %d",
				$item->id
			) );

			if ( ! $exists ) {
				self::create_item( array(
					'title' => $item->title,
					'item_type' => 'compliance',
					'source_module' => 'compliance',
					'source_id' => $item->id,
					'start_date' => $item->due_date . ' 23:59:59',
					'all_day' => 1,
					'color' => '#ef4444',
				) );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Sync events to calendar
	 *
	 * @return int Number synced
	 */
	private static function sync_events() {
		// Implement if Events module exists
		return 0;
	}

	/**
	 * Export to iCal format
	 *
	 * @param array $items Array of calendar items
	 * @return string iCal content
	 */
	public static function export_ical( $items ) {
		$ical = "BEGIN:VCALENDAR\r\n";
		$ical .= "VERSION:2.0\r\n";
		$ical .= "PRODID:-//NonprofitSuite//Calendar//EN\r\n";
		$ical .= "CALSCALE:GREGORIAN\r\n";

		foreach ( $items as $item ) {
			$ical .= "BEGIN:VEVENT\r\n";
			$ical .= "UID:" . $item->id . "@nonprofitsuite\r\n";
			$ical .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
			$ical .= "DTSTART:" . gmdate( 'Ymd\THis\Z', strtotime( $item->start_date ) ) . "\r\n";

			if ( $item->end_date ) {
				$ical .= "DTEND:" . gmdate( 'Ymd\THis\Z', strtotime( $item->end_date ) ) . "\r\n";
			}

			$ical .= "SUMMARY:" . addcslashes( $item->title, "\r\n,;") . "\r\n";

			if ( $item->description ) {
				$ical .= "DESCRIPTION:" . addcslashes( strip_tags( $item->description ), "\r\n,;") . "\r\n";
			}

			if ( $item->location ) {
				$ical .= "LOCATION:" . addcslashes( $item->location, "\r\n,;") . "\r\n";
			}

			$ical .= "END:VEVENT\r\n";
		}

		$ical .= "END:VCALENDAR\r\n";

		return $ical;
	}
}
