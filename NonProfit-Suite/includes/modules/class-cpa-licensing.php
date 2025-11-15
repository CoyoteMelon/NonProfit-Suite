<?php
/**
 * CPA Licensing Module
 *
 * Track state CPA licenses, verify credentials, manage expiration,
 * and assign CPAs to state operations.
 * Part of the Multi-State Professional Licensing System.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_CPA_Licensing {

	/**
	 * Add state license for CPA
	 *
	 * @param int    $cpa_user_id CPA user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data License data
	 * @return int|WP_Error License ID or error
	 */
	public static function add_state_license( $cpa_user_id, $state_code, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_cpa_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_professional_licenses';

		// Check if license already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table}
			WHERE professional_id = %d
			AND professional_type = 'CPA'
			AND state_code = %s",
			absint( $cpa_user_id ),
			sanitize_text_field( $state_code )
		) );

		$license_data = array(
			'professional_id' => absint( $cpa_user_id ),
			'professional_type' => 'CPA',
			'state_code' => sanitize_text_field( $state_code ),
			'license_number' => isset( $data['license_number'] ) ? sanitize_text_field( $data['license_number'] ) : null,
			'license_status' => isset( $data['license_status'] ) ? sanitize_text_field( $data['license_status'] ) : 'active',
			'issue_date' => isset( $data['issue_date'] ) ? sanitize_text_field( $data['issue_date'] ) : null,
			'expiration_date' => isset( $data['expiration_date'] ) ? sanitize_text_field( $data['expiration_date'] ) : null,
			'verified' => isset( $data['verified'] ) ? 1 : 0,
			'verification_date' => isset( $data['verified'] ) ? current_time( 'mysql' ) : null,
			'verification_source' => isset( $data['verification_source'] ) ? sanitize_text_field( $data['verification_source'] ) : null,
			'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
		);

		if ( $existing ) {
			$wpdb->update( $table, $license_data, array( 'id' => $existing->id ) );
			NonprofitSuite_Cache::invalidate_module( 'cpa_licensing' );
			return $existing->id;
		} else {
			$wpdb->insert( $table, $license_data );
			NonprofitSuite_Cache::invalidate_module( 'cpa_licensing' );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Get CPA for specific state
	 *
	 * @param string $state_code State code (2-letter)
	 * @return object|null CPA data or null
	 */
	public static function get_cpa_for_state( $state_code ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return null;
		}

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT pl.*, u.display_name, u.user_email, ca.firm_name
			FROM {$wpdb->prefix}ns_professional_licenses pl
			JOIN {$wpdb->prefix}ns_cpa_access ca ON pl.professional_id = ca.user_id
			JOIN {$wpdb->users} u ON ca.user_id = u.ID
			WHERE pl.state_code = %s
			AND pl.professional_type = 'CPA'
			AND pl.license_status = 'active'
			AND ca.status = 'active'
			AND (pl.expiration_date IS NULL OR pl.expiration_date > CURDATE())
			ORDER BY pl.expiration_date DESC
			LIMIT 1",
			sanitize_text_field( $state_code )
		) );
	}

	/**
	 * Verify CPA is licensed in state
	 *
	 * @param int    $cpa_user_id CPA user ID
	 * @param string $state_code State code (2-letter)
	 * @return bool True if licensed and active
	 */
	public static function verify_state_license( $cpa_user_id, $state_code ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return false;
		}

		global $wpdb;

		$license = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, professional_id, professional_type, state_code, license_number, license_status,
			        issue_date, expiration_date, verified, verification_date, verification_source, notes
			 FROM {$wpdb->prefix}ns_professional_licenses
			WHERE professional_id = %d
			AND professional_type = 'CPA'
			AND state_code = %s
			AND license_status = 'active'
			AND (expiration_date IS NULL OR expiration_date > CURDATE())",
			absint( $cpa_user_id ),
			sanitize_text_field( $state_code )
		) );

		return ! empty( $license );
	}

	/**
	 * Get all state licenses for a CPA
	 *
	 * @param int $cpa_user_id CPA user ID
	 * @return array Array of license objects
	 */
	public static function get_all_state_licenses( $cpa_user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT pl.*, so.state_name
			FROM {$wpdb->prefix}ns_professional_licenses pl
			LEFT JOIN {$wpdb->prefix}ns_state_operations so ON pl.state_code = so.state_code
			WHERE pl.professional_id = %d
			AND pl.professional_type = 'CPA'
			ORDER BY pl.state_code",
			absint( $cpa_user_id )
		) );
	}

	/**
	 * Get expiring licenses (alert system)
	 *
	 * @param int $days_ahead Number of days ahead to check
	 * @return array Array of expiring licenses
	 */
	public static function get_expiring_licenses( $days_ahead = 60 ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$date = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT pl.*, u.display_name, u.user_email, ca.firm_name
			FROM {$wpdb->prefix}ns_professional_licenses pl
			JOIN {$wpdb->prefix}ns_cpa_access ca ON pl.professional_id = ca.user_id
			JOIN {$wpdb->users} u ON ca.user_id = u.ID
			WHERE pl.expiration_date <= %s
			AND pl.expiration_date > CURDATE()
			AND pl.license_status = 'active'
			AND pl.professional_type = 'CPA'
			ORDER BY pl.expiration_date ASC",
			$date
		) );
	}

	/**
	 * Assign CPA to state
	 *
	 * @param int    $cpa_user_id CPA user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data Assignment data
	 * @return int|WP_Error Assignment ID or error
	 */
	public static function assign_to_state( $cpa_user_id, $state_code, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_cpa_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// First verify CPA has license in this state
		if ( ! self::verify_state_license( $cpa_user_id, $state_code ) ) {
			return new WP_Error( 'no_license',
				sprintf( __( 'CPA must be licensed in %s before assignment', 'nonprofitsuite' ), $state_code ) );
		}

		$table = $wpdb->prefix . 'ns_state_professional_assignments';

		$result = $wpdb->insert( $table, array(
			'state_code' => sanitize_text_field( $state_code ),
			'chapter_id' => isset( $data['chapter_id'] ) ? absint( $data['chapter_id'] ) : null,
			'professional_id' => absint( $cpa_user_id ),
			'professional_type' => 'CPA',
			'assignment_type' => isset( $data['assignment_type'] ) ? sanitize_text_field( $data['assignment_type'] ) : 'primary',
			'start_date' => isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : current_time( 'mysql' ),
			'hourly_rate' => isset( $data['hourly_rate'] ) ? floatval( $data['hourly_rate'] ) : null,
			'retainer_amount' => isset( $data['retainer_amount'] ) ? floatval( $data['retainer_amount'] ) : null,
			'services_description' => isset( $data['services_description'] ) ? wp_kses_post( $data['services_description'] ) : null,
			'status' => 'active',
		) );

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to assign CPA to state', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'cpa_licensing' );
		return $wpdb->insert_id;
	}
}
