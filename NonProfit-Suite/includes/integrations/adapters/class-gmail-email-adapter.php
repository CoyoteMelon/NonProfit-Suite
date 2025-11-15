<?php
/**
 * Gmail API Email Adapter
 *
 * Email adapter for Gmail API with send/receive capabilities.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since      1.6.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Gmail_Email_Adapter Class
 *
 * Gmail API email adapter implementation.
 */
class NonprofitSuite_Gmail_Email_Adapter implements NonprofitSuite_Email_Adapter {

	/**
	 * Gmail API configuration.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * OAuth access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://gmail.googleapis.com/gmail/v1';

	/**
	 * Constructor.
	 *
	 * @param array $config Gmail configuration.
	 */
	public function __construct( $config = array() ) {
		$this->config = wp_parse_args( $config, array(
			'client_id'     => get_option( 'ns_gmail_client_id', '' ),
			'client_secret' => get_option( 'ns_gmail_client_secret', '' ),
			'refresh_token' => get_option( 'ns_gmail_refresh_token', '' ),
			'user_email'    => get_option( 'ns_gmail_user_email', '' ),
		) );

		$this->access_token = $this->get_access_token();
	}

	/**
	 * Get or refresh OAuth access token.
	 *
	 * @return string|null Access token or null.
	 */
	private function get_access_token() {
		// Check cached token
		$cached_token = get_transient( 'ns_gmail_access_token' );
		if ( $cached_token ) {
			return $cached_token;
		}

		// Refresh token
		if ( empty( $this->config['refresh_token'] ) ) {
			return null;
		}

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body' => array(
				'client_id'     => $this->config['client_id'],
				'client_secret' => $this->config['client_secret'],
				'refresh_token' => $this->config['refresh_token'],
				'grant_type'    => 'refresh_token',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$expires_in = isset( $body['expires_in'] ) ? $body['expires_in'] - 60 : 3540;
			set_transient( 'ns_gmail_access_token', $body['access_token'], $expires_in );
			return $body['access_token'];
		}

		return null;
	}

	/**
	 * Send an email via Gmail API.
	 *
	 * @param array $email_data Email data.
	 * @return array|WP_Error Send result.
	 */
	public function send( $email_data ) {
		if ( ! $this->access_token ) {
			return new WP_Error( 'no_token', __( 'Gmail authentication required', 'nonprofitsuite' ) );
		}

		// Build RFC 2822 formatted message
		$message = $this->build_mime_message( $email_data );

		// Encode message
		$encoded_message = rtrim( strtr( base64_encode( $message ), '+/', '-_' ), '=' );

		// Send via Gmail API
		$response = wp_remote_post( "{$this->api_base}/users/me/messages/send", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'raw' => $encoded_message,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'send_failed', isset( $body['error']['message'] ) ? $body['error']['message'] : 'Failed to send email' );
		}

		// Log email
		NonprofitSuite_Email_Routing::log_email( array_merge( $email_data, array(
			'direction'  => 'outbound',
			'adapter'    => 'gmail',
			'message_id' => $body['id'],
			'thread_id'  => $body['threadId'],
			'status'     => 'sent',
			'sent_at'    => current_time( 'mysql' ),
		) ) );

		return array(
			'message_id' => $body['id'],
			'thread_id'  => $body['threadId'],
			'status'     => 'sent',
			'metadata'   => $body,
		);
	}

	/**
	 * Build MIME message.
	 *
	 * @param array $email_data Email data.
	 * @return string MIME message.
	 */
	private function build_mime_message( $email_data ) {
		$to = is_array( $email_data['to'] ) ? implode( ', ', $email_data['to'] ) : $email_data['to'];
		$from = isset( $email_data['from_email'] ) ? $email_data['from_email'] : $this->config['user_email'];
		$from_name = isset( $email_data['from_name'] ) ? $email_data['from_name'] : '';

		$message = array();
		$message[] = 'MIME-Version: 1.0';
		$message[] = "To: {$to}";
		$message[] = $from_name ? "From: {$from_name} <{$from}>" : "From: {$from}";
		$message[] = "Subject: {$email_data['subject']}";

		if ( isset( $email_data['reply_to'] ) ) {
			$message[] = "Reply-To: {$email_data['reply_to']}";
		}

		if ( isset( $email_data['cc'] ) ) {
			$message[] = 'Cc: ' . implode( ', ', (array) $email_data['cc'] );
		}

		// Multipart message
		$boundary = uniqid( 'np_' );

		if ( isset( $email_data['body_html'] ) && isset( $email_data['body_text'] ) ) {
			$message[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
			$message[] = '';
			$message[] = "--{$boundary}";
			$message[] = 'Content-Type: text/plain; charset=UTF-8';
			$message[] = '';
			$message[] = $email_data['body_text'];
			$message[] = '';
			$message[] = "--{$boundary}";
			$message[] = 'Content-Type: text/html; charset=UTF-8';
			$message[] = '';
			$message[] = $email_data['body_html'];
			$message[] = '';
			$message[] = "--{$boundary}--";
		} elseif ( isset( $email_data['body_html'] ) ) {
			$message[] = 'Content-Type: text/html; charset=UTF-8';
			$message[] = '';
			$message[] = $email_data['body_html'];
		} else {
			$message[] = 'Content-Type: text/plain; charset=UTF-8';
			$message[] = '';
			$message[] = $email_data['body_text'];
		}

		return implode( "\r\n", $message );
	}

	/**
	 * Fetch incoming emails from Gmail.
	 *
	 * @param array $args Query arguments.
	 * @return array|WP_Error Emails or error.
	 */
	public function fetch_emails( $args = array() ) {
		if ( ! $this->access_token ) {
			return new WP_Error( 'no_token', __( 'Gmail authentication required', 'nonprofitsuite' ) );
		}

		$defaults = array(
			'limit'       => 50,
			'unread_only' => false,
			'label'       => null,
			'query'       => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query
		$query_parts = array();

		if ( $args['unread_only'] ) {
			$query_parts[] = 'is:unread';
		}

		if ( $args['label'] ) {
			$query_parts[] = "label:{$args['label']}";
		}

		if ( $args['query'] ) {
			$query_parts[] = $args['query'];
		}

		$query = implode( ' ', $query_parts );

		// List messages
		$list_url = add_query_arg( array(
			'maxResults' => $args['limit'],
			'q'          => $query,
		), "{$this->api_base}/users/me/messages" );

		$response = wp_remote_get( $list_url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['messages'] ) ) {
			return array();
		}

		// Fetch full message details
		$emails = array();

		foreach ( $body['messages'] as $message_ref ) {
			$email = $this->get_message( $message_ref['id'] );
			if ( ! is_wp_error( $email ) ) {
				$emails[] = $email;
			}
		}

		return $emails;
	}

	/**
	 * Get a specific message by ID.
	 *
	 * @param string $message_id Message ID.
	 * @return array|WP_Error Message data or error.
	 */
	public function get_message( $message_id ) {
		$response = wp_remote_get( "{$this->api_base}/users/me/messages/{$message_id}?format=full", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$message = json_decode( wp_remote_retrieve_body( $response ), true );

		return $this->parse_message( $message );
	}

	/**
	 * Parse Gmail message to standard format.
	 *
	 * @param array $message Gmail message object.
	 * @return array Parsed message.
	 */
	private function parse_message( $message ) {
		$headers = array();
		foreach ( $message['payload']['headers'] as $header ) {
			$headers[ $header['name'] ] = $header['value'];
		}

		$body_text = '';
		$body_html = '';

		// Extract body
		if ( isset( $message['payload']['parts'] ) ) {
			foreach ( $message['payload']['parts'] as $part ) {
				if ( $part['mimeType'] === 'text/plain' && isset( $part['body']['data'] ) ) {
					$body_text = base64_decode( strtr( $part['body']['data'], '-_', '+/' ) );
				} elseif ( $part['mimeType'] === 'text/html' && isset( $part['body']['data'] ) ) {
					$body_html = base64_decode( strtr( $part['body']['data'], '-_', '+/' ) );
				}
			}
		} elseif ( isset( $message['payload']['body']['data'] ) ) {
			$body_text = base64_decode( strtr( $message['payload']['body']['data'], '-_', '+/' ) );
		}

		return array(
			'message_id'   => $message['id'],
			'thread_id'    => $message['threadId'],
			'from_address' => isset( $headers['From'] ) ? $headers['From'] : '',
			'to'           => isset( $headers['To'] ) ? $headers['To'] : '',
			'subject'      => isset( $headers['Subject'] ) ? $headers['Subject'] : '',
			'body_text'    => $body_text,
			'body_html'    => $body_html,
			'date'         => isset( $headers['Date'] ) ? $headers['Date'] : '',
			'labels'       => $message['labelIds'],
			'raw_message'  => $message,
		);
	}

	/**
	 * Get email delivery status.
	 *
	 * @param string $message_id Message ID.
	 * @return array|WP_Error Status information.
	 */
	public function get_status( $message_id ) {
		// Gmail doesn't provide detailed delivery tracking
		// Could check if message exists in Sent folder
		return array(
			'status' => 'sent',
			'message' => 'Gmail API does not provide detailed delivery tracking',
		);
	}

	/**
	 * Validate configuration.
	 *
	 * @return bool|WP_Error Validation result.
	 */
	public function validate_config() {
		if ( empty( $this->config['client_id'] ) || empty( $this->config['client_secret'] ) ) {
			return new WP_Error( 'missing_credentials', __( 'Gmail OAuth credentials are required', 'nonprofitsuite' ) );
		}

		if ( empty( $this->config['refresh_token'] ) ) {
			return new WP_Error( 'not_authorized', __( 'Gmail account not authorized', 'nonprofitsuite' ) );
		}

		if ( ! $this->access_token ) {
			return new WP_Error( 'token_failed', __( 'Failed to obtain access token', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get adapter capabilities.
	 *
	 * @return array Capabilities.
	 */
	public function get_capabilities() {
		return array(
			'send'             => true,
			'receive'          => true,
			'templates'        => false,
			'tracking'         => false,
			'attachments'      => true,
			'html'             => true,
			'scheduled_send'   => false,
			'attachment_limit' => 25 * 1024 * 1024, // 25MB
			'recipient_limit'  => 500,
		);
	}

	/**
	 * Get adapter name.
	 *
	 * @return string Name.
	 */
	public function get_name() {
		return 'Gmail';
	}

	/**
	 * Get provider key.
	 *
	 * @return string Provider key.
	 */
	public function get_provider() {
		return 'gmail';
	}

	/**
	 * Setup Gmail webhook (watch) for push notifications.
	 *
	 * @param string $topic_name Cloud Pub/Sub topic name.
	 * @return array|WP_Error Watch response.
	 */
	public function setup_watch( $topic_name ) {
		if ( ! $this->access_token ) {
			return new WP_Error( 'no_token', __( 'Gmail authentication required', 'nonprofitsuite' ) );
		}

		$response = wp_remote_post( "{$this->api_base}/users/me/watch", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'topicName'  => $topic_name,
				'labelIds'   => array( 'INBOX' ),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
