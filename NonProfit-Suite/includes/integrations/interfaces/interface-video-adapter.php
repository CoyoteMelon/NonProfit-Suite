<?php
/**
 * Video Conferencing Adapter Interface
 *
 * Defines the contract for video providers (Zoom, Google Meet, Teams, Jitsi, etc.)
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
 * Video Adapter Interface
 *
 * All video conferencing adapters must implement this interface.
 */
interface NonprofitSuite_Video_Adapter_Interface {

	/**
	 * Create a video meeting
	 *
	 * @param array $meeting_data Meeting data
	 *                            - title: Meeting title (required)
	 *                            - start_time: Start datetime (required)
	 *                            - duration: Duration in minutes (required)
	 *                            - password: Meeting password (optional)
	 *                            - waiting_room: Enable waiting room (optional)
	 *                            - host_email: Host email (optional)
	 *                            - settings: Additional provider settings (optional)
	 * @return array|WP_Error Meeting data with keys: meeting_id, join_url, host_url, password
	 */
	public function create_meeting( $meeting_data );

	/**
	 * Update a video meeting
	 *
	 * @param string $meeting_id   Meeting identifier
	 * @param array  $meeting_data Updated meeting data
	 * @return array|WP_Error Updated meeting data or WP_Error on failure
	 */
	public function update_meeting( $meeting_id, $meeting_data );

	/**
	 * Delete a video meeting
	 *
	 * @param string $meeting_id Meeting identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_meeting( $meeting_id );

	/**
	 * Get meeting details
	 *
	 * @param string $meeting_id Meeting identifier
	 * @return array|WP_Error Meeting data or WP_Error on failure
	 */
	public function get_meeting( $meeting_id );

	/**
	 * List meetings
	 *
	 * @param array $args Query arguments
	 *                    - type: scheduled, live, upcoming (optional)
	 *                    - from_date: Filter by date (optional)
	 *                    - to_date: Filter by date (optional)
	 *                    - limit: Maximum number (optional)
	 * @return array|WP_Error Array of meetings or WP_Error on failure
	 */
	public function list_meetings( $args = array() );

	/**
	 * Get meeting participants/attendees
	 *
	 * @param string $meeting_id Meeting identifier
	 * @return array|WP_Error Array of participants or WP_Error on failure
	 */
	public function get_participants( $meeting_id );

	/**
	 * Get meeting recordings
	 *
	 * @param string $meeting_id Meeting identifier
	 * @return array|WP_Error Array of recording data or WP_Error on failure
	 *                        Each recording: recording_id, download_url, duration, file_size
	 */
	public function get_recordings( $meeting_id );

	/**
	 * Download meeting recording
	 *
	 * @param string $recording_id Recording identifier
	 * @param string $destination  Local destination path (optional)
	 * @return string|WP_Error Local file path or WP_Error on failure
	 */
	public function download_recording( $recording_id, $destination = null );

	/**
	 * Delete meeting recording
	 *
	 * @param string $recording_id Recording identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_recording( $recording_id );

	/**
	 * Get meeting analytics/report
	 *
	 * @param string $meeting_id Meeting identifier
	 * @return array|WP_Error Analytics data or WP_Error on failure
	 *                        - duration: Actual duration
	 *                        - participant_count: Number of participants
	 *                        - start_time: Actual start time
	 *                        - end_time: Actual end time
	 */
	public function get_meeting_report( $meeting_id );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Zoom", "Google Meet")
	 */
	public function get_provider_name();
}
