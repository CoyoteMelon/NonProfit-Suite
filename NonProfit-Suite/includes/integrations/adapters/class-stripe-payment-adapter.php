<?php
/**
 * Stripe Payment Adapter
 *
 * Payment adapter for Stripe with full support for payments, subscriptions, and payouts.
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
 * NonprofitSuite_Stripe_Payment_Adapter Class
 *
 * Stripe payment adapter implementation.
 */
class NonprofitSuite_Stripe_Payment_Adapter implements NonprofitSuite_Payment_Adapter {

	/**
	 * Stripe configuration.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Stripe API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.stripe.com/v1';

	/**
	 * Constructor.
	 *
	 * @param array $config Stripe configuration.
	 */
	public function __construct( $config = array() ) {
		$this->config = wp_parse_args( $config, array(
			'secret_key'      => get_option( 'ns_stripe_secret_key', '' ),
			'publishable_key' => get_option( 'ns_stripe_publishable_key', '' ),
			'webhook_secret'  => get_option( 'ns_stripe_webhook_secret', '' ),
			'account_id'      => get_option( 'ns_stripe_account_id', '' ), // For Connect
		) );
	}

	/**
	 * Process a one-time payment.
	 *
	 * @param array $payment_data Payment data.
	 * @return array|WP_Error Payment result.
	 */
	public function process_payment( $payment_data ) {
		// Create Payment Intent
		$intent_data = array(
			'amount'               => round( $payment_data['amount'] * 100 ), // Cents
			'currency'             => isset( $payment_data['currency'] ) ? $payment_data['currency'] : 'usd',
			'payment_method'       => $payment_data['payment_method_id'],
			'confirm'              => true,
			'description'          => isset( $payment_data['description'] ) ? $payment_data['description'] : '',
			'receipt_email'        => isset( $payment_data['email'] ) ? $payment_data['email'] : null,
			'metadata'             => isset( $payment_data['metadata'] ) ? $payment_data['metadata'] : array(),
		);

		// Add application fee if using Connect
		if ( ! empty( $this->config['account_id'] ) ) {
			$intent_data['application_fee_amount'] = isset( $payment_data['application_fee'] ) ? round( $payment_data['application_fee'] * 100 ) : 0;
		}

		$response = $this->api_request( 'POST', '/payment_intents', $intent_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Calculate fees
		$fee_amount = $this->calculate_stripe_fee( $payment_data['amount'] );

		return array(
			'transaction_id' => $response['id'],
			'status'         => $response['status'] === 'succeeded' ? 'completed' : 'pending',
			'amount'         => $payment_data['amount'],
			'fee_amount'     => $fee_amount,
			'net_amount'     => $payment_data['amount'] - $fee_amount,
			'metadata'       => $response,
		);
	}

	/**
	 * Create a recurring subscription.
	 *
	 * @param array $subscription_data Subscription data.
	 * @return array|WP_Error Subscription result.
	 */
	public function create_subscription( $subscription_data ) {
		// Create customer first if needed
		$customer_id = isset( $subscription_data['customer_id'] ) ? $subscription_data['customer_id'] : null;

		if ( ! $customer_id ) {
			$customer = $this->create_customer( array(
				'email'          => $subscription_data['email'],
				'name'           => isset( $subscription_data['name'] ) ? $subscription_data['name'] : '',
				'payment_method' => $subscription_data['payment_method_id'],
			) );

			if ( is_wp_error( $customer ) ) {
				return $customer;
			}

			$customer_id = $customer['id'];
		}

		// Attach payment method to customer
		$this->api_request( 'POST', "/payment_methods/{$subscription_data['payment_method_id']}/attach", array(
			'customer' => $customer_id,
		) );

		// Set as default payment method
		$this->api_request( 'POST', "/customers/{$customer_id}", array(
			'invoice_settings' => array(
				'default_payment_method' => $subscription_data['payment_method_id'],
			),
		) );

		// Create subscription
		$sub_data = array(
			'customer' => $customer_id,
			'items'    => array(
				array(
					'price_data' => array(
						'currency'   => isset( $subscription_data['currency'] ) ? $subscription_data['currency'] : 'usd',
						'product'    => $this->get_or_create_product( $subscription_data ),
						'unit_amount' => round( $subscription_data['amount'] * 100 ),
						'recurring'  => array(
							'interval' => $this->map_frequency( $subscription_data['frequency'] ),
						),
					),
				),
			),
			'metadata' => isset( $subscription_data['metadata'] ) ? $subscription_data['metadata'] : array(),
		);

		$response = $this->api_request( 'POST', '/subscriptions', $sub_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'subscription_id'   => $response['id'],
			'customer_id'       => $customer_id,
			'status'            => $response['status'],
			'next_charge_date'  => isset( $response['current_period_end'] ) ? gmdate( 'Y-m-d', $response['current_period_end'] ) : null,
			'metadata'          => $response,
		);
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param string $subscription_id Subscription ID.
	 * @return bool|WP_Error Result.
	 */
	public function cancel_subscription( $subscription_id ) {
		$response = $this->api_request( 'DELETE', "/subscriptions/{$subscription_id}" );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['status'] === 'canceled';
	}

	/**
	 * Process a refund.
	 *
	 * @param string $transaction_id Transaction ID (payment intent or charge).
	 * @param float  $amount         Optional. Amount to refund.
	 * @param string $reason         Optional. Refund reason.
	 * @return array|WP_Error Refund result.
	 */
	public function process_refund( $transaction_id, $amount = null, $reason = '' ) {
		$refund_data = array(
			'payment_intent' => $transaction_id,
		);

		if ( $amount !== null ) {
			$refund_data['amount'] = round( $amount * 100 );
		}

		if ( $reason ) {
			$refund_data['reason'] = $reason;
		}

		$response = $this->api_request( 'POST', '/refunds', $refund_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'refund_id' => $response['id'],
			'status'    => $response['status'],
			'amount'    => $response['amount'] / 100,
			'metadata'  => $response,
		);
	}

	/**
	 * Get available balance for payout.
	 *
	 * @return float|WP_Error Available balance.
	 */
	public function get_available_balance() {
		$response = $this->api_request( 'GET', '/balance' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Sum all available balances across currencies
		$total = 0;
		foreach ( $response['available'] as $balance ) {
			$total += $balance['amount'] / 100;
		}

		return $total;
	}

	/**
	 * Initiate payout to bank account.
	 *
	 * @param float  $amount         Amount to transfer.
	 * @param string $bank_account_id Bank account identifier.
	 * @return array|WP_Error Payout result.
	 */
	public function initiate_payout( $amount, $bank_account_id ) {
		$payout_data = array(
			'amount'      => round( $amount * 100 ),
			'currency'    => 'usd',
			'destination' => $bank_account_id, // Stripe bank account ID
			'method'      => 'standard', // or 'instant'
		);

		$response = $this->api_request( 'POST', '/payouts', $payout_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'payout_id'       => $response['id'],
			'status'          => $response['status'],
			'amount'          => $amount,
			'arrival_date'    => isset( $response['arrival_date'] ) ? gmdate( 'Y-m-d', $response['arrival_date'] ) : null,
			'metadata'        => $response,
		);
	}

	/**
	 * Get transaction details.
	 *
	 * @param string $transaction_id Transaction ID.
	 * @return array|WP_Error Transaction details.
	 */
	public function get_transaction( $transaction_id ) {
		$response = $this->api_request( 'GET', "/payment_intents/{$transaction_id}" );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'transaction_id' => $response['id'],
			'status'         => $response['status'],
			'amount'         => $response['amount'] / 100,
			'fee_amount'     => isset( $response['charges']['data'][0]['balance_transaction'] ) ? $this->get_fee_from_balance_transaction( $response['charges']['data'][0]['balance_transaction'] ) : 0,
			'created'        => gmdate( 'Y-m-d H:i:s', $response['created'] ),
			'metadata'       => $response,
		);
	}

	/**
	 * Validate Stripe configuration.
	 *
	 * @return bool|WP_Error Validation result.
	 */
	public function validate_config() {
		if ( empty( $this->config['secret_key'] ) ) {
			return new WP_Error( 'missing_key', __( 'Stripe secret key is required', 'nonprofitsuite' ) );
		}

		// Test API key
		$response = $this->api_request( 'GET', '/account' );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'invalid_key', __( 'Invalid Stripe API key', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get adapter capabilities.
	 *
	 * @return array Capabilities.
	 */
	public function get_capabilities() {
		return array(
			'one_time_payments' => true,
			'recurring'         => true,
			'refunds'           => true,
			'partial_refunds'   => true,
			'payouts'           => true,
			'payment_methods'   => array( 'card', 'ach', 'sepa' ),
			'currencies'        => array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD' ),
			'min_amount'        => 0.50,
			'max_amount'        => 999999.99,
		);
	}

	/**
	 * Get adapter name.
	 *
	 * @return string Name.
	 */
	public function get_name() {
		return 'Stripe';
	}

	/**
	 * Get processor key.
	 *
	 * @return string Processor key.
	 */
	public function get_processor() {
		return 'stripe';
	}

	/**
	 * Make Stripe API request.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @return array|WP_Error Response data or error.
	 */
	private function api_request( $method, $endpoint, $data = array() ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->config['secret_key'],
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'timeout' => 30,
		);

		if ( 'POST' === $method || 'DELETE' === $method ) {
			$args['body'] = http_build_query( $this->flatten_array( $data ) );
		} elseif ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			return new WP_Error(
				'stripe_error',
				isset( $body['error']['message'] ) ? $body['error']['message'] : 'Stripe API error',
				$body
			);
		}

		return $body;
	}

	/**
	 * Create Stripe customer.
	 *
	 * @param array $customer_data Customer data.
	 * @return array|WP_Error Customer data or error.
	 */
	private function create_customer( $customer_data ) {
		$data = array(
			'email' => $customer_data['email'],
		);

		if ( isset( $customer_data['name'] ) ) {
			$data['name'] = $customer_data['name'];
		}

		if ( isset( $customer_data['payment_method'] ) ) {
			$data['payment_method'] = $customer_data['payment_method'];
		}

		return $this->api_request( 'POST', '/customers', $data );
	}

	/**
	 * Get or create Stripe product for subscription.
	 *
	 * @param array $subscription_data Subscription data.
	 * @return string Product ID.
	 */
	private function get_or_create_product( $subscription_data ) {
		// Use cached product ID if available
		$product_id = get_option( 'ns_stripe_product_id', '' );

		if ( $product_id ) {
			return $product_id;
		}

		// Create product
		$product = $this->api_request( 'POST', '/products', array(
			'name'        => get_bloginfo( 'name' ) . ' Donations',
			'description' => 'Recurring donations and memberships',
		) );

		if ( ! is_wp_error( $product ) ) {
			update_option( 'ns_stripe_product_id', $product['id'] );
			return $product['id'];
		}

		return '';
	}

	/**
	 * Map frequency to Stripe interval.
	 *
	 * @param string $frequency Frequency (monthly, annual, etc.).
	 * @return string Stripe interval.
	 */
	private function map_frequency( $frequency ) {
		$map = array(
			'weekly'    => 'week',
			'monthly'   => 'month',
			'quarterly' => 'month', // Will need interval_count = 3
			'annual'    => 'year',
		);

		return isset( $map[ $frequency ] ) ? $map[ $frequency ] : 'month';
	}

	/**
	 * Calculate Stripe fee.
	 *
	 * Stripe standard: 2.9% + $0.30
	 *
	 * @param float $amount Transaction amount.
	 * @return float Fee amount.
	 */
	private function calculate_stripe_fee( $amount ) {
		return round( ( $amount * 0.029 ) + 0.30, 2 );
	}

	/**
	 * Get fee from balance transaction.
	 *
	 * @param string $balance_transaction_id Balance transaction ID.
	 * @return float Fee amount.
	 */
	private function get_fee_from_balance_transaction( $balance_transaction_id ) {
		$response = $this->api_request( 'GET', "/balance_transactions/{$balance_transaction_id}" );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		return isset( $response['fee'] ) ? $response['fee'] / 100 : 0;
	}

	/**
	 * Flatten nested array for Stripe API.
	 *
	 * Stripe expects nested parameters as param[nested][key].
	 *
	 * @param array  $array  Array to flatten.
	 * @param string $prefix Prefix for keys.
	 * @return array Flattened array.
	 */
	private function flatten_array( $array, $prefix = '' ) {
		$result = array();

		foreach ( $array as $key => $value ) {
			$new_key = $prefix ? "{$prefix}[{$key}]" : $key;

			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->flatten_array( $value, $new_key ) );
			} else {
				$result[ $new_key ] = $value;
			}
		}

		return $result;
	}
}
