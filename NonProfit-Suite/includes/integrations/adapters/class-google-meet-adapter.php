<?php
/**
 * Google Meet Video Conferencing Adapter
 *
 * Integrates with Google Meet using Google Calendar API.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Google_Meet_Adapter implements NonprofitSuite_Video_Conferencing_Adapter {

	private $credentials;
	private $api_base = 'https://www.googleapis.com/calendar/v3';

	public function __construct( $credentials = array() ) {
		$this->credentials = $credentials;
	}

	public function get_provider_name() {
		return 'google_meet';
	}

	public function get_display_name() {
		return 'Google Meet';
	}

	public function uses_oauth() {
		return true;
	}

	public function test_connection( $credentials ) {
		$this->credentials = $credentials;
		$result = $this->api_request( 'GET', '/users/me/calendarList' );
		return is_wp_error( $result ) ? $result : true;
	}

	public function create_meeting( $meeting_data ) {
		// Google Meet meetings are created via Google Calendar events
		$calendar_id = 'primary';
		
		$event = array(
			'summary'     => $meeting_data['topic'],
			'description' => isset( $meeting_data['agenda'] ) ? $meeting_data['agenda'] : '',
			'start'       => array(
				'dateTime' => gmdate( 'c', strtotime( $meeting_data['start_time'] ) ),
				'timeZone' => isset( $meeting_data['timezone'] ) ? $meeting_data['timezone'] : 'UTC',
			),
			'end'         => array(
				'dateTime' => gmdate( 'c', strtotime( $meeting_data['start_time'] ) + ( isset( $meeting_data['duration'] ) ? $meeting_data['duration'] * 60 : 3600 ) ),
				'timeZone' => isset( $meeting_data['timezone'] ) ? $meeting_data['timezone'] : 'UTC',
			),
			'conferenceData' => array(
				'createRequest' => array(
					'requestId'             => wp_generate_uuid4(),
					'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
				),
			),
		);

		$result = $this->api_request( 'POST', "/calendars/{$calendar_id}/events?conferenceDataVersion=1", $event );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$meet_link = isset( $result['conferenceData']['entryPoints'][0]['uri'] ) ? $result['conferenceData']['entryPoints'][0]['uri'] : $result['htmlLink'];

		return array(
			'meeting_id'       => $result['id'],
			'meeting_url'      => $meet_link,
			'join_url'         => $meet_link,
			'meeting_password' => null,
		);
	}

	public function update_meeting( $meeting_id, $meeting_data ) {
		$calendar_id = 'primary';
		
		$event = array(
			'summary'     => $meeting_data['topic'],
			'description' => isset( $meeting_data['agenda'] ) ? $meeting_data['agenda'] : '',
			'start'       => array(
				'dateTime' => gmdate( 'c', strtotime( $meeting_data['start_time'] ) ),
				'timeZone' => isset( $meeting_data['timezone'] ) ? $meeting_data['timezone'] : 'UTC',
			),
			'end'         => array(
				'dateTime' => gmdate( 'c', strtotime( $meeting_data['start_time'] ) + ( isset( $meeting_data['duration'] ) ? $meeting_data['duration'] * 60 : 3600 ) ),
				'timeZone' => isset( $meeting_data['timezone'] ) ? $meeting_data['timezone'] : 'UTC',
			),
		);

		$result = $this->api_request( 'PATCH', "/calendars/{$calendar_id}/events/{$meeting_id}", $event );
		return is_wp_error( $result ) ? $result : true;
	}

	public function delete_meeting( $meeting_id ) {
		$calendar_id = 'primary';
		$result = $this->api_request( 'DELETE', "/calendars/{$calendar_id}/events/{$meeting_id}" );
		return is_wp_error( $result ) ? $result : true;
	}

	public function get_meeting( $meeting_id ) {
		$calendar_id = 'primary';
		return $this->api_request( 'GET', "/calendars/{$calendar_id}/events/{$meeting_id}" );
	}

	public function list_meetings( $args = array() ) {
		$calendar_id = 'primary';
		$params = array(
			'maxResults' => isset( $args['limit'] ) ? $args['limit'] : 30,
			'orderBy'    => 'startTime',
			'singleEvents' => 'true',
		);

		if ( isset( $args['start_date'] ) ) {
			$params['timeMin'] = gmdate( 'c', strtotime( $args['start_date'] ) );
		}

		$result = $this->api_request( 'GET', "/calendars/{$calendar_id}/events?" . http_build_query( $params ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['items'] ) ? $result['items'] : array();
	}

	public function get_participants( $meeting_id ) {
		// Google Meet doesn't provide participant reports via API
		return array();
	}

	public function get_recordings( $meeting_id ) {
		// Google Meet recordings are stored in Google Drive
		// Would need separate Drive API integration
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
