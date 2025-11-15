<?php
/**
 * Segment Analytics Adapter
 *
 * Integrates with Segment for customer data infrastructure and routing.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Segment_Adapter implements NS_Analytics_Adapter {
	/**
	 * Segment Write Key.
	 *
	 * @var string
	 */
	private $write_key;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.segment.io/v1';

	/**
	 * Constructor.
	 *
	 * @param string $write_key Segment Write Key.
	 */
	public function __construct( $write_key ) {
		$this->write_key = $write_key;
	}

	/**
	 * Track an event.
	 *
	 * @param array $event_data Event data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_event( $event_data ) {
		$payload = array(
			'userId'     => $event_data['user_id'] ?? null,
			'anonymousId' => $event_data['anonymous_id'] ?? $this->generate_anonymous_id(),
			'event'      => $event_data['event_name'],
			'properties' => array_merge(
				array(
					'category' => $event_data['event_category'] ?? null,
					'label'    => $event_data['event_label'] ?? null,
					'value'    => $event_data['event_value'] ?? null,
				),
				$event_data['properties'] ?? array()
			),
			'timestamp'  => date( 'c' ),
		);

		return $this->api_request( '/track', $payload );
	}

	/**
	 * Identify a user.
	 *
	 * @param string $user_id User identifier.
	 * @param array  $traits User traits.
	 * @return bool|WP_Error True on success.
	 */
	public function identify_user( $user_id, $traits = array() ) {
		$payload = array(
			'userId'    => $user_id,
			'traits'    => $traits,
			'timestamp' => date( 'c' ),
		);

		return $this->api_request( '/identify', $payload );
	}

	/**
	 * Track a page view.
	 *
	 * @param array $page_data Page data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_page_view( $page_data ) {
		$payload = array(
			'userId'     => $page_data['user_id'] ?? null,
			'anonymousId' => $page_data['anonymous_id'] ?? $this->generate_anonymous_id(),
			'name'       => $page_data['page_title'] ?? '',
			'properties' => array(
				'url'      => $page_data['page_url'] ?? '',
				'referrer' => $page_data['referrer_url'] ?? '',
			),
			'timestamp'  => date( 'c' ),
		);

		return $this->api_request( '/page', $payload );
	}

	/**
	 * Set user properties.
	 *
	 * @param string $user_id User identifier.
	 * @param array  $properties Properties to set.
	 * @return bool|WP_Error True on success.
	 */
	public function set_user_properties( $user_id, $properties ) {
		return $this->identify_user( $user_id, $properties );
	}

	/**
	 * Track a conversion/goal.
	 *
	 * @param array $conversion_data Conversion data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_conversion( $conversion_data ) {
		return $this->track_event(
			array(
				'event_name'  => $conversion_data['conversion_type'] ?? 'Conversion',
				'user_id'     => $conversion_data['user_id'] ?? null,
				'event_value' => $conversion_data['value'] ?? 0,
				'properties'  => array_merge(
					array( 'revenue' => $conversion_data['value'] ?? 0 ),
					$conversion_data['properties'] ?? array()
				),
			)
		);
	}

	/**
	 * Get analytics data for a date range.
	 *
	 * @param array $query Query parameters.
	 * @return array|WP_Error Analytics data.
	 */
	public function get_analytics_data( $query ) {
		return new WP_Error( 'not_supported', 'Segment is a data routing platform. Query downstream destinations for analytics.' );
	}

	/**
	 * Get real-time analytics data.
	 *
	 * @param array $query Query parameters.
	 * @return array|WP_Error Real-time data.
	 */
	public function get_realtime_data( $query = array() ) {
		return new WP_Error( 'not_supported', 'Use Segment Live Events debugger in UI.' );
	}

	/**
	 * Create a custom funnel.
	 *
	 * @param array $funnel_data Funnel configuration.
	 * @return array|WP_Error Funnel data.
	 */
	public function create_funnel( $funnel_data ) {
		return new WP_Error( 'not_supported', 'Create funnels in downstream destinations (GA, Mixpanel, etc.).' );
	}

	/**
	 * Get funnel analytics.
	 *
	 * @param string $funnel_id Funnel identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Funnel analytics.
	 */
	public function get_funnel_analytics( $funnel_id, $query = array() ) {
		return new WP_Error( 'not_supported', 'Query downstream destinations for funnel analytics.' );
	}

	/**
	 * Create a cohort.
	 *
	 * @param array $cohort_data Cohort configuration.
	 * @return array|WP_Error Cohort data.
	 */
	public function create_cohort( $cohort_data ) {
		return new WP_Error( 'not_supported', 'Create cohorts in downstream destinations.' );
	}

	/**
	 * Get cohort analytics.
	 *
	 * @param string $cohort_id Cohort identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Cohort analytics.
	 */
	public function get_cohort_analytics( $cohort_id, $query = array() ) {
		return new WP_Error( 'not_supported', 'Query downstream destinations for cohort analytics.' );
	}

	/**
	 * Export analytics data.
	 *
	 * @param array $export_params Export parameters.
	 * @return string|WP_Error Export data.
	 */
	public function export_data( $export_params ) {
		return new WP_Error( 'not_supported', 'Use Segment Data Warehouse or query downstream destinations.' );
	}

	/**
	 * Test connection.
	 *
	 * @return bool|WP_Error True on success.
	 */
	public function test_connection() {
		$result = $this->track_event(
			array(
				'event_name' => 'connection_test',
				'properties' => array( 'test' => true ),
			)
		);

		return $result;
	}

	/**
	 * Get provider capabilities.
	 *
	 * @return array Supported features.
	 */
	public function get_capabilities() {
		return array(
			'event_tracking'      => true,
			'user_identification' => true,
			'page_tracking'       => true,
			'conversions'         => true,
			'realtime'            => false,
			'reporting'           => false, // Segment is for data routing, not reporting
			'funnels'             => false,
			'cohorts'             => false,
			'export'              => false,
		);
	}

	/**
	 * Make API request to Segment.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $payload Payload data.
	 * @return bool|WP_Error True on success.
	 */
	private function api_request( $endpoint, $payload ) {
		$url = $this->api_base . $endpoint;

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->write_key . ':' ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new WP_Error(
				'segment_error',
				$body['error']['message'] ?? 'Segment API error',
				array( 'status' => $code )
			);
		}

		return true;
	}

	/**
	 * Generate an anonymous ID.
	 *
	 * @return string Anonymous ID.
	 */
	private function generate_anonymous_id() {
		return uniqid( 'anon_', true );
	}
}
