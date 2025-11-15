<?php
/**
 * Square Payment Adapter
 *
 * Adapter for Square payment processing (online + in-person).
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
 * NonprofitSuite_Payment_Square_Adapter Class
 *
 * Implements payment integration using Square API.
 */
class NonprofitSuite_Payment_Square_Adapter implements NonprofitSuite_Payment_Adapter_Interface {

	/**
	 * Square API base URL
	 */
	const API_BASE_URL = 'https://connect.squareup.com/v2/';
	const API_SANDBOX_URL = 'https://connect.squareupsandbox.com/v2/';

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Access token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Sandbox mode
	 *
	 * @var bool
	 */
	private $sandbox;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'payment', 'square' );
		$this->sandbox = ! empty( $this->settings['sandbox_mode'] );
		$this->access_token = $this->sandbox ?
			( $this->settings['sandbox_access_token'] ?? '' ) :
			( $this->settings['access_token'] ?? '' );
	}

	/**
	 * Get API base URL
	 *
	 * @return string
	 */
	private function get_api_url() {
		return $this->sandbox ? self::API_SANDBOX_URL : self::API_BASE_URL;
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
			'payment_method' => '',
			'metadata'       => array(),
		) );

		// Square requires amount in smallest currency unit (cents)
		$amount_money = array(
			'amount'   => (int) $payment_data['amount'],
			'currency' => $payment_data['currency'],
		);

		$payload = array(
			'source_id'         => $payment_data['payment_method'],
			'idempotency_key'   => wp_generate_uuid4(),
			'amount_money'      => $amount_money,
			'autocomplete'      => true,
			'customer_details'  => array(),
			'note'              => $payment_data['description'],
		);

		if ( ! empty( $payment_data['donor_email'] ) ) {
			$payload['customer_details']['email_address'] = $payment_data['donor_email'];
		}

		$response = $this->make_request( 'payments', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'payment_id' => $response['payment']['id'],
			'status'     => $this->map_status( $response['payment']['status'] ),
			'amount'     => $response['payment']['amount_money']['amount'],
			'currency'   => $response['payment']['amount_money']['currency'],
		);
	}

	/**
	 * Capture a payment
	 *
	 * Square payments are autocompleted by default
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

		$refund_amount = isset( $args['amount'] ) ? (int) $args['amount'] : $payment['amount'];

		$payload = array(
			'idempotency_key' => wp_generate_uuid4(),
			'payment_id'      => $payment_id,
			'amount_money'    => array(
				'amount'   => $refund_amount,
				'currency' => $payment['currency'],
			),
			'reason'          => $args['reason'] ?? 'Refund requested',
		);

		$response = $this->make_request( 'refunds', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'refund_id' => $response['refund']['id'],
			'status'    => $response['refund']['status'],
			'amount'    => $response['refund']['amount_money']['amount'],
		);
	}

	/**
	 * Get payment
	 *
	 * @param string $payment_id Payment ID
	 * @return array|WP_Error
	 */
	public function get_payment( $payment_id ) {
		$response = $this->make_request( 'payments/' . $payment_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payment = $response['payment'];

		return array(
			'payment_id' => $payment['id'],
			'status'     => $this->map_status( $payment['status'] ),
			'amount'     => $payment['amount_money']['amount'],
			'currency'   => $payment['amount_money']['currency'],
			'created'    => strtotime( $payment['created_at'] ),
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
			'location_id'    => $this->settings['location_id'] ?? '',
			'created_after'  => '',
			'created_before' => '',
		) );

		$params = array(
			'location_ids' => array( $args['location_id'] ),
			'limit'        => $args['limit'],
		);

		if ( ! empty( $args['created_after'] ) ) {
			$params['begin_time'] = date( 'c', strtotime( $args['created_after'] ) );
		}

		if ( ! empty( $args['created_before'] ) ) {
			$params['end_time'] = date( 'c', strtotime( $args['created_before'] ) );
		}

		$response = $this->make_request( 'payments', $params, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['payments'] ?? array();
	}

	/**
	 * Create subscription
	 *
	 * @param array $subscription_data Subscription data
	 * @return array|WP_Error
	 */
	public function create_subscription( $subscription_data ) {
		// First create customer if needed
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

		// Create subscription via Invoices API (Square doesn't have direct recurring payments)
		// This is a simplified implementation
		return new WP_Error(
			'not_implemented',
			__( 'Square subscriptions require additional setup. Please use Square Dashboard.', 'nonprofitsuite' )
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
		$response = $this->make_request(
			'subscriptions/' . $subscription_id . '/cancel',
			array(),
			'POST'
		);

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
		return new WP_Error( 'not_supported', __( 'Square subscription updates must be done via Square Dashboard', 'nonprofitsuite' ) );
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

		return $response['subscription'];
	}

	/**
	 * Create customer
	 *
	 * @param array $customer_data Customer data
	 * @return array|WP_Error
	 */
	public function create_customer( $customer_data ) {
		$payload = array(
			'idempotency_key' => wp_generate_uuid4(),
			'email_address'   => $customer_data['email'],
		);

		if ( ! empty( $customer_data['name'] ) ) {
			$payload['given_name'] = $customer_data['name'];
		}

		if ( ! empty( $customer_data['phone'] ) ) {
			$payload['phone_number'] = $customer_data['phone'];
		}

		$response = $this->make_request( 'customers', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'customer_id' => $response['customer']['id'],
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

		return $response['customer'];
	}

	/**
	 * Handle webhook
	 *
	 * @param array $payload Webhook payload
	 * @return bool|WP_Error
	 */
	public function handle_webhook( $payload ) {
		// Handle different Square webhook events
		$event_type = $payload['type'] ?? '';

		switch ( $event_type ) {
			case 'payment.created':
			case 'payment.updated':
				do_action( 'ns_square_payment_updated', $payload['data']['object']['payment'] );
				break;

			case 'refund.created':
			case 'refund.updated':
				do_action( 'ns_square_refund_updated', $payload['data']['object']['refund'] );
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
		$webhook_signature_key = $this->settings['webhook_signature_key'] ?? '';

		if ( empty( $webhook_signature_key ) ) {
			return false;
		}

		// Square uses HMAC SHA256
		$expected_signature = base64_encode(
			hash_hmac( 'sha256', $payload, $webhook_signature_key, true )
		);

		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Get checkout URL
	 *
	 * Square uses Checkout API
	 *
	 * @param array $args Checkout arguments
	 * @return string|WP_Error
	 */
	public function get_checkout_url( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'amount'       => 0,
			'currency'     => 'USD',
			'description'  => '',
			'success_url'  => '',
			'cancel_url'   => '',
		) );

		$payload = array(
			'idempotency_key' => wp_generate_uuid4(),
			'order'           => array(
				'location_id' => $this->settings['location_id'] ?? '',
				'line_items'  => array(
					array(
						'name'                 => $args['description'],
						'quantity'             => '1',
						'base_price_money'     => array(
							'amount'   => (int) $args['amount'],
							'currency' => $args['currency'],
						),
					),
				),
			),
			'checkout_options' => array(
				'redirect_url' => $args['success_url'],
			),
		);

		$response = $this->make_request( 'online-checkout/payment-links', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['payment_link']['url'] ?? '';
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->access_token ) ) {
			return new WP_Error( 'not_configured', __( 'Square access token not configured', 'nonprofitsuite' ) );
		}

		// Test with locations API call
		$response = $this->make_request( 'locations', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['locations'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Square';
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
		$url = $this->get_api_url() . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Square-Version' => '2023-10-18',
				'Authorization'  => 'Bearer ' . $this->access_token,
				'Content-Type'   => 'application/json',
			),
			'timeout' => 30,
		);

		if ( $method !== 'GET' && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
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
			$error_message = $body['errors'][0]['detail'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'Square API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}

	/**
	 * Map Square status to standard status
	 *
	 * @param string $status Square status
	 * @return string
	 */
	private function map_status( $status ) {
		$status_map = array(
			'APPROVED'  => 'succeeded',
			'COMPLETED' => 'succeeded',
			'PENDING'   => 'pending',
			'FAILED'    => 'failed',
			'CANCELED'  => 'canceled',
		);

		return $status_map[ $status ] ?? 'pending';
	}
}
