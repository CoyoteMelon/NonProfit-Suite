<?php
/**
 * ACH/eCheck Payment Adapter
 *
 * Adapter for ACH/eCheck payment processing via Stripe ACH.
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
 * NonprofitSuite_Payment_ACH_Adapter Class
 *
 * Implements ACH/eCheck payment integration using Stripe ACH.
 */
class NonprofitSuite_Payment_ACH_Adapter implements NonprofitSuite_Payment_Adapter_Interface {

	/**
	 * Stripe API base URL
	 */
	const API_BASE_URL = 'https://api.stripe.com/v1/';

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'payment', 'ach' );
		$this->api_key = $this->settings['secret_key'] ?? '';
	}

	/**
	 * Create a payment
	 *
	 * @param array $payment_data Payment data
	 * @return array|WP_Error
	 */
	public function create_payment( $payment_data ) {
		$payment_data = wp_parse_args( $payment_data, array(
			'amount'         => 0,
			'currency'       => 'USD',
			'description'    => '',
			'donor_email'    => '',
			'donor_name'     => '',
			'payment_method' => '', // Stripe payment method ID (pm_xxx)
			'metadata'       => array(),
		) );

		// Create payment intent with ACH
		$payload = array(
			'amount'               => $payment_data['amount'],
			'currency'             => strtolower( $payment_data['currency'] ),
			'description'          => $payment_data['description'],
			'payment_method'       => $payment_data['payment_method'],
			'payment_method_types' => array( 'us_bank_account' ),
			'confirm'              => true,
			'metadata'             => $payment_data['metadata'],
		);

		if ( ! empty( $payment_data['donor_email'] ) ) {
			$payload['receipt_email'] = $payment_data['donor_email'];
		}

		$response = $this->make_request( 'payment_intents', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'payment_id' => $response['id'],
			'status'     => $this->map_status( $response['status'] ),
			'amount'     => $response['amount'],
			'currency'   => strtoupper( $response['currency'] ),
		);
	}

	/**
	 * Capture a payment
	 *
	 * ACH payments are auto-confirmed
	 *
	 * @param string $payment_id Payment ID
	 * @return array|WP_Error
	 */
	public function capture_payment( $payment_id ) {
		return $this->get_payment( $payment_id );
	}

	/**
	 * Refund a payment
	 *
	 * @param string $payment_id Payment ID
	 * @param array  $args       Refund arguments
	 * @return array|WP_Error
	 */
	public function refund_payment( $payment_id, $args = array() ) {
		$payment = $this->get_payment( $payment_id );

		if ( is_wp_error( $payment ) ) {
			return $payment;
		}

		$payload = array(
			'payment_intent' => $payment_id,
		);

		if ( isset( $args['amount'] ) ) {
			$payload['amount'] = $args['amount'];
		}

		if ( isset( $args['reason'] ) ) {
			$payload['reason'] = $args['reason'];
		}

		$response = $this->make_request( 'refunds', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'refund_id' => $response['id'],
			'status'    => $this->map_status( $response['status'] ),
			'amount'    => $response['amount'],
		);
	}

	/**
	 * Get payment
	 *
	 * @param string $payment_id Payment ID
	 * @return array|WP_Error
	 */
	public function get_payment( $payment_id ) {
		$response = $this->make_request( 'payment_intents/' . $payment_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'payment_id' => $response['id'],
			'status'     => $this->map_status( $response['status'] ),
			'amount'     => $response['amount'],
			'currency'   => strtoupper( $response['currency'] ),
			'created'    => $response['created'],
		);
	}

	/**
	 * List payments
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public function list_payments( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'limit'          => 50,
			'created_after'  => '',
			'created_before' => '',
		) );

		$params = array(
			'limit' => $args['limit'],
		);

		if ( ! empty( $args['created_after'] ) ) {
			$params['created']['gte'] = strtotime( $args['created_after'] );
		}

		if ( ! empty( $args['created_before'] ) ) {
			$params['created']['lte'] = strtotime( $args['created_before'] );
		}

		$response = $this->make_request( 'payment_intents', $params, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data'] ?? array();
	}

	/**
	 * Create subscription
	 *
	 * @param array $subscription_data Subscription data
	 * @return array|WP_Error
	 */
	public function create_subscription( $subscription_data ) {
		// First ensure customer exists
		$customer_id = $subscription_data['customer_id'] ?? null;

		if ( ! $customer_id ) {
			$customer = $this->create_customer( array(
				'email' => $subscription_data['donor_email'],
				'name'  => $subscription_data['donor_name'] ?? '',
			) );

			if ( is_wp_error( $customer ) ) {
				return $customer;
			}

			$customer_id = $customer['customer_id'];
		}

		// Attach payment method to customer
		if ( ! empty( $subscription_data['payment_method'] ) ) {
			$this->make_request(
				'payment_methods/' . $subscription_data['payment_method'] . '/attach',
				array( 'customer' => $customer_id ),
				'POST'
			);
		}

		// Create subscription
		$payload = array(
			'customer'              => $customer_id,
			'items'                 => array(
				array(
					'price_data' => array(
						'currency'   => strtolower( $subscription_data['currency'] ?? 'USD' ),
						'product'    => $subscription_data['product_id'] ?? '',
						'unit_amount' => $subscription_data['amount'],
						'recurring'  => array(
							'interval' => $subscription_data['interval'] ?? 'month',
						),
					),
				),
			),
			'default_payment_method' => $subscription_data['payment_method'],
		);

		$response = $this->make_request( 'subscriptions', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'subscription_id' => $response['id'],
			'status'          => $response['status'],
		);
	}

	/**
	 * Cancel subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @param array  $args            Cancel arguments
	 * @return bool|WP_Error
	 */
	public function cancel_subscription( $subscription_id, $args = array() ) {
		$response = $this->make_request( 'subscriptions/' . $subscription_id, array(), 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Update subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @param array  $update_data     Update data
	 * @return array|WP_Error
	 */
	public function update_subscription( $subscription_id, $update_data ) {
		$payload = array();

		if ( isset( $update_data['payment_method'] ) ) {
			$payload['default_payment_method'] = $update_data['payment_method'];
		}

		if ( isset( $update_data['metadata'] ) ) {
			$payload['metadata'] = $update_data['metadata'];
		}

		$response = $this->make_request( 'subscriptions/' . $subscription_id, $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'subscription_id' => $response['id'],
			'status'          => $response['status'],
		);
	}

	/**
	 * Get subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @return array|WP_Error
	 */
	public function get_subscription( $subscription_id ) {
		$response = $this->make_request( 'subscriptions/' . $subscription_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'subscription_id' => $response['id'],
			'status'          => $response['status'],
			'current_period_end' => $response['current_period_end'],
		);
	}

	/**
	 * Create customer
	 *
	 * @param array $customer_data Customer data
	 * @return array|WP_Error
	 */
	public function create_customer( $customer_data ) {
		$payload = array(
			'email' => $customer_data['email'],
		);

		if ( ! empty( $customer_data['name'] ) ) {
			$payload['name'] = $customer_data['name'];
		}

		if ( ! empty( $customer_data['phone'] ) ) {
			$payload['phone'] = $customer_data['phone'];
		}

		$response = $this->make_request( 'customers', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'customer_id' => $response['id'],
		);
	}

	/**
	 * Get customer
	 *
	 * @param string $customer_id Customer ID
	 * @return array|WP_Error
	 */
	public function get_customer( $customer_id ) {
		$response = $this->make_request( 'customers/' . $customer_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'customer_id' => $response['id'],
			'email'       => $response['email'],
			'name'        => $response['name'],
		);
	}

	/**
	 * Create bank account setup session
	 *
	 * Returns a session for collecting bank account details
	 *
	 * @param array $args Session arguments
	 * @return array|WP_Error
	 */
	public function create_bank_account_session( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'customer_id' => '',
			'return_url'  => '',
			'success_url' => '',
			'cancel_url'  => '',
		) );

		$payload = array(
			'mode'        => 'setup',
			'customer'    => $args['customer_id'],
			'payment_method_types' => array( 'us_bank_account' ),
			'success_url' => $args['success_url'],
			'cancel_url'  => $args['cancel_url'],
		);

		$response = $this->make_request( 'checkout/sessions', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'session_id' => $response['id'],
			'url'        => $response['url'],
		);
	}

	/**
	 * Handle webhook
	 *
	 * @param array $payload Webhook payload
	 * @return bool|WP_Error
	 */
	public function handle_webhook( $payload ) {
		$event_type = $payload['type'] ?? '';

		switch ( $event_type ) {
			case 'payment_intent.succeeded':
				do_action( 'ns_ach_payment_succeeded', $payload['data']['object'] );
				break;

			case 'payment_intent.payment_failed':
				do_action( 'ns_ach_payment_failed', $payload['data']['object'] );
				break;

			case 'charge.refunded':
				do_action( 'ns_ach_payment_refunded', $payload['data']['object'] );
				break;

			case 'customer.subscription.created':
			case 'customer.subscription.updated':
				do_action( 'ns_ach_subscription_updated', $payload['data']['object'] );
				break;

			case 'customer.subscription.deleted':
				do_action( 'ns_ach_subscription_canceled', $payload['data']['object'] );
				break;

			default:
				// Unknown event type
				break;
		}

		return true;
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload   Raw payload
	 * @param string $signature Signature header
	 * @return bool
	 */
	public function verify_webhook_signature( $payload, $signature ) {
		$webhook_secret = $this->settings['webhook_secret'] ?? '';

		if ( empty( $webhook_secret ) ) {
			return false;
		}

		// Extract timestamp and signatures from header
		$sig_header = array();
		foreach ( explode( ',', $signature ) as $element ) {
			$item = explode( '=', $element, 2 );
			if ( count( $item ) === 2 ) {
				$sig_header[ $item[0] ] = $item[1];
			}
		}

		$timestamp = $sig_header['t'] ?? '';
		$signature_v1 = $sig_header['v1'] ?? '';

		// Compute expected signature
		$signed_payload = $timestamp . '.' . $payload;
		$expected_signature = hash_hmac( 'sha256', $signed_payload, $webhook_secret );

		return hash_equals( $expected_signature, $signature_v1 );
	}

	/**
	 * Get checkout URL
	 *
	 * @param array $args Checkout arguments
	 * @return string|WP_Error
	 */
	public function get_checkout_url( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'amount'      => 0,
			'currency'    => 'USD',
			'description' => '',
			'success_url' => '',
			'cancel_url'  => '',
		) );

		$payload = array(
			'mode'        => 'payment',
			'payment_method_types' => array( 'us_bank_account' ),
			'line_items'  => array(
				array(
					'price_data' => array(
						'currency'    => strtolower( $args['currency'] ),
						'product_data' => array(
							'name' => $args['description'],
						),
						'unit_amount' => $args['amount'],
					),
					'quantity'   => 1,
				),
			),
			'success_url' => $args['success_url'],
			'cancel_url'  => $args['cancel_url'],
		);

		$response = $this->make_request( 'checkout/sessions', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['url'] ?? '';
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'Stripe API key not configured', 'nonprofitsuite' ) );
		}

		// Test with balance API call
		$response = $this->make_request( 'balance', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['available'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'ACH/eCheck';
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint Endpoint
	 * @param array  $data     Request data
	 * @param string $method   HTTP method
	 * @return array|WP_Error
	 */
	private function make_request( $endpoint, $data = array(), $method = 'GET' ) {
		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'timeout' => 30,
		);

		if ( $method !== 'GET' && ! empty( $data ) ) {
			$args['body'] = $this->build_query( $data );
		} elseif ( $method === 'GET' && ! empty( $data ) ) {
			$url .= '?' . http_build_query( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$error_message = $body['error']['message'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'Stripe API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}

	/**
	 * Build query string for nested arrays
	 *
	 * @param array  $data   Data array
	 * @param string $prefix Prefix for keys
	 * @return string
	 */
	private function build_query( $data, $prefix = '' ) {
		$parts = array();

		foreach ( $data as $key => $value ) {
			$key = $prefix ? $prefix . '[' . $key . ']' : $key;

			if ( is_array( $value ) ) {
				$parts[] = $this->build_query( $value, $key );
			} else {
				$parts[] = urlencode( $key ) . '=' . urlencode( $value );
			}
		}

		return implode( '&', $parts );
	}

	/**
	 * Map Stripe status to standard status
	 *
	 * @param string $status Stripe status
	 * @return string
	 */
	private function map_status( $status ) {
		$status_map = array(
			'succeeded'             => 'succeeded',
			'processing'            => 'processing',
			'requires_payment_method' => 'pending',
			'requires_confirmation' => 'pending',
			'requires_action'       => 'pending',
			'canceled'              => 'canceled',
			'failed'                => 'failed',
		);

		return $status_map[ $status ] ?? 'pending';
	}
}
