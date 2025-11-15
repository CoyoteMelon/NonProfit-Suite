<?php
/**
 * CPA Dashboard Module (Compatibility Layer)
 *
 * This class provides backward compatibility by forwarding method calls to the new
 * modular classes: NonprofitSuite_CPA_Access, NonprofitSuite_CPA_Files,
 * and NonprofitSuite_CPA_Licensing.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      1.0.0
 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Access, _Files, or _Licensing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_CPA_Dashboard {

	/**
	 * Grant CPA access
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Access::grant_access()
	 * @param array $data Access data
	 * @return int|WP_Error Access ID or error
	 */
	public static function grant_access( $data ) {
		return NonprofitSuite_CPA_Access::grant_access( $data );
	}

	/**
	 * Get CPA access records
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Access::get_access_records()
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of access records or error
	 */
	public static function get_access_records( $args = array() ) {
		return NonprofitSuite_CPA_Access::get_access_records( $args );
	}

	/**
	 * Revoke CPA access
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Access::revoke_access()
	 * @param int $access_id Access ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function revoke_access( $access_id ) {
		return NonprofitSuite_CPA_Access::revoke_access( $access_id );
	}

	/**
	 * Check if user has CPA access
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Access::has_access()
	 * @param int $user_id User ID
	 * @return bool True if has active access
	 */
	public static function has_access( $user_id ) {
		return NonprofitSuite_CPA_Access::has_access( $user_id );
	}

	/**
	 * Share file with CPA
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Files::share_file()
	 * @param array $data File sharing data
	 * @return int|WP_Error Shared file ID or error
	 */
	public static function share_file( $data ) {
		return NonprofitSuite_CPA_Files::share_file( $data );
	}

	/**
	 * Get shared files
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Files::get_shared_files()
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of shared files or error
	 */
	public static function get_shared_files( $args = array() ) {
		return NonprofitSuite_CPA_Files::get_shared_files( $args );
	}

	/**
	 * Record file download
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Files::record_download()
	 * @param int $file_id File ID
	 * @param int $user_id User ID who downloaded
	 * @return bool True on success
	 */
	public static function record_download( $file_id, $user_id ) {
		return NonprofitSuite_CPA_Files::record_download( $file_id, $user_id );
	}

	/**
	 * Delete shared file
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Files::delete_shared_file()
	 * @param int $file_id File ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function delete_shared_file( $file_id ) {
		return NonprofitSuite_CPA_Files::delete_shared_file( $file_id );
	}

	/**
	 * Get dashboard data for CPA portal
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Access::get_dashboard_data()
	 * @param int $user_id CPA user ID
	 * @return array Dashboard data
	 */
	public static function get_dashboard_data( $user_id ) {
		return NonprofitSuite_CPA_Access::get_dashboard_data( $user_id );
	}

	/**
	 * Get files by category
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Files::get_files_by_category()
	 * @param int    $access_id Access ID
	 * @param string $category File category
	 * @return array Array of files
	 */
	public static function get_files_by_category( $access_id, $category ) {
		return NonprofitSuite_CPA_Files::get_files_by_category( $access_id, $category );
	}

	/**
	 * Add state license for CPA
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Licensing::add_state_license()
	 * @param int    $cpa_user_id CPA user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data License data
	 * @return int|WP_Error License ID or error
	 */
	public static function add_state_license( $cpa_user_id, $state_code, $data ) {
		return NonprofitSuite_CPA_Licensing::add_state_license( $cpa_user_id, $state_code, $data );
	}

	/**
	 * Get CPA for specific state
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Licensing::get_cpa_for_state()
	 * @param string $state_code State code (2-letter)
	 * @return object|null CPA data or null
	 */
	public static function get_cpa_for_state( $state_code ) {
		return NonprofitSuite_CPA_Licensing::get_cpa_for_state( $state_code );
	}

	/**
	 * Verify CPA is licensed in state
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Licensing::verify_state_license()
	 * @param int    $cpa_user_id CPA user ID
	 * @param string $state_code State code (2-letter)
	 * @return bool True if licensed and active
	 */
	public static function verify_state_license( $cpa_user_id, $state_code ) {
		return NonprofitSuite_CPA_Licensing::verify_state_license( $cpa_user_id, $state_code );
	}

	/**
	 * Get all state licenses for a CPA
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Licensing::get_all_state_licenses()
	 * @param int $cpa_user_id CPA user ID
	 * @return array Array of license objects
	 */
	public static function get_all_state_licenses( $cpa_user_id ) {
		return NonprofitSuite_CPA_Licensing::get_all_state_licenses( $cpa_user_id );
	}

	/**
	 * Get expiring licenses (alert system)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Licensing::get_expiring_licenses()
	 * @param int $days_ahead Number of days ahead to check
	 * @return array Array of expiring licenses
	 */
	public static function get_expiring_licenses( $days_ahead = 60 ) {
		return NonprofitSuite_CPA_Licensing::get_expiring_licenses( $days_ahead );
	}

	/**
	 * Assign CPA to state
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Licensing::assign_to_state()
	 * @param int    $cpa_user_id CPA user ID
	 * @param string $state_code State code (2-letter)
	 * @param array  $data Assignment data
	 * @return int|WP_Error Assignment ID or error
	 */
	public static function assign_to_state( $cpa_user_id, $state_code, $data ) {
		return NonprofitSuite_CPA_Licensing::assign_to_state( $cpa_user_id, $state_code, $data );
	}

	/**
	 * Get all CPA users
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_CPA_Access::get_cpa_users()
	 * @return array Array of CPA access records
	 */
	public static function get_cpa_users() {
		return NonprofitSuite_CPA_Access::get_cpa_users();
	}
}
