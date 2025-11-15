<?php
/**
 * Little Green Light CRM Adapter
 *
 * Adapter for Little Green Light donor management integration.
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
 * NonprofitSuite_CRM_LittleGreenLight_Adapter Class
 *
 * Implements CRM integration using Little Green Light API.
 */
class NonprofitSuite_CRM_LittleGreenLight_Adapter implements NonprofitSuite_CRM_Adapter_Interface {

	/**
	 * Little Green Light API base URL
	 */
	const API_BASE_URL = 'https://api.littlegreenlight.com/api/v1/';

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
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'crm', 'littlegreenlight' );
		$this->api_key = $this->settings['api_key'] ?? '';
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
			'external_id'  => '', // LGL constituent_id if updating
		) );

		// Check if contact exists
		if ( ! empty( $contact['external_id'] ) ) {
			return $this->update_contact( $contact );
		}

		// Create new constituent
		$payload = array(
			'first_name' => $contact['first_name'],
			'last_name'  => $contact['last_name'],
			'email_addresses' => array(
				array(
					'email_address' => $contact['email'],
					'is_primary'    => true,
				),
			),
			'phone_numbers' => array(
				array(
					'phone_number' => $contact['phone'],
					'is_primary'   => true,
				),
			),
			'addresses' => array(
				array(
					'street'     => $contact['address'],
					'city'       => $contact['city'],
					'state'      => $contact['state'],
					'zip'        => $contact['zip'],
					'country'    => $contact['country'],
					'is_primary' => true,
				),
			),
		);

		if ( ! empty( $contact['organization'] ) ) {
			$payload['organization_name'] = $contact['organization'];
		}

		$response = $this->make_request( 'constituents', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'contact_id'  => $response['id'] ?? '',
			'external_id' => $response['id'] ?? '',
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
			'first_name' => $contact['first_name'],
			'last_name'  => $contact['last_name'],
		);

		if ( ! empty( $contact['email'] ) ) {
			$payload['email_addresses'] = array(
				array(
					'email_address' => $contact['email'],
					'is_primary'    => true,
				),
			);
		}

		if ( ! empty( $contact['phone'] ) ) {
			$payload['phone_numbers'] = array(
				array(
					'phone_number' => $contact['phone'],
					'is_primary'   => true,
				),
			);
		}

		$response = $this->make_request( 'constituents/' . $contact['external_id'], $payload, 'PUT' );

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
	 * @param string $contact_id Contact ID (LGL constituent_id)
	 * @return array|WP_Error
	 */
	public function get_contact( $contact_id ) {
		$response = $this->make_request( 'constituents/' . $contact_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$email = ! empty( $response['email_addresses'] ) ? $response['email_addresses'][0]['email_address'] : '';
		$phone = ! empty( $response['phone_numbers'] ) ? $response['phone_numbers'][0]['phone_number'] : '';
		$address = ! empty( $response['addresses'] ) ? $response['addresses'][0] : array();

		return array(
			'contact_id'   => $response['id'],
			'first_name'   => $response['first_name'] ?? '',
			'last_name'    => $response['last_name'] ?? '',
			'email'        => $email,
			'phone'        => $phone,
			'address'      => $address['street'] ?? '',
			'city'         => $address['city'] ?? '',
			'state'        => $address['state'] ?? '',
			'zip'          => $address['zip'] ?? '',
			'organization' => $response['organization_name'] ?? '',
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
			'external_id'  => '', // LGL gift_id if updating
		) );

		$payload = array(
			'constituent_id' => $donation['contact_id'],
			'amount'         => $donation['amount'],
			'gift_date'      => $donation['date'],
			'gift_type'      => $donation['payment_type'] ?: 'Cash',
			'note'           => $donation['memo'],
		);

		if ( ! empty( $donation['campaign'] ) ) {
			$payload['campaign_id'] = $donation['campaign'];
		}

		if ( ! empty( $donation['external_id'] ) ) {
			// Update existing gift
			$response = $this->make_request( 'gifts/' . $donation['external_id'], $payload, 'PUT' );
		} else {
			// Create new gift
			$response = $this->make_request( 'gifts', $payload, 'POST' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'donation_id' => $response['id'] ?? '',
			'external_id' => $response['id'] ?? '',
			'status'      => 'synced',
		);
	}

	/**
	 * Get donation
	 *
	 * @param string $donation_id Donation ID (LGL gift_id)
	 * @return array|WP_Error
	 */
	public function get_donation( $donation_id ) {
		$response = $this->make_request( 'gifts/' . $donation_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'donation_id' => $response['id'],
			'contact_id'  => $response['constituent_id'],
			'amount'      => $response['amount'],
			'date'        => $response['gift_date'],
			'campaign'    => $response['campaign_name'] ?? '',
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

		$params = array(
			'limit' => $criteria['limit'],
		);

		if ( ! empty( $criteria['email'] ) ) {
			$params['email'] = $criteria['email'];
		}

		if ( ! empty( $criteria['first_name'] ) ) {
			$params['first_name'] = $criteria['first_name'];
		}

		if ( ! empty( $criteria['last_name'] ) ) {
			$params['last_name'] = $criteria['last_name'];
		}

		$response = $this->make_request( 'constituents/search', $params, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contacts = array();
		if ( ! empty( $response['items'] ) ) {
			foreach ( $response['items'] as $item ) {
				$email = ! empty( $item['email_addresses'] ) ? $item['email_addresses'][0]['email_address'] : '';
				$phone = ! empty( $item['phone_numbers'] ) ? $item['phone_numbers'][0]['phone_number'] : '';

				$contacts[] = array(
					'contact_id' => $item['id'],
					'first_name' => $item['first_name'] ?? '',
					'last_name'  => $item['last_name'] ?? '',
					'email'      => $email,
					'phone'      => $phone,
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
		$response = $this->make_request( 'campaigns', array( 'limit' => 100 ), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$campaigns = array();
		if ( ! empty( $response['items'] ) ) {
			foreach ( $response['items'] as $campaign ) {
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
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'Little Green Light API key not configured', 'nonprofitsuite' ) );
		}

		// Test with constituent search (empty search)
		$response = $this->make_request( 'constituents/search', array( 'limit' => 1 ), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['items'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Little Green Light';
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
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) && ! empty( $data ) ) {
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
			$error_message = $body['error'] ?? $body['message'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'Little Green Light API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}
}
