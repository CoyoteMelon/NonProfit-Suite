<?php
/**
 * Capability Manager
 *
 * Manages custom capabilities for sensitive features like
 * Background Checks and Wealth Research.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 * @since 1.0.0
 */

namespace NonprofitSuite\Helpers;

class NS_Capability_Manager {

	/**
	 * Custom capabilities for sensitive features
	 *
	 * @var array
	 */
	private static $custom_capabilities = array(
		// Background Check capabilities
		'view_background_checks'     => 'View background check results',
		'manage_background_checks'   => 'Request and manage background checks',
		'configure_background_checks' => 'Configure background check settings',

		// Wealth Research capabilities
		'view_wealth_research'       => 'View wealth research data',
		'manage_wealth_research'     => 'Conduct wealth research',
		'configure_wealth_research'  => 'Configure wealth research settings',
	);

	/**
	 * Register custom capabilities
	 *
	 * Called on plugin activation.
	 */
	public static function register_capabilities() {
		// Get administrator role
		$admin_role = get_role( 'administrator' );

		if ( ! $admin_role ) {
			return;
		}

		// Add all custom capabilities to administrator role
		foreach ( array_keys( self::$custom_capabilities ) as $cap ) {
			$admin_role->add_cap( $cap );
		}

		// Log capability registration
		error_log( 'NonprofitSuite: Registered custom capabilities for sensitive features' );
	}

	/**
	 * Remove custom capabilities
	 *
	 * Called on plugin deactivation.
	 */
	public static function remove_capabilities() {
		// Get all roles
		$roles = wp_roles()->roles;

		foreach ( $roles as $role_name => $role_info ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			// Remove all custom capabilities
			foreach ( array_keys( self::$custom_capabilities ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		error_log( 'NonprofitSuite: Removed custom capabilities' );
	}

	/**
	 * Check if Background Checks feature is enabled
	 *
	 * @return bool
	 */
	public static function is_background_checks_enabled() {
		$settings = get_option( 'ns_feature_flags', array() );
		return ! isset( $settings['disable_background_checks'] ) || ! $settings['disable_background_checks'];
	}

	/**
	 * Check if Wealth Research feature is enabled
	 *
	 * @return bool
	 */
	public static function is_wealth_research_enabled() {
		$settings = get_option( 'ns_feature_flags', array() );
		return ! isset( $settings['disable_wealth_research'] ) || ! $settings['disable_wealth_research'];
	}

	/**
	 * Get all custom capabilities
	 *
	 * @return array
	 */
	public static function get_custom_capabilities() {
		return self::$custom_capabilities;
	}

	/**
	 * Grant capability to a role
	 *
	 * @param string $role_name Role name
	 * @param string $capability Capability to grant
	 * @return bool Success
	 */
	public static function grant_capability( $role_name, $capability ) {
		if ( ! array_key_exists( $capability, self::$custom_capabilities ) ) {
			return false;
		}

		$role = get_role( $role_name );

		if ( ! $role ) {
			return false;
		}

		$role->add_cap( $capability );
		return true;
	}

	/**
	 * Revoke capability from a role
	 *
	 * @param string $role_name Role name
	 * @param string $capability Capability to revoke
	 * @return bool Success
	 */
	public static function revoke_capability( $role_name, $capability ) {
		if ( ! array_key_exists( $capability, self::$custom_capabilities ) ) {
			return false;
		}

		$role = get_role( $role_name );

		if ( ! $role ) {
			return false;
		}

		$role->remove_cap( $capability );
		return true;
	}
}
