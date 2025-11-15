<?php
/**
 * Video Conferencing Adapter Interface
 *
 * Defines the contract for all video conferencing integrations.
 * Supports Zoom, Google Meet, Microsoft Teams, etc.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NonprofitSuite_Video_Conferencing_Adapter {

	/**
	 * Get the provider name.
	 *
	 * @return string Provider name (zoom, google_meet, teams).
	 */
	public function get_provider_name();

	/**
	 * Get the display name.
	 *
	 * @return string Display name.
	 */
	public function get_display_name();

	/**
	 * Check if this adapter uses OAuth.
	 *
	 * @return bool True if OAuth is required.
	 */
	public function uses_oauth();

	/**
	 * Test the API connection.
	 *
	 * @param array $credentials API credentials.
	 * @return bool|WP_Error True on success or error.
	 */
	public function test_connection( $credentials );

	/**
	 * Create a meeting.
	 *
	 * @param array $meeting_data Meeting data (topic, start_time, duration, etc).
	 * @return array|WP_Error Result with meeting details or error.
	 */
	public function create_meeting( $meeting_data );

	/**
	 * Update a meeting.
	 *
	 * @param string $meeting_id Provider meeting ID.
	 * @param array  $meeting_data Updated meeting data.
	 * @return bool|WP_Error True on success or error.
	 */
	public function update_meeting( $meeting_id, $meeting_data );

	/**
	 * Delete a meeting.
	 *
	 * @param string $meeting_id Provider meeting ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public function delete_meeting( $meeting_id );

	/**
	 * Get meeting details.
	 *
	 * @param string $meeting_id Provider meeting ID.
	 * @return array|WP_Error Meeting details or error.
	 */
	public function get_meeting( $meeting_id );

	/**
	 * List meetings.
	 *
	 * @param array $args Optional arguments (user_id, start_date, end_date, etc).
	 * @return array|WP_Error Array of meetings or error.
	 */
	public function list_meetings( $args = array() );

	/**
	 * Get meeting participants.
	 *
	 * @param string $meeting_id Provider meeting ID.
	 * @return array|WP_Error Array of participants or error.
	 */
	public function get_participants( $meeting_id );

	/**
	 * Get meeting recordings.
	 *
	 * @param string $meeting_id Provider meeting ID.
	 * @return array|WP_Error Array of recordings or error.
	 */
	public function get_recordings( $meeting_id );
}
