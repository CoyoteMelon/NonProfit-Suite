<?php
/**
 * Bar Admissions Management Module
 *
 * Track state bar licenses, professional admissions, and compliance.
 * Part of the Multi-State Professional Licensing System.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Bar_Admissions {

	/**
	 * Add bar admission for attorney
	 *
	 * @param int    $attorney_user_id Attorney user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data Bar admission data
	 * @return int|WP_Error License ID or error
	 */
	public static function add_bar_admission( $attorney_user_id, $state_code, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_legal_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_professional_licenses';

		// Check if bar admission already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table}
			WHERE professional_id = %d
			AND professional_type = 'Attorney'
			AND state_code = %s",
			absint( $attorney_user_id ),
			sanitize_text_field( $state_code )
		) );

		$license_data = array(
			'professional_id' => absint( $attorney_user_id ),
			'professional_type' => 'Attorney',
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
			NonprofitSuite_Cache::invalidate_module( 'bar_admissions' );
			return $existing->id;
		} else {
			$wpdb->insert( $table, $license_data );
			NonprofitSuite_Cache::invalidate_module( 'bar_admissions' );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Get attorney for specific state
	 *
	 * @param string $state_code State code (2-letter)
	 * @return object|null Attorney data or null
	 */
	public static function get_attorney_for_state( $state_code ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return null;
		}

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT pl.*, u.display_name, u.user_email, la.firm_name
			FROM {$wpdb->prefix}ns_professional_licenses pl
			JOIN {$wpdb->prefix}ns_legal_access la ON pl.professional_id = la.user_id
			JOIN {$wpdb->users} u ON la.user_id = u.ID
			WHERE pl.state_code = %s
			AND pl.professional_type = 'Attorney'
			AND pl.license_status = 'active'
			AND la.status = 'active'
			AND (pl.expiration_date IS NULL OR pl.expiration_date > CURDATE())
			ORDER BY pl.expiration_date DESC
			LIMIT 1",
			sanitize_text_field( $state_code )
		) );
	}

	/**
	 * Verify attorney has bar admission in state
	 *
	 * @param int    $attorney_user_id Attorney user ID
	 * @param string $state_code State code (2-letter)
	 * @return bool True if admitted and active
	 */
	public static function verify_bar_admission( $attorney_user_id, $state_code ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return false;
		}

		global $wpdb;

		$license = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, professional_id, professional_type, state_code, license_number, license_status,
			        issue_date, expiration_date, verified, verification_date, verification_source, notes
			 FROM {$wpdb->prefix}ns_professional_licenses
			WHERE professional_id = %d
			AND professional_type = 'Attorney'
			AND state_code = %s
			AND license_status = 'active'
			AND (expiration_date IS NULL OR expiration_date > CURDATE())",
			absint( $attorney_user_id ),
			sanitize_text_field( $state_code )
		) );

		return ! empty( $license );
	}

	/**
	 * Get all bar admissions for an attorney
	 *
	 * @param int $attorney_user_id Attorney user ID
	 * @return array Array of bar admission objects
	 */
	public static function get_all_bar_admissions( $attorney_user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT pl.*, so.state_name
			FROM {$wpdb->prefix}ns_professional_licenses pl
			LEFT JOIN {$wpdb->prefix}ns_state_operations so ON pl.state_code = so.state_code
			WHERE pl.professional_id = %d
			AND pl.professional_type = 'Attorney'
			ORDER BY pl.state_code",
			absint( $attorney_user_id )
		) );
	}

	/**
	 * Get expiring bar admissions (alert system)
	 *
	 * @param int $days_ahead Number of days ahead to check
	 * @return array Array of expiring bar admissions
	 */
	public static function get_expiring_bar_admissions( $days_ahead = 60 ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$date = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT pl.*, u.display_name, u.user_email, la.firm_name
			FROM {$wpdb->prefix}ns_professional_licenses pl
			JOIN {$wpdb->prefix}ns_legal_access la ON pl.professional_id = la.user_id
			JOIN {$wpdb->users} u ON la.user_id = u.ID
			WHERE pl.expiration_date <= %s
			AND pl.expiration_date > CURDATE()
			AND pl.license_status = 'active'
			AND pl.professional_type = 'Attorney'
			ORDER BY pl.expiration_date ASC",
			$date
		) );
	}

	/**
	 * Assign attorney to state
	 *
	 * @param int    $attorney_user_id Attorney user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data Assignment data
	 * @return int|WP_Error Assignment ID or error
	 */
	public static function assign_to_state( $attorney_user_id, $state_code, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_legal_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// First verify attorney has bar admission in this state
		if ( ! self::verify_bar_admission( $attorney_user_id, $state_code ) ) {
			return new WP_Error( 'no_bar_admission',
				sprintf( __( 'Attorney must be admitted to the bar in %s before assignment', 'nonprofitsuite' ), $state_code ) );
		}

		$table = $wpdb->prefix . 'ns_state_professional_assignments';

		$result = $wpdb->insert( $table, array(
			'state_code' => sanitize_text_field( $state_code ),
			'chapter_id' => isset( $data['chapter_id'] ) ? absint( $data['chapter_id'] ) : null,
			'professional_id' => absint( $attorney_user_id ),
			'professional_type' => 'Attorney',
			'assignment_type' => isset( $data['assignment_type'] ) ? sanitize_text_field( $data['assignment_type'] ) : 'primary',
			'start_date' => isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : current_time( 'mysql' ),
			'hourly_rate' => isset( $data['hourly_rate'] ) ? floatval( $data['hourly_rate'] ) : null,
			'retainer_amount' => isset( $data['retainer_amount'] ) ? floatval( $data['retainer_amount'] ) : null,
			'services_description' => isset( $data['services_description'] ) ? wp_kses_post( $data['services_description'] ) : null,
			'status' => 'active',
		) );

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to assign attorney to state', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'bar_admissions' );
		return $wpdb->insert_id;
	}
}
