<?php
/**
 * Mobile App API Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Mobile_API {

	/**
	 * Generate API token
	 *
	 * @param int    $user_id User ID
	 * @param string $token_name Token name
	 * @param array  $permissions Permissions array
	 * @param int    $expires_days Days until expiration
	 * @return array|WP_Error Token info or error
	 */
	public static function generate_token( $user_id, $token_name, $permissions = array(), $expires_days = 365 ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage API tokens' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Generate secure token
		$token = wp_generate_password( 64, false );
		$token_hash = hash( 'sha256', $token );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_api_tokens',
			array(
				'user_id' => absint( $user_id ),
				'token' => $token_hash,
				'token_name' => sanitize_text_field( $token_name ),
				'permissions' => wp_json_encode( $permissions ),
				'expires_at' => date( 'Y-m-d H:i:s', strtotime( "+{$expires_days} days" ) ),
				'is_active' => 1,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to generate token', 'nonprofitsuite' ) );
		}

		return array(
			'token_id' => $wpdb->insert_id,
			'token' => $token, // Only shown once!
			'token_name' => $token_name,
			'expires_at' => date( 'Y-m-d H:i:s', strtotime( "+{$expires_days} days" ) ),
		);
	}

	/**
	 * Validate API token
	 *
	 * @param string $token API token
	 * @return object|WP_Error Token object or error
	 */
	public static function validate_token( $token ) {
		global $wpdb;

		$token_hash = hash( 'sha256', $token );

		$token_data = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, user_id, token, token_name, permissions, last_used, expires_at, is_active, created_at
			 FROM {$wpdb->prefix}ns_api_tokens
			WHERE token = %s
			AND is_active = 1
			AND (expires_at IS NULL OR expires_at > NOW())",
			$token_hash
		) );

		if ( ! $token_data ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired API token', 'nonprofitsuite' ) );
		}

		// Update last used
		$wpdb->update(
			$wpdb->prefix . 'ns_api_tokens',
			array( 'last_used' => current_time( 'mysql' ) ),
			array( 'id' => $token_data->id ),
			array( '%s' ),
			array( '%d' )
		);

		return $token_data;
	}

	/**
	 * Revoke API token
	 *
	 * @param int $token_id Token ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function revoke_token( $token_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage API tokens' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_api_tokens',
			array( 'is_active' => 0 ),
			array( 'id' => absint( $token_id ) ),
			array( '%d' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Log API request
	 *
	 * @param int    $token_id Token ID
	 * @param string $endpoint Endpoint called
	 * @param string $method HTTP method
	 * @param array  $request_data Request data
	 * @param int    $response_code Response code
	 * @param int    $response_time Response time in ms
	 * @return bool True on success
	 */
	public static function log_request( $token_id, $endpoint, $method, $request_data = array(), $response_code = 200, $response_time = 0 ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'ns_api_logs',
			array(
				'token_id' => absint( $token_id ),
				'endpoint' => sanitize_text_field( $endpoint ),
				'method' => sanitize_text_field( $method ),
				'request_data' => wp_json_encode( $request_data ),
				'response_code' => absint( $response_code ),
				'response_time' => absint( $response_time ),
				'ip_address' => self::get_client_ip(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address
	 */
	private static function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		}

		return sanitize_text_field( $ip );
	}

	/**
	 * Get user tokens
	 *
	 * @param int $user_id User ID
	 * @return array Array of tokens
	 */
	public static function get_user_tokens( $user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, token_name, permissions, last_used, expires_at, is_active, created_at
			FROM {$wpdb->prefix}ns_api_tokens
			WHERE user_id = %d
			ORDER BY created_at DESC",
			absint( $user_id )
		) );
	}

	/**
	 * Get API usage stats
	 *
	 * @param int $token_id Token ID
	 * @param int $days Days to look back
	 * @return array Usage statistics
	 */
	public static function get_usage_stats( $token_id, $days = 30 ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as total_requests,
				AVG(response_time) as avg_response_time,
				MAX(response_time) as max_response_time,
				SUM(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 ELSE 0 END) as successful_requests,
				SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as failed_requests
			FROM {$wpdb->prefix}ns_api_logs
			WHERE token_id = %d
			AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
			absint( $token_id ),
			absint( $days )
		), ARRAY_A );

		return $stats;
	}

	/**
	 * Register REST API routes
	 */
	public static function register_routes() {
		register_rest_route( 'nonprofitsuite/v1', '/auth', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'api_authenticate' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'nonprofitsuite/v1', '/meetings', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'api_get_meetings' ),
			'permission_callback' => array( __CLASS__, 'api_permission_check' ),
		) );

		register_rest_route( 'nonprofitsuite/v1', '/tasks', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'api_get_tasks' ),
			'permission_callback' => array( __CLASS__, 'api_permission_check' ),
		) );
	}

	/**
	 * API authentication endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response
	 */
	public static function api_authenticate( $request ) {
		$token = $request->get_header( 'X-API-Token' );

		if ( ! $token ) {
			return new WP_Error( 'no_token', __( 'API token required', 'nonprofitsuite' ), array( 'status' => 401 ) );
		}

		$token_data = self::validate_token( $token );

		if ( is_wp_error( $token_data ) ) {
			return $token_data;
		}

		return new WP_REST_Response( array(
			'authenticated' => true,
			'user_id' => $token_data->user_id,
		), 200 );
	}

	/**
	 * API permission check
	 *
	 * @param WP_REST_Request $request Request object
	 * @return bool True if authorized
	 */
	public static function api_permission_check( $request ) {
		$token = $request->get_header( 'X-API-Token' );

		if ( ! $token ) {
			return false;
		}

		$token_data = self::validate_token( $token );

		return ! is_wp_error( $token_data );
	}

	/**
	 * API get meetings endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response
	 */
	public static function api_get_meetings( $request ) {
		$meetings = NonprofitSuite_Meetings::get_upcoming_meetings( 10 );

		return new WP_REST_Response( array(
			'meetings' => $meetings,
		), 200 );
	}

	/**
	 * API get tasks endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response
	 */
	public static function api_get_tasks( $request ) {
		$token = $request->get_header( 'X-API-Token' );
		$token_data = self::validate_token( $token );

		$tasks = NonprofitSuite_Tasks::get_user_tasks( $token_data->user_id );

		return new WP_REST_Response( array(
			'tasks' => $tasks,
		), 200 );
	}
}

// Register REST API routes
add_action( 'rest_api_init', array( 'NonprofitSuite_Mobile_API', 'register_routes' ) );
