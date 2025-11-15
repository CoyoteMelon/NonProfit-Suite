<?php
/**
 * Microsoft Teams Video Conferencing Adapter
 *
 * Integrates with Microsoft Teams using Microsoft Graph API.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Teams_Adapter implements NonprofitSuite_Video_Conferencing_Adapter {

	private $credentials;
	private $api_base = 'https://graph.microsoft.com/v1.0';

	public function __construct( $credentials = array() ) {
		$this->credentials = $credentials;
	}

	public function get_provider_name() {
		return 'teams';
	}

	public function get_display_name() {
		return 'Microsoft Teams';
	}

	public function uses_oauth() {
		return true;
	}

	public function test_connection( $credentials ) {
		$this->credentials = $credentials;
		$result = $this->api_request( 'GET', '/me' );
		return is_wp_error( $result ) ? $result : true;
	}

	public function create_meeting( $meeting_data ) {
		$online_meeting = array(
			'subject'    => $meeting_data['topic'],
			'startDateTime' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meeting_data['start_time'] ) ),
			'endDateTime'   => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meeting_data['start_time'] ) + ( isset( $meeting_data['duration'] ) ? $meeting_data['duration'] * 60 : 3600 ) ),
		);

		$result = $this->api_request( 'POST', '/me/onlineMeetings', $online_meeting );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'meeting_id'       => $result['id'],
			'meeting_url'      => $result['joinUrl'],
			'join_url'         => $result['joinUrl'],
			'meeting_password' => null,
		);
	}

	public function update_meeting( $meeting_id, $meeting_data ) {
		$online_meeting = array(
			'subject'    => $meeting_data['topic'],
			'startDateTime' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meeting_data['start_time'] ) ),
			'endDateTime'   => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meeting_data['start_time'] ) + ( isset( $meeting_data['duration'] ) ? $meeting_data['duration'] * 60 : 3600 ) ),
		);

		$result = $this->api_request( 'PATCH', "/me/onlineMeetings/{$meeting_id}", $online_meeting );
		return is_wp_error( $result ) ? $result : true;
	}

	public function delete_meeting( $meeting_id ) {
		$result = $this->api_request( 'DELETE', "/me/onlineMeetings/{$meeting_id}" );
		return is_wp_error( $result ) ? $result : true;
	}

	public function get_meeting( $meeting_id ) {
		return $this->api_request( 'GET', "/me/onlineMeetings/{$meeting_id}" );
	}

	public function list_meetings( $args = array() ) {
		$params = array(
			'$top' => isset( $args['limit'] ) ? $args['limit'] : 30,
		);

		$result = $this->api_request( 'GET', '/me/onlineMeetings?' . http_build_query( $params ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['value'] ) ? $result['value'] : array();
	}

	public function get_participants( $meeting_id ) {
		$result = $this->api_request( 'GET', "/me/onlineMeetings/{$meeting_id}/attendanceReports" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['value'] ) ? $result['value'] : array();
	}

	public function get_recordings( $meeting_id ) {
		// Teams recordings are stored in SharePoint/OneDrive
		// Would need separate Graph API calls
		return array();
	}

	private function api_request( $method, $endpoint, $data = array() ) {
		$access_token = isset( $this->credentials['oauth_token'] ) ? $this->credentials['oauth_token'] : '';

		if ( empty( $access_token ) ) {
			return new WP_Error( 'missing_token', 'Missing OAuth access token' );
		}

		$url = $this->api_base . $endpoint;

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
			return true;
		}

		$result = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = isset( $result['error']['message'] ) ? $result['error']['message'] : 'Unknown error';
			return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		return $result;
	}
}
