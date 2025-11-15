<?php
/**
 * Payment Adapter Interface
 *
 * Defines the contract for all payment processor integrations.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since      1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface NonprofitSuite_Payment_Adapter
 *
 * Standard interface for payment processor adapters.
 */
interface NonprofitSuite_Payment_Adapter {

	/**
	 * Process a one-time payment.
	 *
	 * @param array $payment_data Payment data.
	 * @return array|WP_Error {
	 *     Payment result.
	 *
	 *     @type string $transaction_id  Processor transaction ID.
	 *     @type string $status          Status (completed, pending, failed).
	 *     @type float  $amount          Amount charged.
	 *     @type float  $fee_amount      Processor fee.
	 *     @type float  $net_amount      Net amount received.
	 *     @type array  $metadata        Additional metadata.
	 * }
	 */
	public function process_payment( $payment_data );

	/**
	 * Create a recurring subscription.
	 *
	 * @param array $subscription_data Subscription data.
	 * @return array|WP_Error {
	 *     Subscription result.
	 *
	 *     @type string $subscription_id External subscription ID.
	 *     @type string $status          Status (active, pending).
	 *     @type string $next_charge_date Next charge date.
	 *     @type array  $metadata        Additional metadata.
	 * }
	 */
	public function create_subscription( $subscription_data );

	/**
	 * Cancel a subscription.
	 *
	 * @param string $subscription_id External subscription ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function cancel_subscription( $subscription_id );

	/**
	 * Process a refund.
	 *
	 * @param string $transaction_id External transaction ID.
	 * @param float  $amount         Optional. Amount to refund. NULL for full refund.
	 * @param string $reason         Optional. Refund reason.
	 * @return array|WP_Error Refund result.
	 */
	public function process_refund( $transaction_id, $amount = null, $reason = '' );

	/**
	 * Get balance available for payout/sweep.
	 *
	 * @return float|WP_Error Available balance.
	 */
	public function get_available_balance();

	/**
	 * Initiate payout/transfer to bank account.
	 *
	 * @param float  $amount         Amount to transfer.
	 * @param string $bank_account_id Bank account identifier.
	 * @return array|WP_Error Transfer result.
	 */
	public function initiate_payout( $amount, $bank_account_id );

	/**
	 * Get transaction details.
	 *
	 * @param string $transaction_id External transaction ID.
	 * @return array|WP_Error Transaction details.
	 */
	public function get_transaction( $transaction_id );

	/**
	 * Validate adapter configuration.
	 *
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_config();

	/**
	 * Get adapter capabilities.
	 *
	 * @return array {
	 *     Capabilities.
	 *
	 *     @type bool   $one_time_payments   Supports one-time payments.
	 *     @type bool   $recurring            Supports subscriptions.
	 *     @type bool   $refunds              Supports refunds.
	 *     @type bool   $partial_refunds      Supports partial refunds.
	 *     @type bool   $payouts              Supports automated payouts.
	 *     @type array  $payment_methods      Supported methods (card, ach, etc.).
	 *     @type array  $currencies           Supported currency codes.
	 *     @type float  $min_amount           Minimum transaction amount.
	 *     @type float  $max_amount           Maximum transaction amount.
	 * }
	 */
	public function get_capabilities();

	/**
	 * Get adapter name.
	 *
	 * @return string Adapter name.
	 */
	public function get_name();

	/**
	 * Get processor key.
	 *
	 * @return string Processor key.
	 */
	public function get_processor();
}
