<?php
/**
 * Video Conferencing Manager
 *
 * Central coordinator for video conferencing integrations.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Video_Conferencing_Manager {

	private static $adapters = array();

	public static function init() {
		self::register_adapter( 'zoom', 'NonprofitSuite_Zoom_Adapter' );
		self::register_adapter( 'google_meet', 'NonprofitSuite_Google_Meet_Adapter' );
		self::register_adapter( 'teams', 'NonprofitSuite_Teams_Adapter' );
	}

	public static function register_adapter( $key, $class_name ) {
		self::$adapters[ $key ] = $class_name;
	}

	public static function get_adapter( $provider, $org_id ) {
		if ( ! isset( self::$adapters[ $provider ] ) ) {
			return null;
		}

		$credentials = self::get_provider_credentials( $provider, $org_id );
		if ( ! $credentials ) {
			return null;
		}

		$class_name = self::$adapters[ $provider ];
		return new $class_name( $credentials );
	}

	public static function get_provider_credentials( $provider, $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_video_conferencing_settings';

		$setting = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND provider = %s AND is_active = 1",
				$org_id,
				$provider
			),
			ARRAY_A
		);

		if ( ! $setting ) {
			return null;
		}

		return array(
			'api_key'       => $setting['api_key'],
			'api_secret'    => $setting['api_secret'],
			'oauth_token'   => $setting['oauth_token'],
		);
	}

	public static function create_meeting( $org_id, $provider, $meeting_data ) {
		global $wpdb;

		$adapter = self::get_adapter( $provider, $org_id );
		if ( ! $adapter ) {
			return new WP_Error( 'adapter_not_found', 'Video conferencing adapter not found' );
		}

		$result = $adapter->create_meeting( $meeting_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Save meeting to database
		$wpdb->insert(
			$wpdb->prefix . 'ns_video_meetings',
			array(
				'organization_id'   => $org_id,
				'calendar_event_id' => isset( $meeting_data['calendar_event_id'] ) ? $meeting_data['calendar_event_id'] : null,
				'provider'          => $provider,
				'meeting_id'        => $result['meeting_id'],
				'meeting_url'       => $result['meeting_url'],
				'join_url'          => $result['join_url'],
				'meeting_password'  => $result['meeting_password'],
				'host_id'           => get_current_user_id(),
				'topic'             => $meeting_data['topic'],
				'agenda'            => isset( $meeting_data['agenda'] ) ? $meeting_data['agenda'] : '',
				'start_time'        => $meeting_data['start_time'],
				'duration'          => isset( $meeting_data['duration'] ) ? $meeting_data['duration'] : 60,
				'timezone'          => isset( $meeting_data['timezone'] ) ? $meeting_data['timezone'] : 'UTC',
				'status'            => 'scheduled',
			)
		);

		$meeting_id = $wpdb->insert_id;

		// Link to calendar event if provided
		if ( isset( $meeting_data['calendar_event_id'] ) ) {
			$wpdb->update(
				$wpdb->prefix . 'ns_calendar_events',
				array( 'meeting_url' => $result['join_url'] ),
				array( 'id' => $meeting_data['calendar_event_id'] )
			);
		}

		return $meeting_id;
	}

	public static function delete_meeting( $meeting_id ) {
		global $wpdb;

		$meeting = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_video_meetings WHERE id = %d",
				$meeting_id
			),
			ARRAY_A
		);

		if ( ! $meeting ) {
			return new WP_Error( 'meeting_not_found', 'Meeting not found' );
		}

		$adapter = self::get_adapter( $meeting['provider'], $meeting['organization_id'] );
		if ( $adapter ) {
			$adapter->delete_meeting( $meeting['meeting_id'] );
		}

		$wpdb->update(
			$wpdb->prefix . 'ns_video_meetings',
			array( 'status' => 'cancelled' ),
			array( 'id' => $meeting_id )
		);

		return true;
	}

	public static function get_meeting( $meeting_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_video_meetings WHERE id = %d",
				$meeting_id
			),
			ARRAY_A
		);
	}

	public static function list_meetings( $org_id, $args = array() ) {
		global $wpdb;

		$where = array( 'organization_id = %d' );
		$values = array( $org_id );

		if ( isset( $args['status'] ) ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_video_meetings WHERE {$where_clause} ORDER BY start_time DESC LIMIT 50",
				...$values
			),
			ARRAY_A
		);
	}
}
