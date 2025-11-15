<?php
/**
 * Legal Counsel Dashboard Module (Compatibility Layer)
 *
 * This class provides backward compatibility by forwarding method calls to the new
 * modular classes: NonprofitSuite_Legal_Access, NonprofitSuite_Legal_Matters,
 * and NonprofitSuite_Bar_Admissions.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      1.0.0
 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Access, _Matters, or _Bar_Admissions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Legal_Counsel {

	/**
	 * Grant legal counsel access
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Access::grant_access()
	 * @param array $data Access data
	 * @return int|WP_Error Access ID or error
	 */
	public static function grant_access( $data ) {
		return NonprofitSuite_Legal_Access::grant_access( $data );
	}

	/**
	 * Get legal access records
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Access::get_access_records()
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of access records or error
	 */
	public static function get_access_records( $args = array() ) {
		return NonprofitSuite_Legal_Access::get_access_records( $args );
	}

	/**
	 * Revoke legal access
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Access::revoke_access()
	 * @param int $access_id Access ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function revoke_access( $access_id ) {
		return NonprofitSuite_Legal_Access::revoke_access( $access_id );
	}

	/**
	 * Check if user has legal counsel access
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Access::has_access()
	 * @param int $user_id User ID
	 * @return bool True if has active access
	 */
	public static function has_access( $user_id ) {
		return NonprofitSuite_Legal_Access::has_access( $user_id );
	}

	/**
	 * Create legal matter
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Matters::create_matter()
	 * @param array $data Matter data
	 * @return int|WP_Error Matter ID or error
	 */
	public static function create_matter( $data ) {
		return NonprofitSuite_Legal_Matters::create_matter( $data );
	}

	/**
	 * Get legal matters
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Matters::get_matters()
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of matters or error
	 */
	public static function get_matters( $args = array() ) {
		return NonprofitSuite_Legal_Matters::get_matters( $args );
	}

	/**
	 * Update legal matter
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Matters::update_matter()
	 * @param int   $matter_id Matter ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_matter( $matter_id, $data ) {
		return NonprofitSuite_Legal_Matters::update_matter( $matter_id, $data );
	}

	/**
	 * Get dashboard data for legal counsel portal
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Matters::get_dashboard_data()
	 * @param int $user_id Attorney user ID
	 * @return array Dashboard data
	 */
	public static function get_dashboard_data( $user_id ) {
		return NonprofitSuite_Legal_Matters::get_dashboard_data( $user_id );
	}

	/**
	 * Get matters by type
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Matters::get_matters_by_type()
	 * @param int    $access_id Access ID
	 * @param string $matter_type Matter type
	 * @return array Array of matters
	 */
	public static function get_matters_by_type( $access_id, $matter_type ) {
		return NonprofitSuite_Legal_Matters::get_matters_by_type( $access_id, $matter_type );
	}

	/**
	 * Get high priority matters
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Matters::get_high_priority_matters()
	 * @param int $access_id Access ID
	 * @return array Array of high priority matters
	 */
	public static function get_high_priority_matters( $access_id ) {
		return NonprofitSuite_Legal_Matters::get_high_priority_matters( $access_id );
	}

	/**
	 * Add bar admission for attorney
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Bar_Admissions::add_bar_admission()
	 * @param int    $attorney_user_id Attorney user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data Bar admission data
	 * @return int|WP_Error License ID or error
	 */
	public static function add_bar_admission( $attorney_user_id, $state_code, $data ) {
		return NonprofitSuite_Bar_Admissions::add_bar_admission( $attorney_user_id, $state_code, $data );
	}

	/**
	 * Get attorney for specific state
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Bar_Admissions::get_attorney_for_state()
	 * @param string $state_code State code (2-letter)
	 * @return object|null Attorney data or null
	 */
	public static function get_attorney_for_state( $state_code ) {
		return NonprofitSuite_Bar_Admissions::get_attorney_for_state( $state_code );
	}

	/**
	 * Verify attorney has bar admission in state
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Bar_Admissions::verify_bar_admission()
	 * @param int    $attorney_user_id Attorney user ID
	 * @param string $state_code State code (2-letter)
	 * @return bool True if admitted and active
	 */
	public static function verify_bar_admission( $attorney_user_id, $state_code ) {
		return NonprofitSuite_Bar_Admissions::verify_bar_admission( $attorney_user_id, $state_code );
	}

	/**
	 * Get all bar admissions for an attorney
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Bar_Admissions::get_all_bar_admissions()
	 * @param int $attorney_user_id Attorney user ID
	 * @return array Array of bar admission objects
	 */
	public static function get_all_bar_admissions( $attorney_user_id ) {
		return NonprofitSuite_Bar_Admissions::get_all_bar_admissions( $attorney_user_id );
	}

	/**
	 * Get expiring bar admissions (alert system)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Bar_Admissions::get_expiring_bar_admissions()
	 * @param int $days_ahead Number of days ahead to check
	 * @return array Array of expiring bar admissions
	 */
	public static function get_expiring_bar_admissions( $days_ahead = 60 ) {
		return NonprofitSuite_Bar_Admissions::get_expiring_bar_admissions( $days_ahead );
	}

	/**
	 * Assign attorney to state
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Bar_Admissions::assign_to_state()
	 * @param int    $attorney_user_id Attorney user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data Assignment data
	 * @return int|WP_Error Assignment ID or error
	 */
	public static function assign_to_state( $attorney_user_id, $state_code, $data ) {
		return NonprofitSuite_Bar_Admissions::assign_to_state( $attorney_user_id, $state_code, $data );
	}

	/**
	 * Get all attorneys
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Legal_Access::get_attorneys()
	 * @return array Array of attorney access records
	 */
	public static function get_attorneys() {
		return NonprofitSuite_Legal_Access::get_attorneys();
	}
}
