<?php
/**
 * License Management Class
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Handles Pro license validation and management.
 */
class NonprofitSuite_License {

	/**
	 * License server URL.
	 */
	const LICENSE_SERVER = 'https://silverhost.net/api/licenses/';

	/**
	 * Check if Pro license is active.
	 *
	 * @return bool True if Pro license is active.
	 */
	public static function is_pro_active() {
		$license_key = get_option( 'nonprofitsuite_license_key', '' );
		$license_status = get_option( 'nonprofitsuite_license_status', 'invalid' );
		$last_check = get_option( 'nonprofitsuite_license_last_check', 0 );

		// For development, allow a special development key
		if ( defined( 'NONPROFITSUITE_DEV_MODE' ) && NONPROFITSUITE_DEV_MODE ) {
			return true;
		}

		// Check cache (24 hours)
		if ( $license_status === 'active' && ( time() - $last_check ) < DAY_IN_SECONDS ) {
			return true;
		}

		// Validate license if we have a key
		if ( ! empty( $license_key ) ) {
			return self::validate_license( $license_key );
		}

		return false;
	}

	/**
	 * Validate license key with server.
	 *
	 * @param string $license_key License key to validate.
	 * @return bool True if valid and active.
	 */
	public static function validate_license( $license_key ) {
		$response = wp_remote_post( self::LICENSE_SERVER . 'validate', array(
			'body' => array(
				'license_key' => $license_key,
				'site_url' => get_site_url(),
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['status'] ) && $body['status'] === 'active' ) {
			update_option( 'nonprofitsuite_license_status', 'active' );
			update_option( 'nonprofitsuite_license_last_check', time() );
			update_option( 'nonprofitsuite_license_expires', $body['expires'] ?? '' );
			return true;
		}

		update_option( 'nonprofitsuite_license_status', 'invalid' );
		return false;
	}

	/**
	 * Activate license key.
	 *
	 * @param string $license_key License key to activate.
	 * @return array Result with success status and message.
	 */
	public static function activate_license( $license_key ) {
		$response = wp_remote_post( self::LICENSE_SERVER . 'activate', array(
			'body' => array(
				'license_key' => $license_key,
				'site_url' => get_site_url(),
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not connect to license server.', 'nonprofitsuite' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['success'] ) && $body['success'] ) {
			update_option( 'nonprofitsuite_license_key', $license_key );
			update_option( 'nonprofitsuite_license_status', 'active' );
			update_option( 'nonprofitsuite_license_last_check', time() );
			update_option( 'nonprofitsuite_license_expires', $body['expires'] ?? '' );

			return array(
				'success' => true,
				'message' => __( 'License activated successfully!', 'nonprofitsuite' ),
			);
		}

		return array(
			'success' => false,
			'message' => $body['message'] ?? __( 'Invalid license key.', 'nonprofitsuite' ),
		);
	}

	/**
	 * Deactivate license key.
	 *
	 * @return bool True on success.
	 */
	public static function deactivate_license() {
		$license_key = get_option( 'nonprofitsuite_license_key', '' );

		if ( empty( $license_key ) ) {
			return true;
		}

		$response = wp_remote_post( self::LICENSE_SERVER . 'deactivate', array(
			'body' => array(
				'license_key' => $license_key,
				'site_url' => get_site_url(),
			),
			'timeout' => 15,
		) );

		// Clear local license data regardless of server response
		delete_option( 'nonprofitsuite_license_key' );
		delete_option( 'nonprofitsuite_license_status' );
		delete_option( 'nonprofitsuite_license_last_check' );
		delete_option( 'nonprofitsuite_license_expires' );

		return true;
	}

	/**
	 * Get license status information.
	 *
	 * @return array License information.
	 */
	public static function get_license_info() {
		return array(
			'status' => get_option( 'nonprofitsuite_license_status', 'invalid' ),
			'key' => get_option( 'nonprofitsuite_license_key', '' ),
			'expires' => get_option( 'nonprofitsuite_license_expires', '' ),
			'last_check' => get_option( 'nonprofitsuite_license_last_check', 0 ),
		);
	}

	/**
	 * Check if license is in grace period.
	 *
	 * @return bool True if in grace period.
	 */
	public static function is_in_grace_period() {
		$expires = get_option( 'nonprofitsuite_license_expires', '' );

		if ( empty( $expires ) ) {
			return false;
		}

		$expire_time = strtotime( $expires );
		$grace_end = $expire_time + ( 30 * DAY_IN_SECONDS ); // 30 day grace period

		return time() < $grace_end;
	}

	/**
	 * Show Pro upgrade notice.
	 *
	 * @param string $feature Feature name.
	 * @return string HTML notice.
	 */
	public static function get_upgrade_notice( $feature = '' ) {
		$message = $feature
			? sprintf( __( '%s is a Pro feature.', 'nonprofitsuite' ), $feature )
			: __( 'This is a Pro feature.', 'nonprofitsuite' );

		return sprintf(
			'<div class="ns-pro-notice">
				<h3>%s</h3>
				<p>%s</p>
				<a href="%s" class="ns-button ns-button-primary">%s</a>
			</div>',
			esc_html( $message ),
			esc_html__( 'Upgrade to NonprofitSuite Pro for just $20/year to unlock all 37 modules.', 'nonprofitsuite' ),
			esc_url( admin_url( 'admin.php?page=nonprofitsuite-settings&tab=license' ) ),
			esc_html__( 'Upgrade to Pro', 'nonprofitsuite' )
		);
	}
}
