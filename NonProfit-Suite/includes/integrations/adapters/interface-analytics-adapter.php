<?php
/**
 * Analytics Adapter Interface
 *
 * Defines the contract for analytics provider integrations.
 * All analytics adapters (Google Analytics, Mixpanel, Segment, etc.) must implement this interface.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NS_Analytics_Adapter {
	/**
	 * Track an event.
	 *
	 * @param array $event_data Event data including name, category, properties.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function track_event( $event_data );

	/**
	 * Identify a user.
	 *
	 * @param string $user_id User identifier.
	 * @param array  $traits User traits/properties.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function identify_user( $user_id, $traits = array() );

	/**
	 * Track a page view.
	 *
	 * @param array $page_data Page data including URL, title, properties.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function track_page_view( $page_data );

	/**
	 * Set user properties.
	 *
	 * @param string $user_id User identifier.
	 * @param array  $properties Properties to set.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function set_user_properties( $user_id, $properties );

	/**
	 * Track a conversion/goal.
	 *
	 * @param array $conversion_data Conversion data including type, value, properties.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function track_conversion( $conversion_data );

	/**
	 * Get analytics data for a date range.
	 *
	 * @param array $query Query parameters (metrics, dimensions, date range).
	 * @return array|WP_Error Analytics data, or error.
	 */
	public function get_analytics_data( $query );

	/**
	 * Get real-time analytics data.
	 *
	 * @param array $query Query parameters.
	 * @return array|WP_Error Real-time data, or error.
	 */
	public function get_realtime_data( $query = array() );

	/**
	 * Create a custom funnel.
	 *
	 * @param array $funnel_data Funnel configuration (name, steps).
	 * @return array|WP_Error Funnel data, or error.
	 */
	public function create_funnel( $funnel_data );

	/**
	 * Get funnel analytics.
	 *
	 * @param string $funnel_id Funnel identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Funnel analytics, or error.
	 */
	public function get_funnel_analytics( $funnel_id, $query = array() );

	/**
	 * Create a cohort.
	 *
	 * @param array $cohort_data Cohort configuration.
	 * @return array|WP_Error Cohort data, or error.
	 */
	public function create_cohort( $cohort_data );

	/**
	 * Get cohort analytics.
	 *
	 * @param string $cohort_id Cohort identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Cohort analytics, or error.
	 */
	public function get_cohort_analytics( $cohort_id, $query = array() );

	/**
	 * Export analytics data.
	 *
	 * @param array $export_params Export parameters (format, metrics, date range).
	 * @return string|WP_Error Export file path or data, or error.
	 */
	public function export_data( $export_params );

	/**
	 * Test connection to analytics provider.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection();

	/**
	 * Get provider capabilities.
	 *
	 * @return array Array of supported features.
	 */
	public function get_capabilities();
}
