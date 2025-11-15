<?php
/**
 * Built-in Calendar Adapter
 *
 * Adapter for NonprofitSuite's built-in calendar system.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Calendar_Builtin_Adapter Class
 *
 * Implements calendar integration using the built-in Calendar module.
 */
class NonprofitSuite_Calendar_Builtin_Adapter implements NonprofitSuite_Calendar_Adapter_Interface {

	/**
	 * Create a calendar event
	 *
	 * @param array $event_data Event data
	 * @return array|WP_Error Event data
	 */
	public function create_event( $event_data ) {
		global $wpdb;

		// Map to calendar event format
		$calendar_event = array(
			'title'          => $event_data['title'],
			'description'    => isset( $event_data['description'] ) ? $event_data['description'] : '',
			'start_datetime' => $event_data['start_datetime'],
			'end_datetime'   => $event_data['end_datetime'],
			'location'       => isset( $event_data['location'] ) ? $event_data['location'] : '',
			'all_day'        => isset( $event_data['all_day'] ) ? (int) $event_data['all_day'] : 0,
			'created_by'     => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
		);

		// Insert into calendar events table
		$table = $wpdb->prefix . 'ns_calendar_events';
		$wpdb->insert( $table, $calendar_event );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		$event_id = $wpdb->insert_id;

		/**
		 * Fires after calendar event is created
		 *
		 * @param int   $event_id   Event ID
		 * @param array $event_data Event data
		 */
		do_action( 'ns_calendar_event_created', $event_id, $event_data, 'builtin' );

		return array(
			'event_id'  => $event_id,
			'url'       => admin_url( 'admin.php?page=nonprofitsuite-calendar&event_id=' . $event_id ),
			'html_link' => admin_url( 'admin.php?page=nonprofitsuite-calendar&event_id=' . $event_id ),
		);
	}

	/**
	 * Update a calendar event
	 *
	 * @param string $event_id   Event identifier
	 * @param array  $event_data Updated event data
	 * @return array|WP_Error Updated event data
	 */
	public function update_event( $event_id, $event_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_calendar_events';

		// Build update data
		$update_data = array();
		$allowed_fields = array( 'title', 'description', 'start_datetime', 'end_datetime', 'location', 'all_day' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $event_data[ $field ] ) ) {
				$update_data[ $field ] = $event_data[ $field ];
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No data to update', 'nonprofitsuite' ) );
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		// Update event
		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $event_id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update event', 'nonprofitsuite' ) );
		}

		/**
		 * Fires after calendar event is updated
		 *
		 * @param int   $event_id   Event ID
		 * @param array $event_data Event data
		 */
		do_action( 'ns_calendar_event_updated', $event_id, $event_data, 'builtin' );

		return $this->get_event( $event_id );
	}

	/**
	 * Delete a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete_event( $event_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_calendar_events';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $event_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete event', 'nonprofitsuite' ) );
		}

		do_action( 'ns_calendar_event_deleted', $event_id );

		return true;
	}

	/**
	 * Get a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return array|WP_Error Event data
	 */
	public function get_event( $event_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_calendar_events';
		$event = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$event_id
		), ARRAY_A );

		if ( ! $event ) {
			return new WP_Error( 'event_not_found', __( 'Event not found', 'nonprofitsuite' ) );
		}

		return $event;
	}

	/**
	 * List calendar events
	 *
	 * @param array $args List arguments
	 * @return array|WP_Error Array of events
	 */
	public function list_events( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'start_date' => null,
			'end_date'   => null,
			'limit'      => 100,
		) );

		$table = $wpdb->prefix . 'ns_calendar_events';
		$where = array( '1=1' );
		$prepare_args = array();

		if ( $args['start_date'] ) {
			$where[] = 'start_datetime >= %s';
			$prepare_args[] = $args['start_date'];
		}

		if ( $args['end_date'] ) {
			$where[] = 'end_datetime <= %s';
			$prepare_args[] = $args['end_date'];
		}

		$where_clause = implode( ' AND ', $where );
		$limit = (int) $args['limit'];

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY start_datetime ASC LIMIT {$limit}";

		if ( ! empty( $prepare_args ) ) {
			$query = $wpdb->prepare( $query, $prepare_args );
		}

		$events = $wpdb->get_results( $query, ARRAY_A );

		return $events ? $events : array();
	}

	/**
	 * Add attendees to an event (not supported in basic calendar)
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success
	 */
	public function add_attendees( $event_id, $attendees ) {
		// Basic calendar doesn't support attendee tracking
		return true;
	}

	/**
	 * Remove attendees from an event (not supported in basic calendar)
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success
	 */
	public function remove_attendees( $event_id, $attendees ) {
		// Basic calendar doesn't support attendee tracking
		return true;
	}

	/**
	 * Get iCal/ICS feed URL
	 *
	 * @param array $args Feed arguments
	 * @return string|WP_Error Feed URL
	 */
	public function get_ical_feed( $args = array() ) {
		// Generate iCal feed URL
		$user_id = get_current_user_id();
		$token = md5( 'ns_ical_' . $user_id . AUTH_KEY );

		return add_query_arg(
			array(
				'ns_ical' => 1,
				'user'    => $user_id,
				'token'   => $token,
			),
			home_url()
		);
	}

	/**
	 * Sync events (not applicable for built-in calendar)
	 *
	 * @param array $args Sync arguments
	 * @return array|WP_Error Sync result
	 */
	public function sync_events( $args = array() ) {
		// Built-in calendar is the source of truth, no sync needed
		return array(
			'synced_count'  => 0,
			'skipped_count' => 0,
			'errors'        => array(),
		);
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_calendar_events';

		// Check if table exists
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

		if ( ! $exists ) {
			return new WP_Error( 'table_missing', __( 'Calendar table does not exist', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return __( 'Built-in Calendar', 'nonprofitsuite' );
	}
}
