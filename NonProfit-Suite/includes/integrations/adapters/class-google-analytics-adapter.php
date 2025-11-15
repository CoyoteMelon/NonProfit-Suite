<?php
/**
 * Google Analytics 4 Adapter
 *
 * Integrates with Google Analytics 4 Measurement Protocol and Data API.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Google_Analytics_Adapter implements NS_Analytics_Adapter {
	/**
	 * GA4 Measurement ID (G-XXXXXXXXXX).
	 *
	 * @var string
	 */
	private $measurement_id;

	/**
	 * GA4 API Secret for Measurement Protocol.
	 *
	 * @var string
	 */
	private $api_secret;

	/**
	 * GA4 Property ID for Data API.
	 *
	 * @var string
	 */
	private $property_id;

	/**
	 * Google OAuth access token for Data API.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Measurement Protocol API base URL.
	 *
	 * @var string
	 */
	private $mp_api_base = 'https://www.google-analytics.com/mp/collect';

	/**
	 * Data API base URL.
	 *
	 * @var string
	 */
	private $data_api_base = 'https://analyticsdata.googleapis.com/v1beta';

	/**
	 * Constructor.
	 *
	 * @param string $measurement_id GA4 Measurement ID.
	 * @param string $api_secret API Secret for Measurement Protocol.
	 * @param string $property_id Property ID for Data API.
	 * @param string $access_token OAuth access token.
	 */
	public function __construct( $measurement_id, $api_secret, $property_id = '', $access_token = '' ) {
		$this->measurement_id = $measurement_id;
		$this->api_secret     = $api_secret;
		$this->property_id    = $property_id;
		$this->access_token   = $access_token;
	}

	/**
	 * Track an event.
	 *
	 * @param array $event_data Event data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_event( $event_data ) {
		$client_id = $event_data['user_id'] ?? $this->generate_client_id();

		$payload = array(
			'client_id' => $client_id,
			'events'    => array(
				array(
					'name'   => $event_data['event_name'],
					'params' => array_merge(
						array(
							'event_category' => $event_data['event_category'] ?? '',
							'event_label'    => $event_data['event_label'] ?? '',
							'value'          => floatval( $event_data['event_value'] ?? 0 ),
						),
						$event_data['properties'] ?? array()
					),
				),
			),
		);

		// Add user properties if provided
		if ( ! empty( $event_data['user_properties'] ) ) {
			$payload['user_properties'] = $this->format_user_properties( $event_data['user_properties'] );
		}

		return $this->send_measurement_protocol( $payload );
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
			'client_id'       => $user_id,
			'user_id'         => $user_id,
			'user_properties' => $this->format_user_properties( $traits ),
		);

		return $this->send_measurement_protocol( $payload );
	}

	/**
	 * Track a page view.
	 *
	 * @param array $page_data Page data.
	 * @return bool|WP_Error True on success.
	 */
	public function track_page_view( $page_data ) {
		$client_id = $page_data['user_id'] ?? $this->generate_client_id();

		$payload = array(
			'client_id' => $client_id,
			'events'    => array(
				array(
					'name'   => 'page_view',
					'params' => array(
						'page_location' => $page_data['page_url'] ?? '',
						'page_title'    => $page_data['page_title'] ?? '',
						'page_referrer' => $page_data['referrer_url'] ?? '',
					),
				),
			),
		);

		return $this->send_measurement_protocol( $payload );
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
		$event_name = $conversion_data['conversion_type'] ?? 'conversion';

		return $this->track_event(
			array(
				'event_name'     => $event_name,
				'event_category' => 'conversion',
				'event_value'    => $conversion_data['value'] ?? 0,
				'properties'     => $conversion_data['properties'] ?? array(),
				'user_id'        => $conversion_data['user_id'] ?? null,
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
		if ( empty( $this->property_id ) || empty( $this->access_token ) ) {
			return new WP_Error( 'missing_credentials', 'Property ID and access token required for Data API.' );
		}

		$metrics    = $query['metrics'] ?? array( 'activeUsers', 'sessions' );
		$dimensions = $query['dimensions'] ?? array( 'date' );
		$start_date = $query['start_date'] ?? date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date   = $query['end_date'] ?? date( 'Y-m-d' );

		$request_body = array(
			'dateRanges' => array(
				array(
					'startDate' => $start_date,
					'endDate'   => $end_date,
				),
			),
			'metrics'    => array_map(
				function ( $metric ) {
					return array( 'name' => $metric );
				},
				$metrics
			),
			'dimensions' => array_map(
				function ( $dimension ) {
					return array( 'name' => $dimension );
				},
				$dimensions
			),
		);

		$endpoint = '/properties/' . $this->property_id . ':runReport';

		$response = $this->data_api_request( $endpoint, 'POST', $request_body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->format_data_api_response( $response );
	}

	/**
	 * Get real-time analytics data.
	 *
	 * @param array $query Query parameters.
	 * @return array|WP_Error Real-time data.
	 */
	public function get_realtime_data( $query = array() ) {
		if ( empty( $this->property_id ) || empty( $this->access_token ) ) {
			return new WP_Error( 'missing_credentials', 'Property ID and access token required for Data API.' );
		}

		$metrics    = $query['metrics'] ?? array( 'activeUsers' );
		$dimensions = $query['dimensions'] ?? array();

		$request_body = array(
			'metrics'    => array_map(
				function ( $metric ) {
					return array( 'name' => $metric );
				},
				$metrics
			),
			'dimensions' => array_map(
				function ( $dimension ) {
					return array( 'name' => $dimension );
				},
				$dimensions
			),
		);

		$endpoint = '/properties/' . $this->property_id . ':runRealtimeReport';

		$response = $this->data_api_request( $endpoint, 'POST', $request_body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->format_data_api_response( $response );
	}

	/**
	 * Create a custom funnel.
	 *
	 * @param array $funnel_data Funnel configuration.
	 * @return array|WP_Error Funnel data.
	 */
	public function create_funnel( $funnel_data ) {
		// GA4 funnels are created in the UI, not via API
		return new WP_Error( 'not_supported', 'Funnel creation must be done in GA4 UI.' );
	}

	/**
	 * Get funnel analytics.
	 *
	 * @param string $funnel_id Funnel identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Funnel analytics.
	 */
	public function get_funnel_analytics( $funnel_id, $query = array() ) {
		// Funnel analysis via exploration in GA4
		return new WP_Error( 'not_supported', 'Use GA4 Explorations for funnel analysis.' );
	}

	/**
	 * Create a cohort.
	 *
	 * @param array $cohort_data Cohort configuration.
	 * @return array|WP_Error Cohort data.
	 */
	public function create_cohort( $cohort_data ) {
		// GA4 audiences are created in the UI
		return new WP_Error( 'not_supported', 'Audience/cohort creation must be done in GA4 UI.' );
	}

	/**
	 * Get cohort analytics.
	 *
	 * @param string $cohort_id Cohort identifier.
	 * @param array  $query Query parameters.
	 * @return array|WP_Error Cohort analytics.
	 */
	public function get_cohort_analytics( $cohort_id, $query = array() ) {
		// Cohort analysis via Data API with cohort dimension
		$query['dimensions'] = array_merge( $query['dimensions'] ?? array(), array( 'cohort' ) );
		return $this->get_analytics_data( $query );
	}

	/**
	 * Export analytics data.
	 *
	 * @param array $export_params Export parameters.
	 * @return string|WP_Error Export data.
	 */
	public function export_data( $export_params ) {
		$data = $this->get_analytics_data( $export_params );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$format = $export_params['format'] ?? 'json';

		switch ( $format ) {
			case 'csv':
				return $this->convert_to_csv( $data );
			case 'json':
			default:
				return wp_json_encode( $data );
		}
	}

	/**
	 * Test connection.
	 *
	 * @return bool|WP_Error True on success.
	 */
	public function test_connection() {
		// Test with a simple event
		$result = $this->send_measurement_protocol(
			array(
				'client_id' => $this->generate_client_id(),
				'events'    => array(
					array(
						'name'   => 'connection_test',
						'params' => array( 'test' => true ),
					),
				),
			),
			true // validation_only
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
			'event_tracking'     => true,
			'user_identification' => true,
			'page_tracking'      => true,
			'conversions'        => true,
			'realtime'           => ! empty( $this->property_id ) && ! empty( $this->access_token ),
			'reporting'          => ! empty( $this->property_id ) && ! empty( $this->access_token ),
			'funnels'            => false,
			'cohorts'            => ! empty( $this->property_id ) && ! empty( $this->access_token ),
			'export'             => true,
		);
	}

	/**
	 * Send data via Measurement Protocol.
	 *
	 * @param array $payload Payload data.
	 * @param bool  $validation_only Whether to validate only.
	 * @return bool|WP_Error True on success.
	 */
	private function send_measurement_protocol( $payload, $validation_only = false ) {
		$url = $this->mp_api_base;

		if ( $validation_only ) {
			$url = 'https://www.google-analytics.com/debug/mp/collect';
		}

		$url .= '?measurement_id=' . $this->measurement_id . '&api_secret=' . $this->api_secret;

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $validation_only ) {
			$data = json_decode( $body, true );
			if ( ! empty( $data['validationMessages'] ) ) {
				return new WP_Error( 'validation_failed', 'Validation failed: ' . wp_json_encode( $data['validationMessages'] ) );
			}
		}

		if ( $code >= 400 ) {
			return new WP_Error( 'ga4_error', 'GA4 Measurement Protocol error', array( 'status' => $code ) );
		}

		return true;
	}

	/**
	 * Make Data API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array  $body Request body.
	 * @return array|WP_Error API response.
	 */
	private function data_api_request( $endpoint, $method, $body = array() ) {
		$url = $this->data_api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'ga4_data_api_error',
				$data['error']['message'] ?? 'GA4 Data API error',
				array( 'status' => $code )
			);
		}

		return $data;
	}

	/**
	 * Format user properties for GA4.
	 *
	 * @param array $properties Properties.
	 * @return array Formatted properties.
	 */
	private function format_user_properties( $properties ) {
		$formatted = array();

		foreach ( $properties as $key => $value ) {
			$formatted[ $key ] = array( 'value' => $value );
		}

		return $formatted;
	}

	/**
	 * Format Data API response.
	 *
	 * @param array $response API response.
	 * @return array Formatted data.
	 */
	private function format_data_api_response( $response ) {
		$rows = array();

		foreach ( $response['rows'] ?? array() as $row ) {
			$formatted_row = array();

			foreach ( $row['dimensionValues'] ?? array() as $index => $dimension ) {
				$dimension_name                  = $response['dimensionHeaders'][ $index ]['name'] ?? 'dimension_' . $index;
				$formatted_row[ $dimension_name ] = $dimension['value'];
			}

			foreach ( $row['metricValues'] ?? array() as $index => $metric ) {
				$metric_name                  = $response['metricHeaders'][ $index ]['name'] ?? 'metric_' . $index;
				$formatted_row[ $metric_name ] = $metric['value'];
			}

			$rows[] = $formatted_row;
		}

		return array(
			'rows'   => $rows,
			'totals' => $response['totals'] ?? null,
		);
	}

	/**
	 * Convert data to CSV.
	 *
	 * @param array $data Data to convert.
	 * @return string CSV data.
	 */
	private function convert_to_csv( $data ) {
		if ( empty( $data['rows'] ) ) {
			return '';
		}

		$csv = '';

		// Headers
		$headers = array_keys( $data['rows'][0] );
		$csv    .= implode( ',', $headers ) . "\n";

		// Rows
		foreach ( $data['rows'] as $row ) {
			$csv .= implode( ',', array_values( $row ) ) . "\n";
		}

		return $csv;
	}

	/**
	 * Generate a client ID.
	 *
	 * @return string Client ID.
	 */
	private function generate_client_id() {
		return sprintf( '%d.%d', time(), wp_rand( 100000000, 999999999 ) );
	}
}
