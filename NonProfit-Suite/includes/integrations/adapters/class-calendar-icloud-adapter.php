<?php
/**
 * iCloud Calendar Adapter
 *
 * Adapter for Apple iCloud Calendar using CalDAV protocol.
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
 * NonprofitSuite_Calendar_iCloud_Adapter Class
 *
 * Implements calendar integration using Apple iCloud Calendar via CalDAV protocol.
 */
class NonprofitSuite_Calendar_iCloud_Adapter implements NonprofitSuite_Calendar_Adapter_Interface {

	/**
	 * CalDAV server URL
	 *
	 * @var string
	 */
	private $caldav_url = 'https://caldav.icloud.com/';

	/**
	 * Apple ID (username)
	 *
	 * @var string
	 */
	private $username;

	/**
	 * App-specific password
	 *
	 * @var string
	 */
	private $password;

	/**
	 * Calendar home URL
	 *
	 * @var string
	 */
	private $calendar_home;

	/**
	 * Default calendar URL
	 *
	 * @var string
	 */
	private $calendar_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->load_credentials();
	}

	/**
	 * Load credentials from options
	 *
	 * @return void
	 */
	private function load_credentials() {
		$settings = get_option( 'nonprofitsuite_icloud_calendar_settings', array() );

		$this->username      = isset( $settings['username'] ) ? $settings['username'] : '';
		$this->password      = isset( $settings['password'] ) ? $settings['password'] : '';
		$this->calendar_home = isset( $settings['calendar_home'] ) ? $settings['calendar_home'] : '';
		$this->calendar_url  = isset( $settings['calendar_url'] ) ? $settings['calendar_url'] : '';
	}

	/**
	 * Save credentials to options
	 *
	 * @param array $credentials Credentials array.
	 * @return bool True on success.
	 */
	public function save_credentials( $credentials ) {
		$settings = array(
			'username'      => isset( $credentials['username'] ) ? sanitize_email( $credentials['username'] ) : '',
			'password'      => isset( $credentials['password'] ) ? $credentials['password'] : '',
			'calendar_home' => isset( $credentials['calendar_home'] ) ? esc_url_raw( $credentials['calendar_home'] ) : '',
			'calendar_url'  => isset( $credentials['calendar_url'] ) ? esc_url_raw( $credentials['calendar_url'] ) : '',
		);

		return update_option( 'nonprofitsuite_icloud_calendar_settings', $settings );
	}

	/**
	 * Make a CalDAV request
	 *
	 * @param string $url    Request URL.
	 * @param string $method HTTP method.
	 * @param string $body   Request body.
	 * @param array  $headers Additional headers.
	 * @return array|WP_Error Response or error.
	 */
	private function caldav_request( $url, $method = 'GET', $body = null, $headers = array() ) {
		if ( ! $this->username || ! $this->password ) {
			return new WP_Error( 'not_configured', __( 'iCloud Calendar credentials not configured', 'nonprofitsuite' ) );
		}

		$default_headers = array(
			'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
			'Content-Type'  => 'text/calendar; charset=utf-8',
			'User-Agent'    => 'NonprofitSuite CalDAV Client',
		);

		$headers = array_merge( $default_headers, $headers );

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( $body ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Success codes for CalDAV
		if ( in_array( $status_code, array( 200, 201, 204, 207 ) ) ) {
			return array(
				'status' => $status_code,
				'body'   => $body,
			);
		}

		// Error handling
		return new WP_Error(
			'caldav_error',
			sprintf(
				__( 'CalDAV request failed with status %d: %s', 'nonprofitsuite' ),
				$status_code,
				$body
			)
		);
	}

	/**
	 * Discover calendar home URL
	 *
	 * @return string|WP_Error Calendar home URL or error.
	 */
	public function discover_calendar_home() {
		$principal_url = $this->caldav_url . 'principals/' . urlencode( $this->username ) . '/';

		$body = '<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <c:calendar-home-set />
  </d:prop>
</d:propfind>';

		$response = $this->caldav_request(
			$principal_url,
			'PROPFIND',
			$body,
			array( 'Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse XML response to extract calendar-home-set
		$xml = simplexml_load_string( $response['body'] );
		if ( ! $xml ) {
			return new WP_Error( 'xml_parse_error', __( 'Failed to parse CalDAV response', 'nonprofitsuite' ) );
		}

		$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );
		$xml->registerXPathNamespace( 'd', 'DAV:' );

		$calendar_home = $xml->xpath( '//c:calendar-home-set/d:href' );

		if ( empty( $calendar_home ) ) {
			return new WP_Error( 'calendar_home_not_found', __( 'Could not find calendar home', 'nonprofitsuite' ) );
		}

		return rtrim( $this->caldav_url, '/' ) . (string) $calendar_home[0];
	}

	/**
	 * List available calendars
	 *
	 * @return array|WP_Error Array of calendars or error.
	 */
	public function list_calendars() {
		if ( ! $this->calendar_home ) {
			$calendar_home = $this->discover_calendar_home();
			if ( is_wp_error( $calendar_home ) ) {
				return $calendar_home;
			}
			$this->calendar_home = $calendar_home;
		}

		$body = '<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/">
  <d:prop>
    <d:displayname />
    <cs:getctag />
    <c:calendar-description />
  </d:prop>
</d:propfind>';

		$response = $this->caldav_request(
			$this->calendar_home,
			'PROPFIND',
			$body,
			array( 'Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse calendars from XML
		$xml = simplexml_load_string( $response['body'] );
		if ( ! $xml ) {
			return new WP_Error( 'xml_parse_error', __( 'Failed to parse calendars response', 'nonprofitsuite' ) );
		}

		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$calendars = array();

		foreach ( $xml->xpath( '//d:response' ) as $response_node ) {
			$href = (string) $response_node->xpath( 'd:href' )[0];
			$displayname = $response_node->xpath( 'd:propstat/d:prop/d:displayname' );

			if ( ! empty( $displayname ) ) {
				$calendars[] = array(
					'url'  => rtrim( $this->caldav_url, '/' ) . $href,
					'name' => (string) $displayname[0],
				);
			}
		}

		return $calendars;
	}

	/**
	 * Convert event data to iCal format
	 *
	 * @param array  $event_data Event data.
	 * @param string $uid        Event UID (optional, generated if not provided).
	 * @return string iCal format string.
	 */
	private function event_to_ical( $event_data, $uid = null ) {
		if ( ! $uid ) {
			$uid = wp_generate_uuid4();
		}

		$ical = "BEGIN:VCALENDAR\r\n";
		$ical .= "VERSION:2.0\r\n";
		$ical .= "PRODID:-//NonprofitSuite//CalDAV Client//EN\r\n";
		$ical .= "BEGIN:VEVENT\r\n";
		$ical .= "UID:" . $uid . "\r\n";
		$ical .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";

		// Title
		$ical .= "SUMMARY:" . $this->escape_ical_text( $event_data['title'] ) . "\r\n";

		// Description
		if ( ! empty( $event_data['description'] ) ) {
			$ical .= "DESCRIPTION:" . $this->escape_ical_text( $event_data['description'] ) . "\r\n";
		}

		// Location
		if ( ! empty( $event_data['location'] ) ) {
			$ical .= "LOCATION:" . $this->escape_ical_text( $event_data['location'] ) . "\r\n";
		}

		// Start and end times
		if ( ! empty( $event_data['all_day'] ) ) {
			$ical .= "DTSTART;VALUE=DATE:" . gmdate( 'Ymd', strtotime( $event_data['start_datetime'] ) ) . "\r\n";
			$ical .= "DTEND;VALUE=DATE:" . gmdate( 'Ymd', strtotime( $event_data['end_datetime'] ) ) . "\r\n";
		} else {
			$ical .= "DTSTART:" . gmdate( 'Ymd\THis\Z', strtotime( $event_data['start_datetime'] ) ) . "\r\n";
			$ical .= "DTEND:" . gmdate( 'Ymd\THis\Z', strtotime( $event_data['end_datetime'] ) ) . "\r\n";
		}

		// Attendees
		if ( ! empty( $event_data['attendees'] ) && is_array( $event_data['attendees'] ) ) {
			foreach ( $event_data['attendees'] as $email ) {
				$ical .= "ATTENDEE;CN=" . $email . ";ROLE=REQ-PARTICIPANT:mailto:" . $email . "\r\n";
			}
		}

		// Meeting URL
		if ( ! empty( $event_data['meeting_url'] ) ) {
			$ical .= "URL:" . $event_data['meeting_url'] . "\r\n";
		}

		// Reminders
		if ( ! empty( $event_data['reminders'] ) && is_array( $event_data['reminders'] ) ) {
			foreach ( $event_data['reminders'] as $reminder ) {
				$minutes = isset( $reminder['minutes'] ) ? (int) $reminder['minutes'] : 15;
				$ical .= "BEGIN:VALARM\r\n";
				$ical .= "TRIGGER:-PT" . $minutes . "M\r\n";
				$ical .= "ACTION:DISPLAY\r\n";
				$ical .= "DESCRIPTION:Event reminder\r\n";
				$ical .= "END:VALARM\r\n";
			}
		}

		$ical .= "END:VEVENT\r\n";
		$ical .= "END:VCALENDAR\r\n";

		return $ical;
	}

	/**
	 * Escape text for iCal format
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	private function escape_ical_text( $text ) {
		$text = str_replace( array( "\\", ";", ",", "\n", "\r" ), array( "\\\\", "\\;", "\\,", "\\n", "" ), $text );
		return $text;
	}

	/**
	 * Parse iCal format to event data
	 *
	 * @param string $ical iCal format string.
	 * @return array|WP_Error Event data or error.
	 */
	private function ical_to_event( $ical ) {
		$lines = explode( "\r\n", $ical );
		$event = array();

		foreach ( $lines as $line ) {
			if ( empty( $line ) ) {
				continue;
			}

			if ( strpos( $line, ':' ) !== false ) {
				list( $key, $value ) = explode( ':', $line, 2 );

				// Handle property parameters (e.g., DTSTART;VALUE=DATE)
				if ( strpos( $key, ';' ) !== false ) {
					$key = substr( $key, 0, strpos( $key, ';' ) );
				}

				switch ( $key ) {
					case 'UID':
						$event['uid'] = $value;
						break;
					case 'SUMMARY':
						$event['title'] = $this->unescape_ical_text( $value );
						break;
					case 'DESCRIPTION':
						$event['description'] = $this->unescape_ical_text( $value );
						break;
					case 'LOCATION':
						$event['location'] = $this->unescape_ical_text( $value );
						break;
					case 'DTSTART':
						$event['start_datetime'] = $this->parse_ical_datetime( $value );
						break;
					case 'DTEND':
						$event['end_datetime'] = $this->parse_ical_datetime( $value );
						break;
					case 'URL':
						$event['meeting_url'] = $value;
						break;
				}
			}
		}

		return $event;
	}

	/**
	 * Unescape iCal text
	 *
	 * @param string $text Escaped text.
	 * @return string Unescaped text.
	 */
	private function unescape_ical_text( $text ) {
		$text = str_replace( array( "\\\\", "\\;", "\\,", "\\n" ), array( "\\", ";", ",", "\n" ), $text );
		return $text;
	}

	/**
	 * Parse iCal datetime
	 *
	 * @param string $datetime iCal datetime string.
	 * @return string MySQL datetime.
	 */
	private function parse_ical_datetime( $datetime ) {
		// Handle different formats: 20240101, 20240101T120000Z, etc.
		$datetime = str_replace( array( 'T', 'Z' ), array( ' ', '' ), $datetime );

		if ( strlen( $datetime ) === 8 ) {
			// Date only: 20240101
			return gmdate( 'Y-m-d H:i:s', strtotime( $datetime ) );
		} else {
			// DateTime: 20240101 120000
			return gmdate( 'Y-m-d H:i:s', strtotime( $datetime ) );
		}
	}

	/**
	 * Create a calendar event
	 *
	 * @param array $event_data Event data.
	 * @return array|WP_Error Event data.
	 */
	public function create_event( $event_data ) {
		if ( ! $this->calendar_url ) {
			return new WP_Error( 'no_calendar', __( 'No calendar URL configured', 'nonprofitsuite' ) );
		}

		$uid = wp_generate_uuid4();
		$ical = $this->event_to_ical( $event_data, $uid );

		// Event URL is calendar_url + uid.ics
		$event_url = trailingslashit( $this->calendar_url ) . $uid . '.ics';

		$response = $this->caldav_request( $event_url, 'PUT', $ical );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		/**
		 * Fires after calendar event is created
		 *
		 * @param string $uid        Event UID.
		 * @param array  $event_data Event data.
		 */
		do_action( 'ns_calendar_event_created', $uid, $event_data, 'icloud' );

		return array(
			'event_id'  => $uid,
			'url'       => $event_url,
			'html_link' => 'https://www.icloud.com/calendar/',
		);
	}

	/**
	 * Update a calendar event
	 *
	 * @param string $event_id   Event identifier (UID).
	 * @param array  $event_data Updated event data.
	 * @return array|WP_Error Updated event data.
	 */
	public function update_event( $event_id, $event_data ) {
		if ( ! $this->calendar_url ) {
			return new WP_Error( 'no_calendar', __( 'No calendar URL configured', 'nonprofitsuite' ) );
		}

		// Get existing event to merge data
		$existing = $this->get_event( $event_id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		// Merge existing data with updates
		$merged_data = array_merge( $existing, $event_data );

		$ical = $this->event_to_ical( $merged_data, $event_id );
		$event_url = trailingslashit( $this->calendar_url ) . $event_id . '.ics';

		$response = $this->caldav_request( $event_url, 'PUT', $ical );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		/**
		 * Fires after calendar event is updated
		 *
		 * @param string $event_id   Event UID.
		 * @param array  $event_data Event data.
		 */
		do_action( 'ns_calendar_event_updated', $event_id, $event_data, 'icloud' );

		return $this->get_event( $event_id );
	}

	/**
	 * Delete a calendar event
	 *
	 * @param string $event_id Event identifier (UID).
	 * @return bool|WP_Error True on success.
	 */
	public function delete_event( $event_id ) {
		if ( ! $this->calendar_url ) {
			return new WP_Error( 'no_calendar', __( 'No calendar URL configured', 'nonprofitsuite' ) );
		}

		$event_url = trailingslashit( $this->calendar_url ) . $event_id . '.ics';
		$response = $this->caldav_request( $event_url, 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		do_action( 'ns_calendar_event_deleted', $event_id );

		return true;
	}

	/**
	 * Get a calendar event
	 *
	 * @param string $event_id Event identifier (UID).
	 * @return array|WP_Error Event data.
	 */
	public function get_event( $event_id ) {
		if ( ! $this->calendar_url ) {
			return new WP_Error( 'no_calendar', __( 'No calendar URL configured', 'nonprofitsuite' ) );
		}

		$event_url = trailingslashit( $this->calendar_url ) . $event_id . '.ics';
		$response = $this->caldav_request( $event_url, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->ical_to_event( $response['body'] );
	}

	/**
	 * List calendar events
	 *
	 * @param array $args List arguments.
	 * @return array|WP_Error Array of events.
	 */
	public function list_events( $args = array() ) {
		if ( ! $this->calendar_url ) {
			return new WP_Error( 'no_calendar', __( 'No calendar URL configured', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'start_date' => gmdate( 'Ymd\T000000\Z', strtotime( '-1 month' ) ),
			'end_date'   => gmdate( 'Ymd\T235959\Z', strtotime( '+6 months' ) ),
		) );

		// CalDAV REPORT query
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:getetag />
    <c:calendar-data />
  </d:prop>
  <c:filter>
    <c:comp-filter name="VCALENDAR">
      <c:comp-filter name="VEVENT">
        <c:time-range start="' . $args['start_date'] . '" end="' . $args['end_date'] . '" />
      </c:comp-filter>
    </c:comp-filter>
  </c:filter>
</c:calendar-query>';

		$response = $this->caldav_request(
			$this->calendar_url,
			'REPORT',
			$body,
			array( 'Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse events from XML
		$xml = simplexml_load_string( $response['body'] );
		if ( ! $xml ) {
			return array(); // Return empty array if no events
		}

		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

		$events = array();

		foreach ( $xml->xpath( '//d:response' ) as $response_node ) {
			$calendar_data = $response_node->xpath( 'd:propstat/d:prop/c:calendar-data' );

			if ( ! empty( $calendar_data ) ) {
				$ical_data = (string) $calendar_data[0];
				$event = $this->ical_to_event( $ical_data );

				if ( ! empty( $event ) ) {
					$events[] = $event;
				}
			}
		}

		return $events;
	}

	/**
	 * Add attendees to an event
	 *
	 * @param string $event_id  Event identifier.
	 * @param array  $attendees Array of email addresses.
	 * @return bool|WP_Error True on success.
	 */
	public function add_attendees( $event_id, $attendees ) {
		// Get existing event
		$event = $this->get_event( $event_id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}

		// Merge attendees
		$existing_attendees = isset( $event['attendees'] ) ? $event['attendees'] : array();
		$event['attendees'] = array_unique( array_merge( $existing_attendees, $attendees ) );

		// Update event
		$result = $this->update_event( $event_id, $event );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Remove attendees from an event
	 *
	 * @param string $event_id  Event identifier.
	 * @param array  $attendees Array of email addresses.
	 * @return bool|WP_Error True on success.
	 */
	public function remove_attendees( $event_id, $attendees ) {
		// Get existing event
		$event = $this->get_event( $event_id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}

		// Remove attendees
		$existing_attendees = isset( $event['attendees'] ) ? $event['attendees'] : array();
		$event['attendees'] = array_diff( $existing_attendees, $attendees );

		// Update event
		$result = $this->update_event( $event_id, $event );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Get iCal/ICS feed URL
	 *
	 * @param array $args Feed arguments.
	 * @return string|WP_Error Feed URL.
	 */
	public function get_ical_feed( $args = array() ) {
		if ( ! $this->calendar_url ) {
			return new WP_Error( 'no_calendar', __( 'No calendar URL configured', 'nonprofitsuite' ) );
		}

		// iCloud calendar URL can be used directly as iCal feed
		return $this->calendar_url;
	}

	/**
	 * Sync events from iCloud Calendar to NonprofitSuite
	 *
	 * @param array $args Sync arguments.
	 * @return array|WP_Error Sync result.
	 */
	public function sync_events( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'start_date' => gmdate( 'Ymd\T000000\Z', strtotime( '-1 month' ) ),
			'end_date'   => gmdate( 'Ymd\T235959\Z', strtotime( '+6 months' ) ),
		) );

		// Get events from iCloud
		$icloud_events = $this->list_events( $args );

		if ( is_wp_error( $icloud_events ) ) {
			return $icloud_events;
		}

		$synced_count = 0;
		$skipped_count = 0;
		$errors = array();

		$table = $wpdb->prefix . 'ns_calendar_events';

		foreach ( $icloud_events as $icloud_event ) {
			// Check if event already exists by external_id (UID)
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE external_id = %s AND provider = 'icloud'",
				$icloud_event['uid']
			) );

			$event_data = array(
				'title'          => isset( $icloud_event['title'] ) ? $icloud_event['title'] : 'Untitled',
				'description'    => isset( $icloud_event['description'] ) ? $icloud_event['description'] : '',
				'start_datetime' => $icloud_event['start_datetime'],
				'end_datetime'   => $icloud_event['end_datetime'],
				'location'       => isset( $icloud_event['location'] ) ? $icloud_event['location'] : '',
				'external_id'    => $icloud_event['uid'],
				'provider'       => 'icloud',
				'synced_at'      => current_time( 'mysql' ),
			);

			if ( $existing ) {
				// Update existing event
				$result = $wpdb->update(
					$table,
					$event_data,
					array( 'id' => $existing->id ),
					null,
					array( '%d' )
				);
			} else {
				// Insert new event
				$event_data['created_by'] = get_current_user_id();
				$event_data['created_at'] = current_time( 'mysql' );
				$result = $wpdb->insert( $table, $event_data );
			}

			if ( false === $result ) {
				$errors[] = sprintf(
					__( 'Failed to sync event: %s', 'nonprofitsuite' ),
					$icloud_event['title']
				);
				$skipped_count++;
			} else {
				$synced_count++;
			}
		}

		return array(
			'synced_count'  => $synced_count,
			'skipped_count' => $skipped_count,
			'errors'        => $errors,
		);
	}

	/**
	 * Test connection to iCloud Calendar
	 *
	 * @return bool|WP_Error True if connected.
	 */
	public function test_connection() {
		if ( ! $this->username || ! $this->password ) {
			return new WP_Error( 'not_configured', __( 'iCloud Calendar credentials not configured', 'nonprofitsuite' ) );
		}

		// Try to discover calendar home
		$calendar_home = $this->discover_calendar_home();

		if ( is_wp_error( $calendar_home ) ) {
			return $calendar_home;
		}

		// Try to list calendars
		$calendars = $this->list_calendars();

		if ( is_wp_error( $calendars ) ) {
			return $calendars;
		}

		if ( empty( $calendars ) ) {
			return new WP_Error( 'no_calendars', __( 'No calendars found in iCloud account', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 */
	public function get_provider_name() {
		return __( 'Apple iCloud Calendar', 'nonprofitsuite' );
	}
}
