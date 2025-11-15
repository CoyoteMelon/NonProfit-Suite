<?php
/**
 * Security Helper Class
 *
 * Provides centralized capability checking and permission validation.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Security {

	/**
	 * Check if current user has capability.
	 *
	 * @param string $capability Required capability.
	 * @param string $context Context for error message.
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function check_capability( $capability, $context = '' ) {
		if ( ! current_user_can( $capability ) ) {
			$message = $context
				? sprintf( __( 'You do not have permission to %s.', 'nonprofitsuite' ), $context )
				: __( 'You do not have sufficient permissions.', 'nonprofitsuite' );

			return new WP_Error( 'permission_denied', $message );
		}

		return true;
	}

	/**
	 * Check multiple capabilities (user needs ALL of them).
	 *
	 * @param array  $capabilities Array of required capabilities.
	 * @param string $context Context for error message.
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function check_capabilities( $capabilities, $context = '' ) {
		foreach ( $capabilities as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				$message = $context
					? sprintf( __( 'You do not have permission to %s.', 'nonprofitsuite' ), $context )
					: __( 'You do not have sufficient permissions.', 'nonprofitsuite' );

				return new WP_Error( 'permission_denied', $message );
			}
		}

		return true;
	}

	/**
	 * Check if current user can manage NonprofitSuite.
	 *
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function can_manage_nonprofitsuite() {
		return self::check_capability( 'manage_options', 'manage NonprofitSuite settings' );
	}

	/**
	 * Check if current user can access CPA features.
	 *
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function can_manage_cpa_access() {
		return self::check_capability( 'manage_options', 'manage CPA access' );
	}

	/**
	 * Check if current user can access Legal features.
	 *
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function can_manage_legal_access() {
		return self::check_capability( 'manage_options', 'manage legal counsel access' );
	}

	/**
	 * Check if current user can manage prospects.
	 *
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function can_manage_prospects() {
		return self::check_capability( 'edit_posts', 'manage prospects' );
	}

	/**
	 * Check if current user can manage donors.
	 *
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function can_manage_donors() {
		return self::check_capability( 'edit_posts', 'manage donors' );
	}

	/**
	 * Check if current user can manage volunteers.
	 *
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function can_manage_volunteers() {
		return self::check_capability( 'edit_posts', 'manage volunteers' );
	}

	/**
	 * Check if current user can manage financial data.
	 *
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function can_manage_finances() {
		return self::check_capability( 'manage_options', 'manage financial data' );
	}

	/**
	 * Verify nonce for AJAX requests.
	 *
	 * @param string $nonce Nonce to verify.
	 * @param string $action Action name.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function verify_nonce( $nonce, $action = 'nonprofitsuite_nonce' ) {
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed. Please refresh and try again.', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Check if current user owns or can edit a record.
	 *
	 * @param int    $user_id Owner user ID.
	 * @param string $capability Required capability to override ownership.
	 * @return bool True if user owns record or has capability.
	 */
	public static function can_edit_record( $user_id, $capability = 'edit_others_posts' ) {
		$current_user_id = get_current_user_id();

		// User owns the record
		if ( $current_user_id === absint( $user_id ) ) {
			return true;
		}

		// User has elevated permissions
		if ( current_user_can( $capability ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate and sanitize file path.
	 *
	 * Ensures file path is within WordPress uploads directory and prevents
	 * directory traversal attacks.
	 *
	 * @param string $file_path File path to validate.
	 * @return string|WP_Error Sanitized path or error.
	 */
	public static function validate_file_path( $file_path ) {
		// Get uploads directory
		$upload_dir = wp_upload_dir();
		$base_dir = wp_normalize_path( $upload_dir['basedir'] );

		// Normalize and resolve the file path
		$normalized_path = wp_normalize_path( $file_path );

		// If path is relative, make it absolute
		if ( ! path_is_absolute( $normalized_path ) ) {
			$normalized_path = wp_normalize_path( $base_dir . '/' . $normalized_path );
		}

		// Resolve any ../ or ./ in the path
		if ( file_exists( $normalized_path ) ) {
			$resolved_path = wp_normalize_path( realpath( $normalized_path ) );
		} else {
			// For non-existent files, manually resolve the path
			$resolved_path = $normalized_path;
		}

		// Check if path is within uploads directory
		if ( strpos( $resolved_path, $base_dir ) !== 0 ) {
			return new WP_Error(
				'invalid_file_path',
				__( 'File path must be within the WordPress uploads directory.', 'nonprofitsuite' )
			);
		}

		// Check for dangerous patterns
		if ( preg_match( '#\.\.[/\\\\]#', $file_path ) ) {
			return new WP_Error(
				'dangerous_file_path',
				__( 'File path contains dangerous patterns.', 'nonprofitsuite' )
			);
		}

		return $resolved_path;
	}
}
