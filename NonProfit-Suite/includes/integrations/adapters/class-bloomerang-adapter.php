<?php
/**
 * Bloomerang CRM Adapter
 *
 * Integrates with Bloomerang donor management system using API Key authentication.
 * Bloomerang is a nonprofit-specific CRM focused on donor retention.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Bloomerang_Adapter implements NonprofitSuite_CRM_Adapter {

	/**
	 * API credentials.
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.bloomerang.co/v2';

	/**
	 * Constructor.
	 *
	 * @param array $credentials API credentials.
	 */
	public function __construct( $credentials = array() ) {
		$this->credentials = $credentials;
	}

	/**
	 * Get the CRM provider name.
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'bloomerang';
	}

	/**
	 * Get the display name.
	 *
	 * @return string
	 */
	public function get_display_name() {
		return 'Bloomerang';
	}

	/**
	 * Check if this adapter uses OAuth.
	 *
	 * Bloomerang uses API key authentication, not OAuth.
	 *
	 * @return bool
	 */
	public function uses_oauth() {
		return false;
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * Not applicable for Bloomerang.
	 *
	 * @param array $args Arguments.
	 * @return string|null
	 */
	public function get_oauth_url( $args = array() ) {
		return null;
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * Not applicable for Bloomerang.
	 *
	 * @param string $code Authorization code.
	 * @param array  $args Arguments.
	 * @return array|WP_Error
	 */
	public function exchange_oauth_code( $code, $args = array() ) {
		return new WP_Error( 'not_supported', 'Bloomerang uses API key authentication' );
	}

	/**
	 * Refresh OAuth token.
	 *
	 * Not applicable for Bloomerang.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array|WP_Error
	 */
	public function refresh_oauth_token( $refresh_token ) {
		return new WP_Error( 'not_supported', 'Bloomerang uses API key authentication' );
	}

	/**
	 * Test API connection.
	 *
	 * @param array $credentials Credentials.
	 * @return bool|WP_Error
	 */
	public function test_connection( $credentials ) {
		$this->credentials = $credentials;

		// Test by fetching account info
		$result = $this->api_request( 'GET', '/account' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get supported entity types.
	 *
	 * @return array
	 */
	public function get_supported_entities() {
		return array( 'contact', 'donation', 'activity' );
	}

	/**
	 * Get entity field schema.
	 *
	 * @param string $entity_type Entity type.
	 * @return array|WP_Error
	 */
	public function get_entity_fields( $entity_type ) {
		// Bloomerang doesn't have a field metadata endpoint, so return predefined schemas
		$schemas = array(
			'contact'  => array(
				array( 'name' => 'FirstName', 'label' => 'First Name', 'type' => 'string', 'required' => false, 'updateable' => true ),
				array( 'name' => 'LastName', 'label' => 'Last Name', 'type' => 'string', 'required' => true, 'updateable' => true ),
				array( 'name' => 'EmailAddress', 'label' => 'Email Address', 'type' => 'email', 'required' => false, 'updateable' => true ),
				array( 'name' => 'PhoneNumber', 'label' => 'Phone Number', 'type' => 'phone', 'required' => false, 'updateable' => true ),
				array( 'name' => 'Street', 'label' => 'Street', 'type' => 'string', 'required' => false, 'updateable' => true ),
				array( 'name' => 'City', 'label' => 'City', 'type' => 'string', 'required' => false, 'updateable' => true ),
				array( 'name' => 'State', 'label' => 'State', 'type' => 'string', 'required' => false, 'updateable' => true ),
				array( 'name' => 'PostalCode', 'label' => 'Postal Code', 'type' => 'string', 'required' => false, 'updateable' => true ),
			),
			'donation' => array(
				array( 'name' => 'Amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'updateable' => true ),
				array( 'name' => 'Date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'updateable' => true ),
				array( 'name' => 'FundId', 'label' => 'Fund ID', 'type' => 'number', 'required' => true, 'updateable' => true ),
				array( 'name' => 'Note', 'label' => 'Note', 'type' => 'text', 'required' => false, 'updateable' => true ),
			),
			'activity' => array(
				array( 'name' => 'Subject', 'label' => 'Subject', 'type' => 'string', 'required' => true, 'updateable' => true ),
				array( 'name' => 'Note', 'label' => 'Note', 'type' => 'text', 'required' => false, 'updateable' => true ),
				array( 'name' => 'Date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'updateable' => true ),
			),
		);

		return isset( $schemas[ $entity_type ] ) ? $schemas[ $entity_type ] : array();
	}

	/**
	 * Push a contact to Bloomerang.
	 *
	 * @param array $contact_data Contact data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_contact( $contact_data, $field_mappings ) {
		$mapped_data = $this->map_fields( $contact_data, $field_mappings );

		// Bloomerang requires Type to be set
		if ( ! isset( $mapped_data['Type'] ) ) {
			$mapped_data['Type'] = 'Individual';
		}

		$result = $this->api_request( 'POST', '/constituents', $mapped_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['Id'],
			'success' => true,
		);
	}

	/**
	 * Pull a contact from Bloomerang.
	 *
	 * @param string $crm_id Bloomerang constituent ID.
	 * @param array  $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function pull_contact( $crm_id, $field_mappings ) {
		$result = $this->api_request( 'GET', "/constituent/{$crm_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->reverse_map_fields( $result, $field_mappings );
	}

	/**
	 * Update a contact in Bloomerang.
	 *
	 * @param string $crm_id Bloomerang constituent ID.
	 * @param array  $contact_data Contact data.
	 * @param array  $field_mappings Field mappings.
	 * @return bool|WP_Error
	 */
	public function update_contact( $crm_id, $contact_data, $field_mappings ) {
		$mapped_data = $this->map_fields( $contact_data, $field_mappings );

		$result = $this->api_request( 'PUT', "/constituent/{$crm_id}", $mapped_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Delete a contact from Bloomerang.
	 *
	 * @param string $crm_id Bloomerang constituent ID.
	 * @return bool|WP_Error
	 */
	public function delete_contact( $crm_id ) {
		$result = $this->api_request( 'DELETE', "/constituent/{$crm_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Push a donation to Bloomerang.
	 *
	 * @param array $donation_data Donation data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_donation( $donation_data, $field_mappings ) {
		$mapped_data = $this->map_fields( $donation_data, $field_mappings );

		// Bloomerang requires ConstituentId and Method
		if ( ! isset( $mapped_data['Method'] ) ) {
			$mapped_data['Method'] = 'Cash';
		}

		$result = $this->api_request( 'POST', '/transaction', $mapped_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['Id'],
			'success' => true,
		);
	}

	/**
	 * Push a membership to Bloomerang.
	 *
	 * @param array $membership_data Membership data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_membership( $membership_data, $field_mappings ) {
		// Bloomerang doesn't have a separate membership object, use transactions
		return $this->push_donation( $membership_data, $field_mappings );
	}

	/**
	 * Push an activity to Bloomerang.
	 *
	 * @param array $activity_data Activity data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_activity( $activity_data, $field_mappings ) {
		$mapped_data = $this->map_fields( $activity_data, $field_mappings );

		$result = $this->api_request( 'POST', '/interaction', $mapped_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['Id'],
			'success' => true,
		);
	}

	/**
	 * Search for entities.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $criteria Search criteria.
	 * @param array  $args Arguments.
	 * @return array|WP_Error
	 */
	public function search( $entity_type, $criteria, $args = array() ) {
		$endpoint_map = array(
			'contact'  => '/constituents',
			'donation' => '/transactions',
		);

		$endpoint = isset( $endpoint_map[ $entity_type ] ) ? $endpoint_map[ $entity_type ] : '/constituents';

		// Build query parameters
		$params = array(
			'skip' => isset( $args['offset'] ) ? $args['offset'] : 0,
			'take' => isset( $args['limit'] ) ? $args['limit'] : 50,
		);

		// Add search criteria (Bloomerang uses specific search parameters)
		foreach ( $criteria as $key => $value ) {
			$params[ $key ] = $value;
		}

		$query_string = http_build_query( $params );
		$result = $this->api_request( 'GET', $endpoint . '?' . $query_string );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['Results'] ) ? $result['Results'] : $result;
	}

	/**
	 * Get changes since last sync.
	 *
	 * @param string   $entity_type Entity type.
	 * @param datetime $since Since date/time.
	 * @param array    $args Arguments.
	 * @return array|WP_Error
	 */
	public function get_changes_since( $entity_type, $since, $args = array() ) {
		$endpoint_map = array(
			'contact'  => '/constituents',
			'donation' => '/transactions',
		);

		$endpoint = isset( $endpoint_map[ $entity_type ] ) ? $endpoint_map[ $entity_type ] : '/constituents';

		$params = array(
			'lastModified' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $since ) ),
			'skip'         => 0,
			'take'         => isset( $args['limit'] ) ? $args['limit'] : 100,
		);

		$query_string = http_build_query( $params );
		$result = $this->api_request( 'GET', $endpoint . '?' . $query_string );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['Results'] ) ? $result['Results'] : $result;
	}

	/**
	 * Batch push entities.
	 *
	 * Bloomerang API doesn't support batch operations, so we'll push individually.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $entities Entities to push.
	 * @param array  $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function batch_push( $entity_type, $entities, $field_mappings ) {
		$results = array();

		foreach ( $entities as $entity ) {
			if ( $entity_type === 'contact' ) {
				$result = $this->push_contact( $entity, $field_mappings );
			} elseif ( $entity_type === 'donation' ) {
				$result = $this->push_donation( $entity, $field_mappings );
			} else {
				$result = $this->push_activity( $entity, $field_mappings );
			}

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * Get rate limit status.
	 *
	 * Bloomerang doesn't publicly document rate limits.
	 *
	 * @return array|null
	 */
	public function get_rate_limit_status() {
		return null;
	}

	/**
	 * Make an API request.
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @return array|WP_Error
	 */
	private function api_request( $method, $endpoint, $data = array() ) {
		$api_key = isset( $this->credentials['api_key'] ) ? $this->credentials['api_key'] : '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', 'Missing Bloomerang API key' );
		}

		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'X-API-Key'    => $api_key,
				'Content-Type' => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 204 || $code === 200 && empty( $body ) ) {
			return true; // Success with no content
		}

		$result = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = isset( $result['Message'] ) ? $result['Message'] : 'Unknown error';
			return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		return $result;
	}

	/**
	 * Map fields from NS format to Bloomerang format.
	 *
	 * @param array $data NS data.
	 * @param array $field_mappings Field mappings.
	 * @return array
	 */
	private function map_fields( $data, $field_mappings ) {
		$mapped = array();

		foreach ( $field_mappings as $mapping ) {
			if ( isset( $data[ $mapping['ns_field_name'] ] ) ) {
				$mapped[ $mapping['crm_field_name'] ] = $data[ $mapping['ns_field_name'] ];
			}
		}

		return $mapped;
	}

	/**
	 * Reverse map fields from Bloomerang to NS format.
	 *
	 * @param array $data Bloomerang data.
	 * @param array $field_mappings Field mappings.
	 * @return array
	 */
	private function reverse_map_fields( $data, $field_mappings ) {
		$mapped = array();

		foreach ( $field_mappings as $mapping ) {
			if ( isset( $data[ $mapping['crm_field_name'] ] ) ) {
				$mapped[ $mapping['ns_field_name'] ] = $data[ $mapping['crm_field_name'] ];
			}
		}

		return $mapped;
	}
}
