<?php
/**
 * Microsoft Outlook Calendar Adapter
 *
 * Integrates with Microsoft Graph API (Outlook Calendar) using OAuth 2.0
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
 * NonprofitSuite_Calendar_Outlook_Adapter Class
 *
 * Implements calendar integration with Microsoft Outlook Calendar.
 * Uses Microsoft Graph API with OAuth 2.0 authentication.
 */
class NonprofitSuite_Calendar_Outlook_Adapter implements NonprofitSuite_Calendar_Adapter_Interface {

	/**
	 * Microsoft Graph API base URL
	 *
	 * @var string
	 */
	private $graph_url = 'https://graph.microsoft.com/v1.0';

	/**
	 * Access token
	 *
	 * @var string|null
	 */
	private $access_token;

	/**
	 * Calendar ID to use (default: primary calendar)
	 *
	 * @var string
	 */
	private $calendar_id = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->load_access_token();
		$this->calendar_id = get_option( 'ns_outlook_calendar_id', '' );
	}

	/**
	 * Load access token from options
	 */
	private function load_access_token() {
		$token_data = get_option( 'ns_outlook_calendar_token' );

		if ( $token_data ) {
			$token = json_decode( $token_data, true );

			// Check if token is expired
			if ( isset( $token['expires_at'] ) && $token['expires_at'] < time() ) {
				// Try to refresh
				if ( isset( $token['refresh_token'] ) ) {
					$this->refresh_access_token( $token['refresh_token'] );
				}
			} else {
				$this->access_token = $token['access_token'] ?? null;
			}
		}
	}

	/**
	 * Refresh access token
	 *
	 * @param string $refresh_token Refresh token
	 * @return bool Whether refresh was successful
	 */
	private function refresh_access_token( $refresh_token ) {
		$client_id = get_option( 'ns_outlook_calendar_client_id' );
		$client_secret = get_option( 'ns_outlook_calendar_client_secret' );
		$tenant_id = get_option( 'ns_outlook_calendar_tenant_id', 'common' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return false;
		}

		$token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";

		$response = wp_remote_post( $token_url, array(
			'body' => array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
				'scope'         => 'Calendars.ReadWrite offline_access',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$token_data = array(
				'access_token'  => $body['access_token'],
				'refresh_token' => $body['refresh_token'] ?? $refresh_token,
				'expires_at'    => time() + ( $body['expires_in'] ?? 3600 ),
			);

			update_option( 'ns_outlook_calendar_token', wp_json_encode( $token_data ) );
			$this->access_token = $body['access_token'];

			return true;
		}

		return false;
	}

	/**
	 * Make API request to Microsoft Graph
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method   HTTP method
	 * @param array  $body     Request body
	 * @return array|WP_Error Response data or error
	 */
	private function api_request( $endpoint, $method = 'GET', $body = null ) {
		if ( ! $this->access_token ) {
			return new WP_Error( 'not_connected', __( 'Outlook Calendar not connected', 'nonprofitsuite' ) );
		}

		$url = $this->graph_url . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code >= 400 ) {
			$error = json_decode( $response_body, true );
			$error_message = $error['error']['message'] ?? __( 'Microsoft Graph API error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
		}

		return json_decode( $response_body, true );
	}

	/**
	 * Create a calendar event
	 *
	 * @param array $event_data Event data
	 * @return array|WP_Error Event data with event_id, url, html_link
	 */
	public function create_event( $event_data ) {
		$calendar_path = ! empty( $this->calendar_id )
			? "/me/calendars/{$this->calendar_id}/events"
			: '/me/events';

		$event = array(
			'subject' => $event_data['title'],
			'body'    => array(
				'contentType' => 'HTML',
				'content'     => $event_data['description'] ?? '',
			),
		);

		// Set start and end times
		if ( ! empty( $event_data['all_day'] ) ) {
			$event['isAllDay'] = true;
			$event['start'] = array(
				'dateTime' => date( 'Y-m-d', strtotime( $event_data['start_datetime'] ) ) . 'T00:00:00',
				'timeZone' => wp_timezone_string(),
			);
			$event['end'] = array(
				'dateTime' => date( 'Y-m-d', strtotime( $event_data['end_datetime'] ) ) . 'T23:59:59',
				'timeZone' => wp_timezone_string(),
			);
		} else {
			$event['start'] = array(
				'dateTime' => $this->format_datetime( $event_data['start_datetime'] ),
				'timeZone' => wp_timezone_string(),
			);
			$event['end'] = array(
				'dateTime' => $this->format_datetime( $event_data['end_datetime'] ),
				'timeZone' => wp_timezone_string(),
			);
		}

		// Add location
		if ( ! empty( $event_data['location'] ) ) {
			$event['location'] = array(
				'displayName' => $event_data['location'],
			);
		}

		// Add attendees
		if ( ! empty( $event_data['attendees'] ) ) {
			$event['attendees'] = array();
			foreach ( $event_data['attendees'] as $email ) {
				$event['attendees'][] = array(
					'emailAddress' => array(
						'address' => $email,
					),
					'type' => 'required',
				);
			}
		}

		// Add online meeting (Teams)
		if ( ! empty( $event_data['add_video_conference'] ) ) {
			$event['isOnlineMeeting'] = true;
			$event['onlineMeetingProvider'] = 'teamsForBusiness';
		}

		$result = $this->api_request( $calendar_path, 'POST', $event );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after Outlook Calendar event is created
		 */
		do_action( 'ns_calendar_event_created', $result['id'], $event_data, 'outlook', $result );

		return array(
			'event_id'  => $result['id'],
			'url'       => $result['webLink'],
			'html_link' => $result['webLink'],
			'meet_url'  => $result['onlineMeeting']['joinUrl'] ?? null,
		);
	}

	/**
	 * Update a calendar event
	 *
	 * @param string $event_id   Event identifier
	 * @param array  $event_data Updated event data
	 * @return array|WP_Error Updated event data
	 */
	public function update_event( $event_id, $event_data ) {
		$update = array();

		if ( isset( $event_data['title'] ) ) {
			$update['subject'] = $event_data['title'];
		}

		if ( isset( $event_data['description'] ) ) {
			$update['body'] = array(
				'contentType' => 'HTML',
				'content'     => $event_data['description'],
			);
		}

		if ( isset( $event_data['location'] ) ) {
			$update['location'] = array(
				'displayName' => $event_data['location'],
			);
		}

		if ( isset( $event_data['start_datetime'] ) ) {
			if ( ! empty( $event_data['all_day'] ) ) {
				$update['isAllDay'] = true;
				$update['start'] = array(
					'dateTime' => date( 'Y-m-d', strtotime( $event_data['start_datetime'] ) ) . 'T00:00:00',
					'timeZone' => wp_timezone_string(),
				);
			} else {
				$update['start'] = array(
					'dateTime' => $this->format_datetime( $event_data['start_datetime'] ),
					'timeZone' => wp_timezone_string(),
				);
			}
		}

		if ( isset( $event_data['end_datetime'] ) ) {
			if ( ! empty( $event_data['all_day'] ) ) {
				$update['end'] = array(
					'dateTime' => date( 'Y-m-d', strtotime( $event_data['end_datetime'] ) ) . 'T23:59:59',
					'timeZone' => wp_timezone_string(),
				);
			} else {
				$update['end'] = array(
					'dateTime' => $this->format_datetime( $event_data['end_datetime'] ),
					'timeZone' => wp_timezone_string(),
				);
			}
		}

		$result = $this->api_request( "/me/events/{$event_id}", 'PATCH', $update );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after Outlook Calendar event is updated
		 */
		do_action( 'ns_calendar_event_updated', $event_id, $event_data, 'outlook', $result );

		return array(
			'event_id'  => $result['id'],
			'url'       => $result['webLink'],
			'html_link' => $result['webLink'],
		);
	}

	/**
	 * Delete a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete_event( $event_id ) {
		$result = $this->api_request( "/me/events/{$event_id}", 'DELETE' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after Outlook Calendar event is deleted
		 */
		do_action( 'ns_calendar_event_deleted', $event_id, 'outlook' );

		return true;
	}

	/**
	 * Get a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return array|WP_Error Event data
	 */
	public function get_event( $event_id ) {
		$result = $this->api_request( "/me/events/{$event_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->format_event_response( $result );
	}

	/**
	 * List calendar events
	 *
	 * @param array $args List arguments
	 * @return array|WP_Error Array of events
	 */
	public function list_events( $args = array() ) {
		$calendar_path = ! empty( $this->calendar_id )
			? "/me/calendars/{$this->calendar_id}/events"
			: '/me/events';

		$params = array();

		// Build filter
		$filters = array();
		if ( ! empty( $args['start_date'] ) ) {
			$filters[] = "start/dateTime ge '" . $this->format_datetime( $args['start_date'] ) . "'";
		}
		if ( ! empty( $args['end_date'] ) ) {
			$filters[] = "end/dateTime le '" . $this->format_datetime( $args['end_date'] ) . "'";
		}

		if ( ! empty( $filters ) ) {
			$params[] = '$filter=' . implode( ' and ', $filters );
		}

		$params[] = '$orderby=start/dateTime';

		if ( ! empty( $args['limit'] ) ) {
			$params[] = '$top=' . absint( $args['limit'] );
		}

		$query_string = ! empty( $params ) ? '?' . implode( '&', $params ) : '';
		$result = $this->api_request( $calendar_path . $query_string );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$formatted_events = array();
		foreach ( $result['value'] as $event ) {
			$formatted_events[] = $this->format_event_response( $event );
		}

		return $formatted_events;
	}

	/**
	 * Add attendees to an event
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success
	 */
	public function add_attendees( $event_id, $attendees ) {
		// Get current event
		$event = $this->api_request( "/me/events/{$event_id}" );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$existing_attendees = $event['attendees'] ?? array();

		foreach ( $attendees as $email ) {
			$existing_attendees[] = array(
				'emailAddress' => array(
					'address' => $email,
				),
				'type' => 'required',
			);
		}

		$result = $this->api_request( "/me/events/{$event_id}", 'PATCH', array(
			'attendees' => $existing_attendees,
		) );

		return ! is_wp_error( $result );
	}

	/**
	 * Remove attendees from an event
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success
	 */
	public function remove_attendees( $event_id, $attendees ) {
		// Get current event
		$event = $this->api_request( "/me/events/{$event_id}" );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$existing_attendees = $event['attendees'] ?? array();

		$filtered_attendees = array_filter( $existing_attendees, function( $attendee ) use ( $attendees ) {
			return ! in_array( $attendee['emailAddress']['address'], $attendees );
		} );

		$result = $this->api_request( "/me/events/{$event_id}", 'PATCH', array(
			'attendees' => array_values( $filtered_attendees ),
		) );

		return ! is_wp_error( $result );
	}

	/**
	 * Get iCal/ICS feed URL
	 *
	 * @param array $args Feed arguments
	 * @return string|WP_Error Feed URL
	 */
	public function get_ical_feed( $args = array() ) {
		// Outlook.com doesn't provide direct iCal URLs via API
		// Users need to configure this manually in Outlook settings
		return new WP_Error(
			'not_supported',
			__( 'Please configure iCal feed manually in Outlook settings', 'nonprofitsuite' )
		);
	}

	/**
	 * Sync events from Outlook Calendar to NonprofitSuite
	 *
	 * @param array $args Sync arguments
	 * @return array|WP_Error Sync result
	 */
	public function sync_events( $args = array() ) {
		$result = array(
			'synced_count'  => 0,
			'skipped_count' => 0,
			'errors'        => array(),
		);

		$events = $this->list_events( $args );

		if ( is_wp_error( $events ) ) {
			return $events;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		foreach ( $events as $outlook_event ) {
			// Check if event already exists
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE external_id = %s AND external_provider = 'outlook'",
				$outlook_event['event_id']
			) );

			if ( $exists ) {
				$result['skipped_count']++;
				continue;
			}

			// Create local event
			$event_data = array(
				'title'             => $outlook_event['title'],
				'description'       => $outlook_event['description'],
				'start_datetime'    => $outlook_event['start'],
				'end_datetime'      => $outlook_event['end'],
				'location'          => $outlook_event['location'],
				'all_day'           => $outlook_event['all_day'],
				'external_id'       => $outlook_event['event_id'],
				'external_provider' => 'outlook',
				'external_url'      => $outlook_event['html_link'],
				'created_by'        => get_current_user_id(),
				'created_at'        => current_time( 'mysql' ),
			);

			$inserted = $wpdb->insert( $table, $event_data );

			if ( $inserted ) {
				$result['synced_count']++;
			} else {
				$result['errors'][] = sprintf(
					__( 'Failed to sync event: %s', 'nonprofitsuite' ),
					$outlook_event['title']
				);
			}
		}

		return $result;
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		if ( ! $this->access_token ) {
			return new WP_Error( 'not_authenticated', __( 'Not authenticated with Outlook Calendar', 'nonprofitsuite' ) );
		}

		$result = $this->api_request( '/me/calendars' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return 'Microsoft Outlook Calendar';
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @return string|WP_Error Authorization URL
	 */
	public function get_auth_url() {
		$client_id = get_option( 'ns_outlook_calendar_client_id' );
		$tenant_id = get_option( 'ns_outlook_calendar_tenant_id', 'common' );
		$redirect_uri = admin_url( 'admin.php?page=nonprofitsuite-integrations&action=outlook_calendar_oauth' );

		if ( empty( $client_id ) ) {
			return new WP_Error( 'not_configured', __( 'Outlook Calendar not configured', 'nonprofitsuite' ) );
		}

		$params = array(
			'client_id'     => $client_id,
			'response_type' => 'code',
			'redirect_uri'  => $redirect_uri,
			'response_mode' => 'query',
			'scope'         => 'Calendars.ReadWrite offline_access',
			'state'         => wp_create_nonce( 'outlook_calendar_oauth' ),
		);

		return "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/authorize?" . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code
	 * @return bool|WP_Error True on success
	 */
	public function handle_oauth_callback( $code ) {
		$client_id = get_option( 'ns_outlook_calendar_client_id' );
		$client_secret = get_option( 'ns_outlook_calendar_client_secret' );
		$tenant_id = get_option( 'ns_outlook_calendar_tenant_id', 'common' );
		$redirect_uri = admin_url( 'admin.php?page=nonprofitsuite-integrations&action=outlook_calendar_oauth' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'not_configured', __( 'Outlook Calendar not configured', 'nonprofitsuite' ) );
		}

		$token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";

		$response = wp_remote_post( $token_url, array(
			'body' => array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
				'scope'         => 'Calendars.ReadWrite offline_access',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$token_data = array(
				'access_token'  => $body['access_token'],
				'refresh_token' => $body['refresh_token'] ?? '',
				'expires_at'    => time() + ( $body['expires_in'] ?? 3600 ),
			);

			update_option( 'ns_outlook_calendar_token', wp_json_encode( $token_data ) );
			$this->access_token = $body['access_token'];

			return true;
		}

		return new WP_Error( 'oauth_error', $body['error_description'] ?? __( 'OAuth failed', 'nonprofitsuite' ) );
	}

	/**
	 * Get list of available calendars
	 *
	 * @return array|WP_Error Array of calendars
	 */
	public function get_calendars() {
		$result = $this->api_request( '/me/calendars' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$calendars = array();
		foreach ( $result['value'] as $calendar ) {
			$calendars[] = array(
				'id'      => $calendar['id'],
				'name'    => $calendar['name'],
				'primary' => $calendar['isDefaultCalendar'] ?? false,
			);
		}

		return $calendars;
	}

	/**
	 * Format datetime for Microsoft Graph API
	 *
	 * @param string $datetime DateTime string
	 * @return string Formatted datetime
	 */
	private function format_datetime( $datetime ) {
		$dt = new DateTime( $datetime, new DateTimeZone( wp_timezone_string() ) );
		return $dt->format( 'Y-m-d\TH:i:s' );
	}

	/**
	 * Format event response from Outlook
	 *
	 * @param array $event Outlook event data
	 * @return array Formatted event data
	 */
	private function format_event_response( $event ) {
		return array(
			'event_id'    => $event['id'],
			'title'       => $event['subject'],
			'description' => strip_tags( $event['body']['content'] ?? '' ),
			'location'    => $event['location']['displayName'] ?? '',
			'start'       => $event['start']['dateTime'],
			'end'         => $event['end']['dateTime'],
			'all_day'     => $event['isAllDay'] ?? false,
			'html_link'   => $event['webLink'],
			'meet_url'    => $event['onlineMeeting']['joinUrl'] ?? null,
			'attendees'   => $this->format_attendees( $event['attendees'] ?? array() ),
		);
	}

	/**
	 * Format attendees array
	 *
	 * @param array $attendees Outlook attendees
	 * @return array Formatted attendees
	 */
	private function format_attendees( $attendees ) {
		$formatted = array();
		foreach ( $attendees as $attendee ) {
			$formatted[] = array(
				'email'           => $attendee['emailAddress']['address'],
				'display_name'    => $attendee['emailAddress']['name'] ?? '',
				'response_status' => $attendee['status']['response'] ?? 'none',
			);
		}
		return $formatted;
	}
}
