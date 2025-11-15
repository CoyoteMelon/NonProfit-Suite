<?php
/**
 * Advocacy Module - Policy & Campaigns (Compatibility Layer)
 *
 * This class provides backward compatibility by forwarding method calls to the new
 * modular classes: NonprofitSuite_Advocacy_Issues, NonprofitSuite_Advocacy_Campaigns,
 * and NonprofitSuite_Advocacy_Actions.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      1.0.0
 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues, _Campaigns, or _Actions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Advocacy {

	/**
	 * Create an advocacy issue
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues::create_issue()
	 * @param array $data Issue data
	 * @return int|WP_Error Issue ID or error
	 */
	public static function create_issue( $data ) {
		return NonprofitSuite_Advocacy_Issues::create_issue( $data );
	}

	/**
	 * Get issues with filters
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues::get_issues()
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_issues( $args = array() ) {
		return NonprofitSuite_Advocacy_Issues::get_issues( $args );
	}

	/**
	 * Update issue
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues::update_issue()
	 * @param int $id Issue ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_issue( $id, $data ) {
		return NonprofitSuite_Advocacy_Issues::update_issue( $id, $data );
	}

	/**
	 * Update issue status
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues::update_status()
	 * @param int $id Issue ID
	 * @param string $new_status New status
	 * @return bool|WP_Error
	 */
	public static function update_status( $id, $new_status ) {
		return NonprofitSuite_Advocacy_Issues::update_status( $id, $new_status );
	}

	/**
	 * Get active issues
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues::get_active_issues()
	 * @return array|WP_Error
	 */
	public static function get_active_issues() {
		return NonprofitSuite_Advocacy_Issues::get_active_issues();
	}

	/**
	 * Get issues with upcoming decision dates
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues::get_issues_by_decision_date()
	 * @param int $days_ahead Number of days to look ahead
	 * @return array|WP_Error
	 */
	public static function get_issues_by_decision_date( $days_ahead = 30 ) {
		return NonprofitSuite_Advocacy_Issues::get_issues_by_decision_date( $days_ahead );
	}

	/**
	 * Create a campaign
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::create_campaign()
	 * @param int $issue_id Issue ID
	 * @param array $data Campaign data
	 * @return int|WP_Error Campaign ID or error
	 */
	public static function create_campaign( $issue_id, $data ) {
		return NonprofitSuite_Advocacy_Campaigns::create_campaign( $issue_id, $data );
	}

	/**
	 * Get a single campaign
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::get_campaign()
	 * @param int $id Campaign ID
	 * @return object|WP_Error
	 */
	public static function get_campaign( $id ) {
		return NonprofitSuite_Advocacy_Campaigns::get_campaign( $id );
	}

	/**
	 * Get campaigns with filters
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::get_campaigns()
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_campaigns( $args = array() ) {
		return NonprofitSuite_Advocacy_Campaigns::get_campaigns( $args );
	}

	/**
	 * Update campaign
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::update_campaign()
	 * @param int $id Campaign ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_campaign( $id, $data ) {
		return NonprofitSuite_Advocacy_Campaigns::update_campaign( $id, $data );
	}

	/**
	 * Calculate campaign progress toward goal
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::calculate_progress()
	 * @param int $id Campaign ID
	 * @return int Percentage (0-100)
	 */
	public static function calculate_progress( $id ) {
		return NonprofitSuite_Advocacy_Campaigns::calculate_progress( $id );
	}

	/**
	 * Get active campaigns
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::get_active_campaigns()
	 * @return array|WP_Error
	 */
	public static function get_active_campaigns() {
		return NonprofitSuite_Advocacy_Campaigns::get_active_campaigns();
	}

	/**
	 * Log an advocacy action
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Actions::log_action()
	 * @param int $campaign_id Campaign ID
	 * @param array $data Action data
	 * @return int|WP_Error Action ID or error
	 */
	public static function log_action( $campaign_id, $data ) {
		return NonprofitSuite_Advocacy_Actions::log_action( $campaign_id, $data );
	}

	/**
	 * Get actions for a campaign
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Actions::get_actions()
	 * @param int $campaign_id Campaign ID
	 * @return array|WP_Error
	 */
	public static function get_actions( $campaign_id ) {
		return NonprofitSuite_Advocacy_Actions::get_actions( $campaign_id );
	}

	/**
	 * Get actions by a specific person
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Actions::get_person_actions()
	 * @param int $person_id Person ID
	 * @return array|WP_Error
	 */
	public static function get_person_actions( $person_id ) {
		return NonprofitSuite_Advocacy_Actions::get_person_actions( $person_id );
	}

	/**
	 * Increment campaign action count
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::increment_action_count()
	 * @param int $campaign_id Campaign ID
	 * @return bool
	 */
	public static function increment_action_count( $campaign_id ) {
		return NonprofitSuite_Advocacy_Campaigns::increment_action_count( $campaign_id );
	}

	/**
	 * Get action summary breakdown by type
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Actions::get_action_summary()
	 * @param int $campaign_id Campaign ID
	 * @return array
	 */
	public static function get_action_summary( $campaign_id ) {
		return NonprofitSuite_Advocacy_Actions::get_action_summary( $campaign_id );
	}

	/**
	 * Get campaign report
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::get_campaign_report()
	 * @param int $id Campaign ID
	 * @return array|WP_Error
	 */
	public static function get_campaign_report( $id ) {
		return NonprofitSuite_Advocacy_Campaigns::get_campaign_report( $id );
	}

	/**
	 * Get issue timeline
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Issues::get_issue_timeline()
	 * @param int $id Issue ID
	 * @return array
	 */
	public static function get_issue_timeline( $id ) {
		return NonprofitSuite_Advocacy_Issues::get_issue_timeline( $id );
	}

	/**
	 * Get advocacy dashboard overview
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Campaigns::get_advocacy_dashboard()
	 * @return array
	 */
	public static function get_advocacy_dashboard() {
		return NonprofitSuite_Advocacy_Campaigns::get_advocacy_dashboard();
	}

	/**
	 * Export action list to CSV
	 *
	 * @deprecated 2.0.0 Use NonprofitSuite_Advocacy_Actions::export_action_list()
	 * @param int $campaign_id Campaign ID
	 * @return string|WP_Error CSV content or error
	 */
	public static function export_action_list( $campaign_id ) {
		return NonprofitSuite_Advocacy_Actions::export_action_list( $campaign_id );
	}
}
