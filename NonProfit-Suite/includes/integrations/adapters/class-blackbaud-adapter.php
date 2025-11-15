<?php
/**
 * Blackbaud Target Analytics Adapter
 *
 * Integration with Blackbaud Target Analytics for enterprise wealth screening.
 * Seamless integration with Raiser's Edge and other Blackbaud products.
 *
 * API Documentation: https://developer.blackbaud.com/
 * Pricing: Custom enterprise licensing
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since 1.18.0
 */

namespace NonprofitSuite\Integrations\Adapters;

class NS_Blackbaud_Adapter implements NS_Wealth_Research_Adapter {

	/**
	 * API key (subscription key)
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Access token (OAuth 2.0)
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Refresh token
	 *
	 * @var string
	 */
	private $refresh_token;

	/**
	 * API endpoint
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * Organization ID
	 *
	 * @var int
	 */
	private $organization_id;

	/**
	 * Environment ID
	 *
	 * @var string
	 */
	private $environment_id;

	/**
	 * Constructor
	 *
	 * @param array $config Configuration array
	 */
	public function __construct( $config ) {
		$this->api_key         = $config['api_key'] ?? '';
		$this->access_token    = $config['access_token'] ?? '';
		$this->refresh_token   = $config['refresh_token'] ?? '';
		$this->api_endpoint    = $config['api_endpoint'] ?? 'https://api.sky.blackbaud.com';
		$this->organization_id = $config['organization_id'] ?? 0;
		$this->environment_id  = $config['environment_id'] ?? '';
	}

	/**
	 * Screen individual for basic wealth indicators
	 *
	 * @param array $params Individual information
	 * @return array Screening results
	 */
	public function screen_individual( $params ) {
		$endpoint = '/screening/v1/screenings';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'address_lines' => $params['address'] ?? '',
				'city'          => $params['city'] ?? '',
				'state'         => $params['state'] ?? '',
				'postal_code'   => $params['zip'] ?? '',
				'country'       => 'US',
			),
		);

		if ( ! empty( $params['email'] ) ) {
			$body['email_address'] = $params['email'];
		}

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'          => true,
			'individual_id'    => $data['screening_id'] ?? '',
			'giving_capacity'  => $this->map_capacity_code( $data['capacity_rating_code'] ?? '' ),
			'income_range'     => $data['income_range'] ?? 'Unknown',
			'net_worth_range'  => $data['net_worth_range'] ?? 'Unknown',
			'confidence_score' => $this->map_confidence( $data['confidence'] ?? '' ),
			'cost'             => 0, // Enterprise licensing - no per-call cost
		);
	}

	/**
	 * Get full profile for an individual
	 *
	 * @param array $params Individual identification parameters
	 * @return array Full profile data
	 */
	public function get_profile( $params ) {
		$endpoint = '/screening/v1/profiles';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'address_lines' => $params['address'] ?? '',
				'city'          => $params['city'] ?? '',
				'state'         => $params['state'] ?? '',
				'postal_code'   => $params['zip'] ?? '',
				'country'       => 'US',
			),
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'        => true,
			'individual_id'  => $data['screening_id'] ?? '',
			'wealth_indicators' => array(
				'income_range'          => $data['income_range'] ?? 'Unknown',
				'net_worth_range'       => $data['net_worth_range'] ?? 'Unknown',
				'real_estate_value'     => floatval( $data['real_estate_total'] ?? 0 ),
				'business_affiliations' => $data['business_relationships'] ?? array(),
				'stock_holdings'        => $data['securities'] ?? array(),
			),
			'biographical' => array(
				'age_range'     => $data['age_range'] ?? '',
				'education'     => $data['education'] ?? array(),
				'professional'  => $data['employment'] ?? array(),
			),
			'social' => array(
				'social_media' => $data['social_media'] ?? array(),
				'interests'    => $data['interests'] ?? array(),
			),
			'cost' => 0, // Enterprise licensing
		);
	}

	/**
	 * Get giving capacity rating
	 *
	 * @param array $params Individual identification parameters
	 * @return array Capacity rating results
	 */
	public function get_capacity_rating( $params ) {
		$endpoint = '/screening/v1/capacity';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'address_lines' => $params['address'] ?? '',
				'city'          => $params['city'] ?? '',
				'state'         => $params['state'] ?? '',
				'postal_code'   => $params['zip'] ?? '',
				'country'       => 'US',
			),
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data = $response['data'] ?? array();

		$capacity_code   = $data['capacity_rating_code'] ?? '';
		$capacity_rating = $this->map_capacity_code( $capacity_code );
		$capacity_range  = $this->get_capacity_range( $capacity_rating );

		return array(
			'success'          => true,
			'capacity_rating'  => $capacity_rating,
			'capacity_range'   => $capacity_range,
			'rating_factors'   => $data['rating_factors'] ?? array(),
			'confidence_score' => $this->map_confidence( $data['confidence'] ?? '' ),
			'cost'             => 0,
		);
	}

	/**
	 * Get philanthropic history
	 *
	 * @param array $params Individual identification parameters
	 * @return array Philanthropic history
	 */
	public function get_philanthropic_history( $params ) {
		$endpoint = '/screening/v1/philanthropy';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'address_lines' => $params['address'] ?? '',
				'city'          => $params['city'] ?? '',
				'state'         => $params['state'] ?? '',
				'postal_code'   => $params['zip'] ?? '',
				'country'       => 'US',
			),
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data = $response['data'] ?? array();

		// Parse donations
		$donations = array();
		foreach ( $data['gifts'] ?? array() as $gift ) {
			$donations[] = array(
				'organization' => $gift['recipient'] ?? '',
				'amount'       => floatval( $gift['amount'] ?? 0 ),
				'date'         => $gift['date'] ?? '',
				'type'         => $gift['gift_type'] ?? '',
			);
		}

		return array(
			'success'                  => true,
			'donations'                => $donations,
			'board_affiliations'       => $data['board_service'] ?? array(),
			'political_contributions'  => floatval( $data['political_total'] ?? 0 ),
			'political_parties'        => $data['political_affiliations'] ?? array(),
			'estimated_lifetime'       => floatval( $data['lifetime_giving'] ?? 0 ),
			'cost'                     => 0,
		);
	}

	/**
	 * Get real estate holdings
	 *
	 * @param array $params Individual identification parameters
	 * @return array Real estate data
	 */
	public function get_real_estate_holdings( $params ) {
		$endpoint = '/screening/v1/real-estate';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'address_lines' => $params['address'] ?? '',
				'city'          => $params['city'] ?? '',
				'state'         => $params['state'] ?? '',
				'postal_code'   => $params['zip'] ?? '',
				'country'       => 'US',
			),
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data = $response['data'] ?? array();
		$properties = $data['properties'] ?? array();

		$total_value = 0;
		foreach ( $properties as $property ) {
			$total_value += floatval( $property['market_value'] ?? 0 );
		}

		return array(
			'success'        => true,
			'properties'     => $properties,
			'total_value'    => $total_value,
			'property_count' => count( $properties ),
			'cost'           => 0,
		);
	}

	/**
	 * Get business affiliations
	 *
	 * @param array $params Individual identification parameters
	 * @return array Business affiliation data
	 */
	public function get_business_affiliations( $params ) {
		$endpoint = '/screening/v1/business';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'address_lines' => $params['address'] ?? '',
				'city'          => $params['city'] ?? '',
				'state'         => $params['state'] ?? '',
				'postal_code'   => $params['zip'] ?? '',
				'country'       => 'US',
			),
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'      => true,
			'affiliations' => $data['business_relationships'] ?? array(),
			'cost'         => 0,
		);
	}

	/**
	 * Search by email address
	 *
	 * @param string $email Email address to search
	 * @return array Search results
	 */
	public function search_by_email( $email ) {
		$endpoint = '/screening/v1/search';

		$body = array(
			'search_type' => 'email',
			'email'       => $email,
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'found'         => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'       => true,
			'found'         => ! empty( $data ),
			'individual_id' => $data['screening_id'] ?? '',
			'basic_info'    => array(
				'first_name' => $data['first_name'] ?? '',
				'last_name'  => $data['last_name'] ?? '',
				'city'       => $data['city'] ?? '',
				'state'      => $data['state'] ?? '',
			),
			'cost' => 0,
		);
	}

	/**
	 * Search by name and location
	 *
	 * @param string $first_name First name
	 * @param string $last_name  Last name
	 * @param array  $location   Optional location filters
	 * @return array Search results
	 */
	public function search_by_name( $first_name, $last_name, $location = array() ) {
		$endpoint = '/screening/v1/search';

		$body = array(
			'search_type' => 'name',
			'first_name'  => $first_name,
			'last_name'   => $last_name,
		);

		if ( ! empty( $location ) ) {
			$body['location'] = array(
				'city'        => $location['city'] ?? '',
				'state'       => $location['state'] ?? '',
				'postal_code' => $location['zip'] ?? '',
			);
		}

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'matches'       => array(),
				'match_count'   => 0,
				'error_message' => $response['error_message'] ?? 'Unknown error',
				'cost'          => 0,
			);
		}

		$data    = $response['data'] ?? array();
		$matches = $data['results'] ?? array();

		// Normalize match format
		$normalized_matches = array();
		foreach ( $matches as $match ) {
			$normalized_matches[] = array(
				'individual_id' => $match['screening_id'] ?? '',
				'first_name'    => $match['first_name'] ?? '',
				'last_name'     => $match['last_name'] ?? '',
				'city'          => $match['city'] ?? '',
				'state'         => $match['state'] ?? '',
				'confidence'    => $this->map_confidence( $match['confidence'] ?? '' ),
			);
		}

		return array(
			'success'     => true,
			'matches'     => $normalized_matches,
			'match_count' => count( $normalized_matches ),
			'cost'        => 0,
		);
	}

	/**
	 * Get API usage statistics
	 *
	 * @return array Usage statistics
	 */
	public function get_usage_stats() {
		$endpoint = '/screening/v1/usage';

		$response = $this->api_request( $endpoint, 'GET' );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
			);
		}

		$data = $response['data'] ?? array();

		// Enterprise licensing - return screenings count
		return array(
			'success'         => true,
			'calls_used'      => intval( $data['screenings_this_month'] ?? 0 ),
			'calls_limit'     => 999999, // No limit with enterprise
			'calls_remaining' => 999999,
			'reset_date'      => date( 'Y-m-01', strtotime( '+1 month' ) ),
			'total_cost'      => 0, // Enterprise licensing
		);
	}

	/**
	 * Validate configuration
	 *
	 * @return array Validation results
	 */
	public function validate_configuration() {
		$errors = array();

		if ( empty( $this->api_key ) ) {
			$errors[] = 'API key (subscription key) is required';
		}

		if ( empty( $this->access_token ) ) {
			$errors[] = 'Access token is required';
		}

		if ( empty( $this->environment_id ) ) {
			$errors[] = 'Environment ID is required';
		}

		if ( empty( $this->organization_id ) ) {
			$errors[] = 'Organization ID is required';
		}

		if ( ! empty( $errors ) ) {
			return array(
				'valid'         => false,
				'errors'        => $errors,
				'error_message' => implode( ', ', $errors ),
			);
		}

		// Test API connection
		$test = $this->get_usage_stats();

		if ( ! $test['success'] ) {
			return array(
				'valid'         => false,
				'errors'        => array( 'API connection failed' ),
				'error_message' => $test['error_message'] ?? 'Could not connect to Blackbaud API',
			);
		}

		return array(
			'valid'  => true,
			'errors' => array(),
		);
	}

	/**
	 * Calculate cost for operation
	 *
	 * Blackbaud uses enterprise licensing - no per-operation cost
	 *
	 * @param string $operation Operation type
	 * @param array  $params    Operation parameters
	 * @return float Estimated cost (always 0 for enterprise)
	 */
	public function calculate_cost( $operation, $params = array() ) {
		return 0; // Enterprise licensing
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method   HTTP method
	 * @param array  $body     Request body
	 * @return array Response data
	 */
	private function api_request( $endpoint, $method = 'GET', $body = array() ) {
		$url = $this->api_endpoint . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Bb-Api-Subscription-Key' => $this->api_key,
				'Authorization'            => 'Bearer ' . $this->access_token,
				'Content-Type'             => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'       => false,
				'error_message' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		// Handle token refresh if 401
		if ( 401 === $status_code && ! empty( $this->refresh_token ) ) {
			$refreshed = $this->refresh_access_token();
			if ( $refreshed ) {
				// Retry request with new token
				return $this->api_request( $endpoint, $method, $body );
			}
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'success'       => false,
				'error_message' => $data['message'] ?? 'API request failed',
				'status_code'   => $status_code,
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Refresh OAuth access token
	 *
	 * @return bool Whether refresh succeeded
	 */
	private function refresh_access_token() {
		// OAuth token refresh logic would go here
		// This would update $this->access_token and save to database
		return false;
	}

	/**
	 * Map Blackbaud capacity code to standard rating
	 *
	 * @param string $code Blackbaud capacity code
	 * @return string Standard rating (A+, A, B, C, D)
	 */
	private function map_capacity_code( $code ) {
		$mapping = array(
			'A1' => 'A+',
			'A2' => 'A+',
			'B1' => 'A',
			'B2' => 'A',
			'C1' => 'B',
			'C2' => 'B',
			'D1' => 'C',
			'D2' => 'C',
			'E1' => 'D',
			'E2' => 'D',
		);

		return $mapping[ $code ] ?? 'D';
	}

	/**
	 * Map Blackbaud confidence level to score
	 *
	 * @param string $confidence Confidence level (High, Medium, Low)
	 * @return int Confidence score (0-100)
	 */
	private function map_confidence( $confidence ) {
		$mapping = array(
			'High'   => 90,
			'Medium' => 60,
			'Low'    => 30,
		);

		return $mapping[ $confidence ] ?? 0;
	}

	/**
	 * Get capacity range for rating
	 *
	 * @param string $rating Capacity rating
	 * @return string Giving capacity range
	 */
	private function get_capacity_range( $rating ) {
		$ranges = array(
			'A+' => '$100K+',
			'A'  => '$50K-$100K',
			'B'  => '$10K-$50K',
			'C'  => '$1K-$10K',
			'D'  => 'Under $1K',
		);

		return $ranges[ $rating ] ?? 'Unknown';
	}
}
