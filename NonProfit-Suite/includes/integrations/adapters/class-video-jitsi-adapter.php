<?php
/**
 * Jitsi Meet Video Adapter
 *
 * Adapter for Jitsi Meet video conferencing integration (open-source).
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
 * NonprofitSuite_Video_Jitsi_Adapter Class
 *
 * Implements video conferencing using Jitsi Meet (no API key required).
 */
class NonprofitSuite_Video_Jitsi_Adapter implements NonprofitSuite_Video_Adapter_Interface {

	/**
	 * Jitsi Meet default domain
	 */
	const DEFAULT_DOMAIN = 'meet.jit.si';

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Jitsi domain
	 *
	 * @var string
	 */
	private $domain;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'video', 'jitsi' );
		$this->domain = $this->settings['domain'] ?? self::DEFAULT_DOMAIN;
	}

	/**
	 * Create meeting
	 *
	 * @param array $meeting Meeting data
	 * @return array|WP_Error
	 */
	public function create_meeting( $meeting ) {
		$meeting = wp_parse_args( $meeting, array(
			'title'       => '',
			'start_time'  => '',
			'duration'    => 60,
			'timezone'    => 'UTC',
			'password'    => '',
			'settings'    => array(),
		) );

		// Generate room name from title (sanitized)
		$room_name = $this->generate_room_name( $meeting['title'] );

		// Jitsi doesn't require API calls, just generate the meeting URL
		$meeting_url = 'https://' . $this->domain . '/' . $room_name;

		// Add JWT token if configured for secure rooms
		if ( ! empty( $this->settings['app_id'] ) && ! empty( $this->settings['app_secret'] ) ) {
			$jwt = $this->generate_jwt( $room_name, $meeting );
			$meeting_url .= '?jwt=' . $jwt;
		}

		// Store meeting info locally
		global $wpdb;
		$table_name = $wpdb->prefix . 'ns_jitsi_meetings';

		$wpdb->insert(
			$table_name,
			array(
				'room_name'   => $room_name,
				'title'       => $meeting['title'],
				'start_time'  => $meeting['start_time'],
				'duration'    => $meeting['duration'],
				'meeting_url' => $meeting_url,
				'password'    => $meeting['password'],
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$meeting_id = 'jitsi_' . $room_name;

		do_action( 'ns_jitsi_meeting_created', $meeting_id, $meeting_url );

		return array(
			'meeting_id'  => $meeting_id,
			'meeting_url' => $meeting_url,
			'room_name'   => $room_name,
			'start_url'   => $meeting_url, // Host uses same URL
			'join_url'    => $meeting_url,
			'password'    => $meeting['password'],
		);
	}

	/**
	 * Get meeting
	 *
	 * @param string $meeting_id Meeting ID
	 * @return array|WP_Error
	 */
	public function get_meeting( $meeting_id ) {
		global $wpdb;

		$room_name = str_replace( 'jitsi_', '', $meeting_id );
		$table_name = $wpdb->prefix . 'ns_jitsi_meetings';

		$meeting = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE room_name = %s",
				$room_name
			),
			ARRAY_A
		);

		if ( ! $meeting ) {
			return new WP_Error( 'not_found', __( 'Meeting not found', 'nonprofitsuite' ) );
		}

		return array(
			'meeting_id'  => 'jitsi_' . $meeting['room_name'],
			'title'       => $meeting['title'],
			'start_time'  => $meeting['start_time'],
			'duration'    => (int) $meeting['duration'],
			'meeting_url' => $meeting['meeting_url'],
			'status'      => 'active',
		);
	}

	/**
	 * Update meeting
	 *
	 * @param string $meeting_id Meeting ID
	 * @param array  $data       Update data
	 * @return bool|WP_Error
	 */
	public function update_meeting( $meeting_id, $data ) {
		global $wpdb;

		$room_name = str_replace( 'jitsi_', '', $meeting_id );
		$table_name = $wpdb->prefix . 'ns_jitsi_meetings';

		$update_data = array();

		if ( isset( $data['title'] ) ) {
			$update_data['title'] = $data['title'];
		}

		if ( isset( $data['start_time'] ) ) {
			$update_data['start_time'] = $data['start_time'];
		}

		if ( isset( $data['duration'] ) ) {
			$update_data['duration'] = $data['duration'];
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'room_name' => $room_name ),
			array_fill( 0, count( $update_data ), '%s' ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * Delete meeting
	 *
	 * @param string $meeting_id Meeting ID
	 * @return bool|WP_Error
	 */
	public function delete_meeting( $meeting_id ) {
		global $wpdb;

		$room_name = str_replace( 'jitsi_', '', $meeting_id );
		$table_name = $wpdb->prefix . 'ns_jitsi_meetings';

		$result = $wpdb->delete(
			$table_name,
			array( 'room_name' => $room_name ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * List meetings
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public function list_meetings( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'limit'  => 50,
			'offset' => 0,
		) );

		$table_name = $wpdb->prefix . 'ns_jitsi_meetings';

		$meetings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $meetings as $meeting ) {
			$result[] = array(
				'meeting_id'  => 'jitsi_' . $meeting['room_name'],
				'title'       => $meeting['title'],
				'start_time'  => $meeting['start_time'],
				'duration'    => (int) $meeting['duration'],
				'meeting_url' => $meeting['meeting_url'],
			);
		}

		return $result;
	}

	/**
	 * Get meeting statistics
	 *
	 * Note: Jitsi doesn't provide analytics via API
	 *
	 * @param string $meeting_id Meeting ID
	 * @return array|WP_Error
	 */
	public function get_meeting_stats( $meeting_id ) {
		return new WP_Error( 'not_supported', __( 'Jitsi Meet does not provide meeting analytics', 'nonprofitsuite' ) );
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		// Check if Jitsi domain is accessible
		$response = wp_remote_head( 'https://' . $this->domain );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 400 ) {
			return new WP_Error( 'connection_failed', sprintf( __( 'Cannot reach Jitsi server at %s', 'nonprofitsuite' ), $this->domain ) );
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Jitsi Meet';
	}

	/**
	 * Generate room name from title
	 *
	 * @param string $title Meeting title
	 * @return string Room name
	 */
	private function generate_room_name( $title ) {
		// Sanitize title and add unique suffix
		$sanitized = sanitize_title( $title );
		$unique = substr( md5( $title . time() ), 0, 8 );

		return $sanitized . '-' . $unique;
	}

	/**
	 * Generate JWT token for secure rooms
	 *
	 * Requires JWT PHP library
	 *
	 * @param string $room_name Room name
	 * @param array  $meeting   Meeting data
	 * @return string JWT token
	 */
	private function generate_jwt( $room_name, $meeting ) {
		if ( ! class_exists( 'Firebase\JWT\JWT' ) ) {
			return '';
		}

		$app_id = $this->settings['app_id'] ?? '';
		$app_secret = $this->settings['app_secret'] ?? '';

		$payload = array(
			'aud'     => 'jitsi',
			'iss'     => $app_id,
			'sub'     => $this->domain,
			'room'    => $room_name,
			'context' => array(
				'user' => array(
					'name' => $meeting['host_name'] ?? 'Host',
				),
			),
			'moderator' => true,
			'iat'       => time(),
			'exp'       => time() + ( $meeting['duration'] * 60 ) + 3600, // Duration + 1 hour buffer
		);

		try {
			return \Firebase\JWT\JWT::encode( $payload, $app_secret, 'HS256' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Create Jitsi meetings table
	 *
	 * Should be called during plugin activation or migration
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ns_jitsi_meetings';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			room_name varchar(255) NOT NULL,
			title varchar(255) NOT NULL,
			start_time datetime,
			duration int DEFAULT 60,
			meeting_url text,
			password varchar(100),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY room_name (room_name),
			KEY start_time (start_time)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Get embed code for meeting
	 *
	 * @param string $meeting_id Meeting ID
	 * @param array  $options    Embed options
	 * @return string HTML embed code
	 */
	public function get_embed_code( $meeting_id, $options = array() ) {
		$meeting = $this->get_meeting( $meeting_id );

		if ( is_wp_error( $meeting ) ) {
			return '';
		}

		$options = wp_parse_args( $options, array(
			'width'  => '100%',
			'height' => '600px',
		) );

		$room_name = str_replace( 'jitsi_', '', $meeting_id );

		return sprintf(
			'<iframe src="https://%s/%s" width="%s" height="%s" frameborder="0" allow="camera; microphone; fullscreen; display-capture" allowfullscreen></iframe>',
			esc_attr( $this->domain ),
			esc_attr( $room_name ),
			esc_attr( $options['width'] ),
			esc_attr( $options['height'] )
		);
	}
}
