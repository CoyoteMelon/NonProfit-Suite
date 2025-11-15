<?php
/**
 * SMS Adapter Interface
 *
 * Defines the contract for SMS provider integrations.
 * All SMS adapters (Twilio, Plivo, Vonage, etc.) must implement this interface.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NS_SMS_Adapter {
	/**
	 * Send a single SMS message.
	 *
	 * @param string $to Recipient phone number (E.164 format recommended).
	 * @param string $message Message body.
	 * @param array  $options Optional parameters (from, callback_url, etc.).
	 * @return array|WP_Error Response with message_id and status, or error.
	 */
	public function send_message( $to, $message, $options = array() );

	/**
	 * Send bulk SMS messages.
	 *
	 * @param array $recipients Array of phone numbers.
	 * @param string $message Message body.
	 * @param array  $options Optional parameters.
	 * @return array|WP_Error Array of results per recipient, or error.
	 */
	public function send_bulk( $recipients, $message, $options = array() );

	/**
	 * Get message status.
	 *
	 * @param string $message_id Provider's message ID.
	 * @return array|WP_Error Message status information, or error.
	 */
	public function get_message_status( $message_id );

	/**
	 * Get account balance or credits.
	 *
	 * @return array|WP_Error Balance information, or error.
	 */
	public function get_balance();

	/**
	 * Get available phone numbers for purchase.
	 *
	 * @param string $country_code Country code (e.g., 'US', 'CA').
	 * @param array  $filters Optional filters (area_code, contains, etc.).
	 * @return array|WP_Error Available numbers, or error.
	 */
	public function search_available_numbers( $country_code, $filters = array() );

	/**
	 * Purchase a phone number.
	 *
	 * @param string $phone_number Phone number to purchase.
	 * @return array|WP_Error Purchase result, or error.
	 */
	public function purchase_number( $phone_number );

	/**
	 * Validate webhook signature.
	 *
	 * @param array  $payload Webhook payload data.
	 * @param string $signature Signature from webhook headers.
	 * @param string $url Webhook URL.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_webhook_signature( $payload, $signature, $url );

	/**
	 * Process webhook payload.
	 *
	 * @param array $payload Webhook data.
	 * @return array Processed webhook data with status, message_id, etc.
	 */
	public function process_webhook( $payload );

	/**
	 * Calculate message cost.
	 *
	 * @param string $to Destination phone number.
	 * @param string $message Message body.
	 * @return float Estimated cost in USD.
	 */
	public function calculate_cost( $to, $message );

	/**
	 * Count message segments.
	 *
	 * SMS messages are split into 160-character segments (or 70 for Unicode).
	 *
	 * @param string $message Message body.
	 * @return int Number of segments.
	 */
	public function count_segments( $message );

	/**
	 * Test connection to provider API.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection();

	/**
	 * Get provider capabilities.
	 *
	 * @return array Array of supported features (mms, unicode, delivery_reports, etc.).
	 */
	public function get_capabilities();
}
