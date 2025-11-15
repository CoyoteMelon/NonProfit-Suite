<?php
/**
 * PayPal Payment Adapter
 *
 * Handles PayPal payment processing integration.
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
 * NonprofitSuite_PayPal_Payment_Adapter Class
 *
 * PayPal integration implementation.
 */
class NonprofitSuite_PayPal_Payment_Adapter implements NonprofitSuite_Payment_Adapter {

	/**
	 * PayPal API credentials.
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * PayPal API base URL.
	 *
	 * @var string
	 */
	private $api_base;

	/**
	 * Processor configuration ID.
	 *
	 * @var int
	 */
	private $processor_id;

	/**
	 * Constructor.
	 *
	 * @param int $processor_id Processor configuration ID.
	 */
	public function __construct( $processor_id ) {
		$this->processor_id = $processor_id;

		$processor = $this->get_processor_config( $processor_id );
		if ( $processor ) {
			$this->credentials = json_decode( $processor['credentials'], true );
			$this->api_base    = isset( $this->credentials['sandbox'] ) && $this->credentials['sandbox']
				? 'https://api-m.sandbox.paypal.com'
				: 'https://api-m.paypal.com';
		}
	}

	/**
	 * Process a payment.
	 *
	 * @param array $payment_data Payment data.
	 * @return array|WP_Error Payment result with transaction_id, status, and amounts.
	 */
	public function process_payment( $payment_data ) {
		// Create PayPal order
		$order_data = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'amount' => array(
						'currency_code' => isset( $payment_data['currency'] ) ? strtoupper( $payment_data['currency'] ) : 'USD',
						'value'         => number_format( $payment_data['amount'], 2, '.', '' ),
					),
					'description' => isset( $payment_data['description'] ) ? $payment_data['description'] : 'Donation',
				),
			),
		);

		$response = $this->api_request( 'POST', '/v2/checkout/orders', $order_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// If we have an order_id in payment_data, it means this is a capture request
		if ( isset( $payment_data['order_id'] ) ) {
			$capture_response = $this->api_request( 'POST', "/v2/checkout/orders/{$payment_data['order_id']}/capture", array() );

			if ( is_wp_error( $capture_response ) ) {
				return $capture_response;
			}

			$capture = $capture_response['purchase_units'][0]['payments']['captures'][0];

			// Calculate PayPal fees (2.89% + $0.49 for donations in US)
			$fee_amount = $this->calculate_paypal_fee( $payment_data['amount'] );

			return array(
				'transaction_id' => $capture['id'],
				'status'         => 'completed',
				'amount'         => $payment_data['amount'],
				'fee_amount'     => $fee_amount,
				'net_amount'     => $payment_data['amount'] - $fee_amount,
			);
		}

		// Return order for approval (client-side capture)
		return array(
			'order_id' => $response['id'],
			'status'   => 'pending_approval',
			'links'    => $response['links'],
		);
	}

	/**
	 * Create a subscription.
	 *
	 * @param array $subscription_data Subscription data.
	 * @return array|WP_Error Subscription result with subscription_id.
	 */
	public function create_subscription( $subscription_data ) {
		// First, create a product if needed
		$product_id = $this->get_or_create_product( $subscription_data );
		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		// Create a plan for the subscription
		$plan_id = $this->get_or_create_plan( $product_id, $subscription_data );
		if ( is_wp_error( $plan_id ) ) {
			return $plan_id;
		}

		// Create subscription
		$subscription_request = array(
			'plan_id'    => $plan_id,
			'start_time' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+1 day' ) ),
			'subscriber' => array(
				'email_address' => isset( $subscription_data['email'] ) ? $subscription_data['email'] : '',
			),
		);

		if ( isset( $subscription_data['custom_id'] ) ) {
			$subscription_request['custom_id'] = $subscription_data['custom_id'];
		}

		$response = $this->api_request( 'POST', '/v1/billing/subscriptions', $subscription_request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'subscription_id' => $response['id'],
			'status'          => $response['status'],
			'links'           => $response['links'],
		);
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param string $subscription_id External subscription ID.
	 * @param string $reason          Cancellation reason.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function cancel_subscription( $subscription_id, $reason = '' ) {
		$cancel_data = array(
			'reason' => $reason ? $reason : 'Customer requested cancellation',
		);

		$response = $this->api_request( 'POST', "/v1/billing/subscriptions/{$subscription_id}/cancel", $cancel_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Process a refund.
	 *
	 * @param string $transaction_id External transaction ID.
	 * @param float  $amount         Optional refund amount (null for full refund).
	 * @param string $reason         Refund reason.
	 * @return array|WP_Error Refund result.
	 */
	public function process_refund( $transaction_id, $amount = null, $reason = '' ) {
		$refund_data = array();

		if ( null !== $amount ) {
			$refund_data['amount'] = array(
				'value'         => number_format( $amount, 2, '.', '' ),
				'currency_code' => 'USD',
			);
		}

		if ( $reason ) {
			$refund_data['note_to_payer'] = $reason;
		}

		$response = $this->api_request( 'POST', "/v2/payments/captures/{$transaction_id}/refund", $refund_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'refund_id' => $response['id'],
			'status'    => $response['status'],
			'amount'    => isset( $response['amount']['value'] ) ? (float) $response['amount']['value'] : $amount,
		);
	}

	/**
	 * Get available balance.
	 *
	 * @return float|WP_Error Available balance.
	 */
	public function get_available_balance() {
		$response = $this->api_request( 'GET', '/v1/reporting/balances' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$total = 0;
		if ( isset( $response['balances'] ) ) {
			foreach ( $response['balances'] as $balance ) {
				if ( isset( $balance['available_balance']['value'] ) ) {
					$total += (float) $balance['available_balance']['value'];
				}
			}
		}

		return $total;
	}

	/**
	 * Initiate a payout.
	 *
	 * @param float  $amount          Payout amount.
	 * @param string $bank_account_id Bank account identifier.
	 * @return array|WP_Error Payout result.
	 */
	public function initiate_payout( $amount, $bank_account_id ) {
		// PayPal payouts require the bank account to be pre-configured in PayPal account
		// This creates a payout to the organization's linked bank account

		$payout_data = array(
			'sender_batch_header' => array(
				'sender_batch_id' => 'batch-' . time(),
				'email_subject'   => 'You have a payout!',
				'email_message'   => 'You have received a payout from your PayPal account.',
			),
			'items' => array(
				array(
					'recipient_type' => 'EMAIL',
					'amount'         => array(
						'value'    => number_format( $amount, 2, '.', '' ),
						'currency' => 'USD',
					),
					'note'           => 'Automated sweep payout',
					'sender_item_id' => 'sweep-' . time(),
					'receiver'       => $this->credentials['payout_email'],
				),
			),
		);

		$response = $this->api_request( 'POST', '/v1/payments/payouts', $payout_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'payout_id'    => $response['batch_header']['payout_batch_id'],
			'status'       => $response['batch_header']['batch_status'],
			'arrival_date' => gmdate( 'Y-m-d', strtotime( '+3 days' ) ), // PayPal typically takes 3-5 days
		);
	}

	/**
	 * Get transaction details.
	 *
	 * @param string $transaction_id External transaction ID.
	 * @return array|WP_Error Transaction details.
	 */
	public function get_transaction( $transaction_id ) {
		$response = $this->api_request( 'GET', "/v2/payments/captures/{$transaction_id}" );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'transaction_id' => $response['id'],
			'amount'         => (float) $response['amount']['value'],
			'currency'       => $response['amount']['currency_code'],
			'status'         => $response['status'],
			'created_at'     => $response['create_time'],
		);
	}

	/**
	 * Validate adapter configuration.
	 *
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public function validate_config() {
		if ( empty( $this->credentials['client_id'] ) || empty( $this->credentials['client_secret'] ) ) {
			return new WP_Error( 'missing_credentials', 'PayPal client ID and secret are required' );
		}

		// Test API connection
		$response = $this->api_request( 'GET', '/v1/oauth2/token' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get adapter capabilities.
	 *
	 * @return array Supported features.
	 */
	public function get_capabilities() {
		return array(
			'one_time_payments' => true,
			'subscriptions'     => true,
			'refunds'           => true,
			'payouts'           => true,
			'balance_inquiry'   => true,
			'payment_methods'   => array( 'paypal', 'credit_card', 'debit_card' ),
		);
	}

	/**
	 * Get adapter name.
	 *
	 * @return string Adapter name.
	 */
	public function get_name() {
		return 'PayPal';
	}

	/**
	 * Get processor type.
	 *
	 * @return string Processor type.
	 */
	public function get_processor() {
		return 'paypal';
	}

	/**
	 * Make API request to PayPal.
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @return array|WP_Error Response data or error.
	 */
	private function api_request( $method, $endpoint, $data = array() ) {
		// Get access token
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && 'GET' !== $method ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$parsed      = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			$error_message = isset( $parsed['message'] ) ? $parsed['message'] : 'PayPal API error';
			return new WP_Error( 'paypal_error', $error_message, array( 'status' => $status_code ) );
		}

		return $parsed;
	}

	/**
	 * Get PayPal access token.
	 *
	 * @return string|WP_Error Access token or error.
	 */
	private function get_access_token() {
		// Check for cached token
		$cache_key = 'nonprofitsuite_paypal_token_' . $this->processor_id;
		$cached = get_transient( $cache_key );

		if ( $cached ) {
			return $cached;
		}

		// Request new token
		$url = $this->api_base . '/v1/oauth2/token';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $this->credentials['client_id'] . ':' . $this->credentials['client_secret'] ),
			),
			'body'    => array(
				'grant_type' => 'client_credentials',
			),
			'timeout' => 30,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body   = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $body, true );

		if ( ! isset( $parsed['access_token'] ) ) {
			return new WP_Error( 'auth_failed', 'Failed to get PayPal access token' );
		}

		// Cache token (PayPal tokens are valid for ~9 hours)
		set_transient( $cache_key, $parsed['access_token'], 8 * HOUR_IN_SECONDS );

		return $parsed['access_token'];
	}

	/**
	 * Get or create PayPal product for subscriptions.
	 *
	 * @param array $subscription_data Subscription data.
	 * @return string|WP_Error Product ID or error.
	 */
	private function get_or_create_product( $subscription_data ) {
		$product_name = isset( $subscription_data['product_name'] ) ? $subscription_data['product_name'] : 'Recurring Donation';

		// Try to find existing product
		$cache_key = 'nonprofitsuite_paypal_product_' . md5( $product_name );
		$cached = get_transient( $cache_key );

		if ( $cached ) {
			return $cached;
		}

		// Create new product
		$product_data = array(
			'name'        => $product_name,
			'description' => isset( $subscription_data['description'] ) ? $subscription_data['description'] : 'Recurring donation subscription',
			'type'        => 'SERVICE',
			'category'    => 'NONPROFIT',
		);

		$response = $this->api_request( 'POST', '/v1/catalogs/products', $product_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$product_id = $response['id'];

		// Cache product ID
		set_transient( $cache_key, $product_id, DAY_IN_SECONDS );

		return $product_id;
	}

	/**
	 * Get or create PayPal plan for subscriptions.
	 *
	 * @param string $product_id       Product ID.
	 * @param array  $subscription_data Subscription data.
	 * @return string|WP_Error Plan ID or error.
	 */
	private function get_or_create_plan( $product_id, $subscription_data ) {
		$frequency = isset( $subscription_data['frequency'] ) ? $subscription_data['frequency'] : 'monthly';
		$amount    = $subscription_data['amount'];

		// Map frequency to PayPal interval units
		$interval_map = array(
			'weekly'    => array( 'interval_unit' => 'WEEK', 'interval_count' => 1 ),
			'monthly'   => array( 'interval_unit' => 'MONTH', 'interval_count' => 1 ),
			'quarterly' => array( 'interval_unit' => 'MONTH', 'interval_count' => 3 ),
			'annual'    => array( 'interval_unit' => 'YEAR', 'interval_count' => 1 ),
		);

		$interval = isset( $interval_map[ $frequency ] ) ? $interval_map[ $frequency ] : $interval_map['monthly'];

		// Create plan
		$plan_data = array(
			'product_id'          => $product_id,
			'name'                => ucfirst( $frequency ) . ' Donation - $' . $amount,
			'description'         => ucfirst( $frequency ) . ' recurring donation',
			'status'              => 'ACTIVE',
			'billing_cycles'      => array(
				array(
					'frequency'       => array(
						'interval_unit'  => $interval['interval_unit'],
						'interval_count' => $interval['interval_count'],
					),
					'tenure_type'     => 'REGULAR',
					'sequence'        => 1,
					'total_cycles'    => 0, // Infinite
					'pricing_scheme'  => array(
						'fixed_price' => array(
							'value'         => number_format( $amount, 2, '.', '' ),
							'currency_code' => 'USD',
						),
					),
				),
			),
			'payment_preferences' => array(
				'auto_bill_outstanding'     => true,
				'setup_fee_failure_action'  => 'CONTINUE',
				'payment_failure_threshold' => 3,
			),
		);

		$response = $this->api_request( 'POST', '/v1/billing/plans', $plan_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['id'];
	}

	/**
	 * Calculate PayPal fee.
	 *
	 * @param float $amount Payment amount.
	 * @return float Fee amount.
	 */
	private function calculate_paypal_fee( $amount ) {
		// PayPal standard rate: 2.89% + $0.49 for nonprofits (US)
		return round( ( $amount * 0.0289 ) + 0.49, 2 );
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
