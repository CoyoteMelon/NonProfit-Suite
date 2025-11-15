<?php
/**
 * Payment Webhook Router
 *
 * Routes incoming payment processor webhooks to appropriate handlers.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Webhook_Router Class
 *
 * Central webhook routing for payment processors.
 */
class NonprofitSuite_Webhook_Router {

	/**
	 * Registered webhook handlers.
	 *
	 * @var array
	 */
	private static $handlers = array();

	/**
	 * Initialize webhook router.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_webhook_endpoints' ) );
	}

	/**
	 * Register webhook handlers.
	 *
	 * @param string $processor_type Processor type (stripe, paypal, etc.).
	 * @param string $handler_class  Handler class name.
	 */
	public static function register_handler( $processor_type, $handler_class ) {
		if ( ! class_exists( $handler_class ) ) {
			return new WP_Error( 'invalid_handler', 'Handler class does not exist' );
		}

		if ( ! in_array( 'NonprofitSuite_Webhook_Handler', class_implements( $handler_class ), true ) ) {
			return new WP_Error( 'invalid_interface', 'Handler must implement NonprofitSuite_Webhook_Handler' );
		}

		self::$handlers[ $processor_type ] = $handler_class;
	}

	/**
	 * Register REST API webhook endpoints.
	 */
	public static function register_webhook_endpoints() {
		// Generic webhook endpoint with processor in path
		register_rest_route( 'nonprofitsuite/v1', '/webhooks/(?P<processor>[a-z]+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => '__return_true', // Webhooks are verified via signature
		) );

		// Processor-specific endpoints for convenience
		foreach ( self::$handlers as $processor_type => $handler_class ) {
			register_rest_route( 'nonprofitsuite/v1', "/webhooks/{$processor_type}", array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'processor' => array(
						'default' => $processor_type,
					),
				),
			) );
		}
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @param WP_REST_Request $request Webhook request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public static function handle_webhook( $request ) {
		$processor_type = $request->get_param( 'processor' );
		$raw_payload    = $request->get_body();
		$signature      = $request->get_header( 'Stripe-Signature' ); // Default to Stripe header

		// Get processor-specific signature header
		if ( 'paypal' === $processor_type ) {
			$signature = $request->get_header( 'PAYPAL-TRANSMISSION-SIG' );
		}

		// Log webhook receipt
		self::log_webhook( $processor_type, $raw_payload, 'received' );

		// Get handler for processor
		if ( ! isset( self::$handlers[ $processor_type ] ) ) {
			self::log_webhook( $processor_type, $raw_payload, 'no_handler' );
			return new WP_Error( 'no_handler', 'No handler registered for this processor', array( 'status' => 400 ) );
		}

		$handler_class = self::$handlers[ $processor_type ];
		$handler       = new $handler_class();

		// Verify webhook signature
		if ( ! $handler->verify_signature( $raw_payload, $signature ) ) {
			self::log_webhook( $processor_type, $raw_payload, 'invalid_signature' );
			return new WP_Error( 'invalid_signature', 'Webhook signature verification failed', array( 'status' => 401 ) );
		}

		// Parse payload
		$event = $handler->parse_payload( $raw_payload );
		if ( is_wp_error( $event ) ) {
			self::log_webhook( $processor_type, $raw_payload, 'parse_error', $event->get_error_message() );
			return $event;
		}

		// Process event
		$result = $handler->process_event( $event );
		if ( is_wp_error( $result ) ) {
			self::log_webhook( $processor_type, $raw_payload, 'processing_error', $result->get_error_message() );
			return $result;
		}

		// Log success
		self::log_webhook( $processor_type, $raw_payload, 'processed', json_encode( $result ) );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Webhook processed successfully',
			'result'  => $result,
		) );
	}

	/**
	 * Log webhook activity.
	 *
	 * @param string $processor_type Processor type.
	 * @param string $payload        Raw payload.
	 * @param string $status         Status (received, processed, error).
	 * @param string $details        Optional details.
	 */
	private static function log_webhook( $processor_type, $payload, $status, $details = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_webhook_logs';

		// Create table if it doesn't exist
		self::maybe_create_webhook_log_table();

		$wpdb->insert(
			$table,
			array(
				'processor_type' => $processor_type,
				'payload'        => $payload,
				'status'         => $status,
				'details'        => $details,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Create webhook log table if it doesn't exist.
	 */
	private static function maybe_create_webhook_log_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_webhook_logs';

		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		if ( $table_exists ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			processor_type varchar(50) NOT NULL,
			payload longtext NOT NULL,
			status varchar(50) NOT NULL,
			details text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY processor_type (processor_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get webhook logs.
	 *
	 * @param array $args Query arguments.
	 * @return array Webhook logs.
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_webhook_logs';

		$defaults = array(
			'processor_type' => '',
			'status'         => '',
			'limit'          => 50,
			'offset'         => 0,
			'order_by'       => 'created_at',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array();
		if ( ! empty( $args['processor_type'] ) ) {
			$where[] = $wpdb->prepare( 'processor_type = %s', $args['processor_type'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT * FROM {$table} {$where_clause} ORDER BY {$args['order_by']} {$args['order']} LIMIT {$args['limit']} OFFSET {$args['offset']}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get registered handlers.
	 *
	 * @return array Registered handlers.
	 */
	public static function get_handlers() {
		return self::$handlers;
	}

	/**
	 * Get webhook URL for a processor.
	 *
	 * @param string $processor_type Processor type.
	 * @return string Webhook URL.
	 */
	public static function get_webhook_url( $processor_type ) {
		return rest_url( "nonprofitsuite/v1/webhooks/{$processor_type}" );
	}
}
