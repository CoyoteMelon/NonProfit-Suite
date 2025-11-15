<?php
/**
 * DonorPerfect CRM Adapter
 *
 * Adapter for DonorPerfect donor management integration.
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
 * NonprofitSuite_CRM_DonorPerfect_Adapter Class
 *
 * Implements CRM integration using DonorPerfect API.
 */
class NonprofitSuite_CRM_DonorPerfect_Adapter implements NonprofitSuite_CRM_Adapter_Interface {

	/**
	 * DonorPerfect API base URL
	 */
	const API_BASE_URL = 'https://www.donorperfect.net/prod/xmlrequest.asp';

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
	 * Login ID
	 *
	 * @var string
	 */
	private $login_id;

	/**
	 * Password
	 *
	 * @var string
	 */
	private $password;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'crm', 'donorperfect' );
		$this->api_key = $this->settings['api_key'] ?? '';
		$this->login_id = $this->settings['login_id'] ?? '';
		$this->password = $this->settings['password'] ?? '';
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
			'external_id'  => '', // DonorPerfect donor_id if updating
		) );

		// Check if contact exists
		if ( ! empty( $contact['external_id'] ) ) {
			return $this->update_contact( $contact );
		}

		// Create new donor
		$params = array(
			'first_name' => $contact['first_name'],
			'last_name'  => $contact['last_name'],
			'email'      => $contact['email'],
			'phone'      => $contact['phone'],
			'address'    => $contact['address'],
			'city'       => $contact['city'],
			'state'      => $contact['state'],
			'zip'        => $contact['zip'],
			'country'    => $contact['country'],
			'org_rec'    => $contact['organization'],
		);

		$response = $this->make_request( 'dp_savedonor', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'contact_id'  => $response['donor_id'] ?? '',
			'external_id' => $response['donor_id'] ?? '',
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
		$params = array(
			'donor_id'   => $contact['external_id'],
			'first_name' => $contact['first_name'],
			'last_name'  => $contact['last_name'],
			'email'      => $contact['email'],
			'phone'      => $contact['phone'],
			'address'    => $contact['address'],
			'city'       => $contact['city'],
			'state'      => $contact['state'],
			'zip'        => $contact['zip'],
		);

		$response = $this->make_request( 'dp_savedonor', $params );

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
	 * @param string $contact_id Contact ID (DonorPerfect donor_id)
	 * @return array|WP_Error
	 */
	public function get_contact( $contact_id ) {
		$params = array(
			'donor_id' => $contact_id,
		);

		$response = $this->make_request( 'dp_donor', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['record'] ) ) {
			return new WP_Error( 'not_found', __( 'Contact not found', 'nonprofitsuite' ) );
		}

		$donor = $response['record'];

		return array(
			'contact_id'   => $donor['donor_id'],
			'first_name'   => $donor['first_name'] ?? '',
			'last_name'    => $donor['last_name'] ?? '',
			'email'        => $donor['email'] ?? '',
			'phone'        => $donor['phone'] ?? '',
			'address'      => $donor['address'] ?? '',
			'city'         => $donor['city'] ?? '',
			'state'        => $donor['state'] ?? '',
			'zip'          => $donor['zip'] ?? '',
			'organization' => $donor['org_rec'] ?? '',
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
			'external_id'  => '', // DonorPerfect gift_id if updating
		) );

		$params = array(
			'donor_id'     => $donation['contact_id'],
			'amount'       => $donation['amount'],
			'gift_date'    => $donation['date'],
			'campaign'     => $donation['campaign'],
			'payment_type' => $donation['payment_type'],
			'memo'         => $donation['memo'],
		);

		if ( ! empty( $donation['external_id'] ) ) {
			$params['gift_id'] = $donation['external_id'];
		}

		$response = $this->make_request( 'dp_savegift', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'donation_id' => $response['gift_id'] ?? '',
			'external_id' => $response['gift_id'] ?? '',
			'status'      => 'synced',
		);
	}

	/**
	 * Get donation
	 *
	 * @param string $donation_id Donation ID (DonorPerfect gift_id)
	 * @return array|WP_Error
	 */
	public function get_donation( $donation_id ) {
		$params = array(
			'gift_id' => $donation_id,
		);

		$response = $this->make_request( 'dp_gift', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['record'] ) ) {
			return new WP_Error( 'not_found', __( 'Donation not found', 'nonprofitsuite' ) );
		}

		$gift = $response['record'];

		return array(
			'donation_id' => $gift['gift_id'],
			'contact_id'  => $gift['donor_id'],
			'amount'      => $gift['amount'],
			'date'        => $gift['gift_date'],
			'campaign'    => $gift['campaign'] ?? '',
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

		$where_clauses = array();

		if ( ! empty( $criteria['email'] ) ) {
			$where_clauses[] = "email = '" . esc_sql( $criteria['email'] ) . "'";
		}

		if ( ! empty( $criteria['first_name'] ) ) {
			$where_clauses[] = "first_name LIKE '%" . esc_sql( $criteria['first_name'] ) . "%'";
		}

		if ( ! empty( $criteria['last_name'] ) ) {
			$where_clauses[] = "last_name LIKE '%" . esc_sql( $criteria['last_name'] ) . "%'";
		}

		$where = ! empty( $where_clauses ) ? implode( ' AND ', $where_clauses ) : '1=1';

		$params = array(
			'table'  => 'dp',
			'fields' => 'donor_id,first_name,last_name,email,phone',
			'where'  => $where,
			'limit'  => $criteria['limit'],
		);

		$response = $this->make_request( 'dp_selectrecords', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contacts = array();
		if ( ! empty( $response['records'] ) ) {
			foreach ( $response['records'] as $record ) {
				$contacts[] = array(
					'contact_id' => $record['donor_id'],
					'first_name' => $record['first_name'] ?? '',
					'last_name'  => $record['last_name'] ?? '',
					'email'      => $record['email'] ?? '',
					'phone'      => $record['phone'] ?? '',
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
		$params = array(
			'table'  => 'dpcodes',
			'fields' => 'code,description',
			'where'  => "field_name = 'CAMPAIGN'",
		);

		$response = $this->make_request( 'dp_selectrecords', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$campaigns = array();
		if ( ! empty( $response['records'] ) ) {
			foreach ( $response['records'] as $record ) {
				$campaigns[] = array(
					'id'   => $record['code'],
					'name' => $record['description'],
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
		if ( empty( $this->api_key ) || empty( $this->login_id ) || empty( $this->password ) ) {
			return new WP_Error( 'not_configured', __( 'DonorPerfect credentials not configured', 'nonprofitsuite' ) );
		}

		// Test with a simple query
		$params = array(
			'table'  => 'dp',
			'fields' => 'donor_id',
			'where'  => '1=0', // Return no results, just test connection
			'limit'  => 1,
		);

		$response = $this->make_request( 'dp_selectrecords', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'DonorPerfect';
	}

	/**
	 * Make API request
	 *
	 * @param string $procedure API procedure name
	 * @param array  $params    Request parameters
	 * @return array|WP_Error
	 */
	private function make_request( $procedure, $params = array() ) {
		// Build XML request
		$xml = new SimpleXMLElement( '<request/>' );
		$xml->addChild( 'apikey', $this->api_key );
		$xml->addChild( 'action', $procedure );

		$record = $xml->addChild( 'record' );
		foreach ( $params as $key => $value ) {
			$field = $record->addChild( 'field' );
			$field->addAttribute( 'id', $key );
			$field[0] = $value;
		}

		$response = wp_remote_post( self::API_BASE_URL, array(
			'headers' => array(
				'Content-Type' => 'text/xml',
			),
			'body'    => $xml->asXML(),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'api_error', __( 'DonorPerfect API error', 'nonprofitsuite' ) );
		}

		// Parse XML response
		$result = $this->parse_xml_response( $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Parse XML response
	 *
	 * @param string $xml XML response body
	 * @return array|WP_Error
	 */
	private function parse_xml_response( $xml ) {
		try {
			$data = simplexml_load_string( $xml );

			if ( ! $data ) {
				return new WP_Error( 'parse_error', __( 'Failed to parse XML response', 'nonprofitsuite' ) );
			}

			// Check for errors
			if ( isset( $data->error ) ) {
				return new WP_Error( 'api_error', (string) $data->error );
			}

			// Convert XML to array
			$result = json_decode( wp_json_encode( $data ), true );

			return $result;
		} catch ( Exception $e ) {
			return new WP_Error( 'parse_error', $e->getMessage() );
		}
	}
}
