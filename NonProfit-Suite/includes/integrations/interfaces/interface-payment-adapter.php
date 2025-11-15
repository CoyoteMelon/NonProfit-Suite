<?php
/**
 * Payment Adapter Interface
 *
 * Defines the contract for payment providers (Stripe, PayPal, Square, etc.)
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
 * Payment Adapter Interface
 *
 * All payment adapters must implement this interface.
 */
interface NonprofitSuite_Payment_Adapter_Interface {

	/**
	 * Create a payment intent/charge
	 *
	 * @param array $payment_data Payment data
	 *                            - amount: Amount in cents/smallest currency unit (required)
	 *                            - currency: Currency code (required, default USD)
	 *                            - description: Payment description (optional)
	 *                            - donor_email: Donor email (optional)
	 *                            - donor_name: Donor name (optional)
	 *                            - payment_method: Payment method ID (optional)
	 *                            - metadata: Additional metadata (optional)
	 *                            - recurring: Recurring payment data (optional)
	 * @return array|WP_Error Payment result with keys: payment_id, status, client_secret
	 */
	public function create_payment( $payment_data );

	/**
	 * Capture/confirm a payment
	 *
	 * @param string $payment_id Payment identifier
	 * @return array|WP_Error Payment data or WP_Error on failure
	 */
	public function capture_payment( $payment_id );

	/**
	 * Refund a payment
	 *
	 * @param string $payment_id Payment identifier
	 * @param array  $args       Refund arguments
	 *                           - amount: Refund amount (optional, defaults to full amount)
	 *                           - reason: Refund reason (optional)
	 * @return array|WP_Error Refund data with keys: refund_id, status
	 */
	public function refund_payment( $payment_id, $args = array() );

	/**
	 * Get payment status
	 *
	 * @param string $payment_id Payment identifier
	 * @return array|WP_Error Payment data or WP_Error on failure
	 *                        - status: succeeded, pending, failed, refunded
	 *                        - amount: Payment amount
	 *                        - currency: Currency code
	 *                        - created: Creation timestamp
	 */
	public function get_payment( $payment_id );

	/**
	 * List payments
	 *
	 * @param array $args Query arguments
	 *                    - limit: Maximum number (optional)
	 *                    - starting_after: Pagination cursor (optional)
	 *                    - created_after: Filter by creation date (optional)
	 *                    - created_before: Filter by creation date (optional)
	 * @return array|WP_Error Array of payments or WP_Error on failure
	 */
	public function list_payments( $args = array() );

	/**
	 * Create a recurring subscription
	 *
	 * @param array $subscription_data Subscription data
	 *                                 - amount: Amount per interval (required)
	 *                                 - currency: Currency code (required)
	 *                                 - interval: day, week, month, year (required)
	 *                                 - interval_count: Number of intervals (optional, default 1)
	 *                                 - donor_email: Donor email (required)
	 *                                 - donor_name: Donor name (optional)
	 *                                 - payment_method: Payment method ID (required)
	 *                                 - start_date: Start date (optional)
	 * @return array|WP_Error Subscription data with keys: subscription_id, status
	 */
	public function create_subscription( $subscription_data );

	/**
	 * Cancel a subscription
	 *
	 * @param string $subscription_id Subscription identifier
	 * @param array  $args            Cancel arguments
	 *                                - immediately: Cancel immediately vs at period end (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function cancel_subscription( $subscription_id, $args = array() );

	/**
	 * Update a subscription
	 *
	 * @param string $subscription_id Subscription identifier
	 * @param array  $update_data     Update data (amount, payment_method, etc.)
	 * @return array|WP_Error Updated subscription data or WP_Error on failure
	 */
	public function update_subscription( $subscription_id, $update_data );

	/**
	 * Get subscription status
	 *
	 * @param string $subscription_id Subscription identifier
	 * @return array|WP_Error Subscription data or WP_Error on failure
	 */
	public function get_subscription( $subscription_id );

	/**
	 * Create a customer record
	 *
	 * @param array $customer_data Customer data
	 *                             - email: Email address (required)
	 *                             - name: Full name (optional)
	 *                             - phone: Phone number (optional)
	 *                             - address: Address array (optional)
	 *                             - metadata: Additional metadata (optional)
	 * @return array|WP_Error Customer data with keys: customer_id
	 */
	public function create_customer( $customer_data );

	/**
	 * Get customer data
	 *
	 * @param string $customer_id Customer identifier
	 * @return array|WP_Error Customer data or WP_Error on failure
	 */
	public function get_customer( $customer_id );

	/**
	 * Handle webhook event
	 *
	 * @param array $payload Webhook payload
	 * @return bool|WP_Error True if handled, WP_Error on failure
	 */
	public function handle_webhook( $payload );

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload   Raw payload
	 * @param string $signature Signature header
	 * @return bool True if valid
	 */
	public function verify_webhook_signature( $payload, $signature );

	/**
	 * Get checkout/payment form URL
	 *
	 * @param array $args Form arguments
	 *                    - amount: Amount (optional for flexible donations)
	 *                    - description: Description (optional)
	 *                    - success_url: Redirect URL on success (optional)
	 *                    - cancel_url: Redirect URL on cancel (optional)
	 * @return string|WP_Error Checkout URL or WP_Error on failure
	 */
	public function get_checkout_url( $args = array() );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Stripe", "PayPal")
	 */
	public function get_provider_name();
}
