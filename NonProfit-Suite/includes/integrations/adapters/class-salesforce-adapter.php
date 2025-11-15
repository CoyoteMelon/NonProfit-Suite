<?php
/**
 * Salesforce Nonprofit Cloud (NPSP) CRM Adapter
 *
 * Integrates with Salesforce using OAuth 2.0 and REST API.
 * Supports Nonprofit Success Pack (NPSP) objects and conventions.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Salesforce_Adapter implements NonprofitSuite_CRM_Adapter {

	/**
	 * API credentials.
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * Salesforce instance URL.
	 *
	 * @var string
	 */
	private $instance_url;

	/**
	 * API version.
	 *
	 * @var string
	 */
	private $api_version = 'v58.0';

	/**
	 * Constructor.
	 *
	 * @param array $credentials API credentials.
	 */
	public function __construct( $credentials = array() ) {
		$this->credentials = $credentials;
		$this->instance_url = isset( $credentials['instance_url'] ) ? $credentials['instance_url'] : '';
	}

	/**
	 * Get the CRM provider name.
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'salesforce';
	}

	/**
	 * Get the display name.
	 *
	 * @return string
	 */
	public function get_display_name() {
		return 'Salesforce Nonprofit Cloud';
	}

	/**
	 * Check if this adapter uses OAuth.
	 *
	 * @return bool
	 */
	public function uses_oauth() {
		return true;
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @param array $args Arguments (redirect_uri, state).
	 * @return string
	 */
	public function get_oauth_url( $args = array() ) {
		$client_id = isset( $this->credentials['client_id'] ) ? $this->credentials['client_id'] : '';
		$redirect_uri = isset( $args['redirect_uri'] ) ? $args['redirect_uri'] : admin_url( 'admin.php?page=ns-crm-settings' );
		$state = isset( $args['state'] ) ? $args['state'] : wp_create_nonce( 'ns_salesforce_oauth' );

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
			'scope'         => 'api refresh_token',
		);

		return 'https://login.salesforce.com/services/oauth2/authorize?' . http_build_query( $params );
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
			'https://login.salesforce.com/services/oauth2/token',
			array(
				'body' => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
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
			'instance_url'  => $body['instance_url'],
			'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + 7200 ), // 2 hours
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
			'https://login.salesforce.com/services/oauth2/token',
			array(
				'body' => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
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
			'instance_url' => $body['instance_url'],
			'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + 7200 ),
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
		$this->instance_url = isset( $credentials['instance_url'] ) ? $credentials['instance_url'] : '';

		$result = $this->api_request( 'GET', '/sobjects/Contact/describe' );

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
		return array( 'contact', 'donation', 'membership', 'activity', 'household' );
	}

	/**
	 * Get entity field schema.
	 *
	 * @param string $entity_type Entity type.
	 * @return array|WP_Error
	 */
	public function get_entity_fields( $entity_type ) {
		$sobject_map = array(
			'contact'    => 'Contact',
			'donation'   => 'Opportunity',
			'membership' => 'npe03__Recurring_Donation__c',
			'activity'   => 'Task',
			'household'  => 'Account',
		);

		$sobject = isset( $sobject_map[ $entity_type ] ) ? $sobject_map[ $entity_type ] : 'Contact';

		$result = $this->api_request( 'GET', "/sobjects/{$sobject}/describe" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fields = array();
		foreach ( $result['fields'] as $field ) {
			$fields[] = array(
				'name'       => $field['name'],
				'label'      => $field['label'],
				'type'       => $field['type'],
				'required'   => ! $field['nillable'] && ! $field['defaultedOnCreate'],
				'updateable' => $field['updateable'],
			);
		}

		return $fields;
	}

	/**
	 * Push a contact to Salesforce.
	 *
	 * @param array $contact_data Contact data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_contact( $contact_data, $field_mappings ) {
		$sf_data = $this->map_fields( $contact_data, $field_mappings );

		$result = $this->api_request( 'POST', '/sobjects/Contact', $sf_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['id'],
			'success' => $result['success'],
		);
	}

	/**
	 * Pull a contact from Salesforce.
	 *
	 * @param string $crm_id Salesforce contact ID.
	 * @param array  $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function pull_contact( $crm_id, $field_mappings ) {
		$fields = array_column( $field_mappings, 'crm_field_name' );
		$fields_str = implode( ',', $fields );

		$result = $this->api_request( 'GET', "/sobjects/Contact/{$crm_id}?fields={$fields_str}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->reverse_map_fields( $result, $field_mappings );
	}

	/**
	 * Update a contact in Salesforce.
	 *
	 * @param string $crm_id Salesforce contact ID.
	 * @param array  $contact_data Contact data.
	 * @param array  $field_mappings Field mappings.
	 * @return bool|WP_Error
	 */
	public function update_contact( $crm_id, $contact_data, $field_mappings ) {
		$sf_data = $this->map_fields( $contact_data, $field_mappings );

		$result = $this->api_request( 'PATCH', "/sobjects/Contact/{$crm_id}", $sf_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Delete a contact from Salesforce.
	 *
	 * @param string $crm_id Salesforce contact ID.
	 * @return bool|WP_Error
	 */
	public function delete_contact( $crm_id ) {
		$result = $this->api_request( 'DELETE', "/sobjects/Contact/{$crm_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Push a donation to Salesforce.
	 *
	 * @param array $donation_data Donation data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_donation( $donation_data, $field_mappings ) {
		// In NPSP, donations are Opportunities
		$sf_data = $this->map_fields( $donation_data, $field_mappings );

		// NPSP required fields
		if ( ! isset( $sf_data['RecordTypeId'] ) ) {
			$sf_data['RecordTypeId'] = $this->get_donation_record_type_id();
		}
		if ( ! isset( $sf_data['StageName'] ) ) {
			$sf_data['StageName'] = 'Closed Won';
		}

		$result = $this->api_request( 'POST', '/sobjects/Opportunity', $sf_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['id'],
			'success' => $result['success'],
		);
	}

	/**
	 * Push a membership to Salesforce.
	 *
	 * @param array $membership_data Membership data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_membership( $membership_data, $field_mappings ) {
		// NPSP Recurring Donations for memberships
		$sf_data = $this->map_fields( $membership_data, $field_mappings );

		$result = $this->api_request( 'POST', '/sobjects/npe03__Recurring_Donation__c', $sf_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['id'],
			'success' => $result['success'],
		);
	}

	/**
	 * Push an activity to Salesforce.
	 *
	 * @param array $activity_data Activity data.
	 * @param array $field_mappings Field mappings.
	 * @return array|WP_Error
	 */
	public function push_activity( $activity_data, $field_mappings ) {
		$sf_data = $this->map_fields( $activity_data, $field_mappings );

		$result = $this->api_request( 'POST', '/sobjects/Task', $sf_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'crm_id'  => $result['id'],
			'success' => $result['success'],
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
		$sobject_map = array(
			'contact'  => 'Contact',
			'donation' => 'Opportunity',
		);

		$sobject = isset( $sobject_map[ $entity_type ] ) ? $sobject_map[ $entity_type ] : 'Contact';

		$where_clauses = array();
		foreach ( $criteria as $field => $value ) {
			$where_clauses[] = "{$field} = '{$value}'";
		}

		$where = implode( ' AND ', $where_clauses );
		$limit = isset( $args['limit'] ) ? $args['limit'] : 100;

		$soql = "SELECT Id, Name FROM {$sobject} WHERE {$where} LIMIT {$limit}";

		$result = $this->api_request( 'GET', '/query?q=' . urlencode( $soql ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['records'];
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
		$sobject_map = array(
			'contact'  => 'Contact',
			'donation' => 'Opportunity',
		);

		$sobject = isset( $sobject_map[ $entity_type ] ) ? $sobject_map[ $entity_type ] : 'Contact';

		$since_date = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $since ) );
		$soql = "SELECT Id, Name, LastModifiedDate FROM {$sobject} WHERE LastModifiedDate > {$since_date}";

		$result = $this->api_request( 'GET', '/query?q=' . urlencode( $soql ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['records'];
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
		$sobject_map = array(
			'contact'  => 'Contact',
			'donation' => 'Opportunity',
		);

		$sobject = isset( $sobject_map[ $entity_type ] ) ? $sobject_map[ $entity_type ] : 'Contact';

		$records = array();
		foreach ( $entities as $entity ) {
			$records[] = array(
				'attributes' => array( 'type' => $sobject ),
			) + $this->map_fields( $entity, $field_mappings );
		}

		$result = $this->api_request( 'POST', '/composite/sobjects', array( 'records' => $records ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Get rate limit status.
	 *
	 * @return array|null
	 */
	public function get_rate_limit_status() {
		$result = $this->api_request( 'GET', '/limits' );

		if ( is_wp_error( $result ) ) {
			return null;
		}

		if ( isset( $result['DailyApiRequests'] ) ) {
			return array(
				'limit'      => $result['DailyApiRequests']['Max'],
				'remaining'  => $result['DailyApiRequests']['Remaining'],
				'reset_time' => null, // Salesforce resets at midnight Pacific
			);
		}

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
		$access_token = isset( $this->credentials['access_token'] ) ? $this->credentials['access_token'] : '';

		if ( empty( $access_token ) || empty( $this->instance_url ) ) {
			return new WP_Error( 'missing_credentials', 'Missing access token or instance URL' );
		}

		$url = $this->instance_url . '/services/data/' . $this->api_version . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

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
			return true; // Success with no content (e.g., DELETE)
		}

		$result = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = isset( $result[0]['message'] ) ? $result[0]['message'] : 'Unknown error';
			return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		return $result;
	}

	/**
	 * Map fields from NS format to Salesforce format.
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
	 * Reverse map fields from Salesforce to NS format.
	 *
	 * @param array $data Salesforce data.
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

	/**
	 * Get the NPSP Donation record type ID.
	 *
	 * @return string|null
	 */
	private function get_donation_record_type_id() {
		$soql = "SELECT Id FROM RecordType WHERE SobjectType = 'Opportunity' AND DeveloperName = 'Donation' LIMIT 1";
		$result = $this->api_request( 'GET', '/query?q=' . urlencode( $soql ) );

		if ( is_wp_error( $result ) || empty( $result['records'] ) ) {
			return null;
		}

		return $result['records'][0]['Id'];
	}
}
