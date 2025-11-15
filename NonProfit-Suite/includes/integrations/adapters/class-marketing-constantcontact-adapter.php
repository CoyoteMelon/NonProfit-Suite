<?php
/**
 * Constant Contact Marketing Adapter
 *
 * Adapter for Constant Contact email marketing integration.
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
 * NonprofitSuite_Marketing_ConstantContact_Adapter Class
 *
 * Implements marketing integration using Constant Contact API v3.
 */
class NonprofitSuite_Marketing_ConstantContact_Adapter implements NonprofitSuite_Marketing_Adapter_Interface {

	/**
	 * Constant Contact API base URL
	 */
	const API_BASE_URL = 'https://api.cc.email/v3/';
	const AUTH_URL = 'https://authz.constantcontact.com/oauth2/default/v1/authorize';
	const TOKEN_URL = 'https://authz.constantcontact.com/oauth2/default/v1/token';

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Access token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'marketing', 'constantcontact' );
		$this->access_token = $this->settings['access_token'] ?? '';
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @param string $redirect_uri Redirect URI after authorization
	 * @return string
	 */
	public function get_auth_url( $redirect_uri = '' ) {
		$params = array(
			'client_id'     => $this->settings['client_id'] ?? '',
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => 'contact_data campaign_data offline_access',
			'state'         => wp_create_nonce( 'constantcontact_auth' ),
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code
	 * @return bool|WP_Error
	 */
	public function handle_oauth_callback( $code ) {
		$response = wp_remote_post( self::TOKEN_URL, array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $this->settings['redirect_uri'] ?? '',
				'client_id'    => $this->settings['client_id'] ?? '',
				'client_secret' => $this->settings['client_secret'] ?? '',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$this->settings['access_token'] = $body['access_token'];
			$this->settings['refresh_token'] = $body['refresh_token'];
			$this->settings['token_expiry'] = time() + $body['expires_in'];

			// Update settings
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$manager->update_provider_settings( 'marketing', 'constantcontact', $this->settings );

			$this->access_token = $body['access_token'];

			return true;
		}

		return new WP_Error( 'oauth_failed', __( 'Failed to obtain access token', 'nonprofitsuite' ) );
	}

	/**
	 * Refresh access token
	 *
	 * @return bool|WP_Error
	 */
	private function refresh_access_token() {
		if ( empty( $this->settings['refresh_token'] ) ) {
			return new WP_Error( 'no_refresh_token', __( 'No refresh token available', 'nonprofitsuite' ) );
		}

		$response = wp_remote_post( self::TOKEN_URL, array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $this->settings['refresh_token'],
				'client_id'     => $this->settings['client_id'] ?? '',
				'client_secret' => $this->settings['client_secret'] ?? '',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$this->settings['access_token'] = $body['access_token'];
			$this->settings['refresh_token'] = $body['refresh_token'];
			$this->settings['token_expiry'] = time() + $body['expires_in'];

			// Update settings
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$manager->update_provider_settings( 'marketing', 'constantcontact', $this->settings );

			$this->access_token = $body['access_token'];

			return true;
		}

		return new WP_Error( 'refresh_failed', __( 'Failed to refresh access token', 'nonprofitsuite' ) );
	}

	/**
	 * Add subscriber
	 *
	 * @param array $subscriber Subscriber data
	 * @return array|WP_Error
	 */
	public function add_subscriber( $subscriber ) {
		// Check token expiry
		if ( ! empty( $this->settings['token_expiry'] ) && time() > $this->settings['token_expiry'] - 300 ) {
			$this->refresh_access_token();
		}

		$subscriber = wp_parse_args( $subscriber, array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'lists'      => array(),
			'tags'       => array(),
		) );

		$payload = array(
			'email_address' => array(
				'address' => $subscriber['email'],
			),
			'first_name'    => $subscriber['first_name'],
			'last_name'     => $subscriber['last_name'],
			'list_memberships' => array_map( function( $list_id ) {
				return $list_id;
			}, $subscriber['lists'] ),
		);

		$response = $this->make_request( 'contacts', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'subscriber_id' => $response['contact_id'] ?? '',
			'status'        => 'subscribed',
		);
	}

	/**
	 * Remove subscriber
	 *
	 * @param string $email Email address
	 * @return bool|WP_Error
	 */
	public function remove_subscriber( $email ) {
		// Find contact by email
		$contact = $this->get_subscriber( $email );

		if ( is_wp_error( $contact ) ) {
			return $contact;
		}

		// Delete contact
		$response = $this->make_request( 'contacts/' . $contact['subscriber_id'], array(), 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get subscriber
	 *
	 * @param string $email Email address
	 * @return array|WP_Error
	 */
	public function get_subscriber( $email ) {
		$params = array(
			'email' => $email,
			'status' => 'all',
		);

		$response = $this->make_request( 'contacts', $params, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['contacts'] ) ) {
			return new WP_Error( 'not_found', __( 'Subscriber not found', 'nonprofitsuite' ) );
		}

		$contact = $response['contacts'][0];

		return array(
			'subscriber_id' => $contact['contact_id'],
			'email'         => $contact['email_address']['address'],
			'first_name'    => $contact['first_name'] ?? '',
			'last_name'     => $contact['last_name'] ?? '',
			'status'        => $contact['email_address']['permission_to_send'] ?? '',
		);
	}

	/**
	 * Get lists
	 *
	 * @return array|WP_Error
	 */
	public function get_lists() {
		$response = $this->make_request( 'contact_lists', array( 'limit' => 100 ), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$lists = array();
		if ( ! empty( $response['lists'] ) ) {
			foreach ( $response['lists'] as $list ) {
				$lists[] = array(
					'id'   => $list['list_id'],
					'name' => $list['name'],
				);
			}
		}

		return $lists;
	}

	/**
	 * Create campaign
	 *
	 * @param array $campaign Campaign data
	 * @return array|WP_Error
	 */
	public function create_campaign( $campaign ) {
		$campaign = wp_parse_args( $campaign, array(
			'name'         => '',
			'subject'      => '',
			'from_name'    => '',
			'from_email'   => '',
			'reply_to'     => '',
			'html_content' => '',
			'text_content' => '',
			'list_ids'     => array(),
		) );

		$payload = array(
			'name'              => $campaign['name'],
			'email_campaign_activities' => array(
				array(
					'format_type'      => 5, // Custom HTML
					'from_name'        => $campaign['from_name'],
					'from_email'       => $campaign['from_email'],
					'reply_to_email'   => $campaign['reply_to'],
					'subject'          => $campaign['subject'],
					'html_content'     => $campaign['html_content'],
					'physical_address_in_footer' => array(
						'address_optional' => true,
					),
				),
			),
		);

		$response = $this->make_request( 'emails', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'campaign_id' => $response['campaign_id'] ?? '',
			'status'      => 'draft',
		);
	}

	/**
	 * Send campaign
	 *
	 * @param string $campaign_id Campaign ID
	 * @return bool|WP_Error
	 */
	public function send_campaign( $campaign_id ) {
		// Schedule campaign to send immediately
		$payload = array(
			'scheduled_date' => gmdate( 'c' ), // ISO 8601 format
		);

		$response = $this->make_request( 'emails/activities/' . $campaign_id . '/schedules', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get campaign stats
	 *
	 * @param string $campaign_id Campaign ID
	 * @return array|WP_Error
	 */
	public function get_campaign_stats( $campaign_id ) {
		$response = $this->make_request( 'reports/email_reports/' . $campaign_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'sent'      => $response['stats']['em_sends'] ?? 0,
			'opens'     => $response['stats']['em_opens'] ?? 0,
			'clicks'    => $response['stats']['em_clicks'] ?? 0,
			'bounces'   => $response['stats']['em_bounces'] ?? 0,
			'unsubscribes' => $response['stats']['em_optouts'] ?? 0,
		);
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->access_token ) ) {
			return new WP_Error( 'not_configured', __( 'Constant Contact not connected. Please authenticate.', 'nonprofitsuite' ) );
		}

		// Test with lists request
		$response = $this->make_request( 'contact_lists', array( 'limit' => 1 ), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['lists'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Constant Contact';
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
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
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

		if ( $code === 401 ) {
			// Token expired, try to refresh
			$refresh_result = $this->refresh_access_token();
			if ( ! is_wp_error( $refresh_result ) ) {
				// Retry request with new token
				return $this->make_request( $endpoint, $data, $method );
			}
			return $refresh_result;
		}

		if ( $code < 200 || $code >= 300 ) {
			$error_message = $body[0]['error_message'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'Constant Contact API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}
}
