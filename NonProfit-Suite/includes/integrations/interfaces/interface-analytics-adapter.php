<?php
/**
 * Analytics Adapter Interface
 *
 * Defines the contract for analytics providers (Google Analytics, Mixpanel, etc.)
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics Adapter Interface
 *
 * All analytics adapters must implement this interface.
 */
interface NonprofitSuite_Analytics_Adapter_Interface {

	/**
	 * Track an event
	 *
	 * @param string $event_name Event name
	 * @param array  $properties Event properties (optional)
	 *                           - category: Event category (optional)
	 *                           - label: Event label (optional)
	 *                           - value: Event value (optional)
	 *                           - user_id: User identifier (optional)
	 *                           - custom_data: Additional custom data (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function track_event( $event_name, $properties = array() );

	/**
	 * Track page view
	 *
	 * @param array $args Page view arguments
	 *                    - url: Page URL (required)
	 *                    - title: Page title (optional)
	 *                    - referrer: Referrer URL (optional)
	 *                    - user_id: User identifier (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function track_page_view( $args );

	/**
	 * Identify/update a user
	 *
	 * @param string $user_id User identifier
	 * @param array  $traits  User traits/properties
	 *                        - email: Email address (optional)
	 *                        - name: Full name (optional)
	 *                        - role: User role (optional)
	 *                        - created_at: Account creation date (optional)
	 *                        - custom_traits: Additional traits (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function identify_user( $user_id, $traits = array() );

	/**
	 * Track a conversion/goal
	 *
	 * @param string $conversion_name Conversion name
	 * @param array  $properties      Conversion properties
	 *                                - value: Conversion value (optional)
	 *                                - currency: Currency code (optional)
	 *                                - user_id: User identifier (optional)
	 *                                - custom_data: Additional data (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function track_conversion( $conversion_name, $properties = array() );

	/**
	 * Create a funnel
	 *
	 * @param array $funnel_data Funnel data
	 *                           - name: Funnel name (required)
	 *                           - steps: Array of step names (required)
	 * @return array|WP_Error Funnel data with keys: funnel_id
	 */
	public function create_funnel( $funnel_data );

	/**
	 * Get funnel analytics
	 *
	 * @param string $funnel_id Funnel identifier
	 * @param array  $args      Query arguments
	 *                          - start_date: Start date (optional)
	 *                          - end_date: End date (optional)
	 * @return array|WP_Error Funnel analytics or WP_Error on failure
	 *                        - total_users: Total users entered
	 *                        - conversion_rate: Overall conversion rate
	 *                        - step_data: Data for each step
	 */
	public function get_funnel_analytics( $funnel_id, $args = array() );

	/**
	 * Get event analytics
	 *
	 * @param string $event_name Event name
	 * @param array  $args       Query arguments
	 *                           - start_date: Start date (optional)
	 *                           - end_date: End date (optional)
	 *                           - breakdown_by: Property to break down by (optional)
	 * @return array|WP_Error Event analytics or WP_Error on failure
	 *                        - total_count: Total event occurrences
	 *                        - unique_users: Unique users
	 *                        - breakdown: Breakdown by property (if requested)
	 */
	public function get_event_analytics( $event_name, $args = array() );

	/**
	 * Get user retention data
	 *
	 * @param array $args Query arguments
	 *                    - start_date: Start date (optional)
	 *                    - end_date: End date (optional)
	 *                    - interval: day, week, month (optional)
	 * @return array|WP_Error Retention data or WP_Error on failure
	 */
	public function get_retention( $args = array() );

	/**
	 * Get real-time active users
	 *
	 * @return int|WP_Error Number of active users or WP_Error on failure
	 */
	public function get_active_users();

	/**
	 * Get custom report
	 *
	 * @param array $report_config Report configuration
	 *                             - metrics: Array of metrics to include (required)
	 *                             - dimensions: Array of dimensions (optional)
	 *                             - start_date: Start date (optional)
	 *                             - end_date: End date (optional)
	 *                             - filters: Array of filters (optional)
	 * @return array|WP_Error Report data or WP_Error on failure
	 */
	public function get_custom_report( $report_config );

	/**
	 * Set custom dimension/property
	 *
	 * @param string $dimension_name Dimension name
	 * @param mixed  $value          Dimension value
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function set_custom_dimension( $dimension_name, $value );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Google Analytics", "Mixpanel")
	 */
	public function get_provider_name();
}
