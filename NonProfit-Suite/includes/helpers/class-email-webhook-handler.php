<?php
/**
 * Email Webhook Handler
 *
 * Handles incoming email webhooks from Gmail, Outlook, and other providers.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.6.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Email_Webhook_Handler Class
 *
 * Processes incoming email webhooks.
 */
class NonprofitSuite_Email_Webhook_Handler {

	/**
	 * Initialize webhook handlers.
	 */
	public static function init() {
		// Register REST API endpoints
		add_action( 'rest_api_init', array( __CLASS__, 'register_endpoints' ) );
	}

	/**
	 * Register REST API endpoints for webhooks.
	 */
	public static function register_endpoints() {
		// Gmail push notification endpoint
		register_rest_route( 'nonprofitsuite/v1', '/webhooks/gmail', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_gmail_webhook' ),
			'permission_callback' => '__return_true', // Gmail uses signed requests
		) );

		// Outlook webhook endpoint
		register_rest_route( 'nonprofitsuite/v1', '/webhooks/outlook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_outlook_webhook' ),
			'permission_callback' => '__return_true', // Outlook validates via token
		) );

		// Generic inbound email endpoint (for email forwarding services)
		register_rest_route( 'nonprofitsuite/v1', '/webhooks/inbound', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_inbound_email' ),
			'permission_callback' => array( __CLASS__, 'verify_inbound_auth' ),
		) );

		// Webhook validation endpoint (for Outlook subscription verification)
		register_rest_route( 'nonprofitsuite/v1', '/webhooks/outlook', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'validate_outlook_webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle Gmail push notification webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function handle_gmail_webhook( $request ) {
		$body = json_decode( $request->get_body(), true );

		if ( ! isset( $body['message']['data'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		// Decode Pub/Sub message
		$data = json_decode( base64_decode( $body['message']['data'] ), true );

		if ( ! isset( $data['emailAddress'] ) || ! isset( $data['historyId'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid data' ), 400 );
		}

		// Fetch new messages using Gmail adapter
		$adapter = NonprofitSuite_Email_Manager::get_adapter( 'gmail' );

		if ( ! $adapter ) {
			return new WP_REST_Response( array( 'error' => 'Gmail adapter not configured' ), 500 );
		}

		// Fetch recent unread messages
		$emails = $adapter->fetch_emails( array(
			'unread_only' => true,
			'limit'       => 10,
		) );

		if ( is_wp_error( $emails ) ) {
			return new WP_REST_Response( array( 'error' => $emails->get_error_message() ), 500 );
		}

		// Process each email
		foreach ( $emails as $email ) {
			self::process_received_email( $email, 'gmail' );
		}

		return new WP_REST_Response( array( 'status' => 'processed', 'count' => count( $emails ) ), 200 );
	}

	/**
	 * Handle Outlook webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function handle_outlook_webhook( $request ) {
		$body = json_decode( $request->get_body(), true );

		if ( ! isset( $body['value'] ) || ! is_array( $body['value'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		$adapter = NonprofitSuite_Email_Manager::get_adapter( 'outlook' );

		if ( ! $adapter ) {
			return new WP_REST_Response( array( 'error' => 'Outlook adapter not configured' ), 500 );
		}

		// Process each notification
		foreach ( $body['value'] as $notification ) {
			if ( $notification['changeType'] === 'created' && isset( $notification['resourceData']['id'] ) ) {
				// Fetch the specific message
				// Note: In production, you'd use the adapter to fetch this message
				// For now, just trigger a general fetch
				$emails = $adapter->fetch_emails( array(
					'unread_only' => true,
					'limit'       => 5,
				) );

				if ( ! is_wp_error( $emails ) ) {
					foreach ( $emails as $email ) {
						self::process_received_email( $email, 'outlook' );
					}
				}
			}
		}

		return new WP_REST_Response( array( 'status' => 'processed' ), 200 );
	}

	/**
	 * Validate Outlook webhook subscription.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function validate_outlook_webhook( $request ) {
		$validation_token = $request->get_param( 'validationToken' );

		if ( $validation_token ) {
			// Outlook subscription validation
			return new WP_REST_Response( $validation_token, 200, array(
				'Content-Type' => 'text/plain',
			) );
		}

		return new WP_REST_Response( array( 'error' => 'No validation token' ), 400 );
	}

	/**
	 * Handle generic inbound email.
	 *
	 * For use with email forwarding services or direct SMTP forwarding.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public static function handle_inbound_email( $request ) {
		$params = $request->get_params();

		// Parse email data (format varies by forwarding service)
		$email_data = array(
			'from_address' => isset( $params['from'] ) ? $params['from'] : '',
			'to'           => isset( $params['to'] ) ? $params['to'] : '',
			'subject'      => isset( $params['subject'] ) ? $params['subject'] : '',
			'body_text'    => isset( $params['text'] ) ? $params['text'] : '',
			'body_html'    => isset( $params['html'] ) ? $params['html'] : '',
			'headers'      => isset( $params['headers'] ) ? $params['headers'] : array(),
		);

		self::process_received_email( $email_data, 'inbound' );

		return new WP_REST_Response( array( 'status' => 'received' ), 200 );
	}

	/**
	 * Verify inbound email authentication.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if authenticated.
	 */
	public static function verify_inbound_auth( $request ) {
		// Check for webhook secret in headers or query params
		$secret = $request->get_header( 'X-Webhook-Secret' );

		if ( ! $secret ) {
			$secret = $request->get_param( 'secret' );
		}

		$expected_secret = get_option( 'ns_email_webhook_secret', '' );

		if ( empty( $expected_secret ) ) {
			// No secret configured, generate one
			$expected_secret = wp_generate_password( 32, false );
			update_option( 'ns_email_webhook_secret', $expected_secret );
		}

		return hash_equals( $expected_secret, $secret );
	}

	/**
	 * Process a received email.
	 *
	 * @param array  $email_data Email data.
	 * @param string $source     Source adapter.
	 */
	private static function process_received_email( $email_data, $source ) {
		// Check if already processed (avoid duplicates)
		if ( isset( $email_data['message_id'] ) ) {
			global $wpdb;
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ns_email_log WHERE message_id = %s",
					$email_data['message_id']
				)
			);

			if ( $existing ) {
				return; // Already processed
			}
		}

		// Use the email routing system to process
		NonprofitSuite_Email_Routing::process_incoming_email( $email_data );
	}

	/**
	 * Get webhook URLs for configuration.
	 *
	 * @return array Webhook URLs.
	 */
	public static function get_webhook_urls() {
		$base_url = rest_url( 'nonprofitsuite/v1/webhooks' );

		return array(
			'gmail'   => $base_url . '/gmail',
			'outlook' => $base_url . '/outlook',
			'inbound' => $base_url . '/inbound?secret=' . get_option( 'ns_email_webhook_secret', '' ),
		);
	}
}

// Initialize
NonprofitSuite_Email_Webhook_Handler::init();
