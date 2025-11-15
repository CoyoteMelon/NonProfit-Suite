<?php
/**
 * Prospect Research Module (Compatibility Layer)
 *
 * This class provides backward compatibility by forwarding method calls to the new
 * modular classes: NonprofitSuite_Prospects and NonprofitSuite_Wealth_Indicators.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      1.0.0
 * @deprecated 2.0.0 Use NonprofitSuite_Prospects or NonprofitSuite_Wealth_Indicators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Prospect_Research {

	/**
	 * Create a prospect
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::create_prospect()
	 * @param int $person_id Person ID
	 * @param array $data Prospect data
	 * @return int|WP_Error Prospect ID or error
	 */
	public static function create_prospect( $person_id, $data ) {
		return NonprofitSuite_Prospects::create_prospect( $person_id, $data );
	}

	/**
	 * Get a single prospect
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::get_prospect()
	 * @param int $id Prospect ID
	 * @return object|WP_Error
	 */
	public static function get_prospect( $id ) {
		return NonprofitSuite_Prospects::get_prospect( $id );
	}

	/**
	 * Get prospects with filters
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::get_prospects()
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_prospects( $args = array() ) {
		return NonprofitSuite_Prospects::get_prospects( $args );
	}

	/**
	 * Update prospect
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::update_prospect()
	 * @param int $id Prospect ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_prospect( $id, $data ) {
		return NonprofitSuite_Prospects::update_prospect( $id, $data );
	}

	/**
	 * Move prospect to a new stage
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::move_to_stage()
	 * @param int $id Prospect ID
	 * @param string $new_stage New stage
	 * @return bool|WP_Error
	 */
	public static function move_to_stage( $id, $new_stage ) {
		return NonprofitSuite_Prospects::move_to_stage( $id, $new_stage );
	}

	/**
	 * Calculate capacity based on wealth indicators
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Wealth_Indicators::calculate_capacity()
	 * @param int $prospect_id Prospect ID
	 * @return float
	 */
	public static function calculate_capacity( $prospect_id ) {
		return NonprofitSuite_Wealth_Indicators::calculate_capacity( $prospect_id );
	}

	/**
	 * Get pipeline summary with counts and totals by stage
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::get_pipeline_summary()
	 * @return array
	 */
	public static function get_pipeline_summary() {
		return NonprofitSuite_Prospects::get_pipeline_summary();
	}

	/**
	 * Get major gift pipeline (A+, A, B rated prospects)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::get_major_gift_pipeline()
	 * @return array|WP_Error
	 */
	public static function get_major_gift_pipeline() {
		return NonprofitSuite_Prospects::get_major_gift_pipeline();
	}

	/**
	 * Add wealth indicator
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Wealth_Indicators::add_indicator()
	 * @param int $prospect_id Prospect ID
	 * @param string $type Indicator type
	 * @param array $data Indicator data
	 * @return int|WP_Error Indicator ID or error
	 */
	public static function add_indicator( $prospect_id, $type, $data ) {
		return NonprofitSuite_Wealth_Indicators::add_indicator( $prospect_id, $type, $data );
	}

	/**
	 * Get all indicators for a prospect
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Wealth_Indicators::get_indicators()
	 * @param int $prospect_id Prospect ID
	 * @return array|WP_Error
	 */
	public static function get_indicators( $prospect_id ) {
		return NonprofitSuite_Wealth_Indicators::get_indicators( $prospect_id );
	}

	/**
	 * Verify an indicator
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Wealth_Indicators::verify_indicator()
	 * @param int $id Indicator ID
	 * @return bool|WP_Error
	 */
	public static function verify_indicator( $id ) {
		return NonprofitSuite_Wealth_Indicators::verify_indicator( $id );
	}

	/**
	 * Get capacity score (0-100) based on indicators
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Wealth_Indicators::get_capacity_score()
	 * @param int $prospect_id Prospect ID
	 * @return int
	 */
	public static function get_capacity_score( $prospect_id ) {
		return NonprofitSuite_Wealth_Indicators::get_capacity_score( $prospect_id );
	}

	/**
	 * Log an interaction with a prospect
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::log_interaction()
	 * @param int $prospect_id Prospect ID
	 * @param array $data Interaction data
	 * @return int|WP_Error Interaction ID or error
	 */
	public static function log_interaction( $prospect_id, $data ) {
		return NonprofitSuite_Prospects::log_interaction( $prospect_id, $data );
	}

	/**
	 * Get interaction history for a prospect
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::get_interaction_history()
	 * @param int $prospect_id Prospect ID
	 * @return array|WP_Error
	 */
	public static function get_interaction_history( $prospect_id ) {
		return NonprofitSuite_Prospects::get_interaction_history( $prospect_id );
	}

	/**
	 * Get last contact date for a prospect
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::get_last_contact_date()
	 * @param int $prospect_id Prospect ID
	 * @return string|null
	 */
	public static function get_last_contact_date( $prospect_id ) {
		return NonprofitSuite_Prospects::get_last_contact_date( $prospect_id );
	}

	/**
	 * Get prospects needing contact (no contact in X days)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::get_prospects_needing_contact()
	 * @param int $days_since Number of days since last contact
	 * @return array|WP_Error
	 */
	public static function get_prospects_needing_contact( $days_since = 30 ) {
		return NonprofitSuite_Prospects::get_prospects_needing_contact( $days_since );
	}

	/**
	 * Screen prospect (placeholder for external API integration)
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::screen_prospect()
	 * @param int $person_id Person ID
	 * @return array|WP_Error
	 */
	public static function screen_prospect( $person_id ) {
		return NonprofitSuite_Prospects::screen_prospect( $person_id );
	}

	/**
	 * Find connections between prospect and board members
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::find_connections()
	 * @param int $prospect_id Prospect ID
	 * @return array
	 */
	public static function find_connections( $prospect_id ) {
		return NonprofitSuite_Prospects::find_connections( $prospect_id );
	}

	/**
	 * Generate briefing PDF for meeting prep
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Prospects::generate_briefing()
	 * @param int $prospect_id Prospect ID
	 * @return string|WP_Error PDF file path or error
	 */
	public static function generate_briefing( $prospect_id ) {
		return NonprofitSuite_Prospects::generate_briefing( $prospect_id );
	}
}
