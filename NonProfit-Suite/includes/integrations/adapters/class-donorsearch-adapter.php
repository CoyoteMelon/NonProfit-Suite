<?php
/**
 * DonorSearch Adapter
 *
 * Integration with DonorSearch for philanthropic history and political contribution research.
 * Specializes in historical giving data and peer screening.
 *
 * API Documentation: https://www.donorsearch.net/api-docs
 * Pricing: $2-8 per search
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since 1.18.0
 */

namespace NonprofitSuite\Integrations\Adapters;

class NS_DonorSearch_Adapter implements NS_Wealth_Research_Adapter {

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * API secret
	 *
	 * @var string
	 */
	private $api_secret;

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
	 * Constructor
	 *
	 * @param array $config Configuration array with api_key, api_secret, organization_id, etc.
	 */
	public function __construct( $config ) {
		$this->api_key         = $config['api_key'] ?? '';
		$this->api_secret      = $config['api_secret'] ?? '';
		$this->api_endpoint    = $config['api_endpoint'] ?? 'https://api.donorsearch.net/v2';
		$this->organization_id = $config['organization_id'] ?? 0;
	}

	/**
	 * Screen individual for basic wealth indicators
	 *
	 * @param array $params Individual information
	 * @return array Screening results
	 */
	public function screen_individual( $params ) {
		$endpoint = '/screening/basic';

		$body = array(
			'firstName' => $params['first_name'] ?? '',
			'lastName'  => $params['last_name'] ?? '',
			'city'      => $params['city'] ?? '',
			'state'     => $params['state'] ?? '',
			'zipCode'   => $params['zip'] ?? '',
		);

		if ( ! empty( $params['email'] ) ) {
			$body['email'] = $params['email'];
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
			'individual_id'    => $data['donorId'] ?? '',
			'giving_capacity'  => $this->map_capacity_score( $data['capacityScore'] ?? 0 ),
			'income_range'     => $data['estimatedIncome'] ?? 'Unknown',
			'net_worth_range'  => $data['estimatedNetWorth'] ?? 'Unknown',
			'confidence_score' => $data['confidenceScore'] ?? 0,
			'cost'             => 2.00, // DonorSearch basic screen cost
		);
	}

	/**
	 * Get full profile for an individual
	 *
	 * @param array $params Individual identification parameters
	 * @return array Full profile data
	 */
	public function get_profile( $params ) {
		$endpoint = '/profile/comprehensive';

		$body = array(
			'firstName' => $params['first_name'] ?? '',
			'lastName'  => $params['last_name'] ?? '',
			'city'      => $params['city'] ?? '',
			'state'     => $params['state'] ?? '',
			'zipCode'   => $params['zip'] ?? '',
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
			'individual_id'  => $data['donorId'] ?? '',
			'wealth_indicators' => array(
				'income_range'          => $data['estimatedIncome'] ?? 'Unknown',
				'net_worth_range'       => $data['estimatedNetWorth'] ?? 'Unknown',
				'real_estate_value'     => floatval( $data['realEstateTotal'] ?? 0 ),
				'business_affiliations' => $data['businessAffiliations'] ?? array(),
				'stock_holdings'        => $data['stockHoldings'] ?? array(),
			),
			'biographical' => array(
				'age_range'     => $data['ageRange'] ?? '',
				'education'     => $data['education'] ?? array(),
				'professional'  => $data['employment'] ?? array(),
			),
			'social' => array(
				'social_media' => array(), // DonorSearch doesn't provide social media
				'interests'    => $data['interests'] ?? array(),
			),
			'cost' => 8.00, // DonorSearch full profile cost
		);
	}

	/**
	 * Get giving capacity rating
	 *
	 * @param array $params Individual identification parameters
	 * @return array Capacity rating results
	 */
	public function get_capacity_rating( $params ) {
		$endpoint = '/capacity/rating';

		$body = array(
			'firstName' => $params['first_name'] ?? '',
			'lastName'  => $params['last_name'] ?? '',
			'city'      => $params['city'] ?? '',
			'state'     => $params['state'] ?? '',
			'zipCode'   => $params['zip'] ?? '',
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

		$capacity_rating = $this->map_capacity_score( $data['capacityScore'] ?? 0 );
		$capacity_range  = $this->get_capacity_range( $capacity_rating );

		return array(
			'success'          => true,
			'capacity_rating'  => $capacity_rating,
			'capacity_range'   => $capacity_range,
			'rating_factors'   => $data['ratingFactors'] ?? array(),
			'confidence_score' => $data['confidenceScore'] ?? 0,
			'cost'             => 3.00,
		);
	}

	/**
	 * Get philanthropic history
	 *
	 * THIS IS DONORSEARCH'S SPECIALTY - Most comprehensive philanthropic data
	 *
	 * @param array $params Individual identification parameters
	 * @return array Philanthropic history
	 */
	public function get_philanthropic_history( $params ) {
		$endpoint = '/philanthropy/comprehensive';

		$body = array(
			'firstName' => $params['first_name'] ?? '',
			'lastName'  => $params['last_name'] ?? '',
			'city'      => $params['city'] ?? '',
			'state'     => $params['state'] ?? '',
			'zipCode'   => $params['zip'] ?? '',
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
		foreach ( $data['donations'] ?? array() as $donation ) {
			$donations[] = array(
				'organization' => $donation['organizationName'] ?? '',
				'amount'       => floatval( $donation['amount'] ?? 0 ),
				'date'         => $donation['donationDate'] ?? '',
				'type'         => $donation['donationType'] ?? '',
			);
		}

		return array(
			'success'                  => true,
			'donations'                => $donations,
			'board_affiliations'       => $data['boardMemberships'] ?? array(),
			'political_contributions'  => floatval( $data['politicalTotal'] ?? 0 ),
			'political_parties'        => $data['politicalParties'] ?? array(),
			'estimated_lifetime'       => floatval( $data['lifetimeGiving'] ?? 0 ),
			'giving_patterns'          => $data['givingPatterns'] ?? array(), // DonorSearch specialty
			'peer_donations'           => $data['peerDonations'] ?? array(),  // Peer screening data
			'cost'                     => 5.00, // DonorSearch's best feature
		);
	}

	/**
	 * Get real estate holdings
	 *
	 * @param array $params Individual identification parameters
	 * @return array Real estate data
	 */
	public function get_real_estate_holdings( $params ) {
		$endpoint = '/wealth/real-estate';

		$body = array(
			'firstName' => $params['first_name'] ?? '',
			'lastName'  => $params['last_name'] ?? '',
			'city'      => $params['city'] ?? '',
			'state'     => $params['state'] ?? '',
			'zipCode'   => $params['zip'] ?? '',
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
			$total_value += floatval( $property['marketValue'] ?? 0 );
		}

		return array(
			'success'        => true,
			'properties'     => $properties,
			'total_value'    => $total_value,
			'property_count' => count( $properties ),
			'cost'           => 4.00,
		);
	}

	/**
	 * Get business affiliations
	 *
	 * @param array $params Individual identification parameters
	 * @return array Business affiliation data
	 */
	public function get_business_affiliations( $params ) {
		$endpoint = '/business/affiliations';

		$body = array(
			'firstName' => $params['first_name'] ?? '',
			'lastName'  => $params['last_name'] ?? '',
			'city'      => $params['city'] ?? '',
			'state'     => $params['state'] ?? '',
			'zipCode'   => $params['zip'] ?? '',
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
			'affiliations' => $data['businessAffiliations'] ?? array(),
			'cost'         => 3.50,
		);
	}

	/**
	 * Search by email address
	 *
	 * @param string $email Email address to search
	 * @return array Search results
	 */
	public function search_by_email( $email ) {
		$endpoint = '/search/email';

		$body = array(
			'email' => $email,
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
			'individual_id' => $data['donorId'] ?? '',
			'basic_info'    => array(
				'first_name' => $data['firstName'] ?? '',
				'last_name'  => $data['lastName'] ?? '',
				'city'       => $data['city'] ?? '',
				'state'      => $data['state'] ?? '',
			),
			'cost' => 1.50,
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
		$endpoint = '/search/name';

		$body = array(
			'firstName' => $first_name,
			'lastName'  => $last_name,
		);

		if ( ! empty( $location ) ) {
			$body['city']    = $location['city'] ?? '';
			$body['state']   = $location['state'] ?? '';
			$body['zipCode'] = $location['zip'] ?? '';
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
				'individual_id' => $match['donorId'] ?? '',
				'first_name'    => $match['firstName'] ?? '',
				'last_name'     => $match['lastName'] ?? '',
				'city'          => $match['city'] ?? '',
				'state'         => $match['state'] ?? '',
				'confidence'    => $match['matchScore'] ?? 0,
			);
		}

		return array(
			'success'     => true,
			'matches'     => $normalized_matches,
			'match_count' => count( $normalized_matches ),
			'cost'        => 2.00,
		);
	}

	/**
	 * Get API usage statistics
	 *
	 * @return array Usage statistics
	 */
	public function get_usage_stats() {
		$endpoint = '/account/usage';

		$response = $this->api_request( $endpoint, 'GET' );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Unknown error',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'         => true,
			'calls_used'      => intval( $data['searchesThisMonth'] ?? 0 ),
			'calls_limit'     => intval( $data['monthlyLimit'] ?? 0 ),
			'calls_remaining' => intval( $data['searchesRemaining'] ?? 0 ),
			'reset_date'      => $data['resetDate'] ?? '',
			'total_cost'      => floatval( $data['totalCost'] ?? 0 ),
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
			$errors[] = 'API key is required';
		}

		if ( empty( $this->api_secret ) ) {
			$errors[] = 'API secret is required';
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
				'error_message' => $test['error_message'] ?? 'Could not connect to DonorSearch API',
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
	 * @param string $operation Operation type
	 * @param array  $params    Operation parameters
	 * @return float Estimated cost
	 */
	public function calculate_cost( $operation, $params = array() ) {
		$costs = array(
			'screen'           => 2.00,
			'profile'          => 8.00,
			'capacity'         => 3.00,
			'philanthropy'     => 5.00, // DonorSearch's specialty
			'real_estate'      => 4.00,
			'business'         => 3.50,
			'search_email'     => 1.50,
			'search_name'      => 2.00,
		);

		return $costs[ $operation ] ?? 0;
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

		// Generate authentication signature
		$timestamp = time();
		$signature = $this->generate_signature( $endpoint, $timestamp );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'X-API-Key'       => $this->api_key,
				'X-Signature'     => $signature,
				'X-Timestamp'     => $timestamp,
				'Content-Type'    => 'application/json',
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
	 * Generate HMAC signature for request authentication
	 *
	 * @param string $endpoint  API endpoint
	 * @param int    $timestamp Unix timestamp
	 * @return string HMAC signature
	 */
	private function generate_signature( $endpoint, $timestamp ) {
		$message = $endpoint . $timestamp;
		return hash_hmac( 'sha256', $message, $this->api_secret );
	}

	/**
	 * Map DonorSearch capacity score to standard rating
	 *
	 * @param int $score DonorSearch capacity score (0-100)
	 * @return string Standard rating (A+, A, B, C, D)
	 */
	private function map_capacity_score( $score ) {
		if ( $score >= 90 ) {
			return 'A+';
		} elseif ( $score >= 75 ) {
			return 'A';
		} elseif ( $score >= 50 ) {
			return 'B';
		} elseif ( $score >= 25 ) {
			return 'C';
		} else {
			return 'D';
		}
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
