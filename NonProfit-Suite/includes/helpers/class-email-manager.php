<?php
/**
 * Email Manager
 *
 * Central manager for email adapters and routing.
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
 * NonprofitSuite_Email_Manager Class
 *
 * Manages email adapters and provides unified interface.
 */
class NonprofitSuite_Email_Manager {

	/**
	 * Registered adapters.
	 *
	 * @var array
	 */
	private static $adapters = array();

	/**
	 * Initialize email system.
	 */
	public static function init() {
		// Register email adapters
		self::register_adapter( 'smtp', 'NonprofitSuite_SMTP_Email_Adapter' );
		self::register_adapter( 'gmail', 'NonprofitSuite_Gmail_Email_Adapter' );
		self::register_adapter( 'outlook', 'NonprofitSuite_Outlook_Email_Adapter' );
	}

	/**
	 * Register an email adapter.
	 *
	 * @param string $provider_key Provider key (e.g., 'smtp', 'gmail').
	 * @param string $class_name   Adapter class name.
	 */
	public static function register_adapter( $provider_key, $class_name ) {
		self::$adapters[ $provider_key ] = $class_name;
	}

	/**
	 * Get email adapter instance.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $config       Optional configuration.
	 * @return NonprofitSuite_Email_Adapter|null Adapter instance or null.
	 */
	public static function get_adapter( $provider_key, $config = array() ) {
		if ( ! isset( self::$adapters[ $provider_key ] ) ) {
			return null;
		}

		$class = self::$adapters[ $provider_key ];

		if ( ! class_exists( $class ) ) {
			return null;
		}

		return new $class( $config );
	}

	/**
	 * Get default email adapter.
	 *
	 * @return NonprofitSuite_Email_Adapter Default adapter.
	 */
	public static function get_default_adapter() {
		$default_provider = get_option( 'ns_default_email_provider', 'smtp' );
		return self::get_adapter( $default_provider );
	}

	/**
	 * Send email using appropriate adapter.
	 *
	 * @param array  $email_data   Email data.
	 * @param string $provider_key Optional. Specific provider to use.
	 * @return array|WP_Error Send result.
	 */
	public static function send( $email_data, $provider_key = null ) {
		if ( null === $provider_key ) {
			$adapter = self::get_default_adapter();
		} else {
			$adapter = self::get_adapter( $provider_key );
		}

		if ( ! $adapter ) {
			return new WP_Error( 'no_adapter', __( 'Email adapter not found', 'nonprofitsuite' ) );
		}

		return $adapter->send( $email_data );
	}
}

// Initialize
NonprofitSuite_Email_Manager::init();
