<?php
/**
 * Gmail API Email Adapter
 *
 * Adapter that uses Gmail API for sending emails with OAuth 2.0 authentication.
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
 * NonprofitSuite_Email_Gmail_Adapter Class
 *
 * Implements email integration using Gmail API.
 */
class NonprofitSuite_Email_Gmail_Adapter implements NonprofitSuite_Email_Adapter_Interface {

	/**
	 * Google Client instance
	 *
	 * @var Google_Client
	 */
	private $client;

	/**
	 * Gmail service instance
	 *
	 * @var Google_Service_Gmail
	 */
	private $service;

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'email', 'gmail' );

		$this->init_client();
	}

	/**
	 * Initialize Google Client
	 */
	private function init_client() {
		if ( ! class_exists( 'Google_Client' ) ) {
			return;
		}

		$this->client = new Google_Client();
		$this->client->setApplicationName( 'NonprofitSuite' );
		$this->client->setScopes( Google_Service_Gmail::GMAIL_SEND );

		if ( ! empty( $this->settings['client_id'] ) ) {
			$this->client->setClientId( $this->settings['client_id'] );
		}

		if ( ! empty( $this->settings['client_secret'] ) ) {
			$this->client->setClientSecret( $this->settings['client_secret'] );
		}

		if ( ! empty( $this->settings['access_token'] ) ) {
			$this->client->setAccessToken( $this->settings['access_token'] );

			// Refresh token if expired
			if ( $this->client->isAccessTokenExpired() && ! empty( $this->settings['refresh_token'] ) ) {
				$this->client->fetchAccessTokenWithRefreshToken( $this->settings['refresh_token'] );

				// Save new access token
				$manager = NonprofitSuite_Integration_Manager::get_instance();
				$this->settings['access_token'] = $this->client->getAccessToken();
				$manager->save_provider_settings( 'email', 'gmail', $this->settings );
			}
		}

		$this->service = new Google_Service_Gmail( $this->client );
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

		// Check if client is initialized
		if ( ! $this->service ) {
			return new WP_Error( 'not_initialized', __( 'Gmail API client not initialized', 'nonprofitsuite' ) );
		}

		try {
			// Build email message
			$message = $this->build_message( $args );

			// Send via Gmail API
			$sent_message = $this->service->users_messages->send( 'me', $message );

			do_action( 'ns_email_sent', $args['to'], $args['subject'], $args['message'], 'gmail' );

			return array(
				'message_id' => $sent_message->getId(),
				'thread_id'  => $sent_message->getThreadId(),
				'status'     => 'sent',
			);

		} catch ( Exception $e ) {
			do_action( 'ns_email_failed', $args['to'], $args['subject'], $e, 'gmail' );

			return new WP_Error(
				'send_failed',
				sprintf( __( 'Gmail API error: %s', 'nonprofitsuite' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Build Gmail message
	 *
	 * @param array $args Email arguments
	 * @return Google_Service_Gmail_Message
	 */
	private function build_message( $args ) {
		$email_raw = '';

		// Build headers
		$headers = array();
		$headers[] = sprintf( 'To: %s', is_array( $args['to'] ) ? implode( ', ', $args['to'] ) : $args['to'] );
		$headers[] = sprintf( 'From: %s <%s>', $args['from_name'], $args['from'] );
		$headers[] = sprintf( 'Subject: %s', $args['subject'] );

		if ( $args['reply_to'] ) {
			$headers[] = sprintf( 'Reply-To: %s', $args['reply_to'] );
		}

		if ( $args['cc'] ) {
			$cc = is_array( $args['cc'] ) ? implode( ', ', $args['cc'] ) : $args['cc'];
			$headers[] = sprintf( 'Cc: %s', $cc );
		}

		if ( $args['bcc'] ) {
			$bcc = is_array( $args['bcc'] ) ? implode( ', ', $args['bcc'] ) : $args['bcc'];
			$headers[] = sprintf( 'Bcc: %s', $bcc );
		}

		if ( $args['html'] ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		} else {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		}

		// Add custom headers for tracking
		if ( ! empty( $args['tags'] ) ) {
			$headers[] = sprintf( 'X-NS-Tags: %s', implode( ',', $args['tags'] ) );
		}

		// Combine headers and body
		$email_raw = implode( "\r\n", $headers );
		$email_raw .= "\r\n\r\n";
		$email_raw .= $args['message'];

		// Base64 URL encode
		$raw_message = base64_encode( $email_raw );
		$raw_message = str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), $raw_message );

		// Create Gmail message object
		$message = new Google_Service_Gmail_Message();
		$message->setRaw( $raw_message );

		return $message;
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
	 * @param string $message_id Message identifier
	 * @return array|WP_Error Status array
	 */
	public function get_status( $message_id ) {
		if ( ! $this->service ) {
			return new WP_Error( 'not_initialized', __( 'Gmail API client not initialized', 'nonprofitsuite' ) );
		}

		try {
			$message = $this->service->users_messages->get( 'me', $message_id, array( 'format' => 'metadata' ) );

			return array(
				'message_id' => $message_id,
				'thread_id'  => $message->getThreadId(),
				'status'     => 'delivered', // Gmail API doesn't provide detailed delivery status
				'timestamp'  => date( 'Y-m-d H:i:s', $message->getInternalDate() / 1000 ),
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'status_failed',
				sprintf( __( 'Failed to get message status: %s', 'nonprofitsuite' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Create email template (stored locally)
	 *
	 * Gmail API doesn't have native template support, so we store in database
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
				'provider'    => 'gmail',
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
				"SELECT * FROM {$table} WHERE provider = 'gmail' ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			)
		);

		return $templates ?: array();
	}

	/**
	 * Get email statistics
	 *
	 * Gmail API has limited analytics, this returns basic stats from our log
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
				WHERE provider = 'gmail'
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
		if ( ! $this->client ) {
			return new WP_Error( 'no_client', __( 'Gmail client not initialized', 'nonprofitsuite' ) );
		}

		if ( ! $this->client->getAccessToken() ) {
			return new WP_Error( 'not_authenticated', __( 'Gmail not authenticated. Please connect your account.', 'nonprofitsuite' ) );
		}

		if ( $this->client->isAccessTokenExpired() ) {
			return new WP_Error( 'token_expired', __( 'Gmail access token expired. Please reconnect.', 'nonprofitsuite' ) );
		}

		try {
			// Test API access with a simple profile call
			$profile = $this->service->users->getProfile( 'me' );

			if ( $profile->getEmailAddress() ) {
				return true;
			}

			return new WP_Error( 'connection_failed', __( 'Unable to verify Gmail connection', 'nonprofitsuite' ) );

		} catch ( Exception $e ) {
			return new WP_Error(
				'api_error',
				sprintf( __( 'Gmail API error: %s', 'nonprofitsuite' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return 'Gmail API';
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @return string Authorization URL
	 */
	public function get_auth_url() {
		if ( ! $this->client ) {
			return '';
		}

		$redirect_uri = admin_url( 'admin.php?page=nonprofitsuite-integrations&provider=gmail&action=oauth_callback' );
		$this->client->setRedirectUri( $redirect_uri );

		return $this->client->createAuthUrl();
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code
	 * @return bool|WP_Error True on success
	 */
	public function handle_oauth_callback( $code ) {
		if ( ! $this->client ) {
			return new WP_Error( 'no_client', __( 'Gmail client not initialized', 'nonprofitsuite' ) );
		}

		try {
			$token = $this->client->fetchAccessTokenWithAuthCode( $code );

			if ( isset( $token['error'] ) ) {
				return new WP_Error( 'oauth_error', $token['error_description'] ?? $token['error'] );
			}

			// Save tokens
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$this->settings['access_token'] = $token;
			$this->settings['refresh_token'] = $token['refresh_token'] ?? '';
			$manager->save_provider_settings( 'email', 'gmail', $this->settings );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error(
				'oauth_failed',
				sprintf( __( 'OAuth failed: %s', 'nonprofitsuite' ), $e->getMessage() )
			);
		}
	}
}
