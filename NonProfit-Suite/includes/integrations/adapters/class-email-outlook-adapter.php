<?php
/**
 * Outlook Email Adapter
 *
 * Adapter that uses Microsoft Graph API for sending emails via Outlook/Exchange.
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
 * NonprofitSuite_Email_Outlook_Adapter Class
 *
 * Implements email integration using Microsoft Graph Mail API.
 */
class NonprofitSuite_Email_Outlook_Adapter implements NonprofitSuite_Email_Adapter_Interface {

	/**
	 * Microsoft Graph API base URL
	 */
	const API_BASE_URL = 'https://graph.microsoft.com/v1.0/';

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
		$this->settings = $manager->get_provider_settings( 'email', 'outlook' );
		$this->access_token = $this->settings['access_token'] ?? '';

		// Refresh token if expired
		if ( ! empty( $this->settings['refresh_token'] ) && $this->is_token_expired() ) {
			$this->refresh_access_token();
		}
	}

	/**
	 * Check if access token is expired
	 *
	 * @return bool
	 */
	private function is_token_expired() {
		if ( empty( $this->settings['token_expires_at'] ) ) {
			return true;
		}

		return time() >= $this->settings['token_expires_at'];
	}

	/**
	 * Refresh access token
	 *
	 * @return bool|WP_Error
	 */
	private function refresh_access_token() {
		$response = wp_remote_post( 'https://login.microsoftonline.com/common/oauth2/v2.0/token', array(
			'body' => array(
				'client_id'     => $this->settings['client_id'] ?? '',
				'client_secret' => $this->settings['client_secret'] ?? '',
				'refresh_token' => $this->settings['refresh_token'] ?? '',
				'grant_type'    => 'refresh_token',
				'scope'         => 'https://graph.microsoft.com/Mail.Send offline_access',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$this->settings['access_token'] = $body['access_token'];
			$this->settings['token_expires_at'] = time() + $body['expires_in'];
			$this->access_token = $body['access_token'];
			$manager->save_provider_settings( 'email', 'outlook', $this->settings );

			return true;
		}

		return new WP_Error( 'refresh_failed', __( 'Failed to refresh access token', 'nonprofitsuite' ) );
	}

	/**
	 * Send an email
	 *
	 * @param array $args Email arguments
	 * @return array|WP_Error Result
	 */
	public function send( $args ) {
		$args = wp_parse_args( $args, array(
			'to'          => '',
			'subject'     => '',
			'message'     => '',
			'from'        => $this->settings['from_email'] ?? get_option( 'admin_email' ),
			'from_name'   => $this->settings['from_name'] ?? get_option( 'blogname' ),
			'reply_to'    => '',
			'cc'          => '',
			'bcc'         => '',
			'attachments' => array(),
			'html'        => true,
			'tags'        => array(),
		) );

		// Validate required fields
		if ( empty( $args['to'] ) || empty( $args['subject'] ) || empty( $args['message'] ) ) {
			return new WP_Error( 'missing_required', __( 'Missing required email fields', 'nonprofitsuite' ) );
		}

		// Check if we have an access token
		if ( empty( $this->access_token ) ) {
			return new WP_Error( 'not_authenticated', __( 'Outlook not authenticated', 'nonprofitsuite' ) );
		}

		// Build email message
		$message = $this->build_message( $args );

		// Send via Graph API
		$response = wp_remote_post( self::API_BASE_URL . 'me/sendMail', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $message ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			do_action( 'ns_email_failed', $args['to'], $args['subject'], $response, 'outlook' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 202 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_message = $body['error']['message'] ?? __( 'Unknown error', 'nonprofitsuite' );

			do_action( 'ns_email_failed', $args['to'], $args['subject'], new WP_Error( 'send_failed', $error_message ), 'outlook' );

			return new WP_Error(
				'send_failed',
				sprintf( __( 'Outlook API error: %s', 'nonprofitsuite' ), $error_message )
			);
		}

		do_action( 'ns_email_sent', $args['to'], $args['subject'], $args['message'], 'outlook' );

		return array(
			'message_id' => md5( $args['to'] . $args['subject'] . microtime() ),
			'status'     => 'sent',
		);
	}

	/**
	 * Build Outlook message
	 *
	 * @param array $args Email arguments
	 * @return array
	 */
	private function build_message( $args ) {
		// Build message structure for Graph API
		$message = array(
			'message' => array(
				'subject' => $args['subject'],
				'body'    => array(
					'contentType' => $args['html'] ? 'HTML' : 'Text',
					'content'     => $args['message'],
				),
				'toRecipients' => $this->format_recipients( $args['to'] ),
			),
			'saveToSentItems' => true,
		);

		// Add CC recipients
		if ( ! empty( $args['cc'] ) ) {
			$message['message']['ccRecipients'] = $this->format_recipients( $args['cc'] );
		}

		// Add BCC recipients
		if ( ! empty( $args['bcc'] ) ) {
			$message['message']['bccRecipients'] = $this->format_recipients( $args['bcc'] );
		}

		// Add reply-to
		if ( ! empty( $args['reply_to'] ) ) {
			$message['message']['replyTo'] = $this->format_recipients( $args['reply_to'] );
		}

		return $message;
	}

	/**
	 * Format recipients for Graph API
	 *
	 * @param string|array $recipients
	 * @return array
	 */
	private function format_recipients( $recipients ) {
		if ( ! is_array( $recipients ) ) {
			$recipients = array( $recipients );
		}

		$formatted = array();

		foreach ( $recipients as $recipient ) {
			$formatted[] = array(
				'emailAddress' => array(
					'address' => trim( $recipient ),
				),
			);
		}

		return $formatted;
	}

	/**
	 * Send bulk emails
	 *
	 * @param array $emails Array of email argument arrays
	 * @return array|WP_Error Result
	 */
	public function send_bulk( $emails ) {
		$sent_count = 0;
		$failed_count = 0;
		$errors = array();

		foreach ( $emails as $email ) {
			$result = $this->send( $email );

			if ( is_wp_error( $result ) ) {
				$failed_count++;
				$errors[] = $result->get_error_message();
			} else {
				$sent_count++;
			}

			// Small delay to avoid rate limiting
			usleep( 100000 ); // 0.1 second
		}

		return array(
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
			'errors'       => $errors,
		);
	}

	/**
	 * Get email delivery status
	 *
	 * Graph API doesn't provide detailed delivery tracking for sent messages
	 *
	 * @param string $message_id Message identifier
	 * @return array|WP_Error Status array
	 */
	public function get_status( $message_id ) {
		return new WP_Error(
			'not_supported',
			__( 'Delivery tracking not fully supported by Outlook/Exchange', 'nonprofitsuite' )
		);
	}

	/**
	 * Create email template (stored locally)
	 *
	 * @param array $template_data Template data
	 * @return array|WP_Error Template data
	 */
	public function create_template( $template_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_email_templates';

		$result = $wpdb->insert(
			$table,
			array(
				'provider'    => 'outlook',
				'name'        => $template_data['name'] ?? '',
				'subject'     => $template_data['subject'] ?? '',
				'html'        => $template_data['html'] ?? '',
				'text'        => $template_data['text'] ?? '',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error( 'create_failed', __( 'Failed to create template', 'nonprofitsuite' ) );
		}

		return array(
			'template_id' => $wpdb->insert_id,
		);
	}

	/**
	 * Update email template
	 *
	 * @param string $template_id   Template identifier
	 * @param array  $template_data Updated template data
	 * @return bool|WP_Error True on success
	 */
	public function update_template( $template_id, $template_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_email_templates';

		$update_data = array();

		if ( isset( $template_data['name'] ) ) {
			$update_data['name'] = $template_data['name'];
		}
		if ( isset( $template_data['subject'] ) ) {
			$update_data['subject'] = $template_data['subject'];
		}
		if ( isset( $template_data['html'] ) ) {
			$update_data['html'] = $template_data['html'];
		}
		if ( isset( $template_data['text'] ) ) {
			$update_data['text'] = $template_data['text'];
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $template_id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update template', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Delete email template
	 *
	 * @param string $template_id Template identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete_template( $template_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_email_templates';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $template_id ),
			array( '%d' )
		);

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete template', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * List email templates
	 *
	 * @param array $args List arguments
	 * @return array|WP_Error Array of templates
	 */
	public function list_templates( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'limit'  => 50,
			'offset' => 0,
		) );

		$table = $wpdb->prefix . 'ns_email_templates';

		$templates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE provider = 'outlook' ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			)
		);

		return $templates ?: array();
	}

	/**
	 * Get email statistics
	 *
	 * @param array $args Statistics arguments
	 * @return array|WP_Error Statistics array
	 */
	public function get_statistics( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => date( 'Y-m-d' ),
		) );

		$table = $wpdb->prefix . 'ns_email_log';

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as sent,
					SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
					SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
					SUM(CASE WHEN opened > 0 THEN 1 ELSE 0 END) as opened,
					SUM(CASE WHEN clicked > 0 THEN 1 ELSE 0 END) as clicked
				FROM {$table}
				WHERE provider = 'outlook'
				AND sent_at BETWEEN %s AND %s",
				$args['start_date'] . ' 00:00:00',
				$args['end_date'] . ' 23:59:59'
			),
			ARRAY_A
		);

		return $stats ?: array(
			'sent'      => 0,
			'delivered' => 0,
			'bounced'   => 0,
			'opened'    => 0,
			'clicked'   => 0,
		);
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		if ( empty( $this->access_token ) ) {
			return new WP_Error( 'not_authenticated', __( 'Outlook not authenticated', 'nonprofitsuite' ) );
		}

		// Test API access with a simple profile call
		$response = wp_remote_get( self::API_BASE_URL . 'me', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 200 ) {
			return true;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$error_message = $body['error']['message'] ?? __( 'Connection failed', 'nonprofitsuite' );

		return new WP_Error( 'connection_failed', $error_message );
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return 'Microsoft Outlook';
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @return string Authorization URL
	 */
	public function get_auth_url() {
		$redirect_uri = admin_url( 'admin.php?page=nonprofitsuite-integrations&provider=outlook&action=oauth_callback' );

		$params = array(
			'client_id'     => $this->settings['client_id'] ?? '',
			'response_type' => 'code',
			'redirect_uri'  => $redirect_uri,
			'scope'         => 'https://graph.microsoft.com/Mail.Send offline_access',
			'response_mode' => 'query',
		);

		return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code
	 * @return bool|WP_Error True on success
	 */
	public function handle_oauth_callback( $code ) {
		$redirect_uri = admin_url( 'admin.php?page=nonprofitsuite-integrations&provider=outlook&action=oauth_callback' );

		$response = wp_remote_post( 'https://login.microsoftonline.com/common/oauth2/v2.0/token', array(
			'body' => array(
				'client_id'     => $this->settings['client_id'] ?? '',
				'client_secret' => $this->settings['client_secret'] ?? '',
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
				'scope'         => 'https://graph.microsoft.com/Mail.Send offline_access',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$this->settings['access_token'] = $body['access_token'];
			$this->settings['refresh_token'] = $body['refresh_token'] ?? '';
			$this->settings['token_expires_at'] = time() + $body['expires_in'];
			$this->access_token = $body['access_token'];
			$manager->save_provider_settings( 'email', 'outlook', $this->settings );

			return true;
		}

		$error_message = $body['error_description'] ?? $body['error'] ?? __( 'OAuth failed', 'nonprofitsuite' );
		return new WP_Error( 'oauth_failed', $error_message );
	}
}
