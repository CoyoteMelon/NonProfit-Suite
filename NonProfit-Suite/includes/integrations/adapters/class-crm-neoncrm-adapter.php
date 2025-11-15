<?php
/**
 * NeonCRM Adapter
 *
 * Adapter for NeonCRM donor management integration.
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
 * NonprofitSuite_CRM_NeonCRM_Adapter Class
 *
 * Implements CRM integration using NeonCRM API.
 */
class NonprofitSuite_CRM_NeonCRM_Adapter implements NonprofitSuite_CRM_Adapter_Interface {

	/**
	 * NeonCRM API base URL
	 */
	const API_BASE_URL = 'https://api.neoncrm.com/v2/';

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Organization ID
	 *
	 * @var string
	 */
	private $org_id;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'crm', 'neoncrm' );
		$this->api_key = $this->settings['api_key'] ?? '';
		$this->org_id = $this->settings['org_id'] ?? '';
	}

	/**
	 * Sync contact
	 *
	 * @param array $contact Contact data
	 * @return array|WP_Error
	 */
	public function sync_contact( $contact ) {
		$contact = wp_parse_args( $contact, array(
			'first_name'   => '',
			'last_name'    => '',
			'email'        => '',
			'phone'        => '',
			'address'      => '',
			'city'         => '',
			'state'        => '',
			'zip'          => '',
			'country'      => '',
			'organization' => '',
			'external_id'  => '', // NeonCRM account_id if updating
		) );

		// Check if contact exists
		if ( ! empty( $contact['external_id'] ) ) {
			return $this->update_contact( $contact );
		}

		// Create new account
		$payload = array(
			'individualAccount' => array(
				'primaryContact' => array(
					'firstName' => $contact['first_name'],
					'lastName'  => $contact['last_name'],
					'email1'    => $contact['email'],
					'phone1'    => $contact['phone'],
					'addresses' => array(
						array(
							'addressLine1' => $contact['address'],
							'city'         => $contact['city'],
							'stateProvince' => array( 'code' => $contact['state'] ),
							'zipCode'      => $contact['zip'],
							'country'      => array( 'name' => $contact['country'] ),
						),
					),
				),
			),
		);

		if ( ! empty( $contact['organization'] ) ) {
			$payload['individualAccount']['primaryContact']['companyName'] = $contact['organization'];
		}

		$response = $this->make_request( 'accounts', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'contact_id'  => $response['accountId'] ?? '',
			'external_id' => $response['accountId'] ?? '',
			'status'      => 'synced',
		);
	}

	/**
	 * Update contact
	 *
	 * @param array $contact Contact data
	 * @return array|WP_Error
	 */
	private function update_contact( $contact ) {
		$payload = array(
			'individualAccount' => array(
				'primaryContact' => array(
					'firstName' => $contact['first_name'],
					'lastName'  => $contact['last_name'],
					'email1'    => $contact['email'],
					'phone1'    => $contact['phone'],
				),
			),
		);

		$response = $this->make_request( 'accounts/' . $contact['external_id'], $payload, 'PATCH' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'contact_id'  => $contact['external_id'],
			'external_id' => $contact['external_id'],
			'status'      => 'synced',
		);
	}

	/**
	 * Get contact
	 *
	 * @param string $contact_id Contact ID (NeonCRM account_id)
	 * @return array|WP_Error
	 */
	public function get_contact( $contact_id ) {
		$response = $this->make_request( 'accounts/' . $contact_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$account = $response['individualAccount'] ?? array();
		$primary = $account['primaryContact'] ?? array();
		$address = $primary['addresses'][0] ?? array();

		return array(
			'contact_id'   => $response['accountId'],
			'first_name'   => $primary['firstName'] ?? '',
			'last_name'    => $primary['lastName'] ?? '',
			'email'        => $primary['email1'] ?? '',
			'phone'        => $primary['phone1'] ?? '',
			'address'      => $address['addressLine1'] ?? '',
			'city'         => $address['city'] ?? '',
			'state'        => $address['stateProvince']['code'] ?? '',
			'zip'          => $address['zipCode'] ?? '',
			'organization' => $primary['companyName'] ?? '',
		);
	}

	/**
	 * Sync donation
	 *
	 * @param array $donation Donation data
	 * @return array|WP_Error
	 */
	public function sync_donation( $donation ) {
		$donation = wp_parse_args( $donation, array(
			'contact_id'   => '',
			'amount'       => 0,
			'date'         => date( 'Y-m-d' ),
			'campaign'     => '',
			'payment_type' => '',
			'memo'         => '',
			'external_id'  => '', // NeonCRM donation_id if updating
		) );

		$payload = array(
			'accountId' => $donation['contact_id'],
			'amount'    => $donation['amount'],
			'date'      => $donation['date'],
			'campaign'  => array( 'id' => $donation['campaign'] ),
			'payments'  => array(
				array(
					'amount' => $donation['amount'],
					'tenderType' => array( 'name' => $donation['payment_type'] ),
					'received' => $donation['date'],
				),
			),
			'note'      => $donation['memo'],
		);

		if ( ! empty( $donation['external_id'] ) ) {
			// Update existing donation
			$response = $this->make_request( 'donations/' . $donation['external_id'], $payload, 'PATCH' );
		} else {
			// Create new donation
			$response = $this->make_request( 'donations', $payload, 'POST' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'donation_id' => $response['donationId'] ?? '',
			'external_id' => $response['donationId'] ?? '',
			'status'      => 'synced',
		);
	}

	/**
	 * Get donation
	 *
	 * @param string $donation_id Donation ID (NeonCRM donation_id)
	 * @return array|WP_Error
	 */
	public function get_donation( $donation_id ) {
		$response = $this->make_request( 'donations/' . $donation_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'donation_id' => $response['donationId'],
			'contact_id'  => $response['accountId'],
			'amount'      => $response['amount'],
			'date'        => $response['date'],
			'campaign'    => $response['campaign']['name'] ?? '',
		);
	}

	/**
	 * Search contacts
	 *
	 * @param array $criteria Search criteria
	 * @return array|WP_Error
	 */
	public function search_contacts( $criteria = array() ) {
		$criteria = wp_parse_args( $criteria, array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'limit'      => 50,
		) );

		$search_fields = array();

		if ( ! empty( $criteria['email'] ) ) {
			$search_fields[] = array(
				'field'    => 'Email',
				'operator' => 'EQUAL',
				'value'    => $criteria['email'],
			);
		}

		if ( ! empty( $criteria['first_name'] ) ) {
			$search_fields[] = array(
				'field'    => 'First Name',
				'operator' => 'CONTAIN',
				'value'    => $criteria['first_name'],
			);
		}

		if ( ! empty( $criteria['last_name'] ) ) {
			$search_fields[] = array(
				'field'    => 'Last Name',
				'operator' => 'CONTAIN',
				'value'    => $criteria['last_name'],
			);
		}

		$payload = array(
			'searchFields' => $search_fields,
			'pagination'   => array(
				'currentPage' => 0,
				'pageSize'    => $criteria['limit'],
			),
		);

		$response = $this->make_request( 'accounts/search', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contacts = array();
		if ( ! empty( $response['searchResults'] ) ) {
			foreach ( $response['searchResults'] as $result ) {
				$primary = $result['individualAccount']['primaryContact'] ?? array();
				$contacts[] = array(
					'contact_id' => $result['accountId'],
					'first_name' => $primary['firstName'] ?? '',
					'last_name'  => $primary['lastName'] ?? '',
					'email'      => $primary['email1'] ?? '',
					'phone'      => $primary['phone1'] ?? '',
				);
			}
		}

		return $contacts;
	}

	/**
	 * Get campaigns
	 *
	 * @return array|WP_Error
	 */
	public function get_campaigns() {
		$response = $this->make_request( 'campaigns', array( 'pageSize' => 100 ), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$campaigns = array();
		if ( ! empty( $response['campaigns'] ) ) {
			foreach ( $response['campaigns'] as $campaign ) {
				$campaigns[] = array(
					'id'   => $campaign['id'],
					'name' => $campaign['name'],
				);
			}
		}

		return $campaigns;
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) || empty( $this->org_id ) ) {
			return new WP_Error( 'not_configured', __( 'NeonCRM credentials not configured', 'nonprofitsuite' ) );
		}

		// Test with account search (empty search)
		$payload = array(
			'searchFields' => array(),
			'pagination'   => array(
				'currentPage' => 0,
				'pageSize'    => 1,
			),
		);

		$response = $this->make_request( 'accounts/search', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['pagination'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'NeonCRM';
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint Endpoint
	 * @param array  $data     Request data
	 * @param string $method   HTTP method
	 * @return array|WP_Error
	 */
	private function make_request( $endpoint, $data = array(), $method = 'GET' ) {
		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization'   => 'Basic ' . base64_encode( $this->org_id . ':' . $this->api_key ),
				'Content-Type'    => 'application/json',
				'NEON-API-VERSION' => '2.1',
			),
			'timeout' => 30,
		);

		if ( in_array( $method, array( 'POST', 'PATCH', 'PUT' ) ) && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		} elseif ( $method === 'GET' && ! empty( $data ) ) {
			$url .= '?' . http_build_query( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$error_message = $body['message'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'NeonCRM API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}
}
