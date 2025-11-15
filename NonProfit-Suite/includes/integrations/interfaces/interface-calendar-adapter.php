<?php
/**
 * Calendar Adapter Interface
 *
 * Defines the contract for calendar providers (Google Calendar, Outlook, iCloud, etc.)
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
 * Calendar Adapter Interface
 *
 * All calendar adapters must implement this interface.
 */
interface NonprofitSuite_Calendar_Adapter_Interface {

	/**
	 * Create a calendar event
	 *
	 * @param array $event_data Event data
	 *                          - title: Event title (required)
	 *                          - description: Event description (optional)
	 *                          - start_datetime: Start datetime (required)
	 *                          - end_datetime: End datetime (required)
	 *                          - location: Location (optional)
	 *                          - attendees: Array of email addresses (optional)
	 *                          - meeting_url: Video meeting URL (optional)
	 *                          - reminders: Array of reminder settings (optional)
	 *                          - all_day: Whether all-day event (optional)
	 * @return array|WP_Error Event data with keys: event_id, url, html_link
	 */
	public function create_event( $event_data );

	/**
	 * Update a calendar event
	 *
	 * @param string $event_id   Event identifier
	 * @param array  $event_data Updated event data
	 * @return array|WP_Error Updated event data or WP_Error on failure
	 */
	public function update_event( $event_id, $event_data );

	/**
	 * Delete a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_event( $event_id );

	/**
	 * Get a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return array|WP_Error Event data or WP_Error on failure
	 */
	public function get_event( $event_id );

	/**
	 * List calendar events
	 *
	 * @param array $args List arguments
	 *                    - start_date: Minimum start date (optional)
	 *                    - end_date: Maximum end date (optional)
	 *                    - limit: Maximum number of events (optional)
	 *                    - calendar_id: Specific calendar ID (optional)
	 * @return array|WP_Error Array of events or WP_Error on failure
	 */
	public function list_events( $args = array() );

	/**
	 * Add attendees to an event
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function add_attendees( $event_id, $attendees );

	/**
	 * Remove attendees from an event
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function remove_attendees( $event_id, $attendees );

	/**
	 * Get iCal/ICS feed URL
	 *
	 * @param array $args Feed arguments
	 *                    - calendar_id: Specific calendar ID (optional)
	 * @return string|WP_Error Feed URL or WP_Error on failure
	 */
	public function get_ical_feed( $args = array() );

	/**
	 * Sync events from external calendar to NonprofitSuite
	 *
	 * @param array $args Sync arguments
	 *                    - start_date: Start date for sync (optional)
	 *                    - end_date: End date for sync (optional)
	 *                    - calendar_id: Specific calendar ID (optional)
	 * @return array|WP_Error Sync result with keys: synced_count, skipped_count, errors
	 */
	public function sync_events( $args = array() );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Google Calendar", "Outlook Calendar")
	 */
	public function get_provider_name();
}
