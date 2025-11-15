<?php
/**
 * Venmo Payment Adapter
 *
 * Adapter for Venmo payment processing via Braintree SDK.
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
 * NonprofitSuite_Payment_Venmo_Adapter Class
 *
 * Implements payment integration using Venmo via Braintree.
 */
class NonprofitSuite_Payment_Venmo_Adapter implements NonprofitSuite_Payment_Adapter_Interface {

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Braintree Gateway
	 *
	 * @var object
	 */
	private $gateway;

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
		$this->settings = $manager->get_provider_settings( 'payment', 'venmo' );
		$this->sandbox = ! empty( $this->settings['sandbox_mode'] );
		$this->init_gateway();
	}

	/**
	 * Initialize Braintree Gateway
	 *
	 * Note: Requires Braintree PHP SDK
	 * Install via: composer require braintree/braintree_php
	 */
	private function init_gateway() {
		if ( ! class_exists( 'Braintree\Gateway' ) ) {
			return;
		}

		$environment = $this->sandbox ? 'sandbox' : 'production';

		try {
			$this->gateway = new Braintree\Gateway( array(
				'environment' => $environment,
				'merchantId'  => $this->settings['merchant_id'] ?? '',
				'publicKey'   => $this->settings['public_key'] ?? '',
				'privateKey'  => $this->settings['private_key'] ?? '',
			) );
		} catch ( Exception $e ) {
			// Gateway initialization failed
		}
	}

	/**
	 * Create a payment
	 *
	 * @param array $payment_data Payment data
	 * @return array|WP_Error
	 */
	public function create_payment( $payment_data ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed. Install via: composer require braintree/braintree_php', 'nonprofitsuite' ) );
		}

		$payment_data = wp_parse_args( $payment_data, array(
			'amount'         => 0,
			'payment_method' => '', // Payment method nonce from Braintree JS SDK
			'donor_email'    => '',
			'donor_name'     => '',
			'description'    => '',
			'metadata'       => array(),
		) );

		try {
			$result = $this->gateway->transaction()->sale( array(
				'amount'             => number_format( $payment_data['amount'] / 100, 2, '.', '' ),
				'paymentMethodNonce' => $payment_data['payment_method'],
				'options'            => array(
					'submitForSettlement' => true,
				),
				'customer'           => array(
					'email' => $payment_data['donor_email'],
				),
				'customFields'       => $this->format_metadata( $payment_data['metadata'] ),
			) );

			if ( $result->success ) {
				return array(
					'payment_id' => $result->transaction->id,
					'status'     => $this->map_status( $result->transaction->status ),
					'amount'     => $result->transaction->amount * 100,
					'currency'   => $result->transaction->currencyIsoCode,
				);
			} else {
				return new WP_Error( 'payment_failed', $result->message );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Capture a payment
	 *
	 * Braintree payments are auto-settled
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
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			// Get payment to check refund eligibility
			$payment = $this->get_payment( $payment_id );

			if ( is_wp_error( $payment ) ) {
				return $payment;
			}

			$refund_amount = isset( $args['amount'] ) ?
				number_format( $args['amount'] / 100, 2, '.', '' ) : null;

			$result = $this->gateway->transaction()->refund( $payment_id, $refund_amount );

			if ( $result->success ) {
				return array(
					'refund_id' => $result->transaction->id,
					'status'    => 'succeeded',
					'amount'    => $result->transaction->amount * 100,
				);
			} else {
				return new WP_Error( 'refund_failed', $result->message );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Get payment
	 *
	 * @param string $payment_id Payment ID
	 * @return array|WP_Error
	 */
	public function get_payment( $payment_id ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			$transaction = $this->gateway->transaction()->find( $payment_id );

			return array(
				'payment_id' => $transaction->id,
				'status'     => $this->map_status( $transaction->status ),
				'amount'     => $transaction->amount * 100,
				'currency'   => $transaction->currencyIsoCode,
				'created'    => strtotime( $transaction->createdAt->format( 'Y-m-d H:i:s' ) ),
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * List payments
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public function list_payments( $args = array() ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			$collection = $this->gateway->transaction()->search( array() );

			$payments = array();
			foreach ( $collection as $transaction ) {
				$payments[] = array(
					'payment_id' => $transaction->id,
					'status'     => $this->map_status( $transaction->status ),
					'amount'     => $transaction->amount * 100,
					'currency'   => $transaction->currencyIsoCode,
					'created'    => strtotime( $transaction->createdAt->format( 'Y-m-d H:i:s' ) ),
				);
			}

			return $payments;
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Create subscription
	 *
	 * @param array $subscription_data Subscription data
	 * @return array|WP_Error
	 */
	public function create_subscription( $subscription_data ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		// First create or get customer
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

		// Note: Requires plan to be created in Braintree dashboard
		try {
			$result = $this->gateway->subscription()->create( array(
				'paymentMethodToken' => $subscription_data['payment_method'],
				'planId'             => $subscription_data['plan_id'],
			) );

			if ( $result->success ) {
				return array(
					'subscription_id' => $result->subscription->id,
					'status'          => $result->subscription->status,
				);
			} else {
				return new WP_Error( 'subscription_failed', $result->message );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Cancel subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @param array  $args            Cancel arguments
	 * @return bool|WP_Error
	 */
	public function cancel_subscription( $subscription_id, $args = array() ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			$result = $this->gateway->subscription()->cancel( $subscription_id );

			if ( $result->success ) {
				return true;
			} else {
				return new WP_Error( 'cancel_failed', $result->message );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Update subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @param array  $update_data     Update data
	 * @return array|WP_Error
	 */
	public function update_subscription( $subscription_id, $update_data ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			$params = array();

			if ( isset( $update_data['plan_id'] ) ) {
				$params['planId'] = $update_data['plan_id'];
			}

			if ( isset( $update_data['price'] ) ) {
				$params['price'] = number_format( $update_data['price'] / 100, 2, '.', '' );
			}

			$result = $this->gateway->subscription()->update( $subscription_id, $params );

			if ( $result->success ) {
				return array(
					'subscription_id' => $result->subscription->id,
					'status'          => $result->subscription->status,
				);
			} else {
				return new WP_Error( 'update_failed', $result->message );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Get subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @return array|WP_Error
	 */
	public function get_subscription( $subscription_id ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			$subscription = $this->gateway->subscription()->find( $subscription_id );

			return array(
				'subscription_id' => $subscription->id,
				'status'          => $subscription->status,
				'plan_id'         => $subscription->planId,
				'price'           => $subscription->price * 100,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Create customer
	 *
	 * @param array $customer_data Customer data
	 * @return array|WP_Error
	 */
	public function create_customer( $customer_data ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			$result = $this->gateway->customer()->create( array(
				'email'     => $customer_data['email'],
				'firstName' => $customer_data['name'] ?? '',
			) );

			if ( $result->success ) {
				return array(
					'customer_id' => $result->customer->id,
				);
			} else {
				return new WP_Error( 'customer_creation_failed', $result->message );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Get customer
	 *
	 * @param string $customer_id Customer ID
	 * @return array|WP_Error
	 */
	public function get_customer( $customer_id ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			$customer = $this->gateway->customer()->find( $customer_id );

			return array(
				'customer_id' => $customer->id,
				'email'       => $customer->email,
				'name'        => $customer->firstName . ' ' . $customer->lastName,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Handle webhook
	 *
	 * @param array $payload Webhook payload
	 * @return bool|WP_Error
	 */
	public function handle_webhook( $payload ) {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			// Parse webhook notification
			$webhookNotification = $this->gateway->webhookNotification()->parse(
				$payload['bt_signature'],
				$payload['bt_payload']
			);

			$kind = $webhookNotification->kind;

			switch ( $kind ) {
				case 'subscription_charged_successfully':
					do_action( 'ns_venmo_subscription_charged', $webhookNotification->subscription );
					break;

				case 'subscription_canceled':
					do_action( 'ns_venmo_subscription_canceled', $webhookNotification->subscription );
					break;

				case 'subscription_expired':
					do_action( 'ns_venmo_subscription_expired', $webhookNotification->subscription );
					break;

				default:
					// Unknown event type
					break;
			}

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'webhook_error', $e->getMessage() );
		}
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload   Raw payload
	 * @param string $signature Signature header
	 * @return bool
	 */
	public function verify_webhook_signature( $payload, $signature ) {
		// Braintree handles signature verification in parse() method
		return true;
	}

	/**
	 * Get checkout URL
	 *
	 * Venmo requires client-side integration
	 *
	 * @param array $args Checkout arguments
	 * @return string|WP_Error
	 */
	public function get_checkout_url( $args = array() ) {
		return new WP_Error( 'not_supported', __( 'Venmo requires client-side integration with Braintree Drop-in UI', 'nonprofitsuite' ) );
	}

	/**
	 * Get client token for Braintree Drop-in UI
	 *
	 * @return string|WP_Error
	 */
	public function get_client_token() {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed', 'nonprofitsuite' ) );
		}

		try {
			return $this->gateway->clientToken()->generate();
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->gateway ) {
			return new WP_Error( 'sdk_missing', __( 'Braintree SDK is not installed. Install via: composer require braintree/braintree_php', 'nonprofitsuite' ) );
		}

		if ( empty( $this->settings['merchant_id'] ) || empty( $this->settings['public_key'] ) || empty( $this->settings['private_key'] ) ) {
			return new WP_Error( 'not_configured', __( 'Braintree credentials not configured', 'nonprofitsuite' ) );
		}

		try {
			// Test by generating a client token
			$token = $this->gateway->clientToken()->generate();
			return ! empty( $token );
		} catch ( Exception $e ) {
			return new WP_Error( 'connection_failed', $e->getMessage() );
		}
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Venmo';
	}

	/**
	 * Format metadata for custom fields
	 *
	 * @param array $metadata Metadata array
	 * @return array
	 */
	private function format_metadata( $metadata ) {
		$custom_fields = array();

		foreach ( $metadata as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$custom_fields[ $key ] = $value;
			}
		}

		return $custom_fields;
	}

	/**
	 * Map Braintree status to standard status
	 *
	 * @param string $status Braintree status
	 * @return string
	 */
	private function map_status( $status ) {
		$status_map = array(
			'authorized'            => 'authorized',
			'authorizing'           => 'pending',
			'settled'               => 'succeeded',
			'settling'              => 'processing',
			'submitted_for_settlement' => 'processing',
			'failed'                => 'failed',
			'gateway_rejected'      => 'failed',
			'processor_declined'    => 'failed',
			'voided'                => 'canceled',
		);

		return $status_map[ strtolower( $status ) ] ?? 'pending';
	}
}
