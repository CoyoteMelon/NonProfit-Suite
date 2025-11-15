<?php
/**
 * Webhook Handler
 *
 * Handles incoming webhooks from third-party integration providers.
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
 * NonprofitSuite_Webhook_Handler Class
 *
 * Routes and processes webhooks from external services.
 */
class NonprofitSuite_Webhook_Handler {

	/**
	 * Integration Manager instance
	 *
	 * @var NonprofitSuite_Integration_Manager
	 */
	private $manager;

	/**
	 * Webhook endpoint base
	 *
	 * @var string
	 */
	private $endpoint_base = 'ns-webhook';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_webhook' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Add rewrite rules for webhook endpoints
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^' . $this->endpoint_base . '/([^/]+)/([^/]+)/?',
			'index.php?ns_webhook_category=$matches[1]&ns_webhook_provider=$matches[2]',
			'top'
		);
	}

	/**
	 * Add query vars
	 *
	 * @param array $vars Query vars
	 * @return array Modified query vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'ns_webhook_category';
		$vars[] = 'ns_webhook_provider';
		return $vars;
	}

	/**
	 * Handle incoming webhook
	 */
	public function handle_webhook() {
		$category = get_query_var( 'ns_webhook_category' );
		$provider_id = get_query_var( 'ns_webhook_provider' );

		// Check if this is a webhook request
		if ( empty( $category ) || empty( $provider_id ) ) {
			return;
		}

		// Log webhook attempt
		$this->log_webhook( 'received', $category, $provider_id );

		// Get provider adapter
		$adapter = $this->manager->get_active_provider( $category );

		if ( is_wp_error( $adapter ) ) {
			$this->send_webhook_response( 400, array(
				'error' => 'Invalid provider configuration',
			) );
			return;
		}

		// Verify this is the correct provider
		$active_provider_id = $this->manager->get_active_provider_id( $category );
		if ( $provider_id !== $active_provider_id ) {
			$this->log_webhook( 'provider_mismatch', $category, $provider_id );
			$this->send_webhook_response( 400, array(
				'error' => 'Provider mismatch',
			) );
			return;
		}

		// Get raw request body
		$raw_body = file_get_contents( 'php://input' );
		$headers = $this->get_request_headers();

		// Verify webhook signature if provider supports it
		if ( method_exists( $adapter, 'verify_webhook_signature' ) ) {
			$signature = isset( $headers['X-Signature'] ) ? $headers['X-Signature'] : '';

			if ( empty( $signature ) ) {
				// Try common signature header names
				$signature = isset( $headers['X-Hub-Signature'] ) ? $headers['X-Hub-Signature'] : '';
			}
			if ( empty( $signature ) ) {
				$signature = isset( $headers['Stripe-Signature'] ) ? $headers['Stripe-Signature'] : '';
			}

			$is_valid = $adapter->verify_webhook_signature( $raw_body, $signature );

			if ( ! $is_valid ) {
				$this->log_webhook( 'invalid_signature', $category, $provider_id );
				$this->send_webhook_response( 401, array(
					'error' => 'Invalid signature',
				) );
				return;
			}
		}

		// Parse payload
		$payload = json_decode( $raw_body, true );

		if ( null === $payload && json_last_error() !== JSON_ERROR_NONE ) {
			// Try to parse as form data
			parse_str( $raw_body, $payload );
		}

		// Handle webhook if adapter supports it
		if ( method_exists( $adapter, 'handle_webhook' ) ) {
			try {
				$result = $adapter->handle_webhook( $payload );

				if ( is_wp_error( $result ) ) {
					$this->log_webhook( 'handler_error', $category, $provider_id, $result->get_error_message() );
					$this->send_webhook_response( 400, array(
						'error' => $result->get_error_message(),
					) );
					return;
				}

				/**
				 * Fires after webhook is successfully processed
				 *
				 * @param string $category     Category slug
				 * @param string $provider_id  Provider ID
				 * @param array  $payload      Webhook payload
				 * @param mixed  $result       Handler result
				 */
				do_action( 'ns_webhook_processed', $category, $provider_id, $payload, $result );

				$this->log_webhook( 'success', $category, $provider_id );
				$this->send_webhook_response( 200, array(
					'success' => true,
				) );

			} catch ( Exception $e ) {
				$this->log_webhook( 'exception', $category, $provider_id, $e->getMessage() );
				$this->send_webhook_response( 500, array(
					'error' => 'Internal server error',
				) );
			}
		} else {
			// Provider doesn't support webhooks
			$this->log_webhook( 'not_supported', $category, $provider_id );
			$this->send_webhook_response( 501, array(
				'error' => 'Webhooks not supported by this provider',
			) );
		}
	}

	/**
	 * Get webhook URL for a provider
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @return string Webhook URL
	 */
	public function get_webhook_url( $category, $provider_id ) {
		return home_url( $this->endpoint_base . '/' . $category . '/' . $provider_id );
	}

	/**
	 * Send webhook response
	 *
	 * @param int   $status_code HTTP status code
	 * @param array $data        Response data
	 */
	private function send_webhook_response( $status_code, $data ) {
		status_header( $status_code );
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		exit;
	}

	/**
	 * Get request headers
	 *
	 * @return array Headers
	 */
	private function get_request_headers() {
		$headers = array();

		// Get all headers
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
		} else {
			// Fallback for servers without getallheaders()
			foreach ( $_SERVER as $key => $value ) {
				if ( substr( $key, 0, 5 ) === 'HTTP_' ) {
					$header = str_replace( ' ', '-', ucwords( str_replace( '_', ' ', strtolower( substr( $key, 5 ) ) ) ) );
					$headers[ $header ] = $value;
				}
			}
		}

		return $headers;
	}

	/**
	 * Log webhook activity
	 *
	 * @param string $event       Event type
	 * @param string $category    Category slug
	 * @param string $provider_id Provider ID
	 * @param string $details     Additional details (optional)
	 */
	private function log_webhook( $event, $category, $provider_id, $details = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_webhook_log';

		// Check if table exists, if not, skip logging
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		if ( ! $exists ) {
			return;
		}

		$log_entry = array(
			'event'       => $event,
			'category'    => $category,
			'provider_id' => $provider_id,
			'details'     => $details,
			'ip_address'  => $this->get_client_ip(),
			'created_at'  => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $log_entry );

		/**
		 * Fires after webhook is logged
		 *
		 * @param string $event       Event type
		 * @param string $category    Category
		 * @param string $provider_id Provider ID
		 * @param array  $log_entry   Log entry data
		 */
		do_action( 'ns_webhook_logged', $event, $category, $provider_id, $log_entry );
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address
	 */
	private function get_client_ip() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return sanitize_text_field( $ip );
	}

	/**
	 * Get webhook logs
	 *
	 * @param array $args Query arguments
	 * @return array Webhook logs
	 */
	public function get_webhook_logs( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_webhook_log';

		// Check if table exists
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		if ( ! $exists ) {
			return array();
		}

		$args = wp_parse_args( $args, array(
			'category'    => null,
			'provider_id' => null,
			'event'       => null,
			'limit'       => 100,
			'offset'      => 0,
		) );

		$where = array( '1=1' );
		$prepare_args = array();

		if ( $args['category'] ) {
			$where[] = 'category = %s';
			$prepare_args[] = $args['category'];
		}

		if ( $args['provider_id'] ) {
			$where[] = 'provider_id = %s';
			$prepare_args[] = $args['provider_id'];
		}

		if ( $args['event'] ) {
			$where[] = 'event = %s';
			$prepare_args[] = $args['event'];
		}

		$where_clause = implode( ' AND ', $where );
		$limit = (int) $args['limit'];
		$offset = (int) $args['offset'];

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT {$offset}, {$limit}";

		if ( ! empty( $prepare_args ) ) {
			$query = $wpdb->prepare( $query, $prepare_args );
		}

		$logs = $wpdb->get_results( $query, ARRAY_A );

		return $logs ? $logs : array();
	}
}

// Initialize webhook handler
new NonprofitSuite_Webhook_Handler();
