<?php
/**
 * Mixpanel Analytics Adapter
 *
 * Integrates with Mixpanel for product analytics, event tracking, and user behavior analysis.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Mixpanel_Adapter implements NS_Analytics_Adapter {
	/**
	 * Mixpanel Project Token.
	 *
	 * @var string
	 */
	private $project_token;

	/**
	 * Mixpanel API Secret (for server-side operations).
	 *
	 * @var string
	 */
	private $api_secret;

	/**
	 * Mixpanel Service Account username.
	 *
	 * @var string
	 */
	private $service_account;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.mixpanel.com';

	/**
	 * Constructor.
	 *
	 * @param string $project_token Mixpanel Project Token.
	 * @param string $api_secret API Secret.
	 * @param string $service_account Service Account username.
	 */
	public function __construct( $project_token, $api_secret = '', $service_account = '' ) {
		$this->project_token   = $project_token;
		$this->api_secret      = $api_secret;
		$this->service_account = $service_account;
	}

	/**
	 * Track an event.
	 *
	 * @param array $event_data Event data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_event( $event_data ) {
		$distinct_id = $event_data['user_id'] ?? $this->generate_distinct_id();

		$event = array(
			'event'      => $event_data['event_name'],
			'properties' => array_merge(
				array(
					'token'       => $this->project_token,
					'distinct_id' => $distinct_id,
					'time'        => time(),
					'$insert_id'  => $this->generate_insert_id(),
				),
				$event_data['properties'] ?? array(),
				array(
					'category' => $event_data['event_category'] ?? null,
					'label'    => $event_data['event_label'] ?? null,
					'value'    => $event_data['event_value'] ?? null,
				)
			),
		);

		// Remove null values
		$event['properties'] = array_filter(
			$event['properties'],
			function ( $value ) {
				return ! is_null( $value );
			}
		);

		return $this->send_track_request( array( $event ) );
	}

	/**
	 * Identify a user.
	 *
	 * @param string $user_id User identifier.
	 * @param array  $traits User traits.
	 * @return bool|WP_Error True on success.
	 */
	public function identify_user( $user_id, $traits = array() ) {
		return $this->set_user_properties( $user_id, $traits );
	}

	/**
	 * Track a page view.
	 *
	 * @param array $page_data Page data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_page_view( $page_data ) {
		return $this->track_event(
			array(
				'event_name' => '$mp_web_page_view',
				'user_id'    => $page_data['user_id'] ?? null,
				'properties' => array(
					'$current_url' => $page_data['page_url'] ?? '',
					'$title'       => $page_data['page_title'] ?? '',
					'$referrer'    => $page_data['referrer_url'] ?? '',
				),
			)
		);
	}

	/**
	 * Set user properties.
	 *
	 * @param string $user_id User identifier.
	 * @param array  $properties Properties to set.
	 * @return bool|WP_Error True on success.
	 */
	public function set_user_properties( $user_id, $properties ) {
		$profile = array(
			'$token'       => $this->project_token,
			'$distinct_id' => $user_id,
			'$set'         => $properties,
		);

		return $this->send_engage_request( array( $profile ) );
	}

	/**
	 * Track a conversion/goal.
	 *
	 * @param array $conversion_data Conversion data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_conversion( $conversion_data ) {
		$event_name = $conversion_data['conversion_type'] ?? 'Conversion';

		return $this->track_event(
			array(
				'event_name'  => $event_name,
				'user_id'     => $conversion_data['user_id'] ?? null,
				'event_value' => $conversion_data['value'] ?? 0,
				'properties'  => array_merge(
					array( '$value' => $conversion_data['value'] ?? 0 ),
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
		if ( empty( $this->api_secret ) ) {
			return new WP_Error( 'missing_credentials', 'API Secret required for query operations.' );
		}

		$event      = $query['event'] ?? '';
		$from_date  = $query['start_date'] ?? date( 'Y-m-d', strtotime( '-30 days' ) );
		$to_date    = $query['end_date'] ?? date( 'Y-m-d' );
		$unit       = $query['unit'] ?? 'day'; // day, week, month

		$params = array(
			'event'     => $event,
			'type'      => 'general',
			'unit'      => $unit,
			'from_date' => $from_date,
			'to_date'   => $to_date,
		);

		$response = $this->query_api_request( '/api/2.0/events', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data'] ?? array();
	}

	/**
	 * Get real-time analytics data.
	 *
	 * @param array $query Query parameters.
	 * @return array|WP_Error Real-time data.
	 */
	public function get_realtime_data( $query = array() ) {
		// Mixpanel doesn't have dedicated real-time API
		// Use live view feature in UI
		return new WP_Error( 'not_supported', 'Use Mixpanel Live View for real-time data.' );
	}

	/**
	 * Create a custom funnel.
	 *
	 * @param array $funnel_data Funnel configuration.
	 * @return array|WP_Error Funnel data.
	 */
	public function create_funnel( $funnel_data ) {
		if ( empty( $this->api_secret ) || empty( $this->service_account ) ) {
			return new WP_Error( 'missing_credentials', 'API Secret and Service Account required.' );
		}

		$funnel = array(
			'funnel_id'   => absint( $funnel_data['funnel_id'] ?? time() ),
			'name'        => $funnel_data['name'],
			'events'      => $funnel_data['steps'], // Array of event names
			'window_days' => $funnel_data['window_days'] ?? 7,
		);

		// Mixpanel funnels are created via UI or Data Management API
		// This is a simplified representation
		return $funnel;
	}

	/**
	 * Get funnel analytics.
	 *
	 * @param string $funnel_id Funnel identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Funnel analytics.
	 */
	public function get_funnel_analytics( $funnel_id, $query = array() ) {
		if ( empty( $this->api_secret ) ) {
			return new WP_Error( 'missing_credentials', 'API Secret required.' );
		}

		$from_date = $query['start_date'] ?? date( 'Y-m-d', strtotime( '-30 days' ) );
		$to_date   = $query['end_date'] ?? date( 'Y-m-d' );
		$unit      = $query['unit'] ?? 'day';

		$params = array(
			'funnel_id' => $funnel_id,
			'from_date' => $from_date,
			'to_date'   => $to_date,
			'unit'      => $unit,
		);

		$response = $this->query_api_request( '/api/2.0/funnels', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data'] ?? array();
	}

	/**
	 * Create a cohort.
	 *
	 * @param array $cohort_data Cohort configuration.
	 * @return array|WP_Error Cohort data.
	 */
	public function create_cohort( $cohort_data ) {
		// Cohorts/people segments are created via UI
		return new WP_Error( 'not_supported', 'Cohorts must be created in Mixpanel UI.' );
	}

	/**
	 * Get cohort analytics.
	 *
	 * @param string $cohort_id Cohort identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Cohort analytics.
	 */
	public function get_cohort_analytics( $cohort_id, $query = array() ) {
		if ( empty( $this->api_secret ) ) {
			return new WP_Error( 'missing_credentials', 'API Secret required.' );
		}

		$params = array(
			'cohort_id' => $cohort_id,
		);

		$response = $this->query_api_request( '/api/2.0/engage/cohorts', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Export analytics data.
	 *
	 * @param array $export_params Export parameters.
	 * @return string|WP_Error Export data.
	 */
	public function export_data( $export_params ) {
		if ( empty( $this->api_secret ) ) {
			return new WP_Error( 'missing_credentials', 'API Secret required for export.' );
		}

		$from_date = $export_params['start_date'] ?? date( 'Y-m-d', strtotime( '-30 days' ) );
		$to_date   = $export_params['end_date'] ?? date( 'Y-m-d' );
		$event     = $export_params['event'] ?? null;

		$params = array(
			'from_date' => $from_date,
			'to_date'   => $to_date,
		);

		if ( $event ) {
			$params['event'] = wp_json_encode( array( $event ) );
		}

		$response = $this->query_api_request( '/api/2.0/export', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Test connection.
	 *
	 * @return bool|WP_Error True on success.
	 */
	public function test_connection() {
		// Send a test event
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
			'reporting'           => ! empty( $this->api_secret ),
			'funnels'             => ! empty( $this->api_secret ),
			'cohorts'             => ! empty( $this->api_secret ),
			'export'              => ! empty( $this->api_secret ),
		);
	}

	/**
	 * Send track request to Mixpanel.
	 *
	 * @param array $events Array of events.
	 * @return bool|WP_Error True on success.
	 */
	private function send_track_request( $events ) {
		$url = $this->api_base . '/track';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'text/plain',
				),
				'body'    => wp_json_encode( $events ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code !== 200 || $body !== '1' ) {
			return new WP_Error( 'mixpanel_error', 'Failed to track event', array( 'response' => $body ) );
		}

		return true;
	}

	/**
	 * Send engage request (set user properties).
	 *
	 * @param array $profiles Array of profile updates.
	 * @return bool|WP_Error True on success.
	 */
	private function send_engage_request( $profiles ) {
		$url = $this->api_base . '/engage';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'text/plain',
				),
				'body'    => wp_json_encode( $profiles ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code !== 200 || $body !== '1' ) {
			return new WP_Error( 'mixpanel_error', 'Failed to update profile', array( 'response' => $body ) );
		}

		return true;
	}

	/**
	 * Make query API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params Query parameters.
	 * @return array|WP_Error API response.
	 */
	private function query_api_request( $endpoint, $params ) {
		$params['expire'] = time() + 600; // 10 minutes expiration

		$url = $this->api_base . $endpoint . '?' . http_build_query( $params );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->service_account . ':' . $this->api_secret ),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'mixpanel_api_error',
				$data['error'] ?? 'Mixpanel API error',
				array( 'status' => $code )
			);
		}

		return $data;
	}

	/**
	 * Generate a distinct ID.
	 *
	 * @return string Distinct ID.
	 */
	private function generate_distinct_id() {
		return uniqid( 'user_', true );
	}

	/**
	 * Generate an insert ID (for deduplication).
	 *
	 * @return string Insert ID.
	 */
	private function generate_insert_id() {
		return uniqid( '', true );
	}
}
