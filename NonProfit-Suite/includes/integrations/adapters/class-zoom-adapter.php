<?php
/**
 * Zoom Video Conferencing Adapter
 *
 * Integrates with Zoom using OAuth 2.0 and REST API.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Zoom_Adapter implements NonprofitSuite_Video_Conferencing_Adapter {

	private $credentials;
	private $api_base = 'https://api.zoom.us/v2';

	public function __construct( $credentials = array() ) {
		$this->credentials = $credentials;
	}

	public function get_provider_name() {
		return 'zoom';
	}

	public function get_display_name() {
		return 'Zoom';
	}

	public function uses_oauth() {
		return true;
	}

	public function test_connection( $credentials ) {
		$this->credentials = $credentials;
		$result = $this->api_request( 'GET', '/users/me' );
		return is_wp_error( $result ) ? $result : true;
	}

	public function create_meeting( $meeting_data ) {
		$zoom_meeting = array(
			'topic'      => $meeting_data['topic'],
			'type'       => 2, // Scheduled meeting
			'start_time' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meeting_data['start_time'] ) ),
			'duration'   => isset( $meeting_data['duration'] ) ? $meeting_data['duration'] : 60,
			'timezone'   => isset( $meeting_data['timezone'] ) ? $meeting_data['timezone'] : 'UTC',
			'agenda'     => isset( $meeting_data['agenda'] ) ? $meeting_data['agenda'] : '',
			'settings'   => array(
				'host_video'        => isset( $meeting_data['host_video'] ) ? $meeting_data['host_video'] : true,
				'participant_video' => isset( $meeting_data['participant_video'] ) ? $meeting_data['participant_video'] : true,
				'join_before_host'  => isset( $meeting_data['join_before_host'] ) ? $meeting_data['join_before_host'] : false,
				'mute_upon_entry'   => isset( $meeting_data['mute_upon_entry'] ) ? $meeting_data['mute_upon_entry'] : false,
				'waiting_room'      => isset( $meeting_data['waiting_room'] ) ? $meeting_data['waiting_room'] : true,
				'auto_recording'    => isset( $meeting_data['auto_recording'] ) ? $meeting_data['auto_recording'] : 'none',
			),
		);

		$result = $this->api_request( 'POST', '/users/me/meetings', $zoom_meeting );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'meeting_id'       => $result['id'],
			'meeting_url'      => $result['start_url'],
			'join_url'         => $result['join_url'],
			'meeting_password' => isset( $result['password'] ) ? $result['password'] : null,
		);
	}

	public function update_meeting( $meeting_id, $meeting_data ) {
		$zoom_meeting = array(
			'topic'      => $meeting_data['topic'],
			'start_time' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meeting_data['start_time'] ) ),
			'duration'   => isset( $meeting_data['duration'] ) ? $meeting_data['duration'] : 60,
			'timezone'   => isset( $meeting_data['timezone'] ) ? $meeting_data['timezone'] : 'UTC',
			'agenda'     => isset( $meeting_data['agenda'] ) ? $meeting_data['agenda'] : '',
		);

		$result = $this->api_request( 'PATCH', "/meetings/{$meeting_id}", $zoom_meeting );
		return is_wp_error( $result ) ? $result : true;
	}

	public function delete_meeting( $meeting_id ) {
		$result = $this->api_request( 'DELETE', "/meetings/{$meeting_id}" );
		return is_wp_error( $result ) ? $result : true;
	}

	public function get_meeting( $meeting_id ) {
		return $this->api_request( 'GET', "/meetings/{$meeting_id}" );
	}

	public function list_meetings( $args = array() ) {
		$params = array(
			'type'      => isset( $args['type'] ) ? $args['type'] : 'scheduled',
			'page_size' => isset( $args['limit'] ) ? $args['limit'] : 30,
		);

		$result = $this->api_request( 'GET', '/users/me/meetings?' . http_build_query( $params ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['meetings'] ) ? $result['meetings'] : array();
	}

	public function get_participants( $meeting_id ) {
		$result = $this->api_request( 'GET', "/report/meetings/{$meeting_id}/participants" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['participants'] ) ? $result['participants'] : array();
	}

	public function get_recordings( $meeting_id ) {
		$result = $this->api_request( 'GET', "/meetings/{$meeting_id}/recordings" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result['recording_files'] ) ? $result['recording_files'] : array();
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
			$message = isset( $result['message'] ) ? $result['message'] : 'Unknown error';
			return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		return $result;
	}
}
