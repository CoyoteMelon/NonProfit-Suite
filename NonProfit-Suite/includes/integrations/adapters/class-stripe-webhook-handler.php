<?php
/**
 * Stripe Webhook Handler
 *
 * Handles incoming webhooks from Stripe payment processor.
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
 * NonprofitSuite_Stripe_Webhook_Handler Class
 *
 * Processes Stripe webhook events.
 */
class NonprofitSuite_Stripe_Webhook_Handler implements NonprofitSuite_Webhook_Handler {

	/**
	 * Webhook signing secret.
	 *
	 * @var string
	 */
	private $webhook_secret;

	/**
	 * Constructor.
	 *
	 * @param int $processor_id Processor configuration ID.
	 */
	public function __construct( $processor_id = null ) {
		if ( $processor_id ) {
			$processor              = $this->get_processor_config( $processor_id );
			$credentials            = json_decode( $processor['credentials'], true );
			$this->webhook_secret = isset( $credentials['webhook_secret'] ) ? $credentials['webhook_secret'] : '';
		}
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload   Raw webhook payload.
	 * @param string $signature Webhook signature header.
	 * @return bool True if signature is valid.
	 */
	public function verify_signature( $payload, $signature ) {
		if ( empty( $this->webhook_secret ) ) {
			// If no secret configured, we can't verify (development mode)
			return true;
		}

		// Parse signature header
		$elements = explode( ',', $signature );
		$timestamp = 0;
		$signatures = array();

		foreach ( $elements as $element ) {
			list( $key, $value ) = explode( '=', $element, 2 );
			if ( 't' === $key ) {
				$timestamp = (int) $value;
			} elseif ( 'v1' === $key ) {
				$signatures[] = $value;
			}
		}

		// Check timestamp tolerance (5 minutes)
		$tolerance = 300;
		if ( abs( time() - $timestamp ) > $tolerance ) {
			return false;
		}

		// Compute expected signature
		$signed_payload = $timestamp . '.' . $payload;
		$expected_signature = hash_hmac( 'sha256', $signed_payload, $this->webhook_secret );

		// Compare signatures
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected_signature, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Process webhook event.
	 *
	 * @param array $event Parsed webhook event data.
	 * @return array|WP_Error Processing result.
	 */
	public function process_event( $event ) {
		$event_type = $event['type'];
		$event_data = $event['data']['object'];

		switch ( $event_type ) {
			case 'payment_intent.succeeded':
				return $this->handle_payment_succeeded( $event_data );

			case 'payment_intent.payment_failed':
				return $this->handle_payment_failed( $event_data );

			case 'charge.refunded':
				return $this->handle_charge_refunded( $event_data );

			case 'charge.dispute.created':
				return $this->handle_dispute_created( $event_data );

			case 'charge.dispute.closed':
				return $this->handle_dispute_closed( $event_data );

			case 'customer.subscription.created':
				return $this->handle_subscription_created( $event_data );

			case 'customer.subscription.updated':
				return $this->handle_subscription_updated( $event_data );

			case 'customer.subscription.deleted':
				return $this->handle_subscription_deleted( $event_data );

			case 'invoice.payment_succeeded':
				return $this->handle_invoice_paid( $event_data );

			case 'invoice.payment_failed':
				return $this->handle_invoice_failed( $event_data );

			default:
				return array(
					'status'  => 'ignored',
					'message' => 'Event type not handled: ' . $event_type,
				);
		}
	}

	/**
	 * Parse webhook payload.
	 *
	 * @param string $payload Raw webhook payload.
	 * @return array|WP_Error Parsed event data.
	 */
	public function parse_payload( $payload ) {
		$event = json_decode( $payload, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', 'Invalid JSON payload' );
		}

		if ( ! isset( $event['type'] ) || ! isset( $event['data'] ) ) {
			return new WP_Error( 'invalid_event', 'Invalid event structure' );
		}

		return $event;
	}

	/**
	 * Get supported event types.
	 *
	 * @return array List of supported webhook event types.
	 */
	public function get_supported_events() {
		return array(
			'payment_intent.succeeded',
			'payment_intent.payment_failed',
			'charge.refunded',
			'charge.dispute.created',
			'charge.dispute.closed',
			'customer.subscription.created',
			'customer.subscription.updated',
			'customer.subscription.deleted',
			'invoice.payment_succeeded',
			'invoice.payment_failed',
		);
	}

	/**
	 * Get processor type.
	 *
	 * @return string Processor type.
	 */
	public function get_processor_type() {
		return 'stripe';
	}

	/**
	 * Handle successful payment.
	 *
	 * @param array $payment_intent Payment intent data.
	 * @return array Processing result.
	 */
	private function handle_payment_succeeded( $payment_intent ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		// Check if transaction already exists
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status FROM {$table} WHERE processor_transaction_id = %s",
				$payment_intent['id']
			),
			ARRAY_A
		);

		if ( $existing ) {
			// Update status if it changed
			if ( 'completed' !== $existing['status'] ) {
				$wpdb->update(
					$table,
					array( 'status' => 'completed' ),
					array( 'id' => $existing['id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}

			return array(
				'status'  => 'updated',
				'message' => 'Transaction status updated to completed',
				'transaction_id' => $existing['id'],
			);
		}

		// Get metadata for context
		$metadata = isset( $payment_intent['metadata'] ) ? $payment_intent['metadata'] : array();

		// Create new transaction
		$amount = $payment_intent['amount'] / 100; // Convert from cents
		$fee_amount = 0;

		// Get fee from balance transaction if available
		if ( isset( $payment_intent['charges']['data'][0]['balance_transaction'] ) ) {
			$balance_txn_id = $payment_intent['charges']['data'][0]['balance_transaction'];
			// Fee would be fetched from Stripe API in real implementation
			$fee_amount = $amount * 0.029 + 0.30; // Estimate
		}

		$transaction_data = array(
			'organization_id'          => isset( $metadata['organization_id'] ) ? $metadata['organization_id'] : 0,
			'donor_id'                 => isset( $metadata['donor_id'] ) ? $metadata['donor_id'] : null,
			'processor_id'             => isset( $metadata['processor_id'] ) ? $metadata['processor_id'] : 0,
			'processor_transaction_id' => $payment_intent['id'],
			'amount'                   => $amount,
			'fee_amount'               => $fee_amount,
			'net_amount'               => $amount - $fee_amount,
			'currency'                 => strtoupper( $payment_intent['currency'] ),
			'status'                   => 'completed',
			'payment_type'             => isset( $metadata['payment_type'] ) ? $metadata['payment_type'] : 'donation',
			'description'              => isset( $payment_intent['description'] ) ? $payment_intent['description'] : '',
			'fee_paid_by'              => isset( $metadata['fee_paid_by'] ) ? $metadata['fee_paid_by'] : 'org',
			'created_at'               => gmdate( 'Y-m-d H:i:s', $payment_intent['created'] ),
		);

		$wpdb->insert( $table, $transaction_data );
		$transaction_id = $wpdb->insert_id;

		// If this is a pledge payment, update the pledge
		if ( isset( $metadata['pledge_id'] ) ) {
			NonprofitSuite_Pledge_Manager::record_payment( $metadata['pledge_id'], $amount );
		}

		return array(
			'status'         => 'processed',
			'message'        => 'Payment recorded successfully',
			'transaction_id' => $transaction_id,
		);
	}

	/**
	 * Handle failed payment.
	 *
	 * @param array $payment_intent Payment intent data.
	 * @return array Processing result.
	 */
	private function handle_payment_failed( $payment_intent ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		// Update transaction status to failed
		$wpdb->update(
			$table,
			array(
				'status' => 'failed',
				'failure_reason' => isset( $payment_intent['last_payment_error']['message'] ) ? $payment_intent['last_payment_error']['message'] : 'Unknown error',
			),
			array( 'processor_transaction_id' => $payment_intent['id'] ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		// Notify treasurer
		$metadata = isset( $payment_intent['metadata'] ) ? $payment_intent['metadata'] : array();
		if ( isset( $metadata['donor_id'] ) ) {
			$this->notify_payment_failed( $metadata['donor_id'], $payment_intent );
		}

		return array(
			'status'  => 'processed',
			'message' => 'Payment failure recorded',
		);
	}

	/**
	 * Handle refunded charge.
	 *
	 * @param array $charge Charge data.
	 * @return array Processing result.
	 */
	private function handle_charge_refunded( $charge ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$refund_amount = 0;
		if ( isset( $charge['refunds']['data'] ) && ! empty( $charge['refunds']['data'] ) ) {
			foreach ( $charge['refunds']['data'] as $refund ) {
				$refund_amount += $refund['amount'] / 100;
			}
		}

		// Update transaction
		$wpdb->update(
			$table,
			array(
				'status'         => $charge['refunded'] ? 'refunded' : 'partially_refunded',
				'refund_amount'  => $refund_amount,
			),
			array( 'processor_transaction_id' => $charge['payment_intent'] ),
			array( '%s', '%f' ),
			array( '%s' )
		);

		return array(
			'status'        => 'processed',
			'message'       => 'Refund recorded',
			'refund_amount' => $refund_amount,
		);
	}

	/**
	 * Handle dispute created.
	 *
	 * @param array $dispute Dispute data.
	 * @return array Processing result.
	 */
	private function handle_dispute_created( $dispute ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		// Update transaction status
		$wpdb->update(
			$table,
			array(
				'status'           => 'disputed',
				'dispute_id'       => $dispute['id'],
				'dispute_reason'   => $dispute['reason'],
				'dispute_amount'   => $dispute['amount'] / 100,
			),
			array( 'processor_transaction_id' => $dispute['charge'] ),
			array( '%s', '%s', '%s', '%f' ),
			array( '%s' )
		);

		// Send notification email
		$this->notify_dispute_created( $dispute );

		return array(
			'status'     => 'processed',
			'message'    => 'Dispute recorded and notification sent',
			'dispute_id' => $dispute['id'],
		);
	}

	/**
	 * Handle dispute closed.
	 *
	 * @param array $dispute Dispute data.
	 * @return array Processing result.
	 */
	private function handle_dispute_closed( $dispute ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$status = 'won' === $dispute['status'] ? 'completed' : 'lost';

		$wpdb->update(
			$table,
			array(
				'status'          => $status,
				'dispute_status'  => $dispute['status'],
			),
			array( 'dispute_id' => $dispute['id'] ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		return array(
			'status'  => 'processed',
			'message' => 'Dispute closure recorded',
		);
	}

	/**
	 * Handle subscription created.
	 *
	 * @param array $subscription Subscription data.
	 * @return array Processing result.
	 */
	private function handle_subscription_created( $subscription ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$metadata = isset( $subscription['metadata'] ) ? $subscription['metadata'] : array();

		$subscription_data = array(
			'organization_id'  => isset( $metadata['organization_id'] ) ? $metadata['organization_id'] : 0,
			'donor_id'         => isset( $metadata['donor_id'] ) ? $metadata['donor_id'] : null,
			'processor_id'     => isset( $metadata['processor_id'] ) ? $metadata['processor_id'] : 0,
			'subscription_id'  => $subscription['id'],
			'amount'           => $subscription['items']['data'][0]['price']['unit_amount'] / 100,
			'currency'         => strtoupper( $subscription['currency'] ),
			'frequency'        => $subscription['items']['data'][0]['price']['recurring']['interval'],
			'next_charge_date' => gmdate( 'Y-m-d', $subscription['current_period_end'] ),
			'status'           => $subscription['status'],
			'created_at'       => gmdate( 'Y-m-d H:i:s', $subscription['created'] ),
		);

		$wpdb->insert( $table, $subscription_data );

		return array(
			'status'           => 'processed',
			'message'          => 'Subscription created',
			'subscription_id'  => $subscription['id'],
		);
	}

	/**
	 * Handle subscription updated.
	 *
	 * @param array $subscription Subscription data.
	 * @return array Processing result.
	 */
	private function handle_subscription_updated( $subscription ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$wpdb->update(
			$table,
			array(
				'status'           => $subscription['status'],
				'next_charge_date' => gmdate( 'Y-m-d', $subscription['current_period_end'] ),
			),
			array( 'subscription_id' => $subscription['id'] ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		return array(
			'status'  => 'processed',
			'message' => 'Subscription updated',
		);
	}

	/**
	 * Handle subscription deleted.
	 *
	 * @param array $subscription Subscription data.
	 * @return array Processing result.
	 */
	private function handle_subscription_deleted( $subscription ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$wpdb->update(
			$table,
			array(
				'status'     => 'cancelled',
				'ended_at'   => current_time( 'mysql' ),
			),
			array( 'subscription_id' => $subscription['id'] ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		return array(
			'status'  => 'processed',
			'message' => 'Subscription cancelled',
		);
	}

	/**
	 * Handle successful invoice payment.
	 *
	 * @param array $invoice Invoice data.
	 * @return array Processing result.
	 */
	private function handle_invoice_paid( $invoice ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		// Update recurring donation totals
		if ( isset( $invoice['subscription'] ) ) {
			$recurring = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE subscription_id = %s",
					$invoice['subscription']
				),
				ARRAY_A
			);

			if ( $recurring ) {
				$wpdb->update(
					$table,
					array(
						'total_charged' => $recurring['total_charged'] + ( $invoice['amount_paid'] / 100 ),
						'charge_count'  => $recurring['charge_count'] + 1,
						'last_charge_date' => gmdate( 'Y-m-d', $invoice['status_transitions']['paid_at'] ),
					),
					array( 'id' => $recurring['id'] ),
					array( '%f', '%d', '%s' ),
					array( '%d' )
				);
			}
		}

		return array(
			'status'  => 'processed',
			'message' => 'Recurring payment recorded',
		);
	}

	/**
	 * Handle failed invoice payment.
	 *
	 * @param array $invoice Invoice data.
	 * @return array Processing result.
	 */
	private function handle_invoice_failed( $invoice ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		// Update recurring donation status
		if ( isset( $invoice['subscription'] ) ) {
			$wpdb->update(
				$table,
				array(
					'status'       => 'failed',
					'failure_reason' => isset( $invoice['last_payment_error']['message'] ) ? $invoice['last_payment_error']['message'] : 'Payment failed',
				),
				array( 'subscription_id' => $invoice['subscription'] ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			// Notify donor and treasurer
			$this->notify_recurring_payment_failed( $invoice );
		}

		return array(
			'status'  => 'processed',
			'message' => 'Recurring payment failure recorded',
		);
	}

	/**
	 * Notify when payment fails.
	 *
	 * @param int   $donor_id       Donor ID.
	 * @param array $payment_intent Payment intent data.
	 */
	private function notify_payment_failed( $donor_id, $payment_intent ) {
		// Implementation would use email system to notify
		do_action( 'nonprofitsuite_payment_failed', $donor_id, $payment_intent );
	}

	/**
	 * Notify when dispute is created.
	 *
	 * @param array $dispute Dispute data.
	 */
	private function notify_dispute_created( $dispute ) {
		// Implementation would use email system to notify treasurer
		do_action( 'nonprofitsuite_dispute_created', $dispute );
	}

	/**
	 * Notify when recurring payment fails.
	 *
	 * @param array $invoice Invoice data.
	 */
	private function notify_recurring_payment_failed( $invoice ) {
		// Implementation would use email system to notify donor and treasurer
		do_action( 'nonprofitsuite_recurring_payment_failed', $invoice );
	}

	/**
	 * Get processor configuration.
	 *
	 * @param int $processor_id Processor ID.
	 * @return array|null Processor configuration.
	 */
	private function get_processor_config( $processor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $processor_id ),
			ARRAY_A
		);
	}
}
