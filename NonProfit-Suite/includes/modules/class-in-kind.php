<?php
/**
 * In-Kind Donations & Asset Management Module (Compatibility Layer)
 *
 * This class provides backward compatibility by forwarding method calls to the new
 * modular classes: NonprofitSuite_InKind_Donations and NonprofitSuite_Asset_Management.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      1.0.0
 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations or NonprofitSuite_Asset_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_InKind {

	/**
	 * Record an in-kind donation
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations::record_donation()
	 * @param array $data Donation data
	 * @return int|WP_Error Donation ID or error
	 */
	public static function record_donation( $data ) {
		return NonprofitSuite_InKind_Donations::record_donation( $data );
	}

	/**
	 * Get donations with filters
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations::get_donations()
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_donations( $args = array() ) {
		return NonprofitSuite_InKind_Donations::get_donations( $args );
	}

	/**
	 * Calculate annual in-kind value
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations::calculate_annual_in_kind_value()
	 * @param int $year Year
	 * @return float
	 */
	public static function calculate_annual_in_kind_value( $year ) {
		return NonprofitSuite_InKind_Donations::calculate_annual_in_kind_value( $year );
	}

	/**
	 * Generate tax receipt (acknowledgment letter)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations::generate_tax_receipt()
	 * @param int $id Donation ID
	 * @return string|WP_Error Receipt content or error
	 */
	public static function generate_tax_receipt( $id ) {
		return NonprofitSuite_InKind_Donations::generate_tax_receipt( $id );
	}

	/**
	 * Get donations by category for year (Form 990)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations::get_donations_by_category()
	 * @param int $year Year
	 * @return array
	 */
	public static function get_donations_by_category( $year ) {
		return NonprofitSuite_InKind_Donations::get_donations_by_category( $year );
	}

	/**
	 * Flag items needing appraisal (>$5000)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations::flag_for_appraisal()
	 * @param int $id Donation ID
	 * @return bool|WP_Error
	 */
	public static function flag_for_appraisal( $id ) {
		return NonprofitSuite_InKind_Donations::flag_for_appraisal( $id );
	}

	/**
	 * Convert in-kind donation to asset
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::convert_donation_to_asset()
	 * @param int $donation_id Donation ID
	 * @return int|WP_Error Asset ID or error
	 */
	public static function convert_to_asset( $donation_id ) {
		return NonprofitSuite_Asset_Management::convert_donation_to_asset( $donation_id );
	}

	/**
	 * Create an asset
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::create_asset()
	 * @param array $data Asset data
	 * @return int|WP_Error Asset ID or error
	 */
	public static function create_asset( $data ) {
		return NonprofitSuite_Asset_Management::create_asset( $data );
	}

	/**
	 * Get a single asset
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::get_asset()
	 * @param int $id Asset ID
	 * @return object|WP_Error
	 */
	public static function get_asset( $id ) {
		return NonprofitSuite_Asset_Management::get_asset( $id );
	}

	/**
	 * Get assets with filters
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::get_assets()
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_assets( $args = array() ) {
		return NonprofitSuite_Asset_Management::get_assets( $args );
	}

	/**
	 * Update asset
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::update_asset()
	 * @param int $id Asset ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_asset( $id, $data ) {
		return NonprofitSuite_Asset_Management::update_asset( $id, $data );
	}

	/**
	 * Calculate depreciation for an asset
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::depreciate_asset()
	 * @param int $id Asset ID
	 * @return float|WP_Error Depreciation amount or error
	 */
	public static function depreciate_asset( $id ) {
		return NonprofitSuite_Asset_Management::depreciate_asset( $id );
	}

	/**
	 * Calculate current value after depreciation
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::calculate_current_value()
	 * @param int $id Asset ID
	 * @return float
	 */
	public static function calculate_current_value( $id ) {
		return NonprofitSuite_Asset_Management::calculate_current_value( $id );
	}

	/**
	 * Dispose of an asset
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::dispose_asset()
	 * @param int $id Asset ID
	 * @param string $method Disposal method
	 * @param float $value Disposal value
	 * @return bool|WP_Error
	 */
	public static function dispose_asset( $id, $method, $value = 0 ) {
		return NonprofitSuite_Asset_Management::dispose_asset( $id, $method, $value );
	}

	/**
	 * Get asset register (complete inventory list)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::get_asset_register()
	 * @return array|WP_Error
	 */
	public static function get_asset_register() {
		return NonprofitSuite_Asset_Management::get_asset_register();
	}

	/**
	 * Get depreciation schedule
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::get_depreciation_schedule()
	 * @return array
	 */
	public static function get_depreciation_schedule() {
		return NonprofitSuite_Asset_Management::get_depreciation_schedule();
	}

	/**
	 * Assign asset to person
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::assign_asset()
	 * @param int $id Asset ID
	 * @param int $person_id Person ID
	 * @return bool|WP_Error
	 */
	public static function assign_asset( $id, $person_id ) {
		return NonprofitSuite_Asset_Management::assign_asset( $id, $person_id );
	}

	/**
	 * Transfer asset to new location
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::transfer_asset()
	 * @param int $id Asset ID
	 * @param string $new_location New location
	 * @return bool|WP_Error
	 */
	public static function transfer_asset( $id, $new_location ) {
		return NonprofitSuite_Asset_Management::transfer_asset( $id, $new_location );
	}

	/**
	 * Get Form 990 Schedule M data (non-cash contributions)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_InKind_Donations::get_form_990_schedule_m_data()
	 * @param int $year Year
	 * @return array
	 */
	public static function get_form_990_schedule_m_data( $year ) {
		return NonprofitSuite_InKind_Donations::get_form_990_schedule_m_data( $year );
	}

	/**
	 * Get asset summary by category
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::get_asset_summary()
	 * @return array
	 */
	public static function get_asset_summary() {
		return NonprofitSuite_Asset_Management::get_asset_summary();
	}

	/**
	 * Get retired/disposed assets for year
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Asset_Management::get_retired_assets()
	 * @param int $year Year
	 * @return array|WP_Error
	 */
	public static function get_retired_assets( $year ) {
		return NonprofitSuite_Asset_Management::get_retired_assets( $year );
	}
}
