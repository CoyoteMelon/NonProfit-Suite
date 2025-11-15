<?php
/**
 * WealthEngine Adapter
 *
 * Integration with WealthEngine for donor wealth research and capacity ratings.
 * Provides comprehensive wealth indicators, philanthropic history, and giving capacity.
 *
 * API Documentation: https://api.wealthengine.com/
 * Pricing: $1-5 per lookup
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since 1.18.0
 */

namespace NonprofitSuite\Integrations\Adapters;

class NS_WealthEngine_Adapter implements NS_Wealth_Research_Adapter {

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

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
	 * @param array $config Configuration array with api_key, organization_id, etc.
	 */
	public function __construct( $config ) {
		$this->api_key         = $config['api_key'] ?? '';
		$this->api_endpoint    = $config['api_endpoint'] ?? 'https://api.wealthengine.com/v1';
		$this->organization_id = $config['organization_id'] ?? 0;
	}

	/**
	 * Screen individual for basic wealth indicators
	 *
	 * @param array $params Individual information
	 * @return array Screening results
	 */
	public function screen_individual( $params ) {
		$endpoint = '/profile/basic';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'line1'      => $params['address'] ?? '',
				'city'       => $params['city'] ?? '',
				'state'      => $params['state'] ?? '',
				'zip'        => $params['zip'] ?? '',
			),
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
			'individual_id'    => $data['individual_id'] ?? '',
			'giving_capacity'  => $this->map_capacity_rating( $data['wealth_capacity_rating'] ?? 0 ),
			'income_range'     => $this->format_income_range( $data['estimated_income'] ?? 0 ),
			'net_worth_range'  => $this->format_net_worth_range( $data['estimated_net_worth'] ?? 0 ),
			'confidence_score' => $data['confidence_code'] ?? 0,
			'cost'             => 2.50, // WealthEngine basic screen cost
		);
	}

	/**
	 * Get full profile for an individual
	 *
	 * @param array $params Individual identification parameters
	 * @return array Full profile data
	 */
	public function get_profile( $params ) {
		$endpoint = '/profile/full';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'line1'      => $params['address'] ?? '',
				'city'       => $params['city'] ?? '',
				'state'      => $params['state'] ?? '',
				'zip'        => $params['zip'] ?? '',
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
			'individual_id'  => $data['individual_id'] ?? '',
			'wealth_indicators' => array(
				'income_range'          => $this->format_income_range( $data['estimated_income'] ?? 0 ),
				'net_worth_range'       => $this->format_net_worth_range( $data['estimated_net_worth'] ?? 0 ),
				'real_estate_value'     => floatval( $data['real_estate_value'] ?? 0 ),
				'business_affiliations' => $data['business_affiliations'] ?? array(),
				'stock_holdings'        => $data['stock_holdings'] ?? array(),
			),
			'biographical' => array(
				'age_range'     => $data['age_range'] ?? '',
				'education'     => $data['education'] ?? array(),
				'professional'  => $data['employment_history'] ?? array(),
			),
			'social' => array(
				'social_media' => $data['social_profiles'] ?? array(),
				'interests'    => $data['interests'] ?? array(),
			),
			'cost' => 5.00, // WealthEngine full profile cost
		);
	}

	/**
	 * Get giving capacity rating
	 *
	 * @param array $params Individual identification parameters
	 * @return array Capacity rating results
	 */
	public function get_capacity_rating( $params ) {
		$endpoint = '/wealth/capacity';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'line1'      => $params['address'] ?? '',
				'city'       => $params['city'] ?? '',
				'state'      => $params['state'] ?? '',
				'zip'        => $params['zip'] ?? '',
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

		$capacity_rating = $this->map_capacity_rating( $data['wealth_capacity_rating'] ?? 0 );
		$capacity_range  = $this->get_capacity_range( $capacity_rating );

		return array(
			'success'          => true,
			'capacity_rating'  => $capacity_rating,
			'capacity_range'   => $capacity_range,
			'rating_factors'   => $data['rating_factors'] ?? array(),
			'confidence_score' => $data['confidence_code'] ?? 0,
			'cost'             => 3.00,
		);
	}

	/**
	 * Get philanthropic history
	 *
	 * @param array $params Individual identification parameters
	 * @return array Philanthropic history
	 */
	public function get_philanthropic_history( $params ) {
		$endpoint = '/philanthropy/history';

		$body = array(
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'line1'      => $params['address'] ?? '',
				'city'       => $params['city'] ?? '',
				'state'      => $params['state'] ?? '',
				'zip'        => $params['zip'] ?? '',
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
			'success'                  => true,
			'donations'                => $data['donations'] ?? array(),
			'board_affiliations'       => $data['board_memberships'] ?? array(),
			'political_contributions'  => floatval( $data['political_donations_total'] ?? 0 ),
			'political_parties'        => $data['political_affiliations'] ?? array(),
			'estimated_lifetime'       => floatval( $data['estimated_lifetime_giving'] ?? 0 ),
			'cost'                     => 4.00,
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
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'line1'      => $params['address'] ?? '',
				'city'       => $params['city'] ?? '',
				'state'      => $params['state'] ?? '',
				'zip'        => $params['zip'] ?? '',
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
			$total_value += floatval( $property['assessed_value'] ?? 0 );
		}

		return array(
			'success'        => true,
			'properties'     => $properties,
			'total_value'    => $total_value,
			'property_count' => count( $properties ),
			'cost'           => 3.50,
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
			'first_name' => $params['first_name'] ?? '',
			'last_name'  => $params['last_name'] ?? '',
			'address'    => array(
				'line1'      => $params['address'] ?? '',
				'city'       => $params['city'] ?? '',
				'state'      => $params['state'] ?? '',
				'zip'        => $params['zip'] ?? '',
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
			'affiliations' => $data['affiliations'] ?? array(),
			'cost'         => 3.00,
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
			'individual_id' => $data['individual_id'] ?? '',
			'basic_info'    => array(
				'first_name' => $data['first_name'] ?? '',
				'last_name'  => $data['last_name'] ?? '',
				'city'       => $data['city'] ?? '',
				'state'      => $data['state'] ?? '',
			),
			'cost' => 1.00,
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
			'first_name' => $first_name,
			'last_name'  => $last_name,
		);

		if ( ! empty( $location ) ) {
			$body['location'] = array(
				'city'  => $location['city'] ?? '',
				'state' => $location['state'] ?? '',
				'zip'   => $location['zip'] ?? '',
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
		$matches = $data['matches'] ?? array();

		return array(
			'success'     => true,
			'matches'     => $matches,
			'match_count' => count( $matches ),
			'cost'        => 1.50,
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
			'calls_used'      => intval( $data['calls_used'] ?? 0 ),
			'calls_limit'     => intval( $data['calls_limit'] ?? 0 ),
			'calls_remaining' => intval( $data['calls_remaining'] ?? 0 ),
			'reset_date'      => $data['reset_date'] ?? '',
			'total_cost'      => floatval( $data['total_cost'] ?? 0 ),
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
				'error_message' => $test['error_message'] ?? 'Could not connect to WealthEngine API',
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
			'screen'           => 2.50,
			'profile'          => 5.00,
			'capacity'         => 3.00,
			'philanthropy'     => 4.00,
			'real_estate'      => 3.50,
			'business'         => 3.00,
			'search_email'     => 1.00,
			'search_name'      => 1.50,
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

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
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
				'error_message' => $data['error'] ?? 'API request failed',
				'status_code'   => $status_code,
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Map WealthEngine capacity rating to standard rating
	 *
	 * @param int $we_rating WealthEngine rating (1-10)
	 * @return string Standard rating (A+, A, B, C, D)
	 */
	private function map_capacity_rating( $we_rating ) {
		if ( $we_rating >= 9 ) {
			return 'A+';
		} elseif ( $we_rating >= 7 ) {
			return 'A';
		} elseif ( $we_rating >= 5 ) {
			return 'B';
		} elseif ( $we_rating >= 3 ) {
			return 'C';
		} else {
			return 'D';
		}
	}

	/**
	 * Format income range
	 *
	 * @param int $income Estimated income
	 * @return string Formatted range
	 */
	private function format_income_range( $income ) {
		if ( $income >= 1000000 ) {
			return '$1M+';
		} elseif ( $income >= 500000 ) {
			return '$500K-$1M';
		} elseif ( $income >= 250000 ) {
			return '$250K-$500K';
		} elseif ( $income >= 100000 ) {
			return '$100K-$250K';
		} elseif ( $income >= 50000 ) {
			return '$50K-$100K';
		} else {
			return 'Under $50K';
		}
	}

	/**
	 * Format net worth range
	 *
	 * @param int $net_worth Estimated net worth
	 * @return string Formatted range
	 */
	private function format_net_worth_range( $net_worth ) {
		if ( $net_worth >= 10000000 ) {
			return '$10M+';
		} elseif ( $net_worth >= 5000000 ) {
			return '$5M-$10M';
		} elseif ( $net_worth >= 1000000 ) {
			return '$1M-$5M';
		} elseif ( $net_worth >= 500000 ) {
			return '$500K-$1M';
		} elseif ( $net_worth >= 100000 ) {
			return '$100K-$500K';
		} else {
			return 'Under $100K';
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
