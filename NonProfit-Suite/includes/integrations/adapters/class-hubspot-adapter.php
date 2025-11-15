<?php
/**
 * HubSpot CRM Adapter
 *
 * Integrates with HubSpot CRM using OAuth 2.0 or API Key authentication.
 * Supports Contacts, Companies, Deals, and Activities.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_HubSpot_Adapter implements NonprofitSuite_CRM_Adapter {

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
	private $api_base = 'https://api.hubapi.com';

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
		return 'hubspot';
	}

	/**
	 * Get the display name.
	 *
	 * @return string
	 */
	public function get_display_name() {
		return 'HubSpot CRM';
	}

	/**
	 * Check if this adapter uses OAuth.
	 *
	 * @return bool
	 */
	public function uses_oauth() {
		return isset( $this->credentials['auth_type'] ) && $this->credentials['auth_type'] === 'oauth';
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @param array $args Arguments.
	 * @return string
	 */
	public function get_oauth_url( $args = array() ) {
		$client_id = isset( $this->credentials['client_id'] ) ? $this->credentials['client_id'] : '';
		$redirect_uri = isset( $args['redirect_uri'] ) ? $args['redirect_uri'] : admin_url( 'admin.php?page=ns-crm-settings' );
		$state = isset( $args['state'] ) ? $args['state'] : wp_create_nonce( 'ns_hubspot_oauth' );

		$params = array(
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'scope'        => 'crm.objects.contacts.read crm.objects.contacts.write crm.objects.companies.read crm.objects.companies.write crm.objects.deals.read crm.objects.deals.write',
			'state'        => $state,
		);

		return 'https://app.hubspot.com/oauth/authorize?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code Authorization code.
	 * @param array  $args Arguments.
	 * @return array|WP_Error
	 */
	public function exchange_oauth_code( $code, $args = array() ) {
		$client_id = isset( $this->credentials['client_id'] ) ? $this->credentials['client_id'] : '';
		$client_secret = isset( $this->credentials['client_secret'] ) ? $this->credentials['client_secret'] : '';
		$redirect_uri = isset( $args['redirect_uri'] ) ? $args['redirect_uri'] : admin_url( 'admin.php?page=ns-crm-settings' );

		$response = wp_remote_post(
			'https://api.hubapi.com/oauth/v1/token',
			array(
				'body' => array(
					'grant_type'    => 'authorization_code',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error_description'] );
		}

		return array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'],
			'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + $body['expires_in'] ),
		);
	}

	/**
	 * Refresh OAuth token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array|WP_Error
	 */
	public function refresh_oauth_token( $refresh_token ) {
		$client_id = isset( $this->credentials['client_id'] ) ? $this->credentials['client_id'] : '';
		$client_secret = isset( $this->credentials['client_secret'] ) ? $this->credentials['client_secret'] : '';

		$response = wp_remote_post(
			'https://api.hubapi.com/oauth/v1/token',
			array(
				'body' => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_refresh_error', $body['error_description'] );
		}

		return array(
			'access_token' => $body['access_token'],
			'refresh_token' => $body['refresh_token'],
			'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + $body['expires_in'] ),
		);
	}

	/**
	 * Test API connection.
	 *
	 * @param array $credentials Credentials.
	 * @return bool|WP_Error
	 */
	public function test_connection( $credentials ) {
		$this->credentials = $credentials;

		// Try to get contact properties to test connection
		$result = $this->api_request( 'GET', '/crm/v3/properties/contacts' );

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
		return array( 'contact', 'donation', 'activity', 'company' );
	}

	/**
	 * Get entity field schema.
	 *
	 * @param string $entity_type Entity type.
	 * @return array|WP_Error
	 */
	public function get_entity_fields( $entity_type ) {
		$object_map = array(
			'contact'  => 'contacts',
			'donation' => 'deals',
			'activity' => 'notes',
			'company'  => 'companies',
		);

		$object_type = isset( $object_map[ $entity_type ] ) ? $object_map[ $entity_type ] : 'contacts';

		$result = $this->api_request( 'GET', "/crm/v3/properties/{$object_type}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fields = array();
		foreach ( $result['results'] as $property ) {
			$fields[] = array(
				'name'       => $property['name'],
				'label'      => $property['label'],
				'type'       => $property['type'],
				'required'   => isset( $property['required'] ) && $property['required'],
				'updateable' => ! isset( $property['modificationMetadata']['readOnlyValue'] ) || ! $property['modificationMetadata']['readOnlyValue'],
			);
		}

		return $fields;
	}

	/**
	 * Push a contact to HubSpot.
	 *
	 * @param array $contact_data Contact data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_contact( $contact_data, $field_mappings ) {
		$properties = $this->map_fields( $contact_data, $field_mappings );

		$result = $this->api_request(
			'POST',
			'/crm/v3/objects/contacts',
			array( 'properties' => $properties )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['id'],
			'success' => true,
		);
	}

	/**
	 * Pull a contact from HubSpot.
	 *
	 * @param string $crm_id HubSpot contact ID.
	 * @param array  $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function pull_contact( $crm_id, $field_mappings ) {
		$properties = array_column( $field_mappings, 'crm_field_name' );
		$properties_str = implode( ',', $properties );

		$result = $this->api_request( 'GET', "/crm/v3/objects/contacts/{$crm_id}?properties={$properties_str}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->reverse_map_fields( $result['properties'], $field_mappings );
	}

	/**
	 * Update a contact in HubSpot.
	 *
	 * @param string $crm_id HubSpot contact ID.
	 * @param array  $contact_data Contact data.
	 * @param array  $field_mappings Field mappings.
	 * @return bool|WP_Error
	 */
	public function update_contact( $crm_id, $contact_data, $field_mappings ) {
		$properties = $this->map_fields( $contact_data, $field_mappings );

		$result = $this->api_request(
			'PATCH',
			"/crm/v3/objects/contacts/{$crm_id}",
			array( 'properties' => $properties )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Delete a contact from HubSpot.
	 *
	 * @param string $crm_id HubSpot contact ID.
	 * @return bool|WP_Error
	 */
	public function delete_contact( $crm_id ) {
		$result = $this->api_request( 'DELETE', "/crm/v3/objects/contacts/{$crm_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Push a donation to HubSpot.
	 *
	 * In HubSpot, donations are tracked as Deals.
	 *
	 * @param array $donation_data Donation data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_donation( $donation_data, $field_mappings ) {
		$properties = $this->map_fields( $donation_data, $field_mappings );

		// Set pipeline and stage if not already set
		if ( ! isset( $properties['pipeline'] ) ) {
			$properties['pipeline'] = 'default';
		}
		if ( ! isset( $properties['dealstage'] ) ) {
			$properties['dealstage'] = 'closedwon';
		}

		$result = $this->api_request(
			'POST',
			'/crm/v3/objects/deals',
			array( 'properties' => $properties )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['id'],
			'success' => true,
		);
	}

	/**
	 * Push a membership to HubSpot.
	 *
	 * @param array $membership_data Membership data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_membership( $membership_data, $field_mappings ) {
		// HubSpot doesn't have a native membership object, so use deals with custom properties
		return $this->push_donation( $membership_data, $field_mappings );
	}

	/**
	 * Push an activity to HubSpot.
	 *
	 * @param array $activity_data Activity data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_activity( $activity_data, $field_mappings ) {
		$properties = $this->map_fields( $activity_data, $field_mappings );

		$result = $this->api_request(
			'POST',
			'/crm/v3/objects/notes',
			array( 'properties' => $properties )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['id'],
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
		$object_map = array(
			'contact'  => 'contacts',
			'donation' => 'deals',
		);

		$object_type = isset( $object_map[ $entity_type ] ) ? $object_map[ $entity_type ] : 'contacts';

		$filters = array();
		foreach ( $criteria as $field => $value ) {
			$filters[] = array(
				'propertyName' => $field,
				'operator'     => 'EQ',
				'value'        => $value,
			);
		}

		$search_data = array(
			'filterGroups' => array(
				array(
					'filters' => $filters,
				),
			),
			'limit'        => isset( $args['limit'] ) ? $args['limit'] : 100,
		);

		$result = $this->api_request( 'POST', "/crm/v3/objects/{$object_type}/search", $search_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['results'];
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
		$object_map = array(
			'contact'  => 'contacts',
			'donation' => 'deals',
		);

		$object_type = isset( $object_map[ $entity_type ] ) ? $object_map[ $entity_type ] : 'contacts';

		$since_timestamp = strtotime( $since ) * 1000; // HubSpot uses milliseconds

		$search_data = array(
			'filterGroups' => array(
				array(
					'filters' => array(
						array(
							'propertyName' => 'hs_lastmodifieddate',
							'operator'     => 'GT',
							'value'        => $since_timestamp,
						),
					),
				),
			),
			'limit'        => isset( $args['limit'] ) ? $args['limit'] : 100,
		);

		$result = $this->api_request( 'POST', "/crm/v3/objects/{$object_type}/search", $search_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['results'];
	}

	/**
	 * Batch push entities.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $entities Entities to push.
	 * @param array  $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function batch_push( $entity_type, $entities, $field_mappings ) {
		$object_map = array(
			'contact'  => 'contacts',
			'donation' => 'deals',
		);

		$object_type = isset( $object_map[ $entity_type ] ) ? $object_map[ $entity_type ] : 'contacts';

		$inputs = array();
		foreach ( $entities as $entity ) {
			$inputs[] = array(
				'properties' => $this->map_fields( $entity, $field_mappings ),
			);
		}

		$result = $this->api_request( 'POST', "/crm/v3/objects/{$object_type}/batch/create", array( 'inputs' => $inputs ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['results'];
	}

	/**
	 * Get rate limit status.
	 *
	 * @return array|null
	 */
	public function get_rate_limit_status() {
		// HubSpot rate limits are returned in response headers
		// This would need to be tracked from the last API response
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
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 30,
		);

		// Authentication
		if ( $this->uses_oauth() && isset( $this->credentials['access_token'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->credentials['access_token'];
		} elseif ( isset( $this->credentials['api_key'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->credentials['api_key'];
		} else {
			return new WP_Error( 'missing_auth', 'Missing API authentication' );
		}

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 204 ) {
			return true; // Success with no content
		}

		$result = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = isset( $result['message'] ) ? $result['message'] : 'Unknown error';
			return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		return $result;
	}

	/**
	 * Map fields from NS format to HubSpot format.
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
	 * Reverse map fields from HubSpot to NS format.
	 *
	 * @param array $data HubSpot data.
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
