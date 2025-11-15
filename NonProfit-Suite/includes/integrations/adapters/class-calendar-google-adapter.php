<?php
/**
 * Google Calendar Adapter
 *
 * Integrates with Google Calendar API using OAuth 2.0
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
 * NonprofitSuite_Calendar_Google_Adapter Class
 *
 * Implements calendar integration with Google Calendar.
 * Uses Google Calendar API v3 with OAuth 2.0 authentication.
 */
class NonprofitSuite_Calendar_Google_Adapter implements NonprofitSuite_Calendar_Adapter_Interface {

	/**
	 * Google Client instance
	 *
	 * @var Google_Client|null
	 */
	private $client;

	/**
	 * Google Calendar Service
	 *
	 * @var Google_Service_Calendar|null
	 */
	private $service;

	/**
	 * Calendar ID to use (default: 'primary')
	 *
	 * @var string
	 */
	private $calendar_id = 'primary';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_client();
	}

	/**
	 * Initialize Google Client
	 *
	 * @return bool Whether initialization was successful
	 */
	private function init_client() {
		// Check if Google API PHP Client is available
		if ( ! class_exists( 'Google_Client' ) ) {
			return false;
		}

		try {
			$this->client = new Google_Client();
			$this->client->setApplicationName( 'NonprofitSuite' );
			$this->client->setScopes( array(
				Google_Service_Calendar::CALENDAR_EVENTS,
				Google_Service_Calendar::CALENDAR_READONLY,
			) );

			// Get OAuth credentials from settings
			$client_id = get_option( 'ns_google_calendar_client_id' );
			$client_secret = get_option( 'ns_google_calendar_client_secret' );
			$redirect_uri = admin_url( 'admin.php?page=nonprofitsuite-integrations&action=google_calendar_oauth' );

			if ( empty( $client_id ) || empty( $client_secret ) ) {
				return false;
			}

			$this->client->setClientId( $client_id );
			$this->client->setClientSecret( $client_secret );
			$this->client->setRedirectUri( $redirect_uri );
			$this->client->setAccessType( 'offline' );
			$this->client->setPrompt( 'consent' );

			// Load access token if available
			$access_token = get_option( 'ns_google_calendar_access_token' );
			if ( $access_token ) {
				$this->client->setAccessToken( json_decode( $access_token, true ) );

				// Refresh token if expired
				if ( $this->client->isAccessTokenExpired() ) {
					$refresh_token = $this->client->getRefreshToken();
					if ( $refresh_token ) {
						$this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
						update_option( 'ns_google_calendar_access_token', wp_json_encode( $this->client->getAccessToken() ) );
					}
				}
			}

			$this->service = new Google_Service_Calendar( $this->client );

			// Get calendar ID setting
			$calendar_id = get_option( 'ns_google_calendar_id', 'primary' );
			if ( ! empty( $calendar_id ) ) {
				$this->calendar_id = $calendar_id;
			}

			return true;

		} catch ( Exception $e ) {
			error_log( 'Google Calendar initialization error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Create a calendar event
	 *
	 * @param array $event_data Event data
	 * @return array|WP_Error Event data with event_id, url, html_link
	 */
	public function create_event( $event_data ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			$event = new Google_Service_Calendar_Event();
			$event->setSummary( $event_data['title'] );

			if ( ! empty( $event_data['description'] ) ) {
				$event->setDescription( $event_data['description'] );
			}

			if ( ! empty( $event_data['location'] ) ) {
				$event->setLocation( $event_data['location'] );
			}

			// Set start and end times
			$start = new Google_Service_Calendar_EventDateTime();
			if ( ! empty( $event_data['all_day'] ) ) {
				$start->setDate( date( 'Y-m-d', strtotime( $event_data['start_datetime'] ) ) );
			} else {
				$start->setDateTime( $this->format_datetime( $event_data['start_datetime'] ) );
				$start->setTimeZone( wp_timezone_string() );
			}
			$event->setStart( $start );

			$end = new Google_Service_Calendar_EventDateTime();
			if ( ! empty( $event_data['all_day'] ) ) {
				$end->setDate( date( 'Y-m-d', strtotime( $event_data['end_datetime'] ) ) );
			} else {
				$end->setDateTime( $this->format_datetime( $event_data['end_datetime'] ) );
				$end->setTimeZone( wp_timezone_string() );
			}
			$event->setEnd( $end );

			// Add attendees
			if ( ! empty( $event_data['attendees'] ) ) {
				$attendees = array();
				foreach ( $event_data['attendees'] as $email ) {
					$attendee = new Google_Service_Calendar_EventAttendee();
					$attendee->setEmail( $email );
					$attendees[] = $attendee;
				}
				$event->setAttendees( $attendees );
			}

			// Add conferencing (Google Meet)
			if ( ! empty( $event_data['add_video_conference'] ) ) {
				$conference_request = new Google_Service_Calendar_CreateConferenceRequest();
				$conference_request->setRequestId( uniqid() );

				$conference_solution_key = new Google_Service_Calendar_ConferenceSolutionKey();
				$conference_solution_key->setType( 'hangoutsMeet' );
				$conference_request->setConferenceSolutionKey( $conference_solution_key );

				$conference_data = new Google_Service_Calendar_ConferenceData();
				$conference_data->setCreateRequest( $conference_request );
				$event->setConferenceData( $conference_data );
			}

			// Add reminders
			if ( ! empty( $event_data['reminders'] ) ) {
				$reminders = new Google_Service_Calendar_EventReminders();
				$reminders->setUseDefault( false );

				$overrides = array();
				foreach ( $event_data['reminders'] as $reminder ) {
					$override = new Google_Service_Calendar_EventReminder();
					$override->setMethod( $reminder['method'] ?? 'email' );
					$override->setMinutes( $reminder['minutes'] ?? 60 );
					$overrides[] = $override;
				}
				$reminders->setOverrides( $overrides );
				$event->setReminders( $reminders );
			}

			// Create the event
			$opt_params = array();
			if ( ! empty( $event_data['add_video_conference'] ) ) {
				$opt_params['conferenceDataVersion'] = 1;
			}
			if ( ! empty( $event_data['send_notifications'] ) ) {
				$opt_params['sendUpdates'] = 'all';
			}

			$created_event = $this->service->events->insert( $this->calendar_id, $event, $opt_params );

			/**
			 * Fires after Google Calendar event is created
			 *
			 * @param string $event_id      Google Calendar event ID
			 * @param array  $event_data    Event data
			 * @param object $created_event Google Calendar event object
			 */
			do_action( 'ns_calendar_event_created', $created_event->getId(), $event_data, 'google', $created_event );

			return array(
				'event_id'  => $created_event->getId(),
				'url'       => $created_event->getHtmlLink(),
				'html_link' => $created_event->getHtmlLink(),
				'meet_url'  => $this->get_meet_url( $created_event ),
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Update a calendar event
	 *
	 * @param string $event_id   Event identifier
	 * @param array  $event_data Updated event data
	 * @return array|WP_Error Updated event data
	 */
	public function update_event( $event_id, $event_data ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			// Get existing event
			$event = $this->service->events->get( $this->calendar_id, $event_id );

			// Update fields
			if ( isset( $event_data['title'] ) ) {
				$event->setSummary( $event_data['title'] );
			}

			if ( isset( $event_data['description'] ) ) {
				$event->setDescription( $event_data['description'] );
			}

			if ( isset( $event_data['location'] ) ) {
				$event->setLocation( $event_data['location'] );
			}

			if ( isset( $event_data['start_datetime'] ) ) {
				$start = new Google_Service_Calendar_EventDateTime();
				if ( ! empty( $event_data['all_day'] ) ) {
					$start->setDate( date( 'Y-m-d', strtotime( $event_data['start_datetime'] ) ) );
				} else {
					$start->setDateTime( $this->format_datetime( $event_data['start_datetime'] ) );
					$start->setTimeZone( wp_timezone_string() );
				}
				$event->setStart( $start );
			}

			if ( isset( $event_data['end_datetime'] ) ) {
				$end = new Google_Service_Calendar_EventDateTime();
				if ( ! empty( $event_data['all_day'] ) ) {
					$end->setDate( date( 'Y-m-d', strtotime( $event_data['end_datetime'] ) ) );
				} else {
					$end->setDateTime( $this->format_datetime( $event_data['end_datetime'] ) );
					$end->setTimeZone( wp_timezone_string() );
				}
				$event->setEnd( $end );
			}

			// Update the event
			$opt_params = array();
			if ( ! empty( $event_data['send_notifications'] ) ) {
				$opt_params['sendUpdates'] = 'all';
			}

			$updated_event = $this->service->events->update( $this->calendar_id, $event_id, $event, $opt_params );

			/**
			 * Fires after Google Calendar event is updated
			 */
			do_action( 'ns_calendar_event_updated', $event_id, $event_data, 'google', $updated_event );

			return array(
				'event_id'  => $updated_event->getId(),
				'url'       => $updated_event->getHtmlLink(),
				'html_link' => $updated_event->getHtmlLink(),
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Delete a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete_event( $event_id ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			$this->service->events->delete( $this->calendar_id, $event_id );

			/**
			 * Fires after Google Calendar event is deleted
			 */
			do_action( 'ns_calendar_event_deleted', $event_id, 'google' );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Get a calendar event
	 *
	 * @param string $event_id Event identifier
	 * @return array|WP_Error Event data
	 */
	public function get_event( $event_id ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			$event = $this->service->events->get( $this->calendar_id, $event_id );
			return $this->format_event_response( $event );

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * List calendar events
	 *
	 * @param array $args List arguments
	 * @return array|WP_Error Array of events
	 */
	public function list_events( $args = array() ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			$params = array(
				'singleEvents' => true,
				'orderBy'      => 'startTime',
			);

			if ( ! empty( $args['start_date'] ) ) {
				$params['timeMin'] = $this->format_datetime( $args['start_date'] );
			}

			if ( ! empty( $args['end_date'] ) ) {
				$params['timeMax'] = $this->format_datetime( $args['end_date'] );
			}

			if ( ! empty( $args['limit'] ) ) {
				$params['maxResults'] = absint( $args['limit'] );
			}

			$calendar_id = ! empty( $args['calendar_id'] ) ? $args['calendar_id'] : $this->calendar_id;

			$events = $this->service->events->listEvents( $calendar_id, $params );

			$formatted_events = array();
			foreach ( $events->getItems() as $event ) {
				$formatted_events[] = $this->format_event_response( $event );
			}

			return $formatted_events;

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Add attendees to an event
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success
	 */
	public function add_attendees( $event_id, $attendees ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			$event = $this->service->events->get( $this->calendar_id, $event_id );
			$existing_attendees = $event->getAttendees() ?: array();

			foreach ( $attendees as $email ) {
				$attendee = new Google_Service_Calendar_EventAttendee();
				$attendee->setEmail( $email );
				$existing_attendees[] = $attendee;
			}

			$event->setAttendees( $existing_attendees );
			$this->service->events->update( $this->calendar_id, $event_id, $event, array( 'sendUpdates' => 'all' ) );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Remove attendees from an event
	 *
	 * @param string $event_id  Event identifier
	 * @param array  $attendees Array of email addresses
	 * @return bool|WP_Error True on success
	 */
	public function remove_attendees( $event_id, $attendees ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			$event = $this->service->events->get( $this->calendar_id, $event_id );
			$existing_attendees = $event->getAttendees() ?: array();

			$filtered_attendees = array_filter( $existing_attendees, function( $attendee ) use ( $attendees ) {
				return ! in_array( $attendee->getEmail(), $attendees );
			} );

			$event->setAttendees( array_values( $filtered_attendees ) );
			$this->service->events->update( $this->calendar_id, $event_id, $event, array( 'sendUpdates' => 'all' ) );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Get iCal/ICS feed URL
	 *
	 * @param array $args Feed arguments
	 * @return string|WP_Error Feed URL
	 */
	public function get_ical_feed( $args = array() ) {
		$calendar_id = ! empty( $args['calendar_id'] ) ? $args['calendar_id'] : $this->calendar_id;

		// Google Calendar public iCal feed format
		// Note: This requires the calendar to be made public
		$ical_url = sprintf(
			'https://calendar.google.com/calendar/ical/%s/public/basic.ics',
			urlencode( $calendar_id )
		);

		return $ical_url;
	}

	/**
	 * Sync events from Google Calendar to NonprofitSuite
	 *
	 * @param array $args Sync arguments
	 * @return array|WP_Error Sync result
	 */
	public function sync_events( $args = array() ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		$result = array(
			'synced_count'  => 0,
			'skipped_count' => 0,
			'errors'        => array(),
		);

		try {
			$events = $this->list_events( $args );

			if ( is_wp_error( $events ) ) {
				return $events;
			}

			global $wpdb;
			$table = $wpdb->prefix . 'ns_calendar_events';

			foreach ( $events as $google_event ) {
				// Check if event already exists
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$table} WHERE external_id = %s AND external_provider = 'google'",
					$google_event['event_id']
				) );

				if ( $exists ) {
					$result['skipped_count']++;
					continue;
				}

				// Create local event
				$event_data = array(
					'title'             => $google_event['title'],
					'description'       => $google_event['description'],
					'start_datetime'    => $google_event['start'],
					'end_datetime'      => $google_event['end'],
					'location'          => $google_event['location'],
					'all_day'           => $google_event['all_day'],
					'external_id'       => $google_event['event_id'],
					'external_provider' => 'google',
					'external_url'      => $google_event['html_link'],
					'created_by'        => get_current_user_id(),
					'created_at'        => current_time( 'mysql' ),
				);

				$inserted = $wpdb->insert( $table, $event_data );

				if ( $inserted ) {
					$result['synced_count']++;
				} else {
					$result['errors'][] = sprintf(
						__( 'Failed to sync event: %s', 'nonprofitsuite' ),
						$google_event['title']
					);
				}
			}

			return $result;

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		if ( ! $this->service ) {
			return new WP_Error( 'not_initialized', __( 'Google Calendar service not initialized', 'nonprofitsuite' ) );
		}

		if ( ! $this->client->getAccessToken() ) {
			return new WP_Error( 'not_authenticated', __( 'Not authenticated with Google Calendar', 'nonprofitsuite' ) );
		}

		try {
			// Try to fetch calendar list as a connection test
			$calendar_list = $this->service->calendarList->listCalendarList( array( 'maxResults' => 1 ) );
			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'connection_failed', $e->getMessage() );
		}
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return 'Google Calendar';
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @return string|WP_Error Authorization URL
	 */
	public function get_auth_url() {
		if ( ! $this->client ) {
			return new WP_Error( 'not_initialized', __( 'Google Calendar client not initialized', 'nonprofitsuite' ) );
		}

		return $this->client->createAuthUrl();
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code from Google
	 * @return bool|WP_Error True on success
	 */
	public function handle_oauth_callback( $code ) {
		if ( ! $this->client ) {
			return new WP_Error( 'not_initialized', __( 'Google Calendar client not initialized', 'nonprofitsuite' ) );
		}

		try {
			$token = $this->client->fetchAccessTokenWithAuthCode( $code );

			if ( isset( $token['error'] ) ) {
				return new WP_Error( 'oauth_error', $token['error'] );
			}

			update_option( 'ns_google_calendar_access_token', wp_json_encode( $token ) );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'oauth_error', $e->getMessage() );
		}
	}

	/**
	 * Get list of available calendars
	 *
	 * @return array|WP_Error Array of calendars
	 */
	public function get_calendars() {
		if ( ! $this->service ) {
			return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'nonprofitsuite' ) );
		}

		try {
			$calendar_list = $this->service->calendarList->listCalendarList();
			$calendars = array();

			foreach ( $calendar_list->getItems() as $calendar ) {
				$calendars[] = array(
					'id'      => $calendar->getId(),
					'name'    => $calendar->getSummary(),
					'primary' => $calendar->getPrimary(),
				);
			}

			return $calendars;

		} catch ( Exception $e ) {
			return new WP_Error( 'google_api_error', $e->getMessage() );
		}
	}

	/**
	 * Format datetime for Google Calendar API
	 *
	 * @param string $datetime DateTime string
	 * @return string Formatted datetime
	 */
	private function format_datetime( $datetime ) {
		$dt = new DateTime( $datetime, new DateTimeZone( wp_timezone_string() ) );
		return $dt->format( DateTime::RFC3339 );
	}

	/**
	 * Format event response from Google Calendar
	 *
	 * @param Google_Service_Calendar_Event $event Google Calendar event
	 * @return array Formatted event data
	 */
	private function format_event_response( $event ) {
		$start = $event->getStart();
		$end = $event->getEnd();

		return array(
			'event_id'    => $event->getId(),
			'title'       => $event->getSummary(),
			'description' => $event->getDescription(),
			'location'    => $event->getLocation(),
			'start'       => $start->getDateTime() ?: $start->getDate(),
			'end'         => $end->getDateTime() ?: $end->getDate(),
			'all_day'     => ! empty( $start->getDate() ),
			'html_link'   => $event->getHtmlLink(),
			'meet_url'    => $this->get_meet_url( $event ),
			'attendees'   => $this->format_attendees( $event->getAttendees() ),
		);
	}

	/**
	 * Get Google Meet URL from event
	 *
	 * @param Google_Service_Calendar_Event $event Google Calendar event
	 * @return string|null Meet URL
	 */
	private function get_meet_url( $event ) {
		$conference_data = $event->getConferenceData();
		if ( $conference_data ) {
			$entry_points = $conference_data->getEntryPoints();
			if ( $entry_points ) {
				foreach ( $entry_points as $entry_point ) {
					if ( $entry_point->getEntryPointType() === 'video' ) {
						return $entry_point->getUri();
					}
				}
			}
		}
		return null;
	}

	/**
	 * Format attendees array
	 *
	 * @param array|null $attendees Google Calendar attendees
	 * @return array Formatted attendees
	 */
	private function format_attendees( $attendees ) {
		if ( ! $attendees ) {
			return array();
		}

		$formatted = array();
		foreach ( $attendees as $attendee ) {
			$formatted[] = array(
				'email'          => $attendee->getEmail(),
				'display_name'   => $attendee->getDisplayName(),
				'response_status' => $attendee->getResponseStatus(),
			);
		}

		return $formatted;
	}
}
