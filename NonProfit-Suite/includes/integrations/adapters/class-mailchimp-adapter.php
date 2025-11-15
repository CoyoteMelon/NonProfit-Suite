<?php
/**
 * Mailchimp Marketing Adapter
 *
 * Integrates with Mailchimp for email marketing campaigns.
 * Supports audiences, campaigns, and analytics.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Mailchimp_Adapter implements NonprofitSuite_Marketing_Adapter {

	private $credentials;
	private $api_base = 'https://<dc>.api.mailchimp.com/3.0';

	public function __construct( $credentials = array() ) {
		$this->credentials = $credentials;
		if ( ! empty( $credentials['server_prefix'] ) ) {
			$this->api_base = str_replace( '<dc>', $credentials['server_prefix'], $this->api_base );
		}
	}

	public function get_platform_name() {
		return 'mailchimp';
	}

	public function get_display_name() {
		return 'Mailchimp';
	}

	public function uses_oauth() {
		return false; // Mailchimp uses API keys
	}

	public function get_supported_types() {
		return array( 'email' );
	}

	public function test_connection( $credentials ) {
		$this->credentials = $credentials;
		$result = $this->api_request( 'GET', '/ping' );
		return is_wp_error( $result ) ? $result : true;
	}

	public function create_audience( $segment_data ) {
		$list_data = array(
			'name'                => $segment_data['segment_name'],
			'contact'             => array(
				'company'  => isset( $segment_data['company'] ) ? $segment_data['company'] : 'Organization',
				'address1' => isset( $segment_data['address'] ) ? $segment_data['address'] : '',
				'city'     => isset( $segment_data['city'] ) ? $segment_data['city'] : '',
				'state'    => isset( $segment_data['state'] ) ? $segment_data['state'] : '',
				'zip'      => isset( $segment_data['zip'] ) ? $segment_data['zip'] : '',
				'country'  => isset( $segment_data['country'] ) ? $segment_data['country'] : 'US',
			),
			'permission_reminder' => 'You are receiving this email because you subscribed to our mailing list.',
			'campaign_defaults'   => array(
				'from_name'  => isset( $this->credentials['from_name'] ) ? $this->credentials['from_name'] : 'Organization',
				'from_email' => isset( $this->credentials['from_email'] ) ? $this->credentials['from_email'] : 'noreply@example.org',
				'subject'    => '',
				'language'   => 'en',
			),
			'email_type_option'   => true,
		);

		$result = $this->api_request( 'POST', '/lists', $list_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'platform_list_id' => $result['id'],
			'success'          => true,
		);
	}

	public function add_contacts_to_audience( $platform_list_id, $contacts ) {
		$members = array();
		foreach ( $contacts as $contact ) {
			$members[] = array(
				'email_address' => $contact['email'],
				'status'        => 'subscribed',
				'merge_fields'  => array(
					'FNAME' => isset( $contact['first_name'] ) ? $contact['first_name'] : '',
					'LNAME' => isset( $contact['last_name'] ) ? $contact['last_name'] : '',
				),
			);
		}

		$result = $this->api_request( 'POST', "/lists/{$platform_list_id}", array( 'members' => $members, 'update_existing' => true ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'       => true,
			'total_created' => isset( $result['total_created'] ) ? $result['total_created'] : 0,
			'total_updated' => isset( $result['total_updated'] ) ? $result['total_updated'] : 0,
		);
	}

	public function remove_contact_from_audience( $platform_list_id, $email ) {
		$subscriber_hash = md5( strtolower( $email ) );
		$result = $this->api_request( 'DELETE', "/lists/{$platform_list_id}/members/{$subscriber_hash}" );
		return is_wp_error( $result ) ? $result : true;
	}

	public function create_campaign( $campaign_data ) {
		$mc_campaign = array(
			'type'       => 'regular',
			'recipients' => array(
				'list_id' => $campaign_data['platform_list_id'],
			),
			'settings'   => array(
				'subject_line' => $campaign_data['subject'],
				'preview_text' => isset( $campaign_data['preview_text'] ) ? $campaign_data['preview_text'] : '',
				'title'        => $campaign_data['campaign_name'],
				'from_name'    => isset( $this->credentials['from_name'] ) ? $this->credentials['from_name'] : 'Organization',
				'reply_to'     => isset( $this->credentials['reply_to_email'] ) ? $this->credentials['reply_to_email'] : $this->credentials['from_email'],
			),
		);

		$result = $this->api_request( 'POST', '/campaigns', $mc_campaign );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Set content
		$content_result = $this->api_request( 'PUT', "/campaigns/{$result['id']}/content", array( 'html' => $campaign_data['content'] ) );

		if ( is_wp_error( $content_result ) ) {
			return $content_result;
		}

		return array(
			'platform_campaign_id' => $result['id'],
			'success'              => true,
		);
	}

	public function send_campaign( $platform_campaign_id, $args = array() ) {
		$result = $this->api_request( 'POST', "/campaigns/{$platform_campaign_id}/actions/send" );
		return is_wp_error( $result ) ? $result : true;
	}

	public function get_campaign_stats( $platform_campaign_id ) {
		$result = $this->api_request( 'GET', "/reports/{$platform_campaign_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'emails_sent'    => $result['emails_sent'],
			'opens'          => $result['opens']['opens_total'],
			'unique_opens'   => $result['opens']['unique_opens'],
			'clicks'         => $result['clicks']['clicks_total'],
			'unique_clicks'  => $result['clicks']['unique_clicks'],
			'unsubscribed'   => $result['unsubscribed'],
			'bounces'        => $result['bounces']['hard_bounces'] + $result['bounces']['soft_bounces'],
			'open_rate'      => $result['opens']['open_rate'],
			'click_rate'     => $result['clicks']['click_rate'],
		);
	}

	public function send_transactional( $message_data ) {
		// Mailchimp doesn't support transactional emails via regular API
		// Would need Mandrill integration
		return new WP_Error( 'not_supported', 'Use Mandrill adapter for transactional emails' );
	}

	public function get_unsubscribed( $platform_list_id, $args = array() ) {
		$params = array(
			'status' => 'unsubscribed',
			'count'  => isset( $args['limit'] ) ? $args['limit'] : 100,
		);

		$result = $this->api_request( 'GET', "/lists/{$platform_list_id}/members?" . http_build_query( $params ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$unsubscribed = array();
		foreach ( $result['members'] as $member ) {
			$unsubscribed[] = $member['email_address'];
		}

		return $unsubscribed;
	}

	public function get_campaign_events( $platform_campaign_id, $args = array() ) {
		$event_type = isset( $args['event_type'] ) ? $args['event_type'] : 'opened';
		$endpoint_map = array(
			'opened'  => "/reports/{$platform_campaign_id}/open-details",
			'clicked' => "/reports/{$platform_campaign_id}/click-details",
		);

		$endpoint = isset( $endpoint_map[ $event_type ] ) ? $endpoint_map[ $event_type ] : $endpoint_map['opened'];
		$result = $this->api_request( 'GET', $endpoint );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['members'] ) ? $result['members'] : array();
	}

	public function get_rate_limit_status() {
		// Mailchimp doesn't publicly expose rate limits
		return null;
	}

	private function api_request( $method, $endpoint, $data = array() ) {
		$api_key = isset( $this->credentials['api_key'] ) ? $this->credentials['api_key'] : '';

		if ( empty( $api_key ) || strpos( $this->api_base, '<dc>' ) !== false ) {
			return new WP_Error( 'missing_credentials', 'Missing API key or server prefix' );
		}

		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 204 ) {
			return true;
		}

		$result = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = isset( $result['detail'] ) ? $result['detail'] : 'Unknown error';
			return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		return $result;
	}
}
