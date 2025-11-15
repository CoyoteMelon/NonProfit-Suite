<?php
/**
 * SMS Adapter Interface
 *
 * Defines the contract for SMS providers (Twilio, Brevo SMS, SimpleTexting, etc.)
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
 * SMS Adapter Interface
 *
 * All SMS adapters must implement this interface.
 */
interface NonprofitSuite_SMS_Adapter_Interface {

	/**
	 * Send an SMS message
	 *
	 * @param array $args SMS arguments
	 *                    - to: Recipient phone number (required)
	 *                    - message: Message text (required)
	 *                    - from: From phone number (optional)
	 * @return array|WP_Error Result with keys: message_id, status, segments
	 */
	public function send_sms( $args );

	/**
	 * Send bulk SMS messages
	 *
	 * @param array $messages Array of SMS argument arrays (same format as send_sms())
	 * @return array|WP_Error Result with keys: sent_count, failed_count, errors
	 */
	public function send_bulk_sms( $messages );

	/**
	 * Get SMS delivery status
	 *
	 * @param string $message_id Message identifier from send_sms()
	 * @return array|WP_Error Status array or WP_Error on failure
	 *                        - status: queued, sent, delivered, failed, undelivered
	 *                        - timestamp: Status timestamp
	 *                        - error_code: Error code if failed (optional)
	 */
	public function get_sms_status( $message_id );

	/**
	 * Get received messages (for two-way SMS)
	 *
	 * @param array $args Query arguments
	 *                    - since: Get messages since date (optional)
	 *                    - limit: Maximum number (optional)
	 *                    - phone_number: Filter by phone number (optional)
	 * @return array|WP_Error Array of messages or WP_Error on failure
	 *                        Each message: message_id, from, to, body, timestamp
	 */
	public function get_received_messages( $args = array() );

	/**
	 * Create SMS campaign
	 *
	 * @param array $campaign_data Campaign data
	 *                             - name: Campaign name (required)
	 *                             - message: Message text (required)
	 *                             - recipients: Array of phone numbers (required)
	 *                             - schedule_time: Schedule for future send (optional)
	 * @return array|WP_Error Campaign data with keys: campaign_id
	 */
	public function create_campaign( $campaign_data );

	/**
	 * Send SMS campaign
	 *
	 * @param string $campaign_id Campaign identifier
	 * @return array|WP_Error Result with keys: sent_count, failed_count
	 */
	public function send_campaign( $campaign_id );

	/**
	 * Get campaign statistics
	 *
	 * @param string $campaign_id Campaign identifier
	 * @return array|WP_Error Statistics or WP_Error on failure
	 *                        - sent: Number sent
	 *                        - delivered: Number delivered
	 *                        - failed: Number failed
	 *                        - clicks: Number of link clicks (if tracking enabled)
	 */
	public function get_campaign_stats( $campaign_id );

	/**
	 * Opt-in a phone number to receive messages
	 *
	 * @param string $phone_number Phone number
	 * @param array  $args         Opt-in arguments
	 *                             - list_id: Add to specific list (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function opt_in( $phone_number, $args = array() );

	/**
	 * Opt-out a phone number from receiving messages
	 *
	 * @param string $phone_number Phone number
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function opt_out( $phone_number );

	/**
	 * Check if phone number is opted in
	 *
	 * @param string $phone_number Phone number
	 * @return bool|WP_Error True if opted in, false if opted out, WP_Error on failure
	 */
	public function is_opted_in( $phone_number );

	/**
	 * Get SMS usage statistics
	 *
	 * @param array $args Statistics arguments
	 *                    - start_date: Start date (optional)
	 *                    - end_date: End date (optional)
	 * @return array|WP_Error Statistics array or WP_Error on failure
	 *                        - total_sent: Total messages sent
	 *                        - total_delivered: Total delivered
	 *                        - total_failed: Total failed
	 *                        - segments_used: Total segments used
	 *                        - estimated_cost: Estimated cost
	 */
	public function get_usage( $args = array() );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Twilio", "SimpleTexting")
	 */
	public function get_provider_name();
}
