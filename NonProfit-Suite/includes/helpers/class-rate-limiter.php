<?php
/**
 * Rate Limiter Helper
 *
 * Prevents abuse of AJAX endpoints and API calls
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter class for preventing abuse
 */
class NonprofitSuite_Rate_Limiter {

	/**
	 * Default rate limits by endpoint type
	 *
	 * @var array
	 */
	private static $default_limits = array(
		'ajax_general'  => array( 'limit' => 60, 'window' => 60 ),   // 60 requests per minute
		'ajax_write'    => array( 'limit' => 30, 'window' => 60 ),   // 30 write operations per minute
		'ajax_export'   => array( 'limit' => 10, 'window' => 60 ),   // 10 exports per minute
		'ajax_autosave' => array( 'limit' => 120, 'window' => 60 ),  // 120 autosaves per minute (2 per second)
		'api'           => array( 'limit' => 100, 'window' => 60 ),  // 100 API calls per minute
	);

	/**
	 * Check if action is rate limited
	 *
	 * @param string $action Action identifier
	 * @param string $type Rate limit type (ajax_general, ajax_write, ajax_export, ajax_autosave, api)
	 * @param int    $user_id User ID (optional, defaults to current user)
	 * @return bool True if allowed, false if rate limited
	 */
	public static function check( $action, $type = 'ajax_general', $user_id = null ) {
		// Allow unlimited for administrators (optional - can be disabled for security)
		if ( current_user_can( 'manage_options' ) && apply_filters( 'nonprofitsuite_rate_limit_exempt_admins', false ) ) {
			return true;
		}

		// Get user ID
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// For non-logged-in users, use IP address
		$identifier = $user_id ? 'user_' . $user_id : 'ip_' . self::get_client_ip();

		// Get rate limits
		$limits = self::get_limits( $type );
		$limit = $limits['limit'];
		$window = $limits['window'];

		// Create transient key
		$transient_key = 'ns_rate_' . md5( $action . '_' . $identifier );

		// Get current count
		$count = get_transient( $transient_key );

		if ( false === $count ) {
			// First request in window
			set_transient( $transient_key, 1, $window );
			return true;
		}

		// Increment count
		$count = absint( $count ) + 1;

		// Check if over limit
		if ( $count > $limit ) {
			// Log rate limit violation
			self::log_violation( $action, $identifier, $count, $limit );
			return false;
		}

		// Update count
		set_transient( $transient_key, $count, $window );
		return true;
	}

	/**
	 * Get rate limits for a type
	 *
	 * @param string $type Rate limit type
	 * @return array Rate limits with 'limit' and 'window' keys
	 */
	private static function get_limits( $type ) {
		$defaults = isset( self::$default_limits[ $type ] ) ? self::$default_limits[ $type ] : self::$default_limits['ajax_general'];

		// Allow filtering of rate limits
		return apply_filters( 'nonprofitsuite_rate_limits', $defaults, $type );
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address
	 */
	private static function get_client_ip() {
		// Check for proxy headers first
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Take first IP if comma-separated
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}
				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Log rate limit violation
	 *
	 * @param string $action Action that was rate limited
	 * @param string $identifier User/IP identifier
	 * @param int    $count Current request count
	 * @param int    $limit Rate limit
	 */
	private static function log_violation( $action, $identifier, $count, $limit ) {
		// Only log if debug mode is enabled
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$message = sprintf(
			'[NonprofitSuite] Rate limit exceeded: Action=%s, Identifier=%s, Count=%d, Limit=%d',
			$action,
			$identifier,
			$count,
			$limit
		);

		error_log( $message );

		// Allow custom logging
		do_action( 'nonprofitsuite_rate_limit_exceeded', $action, $identifier, $count, $limit );
	}

	/**
	 * Send rate limit error response
	 *
	 * @param string $action Action that was rate limited
	 */
	public static function send_error( $action = '' ) {
		$message = __( 'Too many requests. Please slow down and try again in a moment.', 'nonprofitsuite' );

		if ( wp_doing_ajax() ) {
			wp_send_json_error(
				array(
					'message' => $message,
					'code'    => 'rate_limit_exceeded',
				),
				429
			);
		} else {
			wp_die(
				esc_html( $message ),
				esc_html__( 'Too Many Requests', 'nonprofitsuite' ),
				array( 'response' => 429 )
			);
		}
	}

	/**
	 * Clear rate limit for specific action and user
	 *
	 * Useful for testing or manual override
	 *
	 * @param string $action Action identifier
	 * @param int    $user_id User ID (optional)
	 */
	public static function clear( $action, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$identifier = $user_id ? 'user_' . $user_id : 'ip_' . self::get_client_ip();
		$transient_key = 'ns_rate_' . md5( $action . '_' . $identifier );

		delete_transient( $transient_key );
	}

	/**
	 * Get remaining requests for action
	 *
	 * @param string $action Action identifier
	 * @param string $type Rate limit type
	 * @param int    $user_id User ID (optional)
	 * @return array Array with 'remaining' and 'limit' keys
	 */
	public static function get_remaining( $action, $type = 'ajax_general', $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$identifier = $user_id ? 'user_' . $user_id : 'ip_' . self::get_client_ip();
		$transient_key = 'ns_rate_' . md5( $action . '_' . $identifier );

		$limits = self::get_limits( $type );
		$count = get_transient( $transient_key );
		$count = $count ? absint( $count ) : 0;

		return array(
			'remaining' => max( 0, $limits['limit'] - $count ),
			'limit'     => $limits['limit'],
			'reset'     => time() + $limits['window'],
		);
	}
}
