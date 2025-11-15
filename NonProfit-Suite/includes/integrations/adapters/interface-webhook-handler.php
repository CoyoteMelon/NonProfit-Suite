<?php
/**
 * Payment Webhook Handler Interface
 *
 * Defines the contract for payment processor webhook handlers.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Webhook_Handler Interface
 *
 * Interface for payment processor webhook handlers.
 */
interface NonprofitSuite_Webhook_Handler {

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload   Raw webhook payload.
	 * @param string $signature Webhook signature header.
	 * @return bool True if signature is valid.
	 */
	public function verify_signature( $payload, $signature );

	/**
	 * Process webhook event.
	 *
	 * @param array $event Parsed webhook event data.
	 * @return array|WP_Error Processing result.
	 */
	public function process_event( $event );

	/**
	 * Parse webhook payload.
	 *
	 * @param string $payload Raw webhook payload.
	 * @return array|WP_Error Parsed event data.
	 */
	public function parse_payload( $payload );

	/**
	 * Get supported event types.
	 *
	 * @return array List of supported webhook event types.
	 */
	public function get_supported_events();

	/**
	 * Get processor type.
	 *
	 * @return string Processor type (stripe, paypal, etc.).
	 */
	public function get_processor_type();
}
