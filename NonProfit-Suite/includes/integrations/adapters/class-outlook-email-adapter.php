<?php
/**
 * Outlook/Exchange Email Adapter
 *
 * Email adapter for Microsoft Graph API (Outlook/Exchange) with send/receive.
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
 * NonprofitSuite_Outlook_Email_Adapter Class
 *
 * Microsoft Graph API email adapter implementation.
 */
class NonprofitSuite_Outlook_Email_Adapter implements NonprofitSuite_Email_Adapter {

	/**
	 * Outlook/Graph API configuration.
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
	 * Microsoft Graph API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://graph.microsoft.com/v1.0';

	/**
	 * Constructor.
	 *
	 * @param array $config Outlook configuration.
	 */
	public function __construct( $config = array() ) {
		$this->config = wp_parse_args( $config, array(
			'client_id'     => get_option( 'ns_outlook_client_id', '' ),
			'client_secret' => get_option( 'ns_outlook_client_secret', '' ),
			'tenant_id'     => get_option( 'ns_outlook_tenant_id', 'common' ),
			'refresh_token' => get_option( 'ns_outlook_refresh_token', '' ),
			'user_email'    => get_option( 'ns_outlook_user_email', '' ),
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
		$cached_token = get_transient( 'ns_outlook_access_token' );
		if ( $cached_token ) {
			return $cached_token;
		}

		// Refresh token
		if ( empty( $this->config['refresh_token'] ) ) {
			return null;
		}

		$token_url = "https://login.microsoftonline.com/{$this->config['tenant_id']}/oauth2/v2.0/token";

		$response = wp_remote_post( $token_url, array(
			'body' => array(
				'client_id'     => $this->config['client_id'],
				'client_secret' => $this->config['client_secret'],
				'refresh_token' => $this->config['refresh_token'],
				'grant_type'    => 'refresh_token',
				'scope'         => 'https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/Mail.Read offline_access',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$expires_in = isset( $body['expires_in'] ) ? $body['expires_in'] - 60 : 3540;
			set_transient( 'ns_outlook_access_token', $body['access_token'], $expires_in );
			return $body['access_token'];
		}

		return null;
	}

	/**
	 * Send an email via Microsoft Graph API.
	 *
	 * @param array $email_data Email data.
	 * @return array|WP_Error Send result.
	 */
	public function send( $email_data ) {
		if ( ! $this->access_token ) {
			return new WP_Error( 'no_token', __( 'Outlook authentication required', 'nonprofitsuite' ) );
		}

		// Build message payload
		$message = $this->build_message_payload( $email_data );

		// Send via Graph API
		$response = wp_remote_post( "{$this->api_base}/me/sendMail", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $message ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 202 && $code !== 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new WP_Error( 'send_failed', isset( $body['error']['message'] ) ? $body['error']['message'] : 'Failed to send email' );
		}

		// Graph API sendMail doesn't return message ID, generate UUID
		$message_id = wp_generate_uuid4();

		// Log email
		NonprofitSuite_Email_Routing::log_email( array_merge( $email_data, array(
			'direction'  => 'outbound',
			'adapter'    => 'outlook',
			'message_id' => $message_id,
			'status'     => 'sent',
			'sent_at'    => current_time( 'mysql' ),
		) ) );

		return array(
			'message_id' => $message_id,
			'status'     => 'sent',
			'metadata'   => array(),
		);
	}

	/**
	 * Build message payload for Graph API.
	 *
	 * @param array $email_data Email data.
	 * @return array Message payload.
	 */
	private function build_message_payload( $email_data ) {
		$to_recipients = array();
		$to_addresses = is_array( $email_data['to'] ) ? $email_data['to'] : array( $email_data['to'] );

		foreach ( $to_addresses as $email ) {
			$to_recipients[] = array(
				'emailAddress' => array(
					'address' => $email,
				),
			);
		}

		$message = array(
			'message' => array(
				'subject' => $email_data['subject'],
				'body'    => array(
					'contentType' => isset( $email_data['body_html'] ) ? 'HTML' : 'Text',
					'content'     => isset( $email_data['body_html'] ) ? $email_data['body_html'] : $email_data['body_text'],
				),
				'toRecipients' => $to_recipients,
			),
			'saveToSentItems' => true,
		);

		// From (if specified)
		if ( isset( $email_data['from_email'] ) ) {
			$message['message']['from'] = array(
				'emailAddress' => array(
					'address' => $email_data['from_email'],
					'name'    => isset( $email_data['from_name'] ) ? $email_data['from_name'] : '',
				),
			);
		}

		// CC recipients
		if ( isset( $email_data['cc'] ) ) {
			$cc_recipients = array();
			foreach ( (array) $email_data['cc'] as $cc_email ) {
				$cc_recipients[] = array(
					'emailAddress' => array( 'address' => $cc_email ),
				);
			}
			$message['message']['ccRecipients'] = $cc_recipients;
		}

		// BCC recipients
		if ( isset( $email_data['bcc'] ) ) {
			$bcc_recipients = array();
			foreach ( (array) $email_data['bcc'] as $bcc_email ) {
				$bcc_recipients[] = array(
					'emailAddress' => array( 'address' => $bcc_email ),
				);
			}
			$message['message']['bccRecipients'] = $bcc_recipients;
		}

		// Reply-To
		if ( isset( $email_data['reply_to'] ) ) {
			$message['message']['replyTo'] = array(
				array(
					'emailAddress' => array( 'address' => $email_data['reply_to'] ),
				),
			);
		}

		return $message;
	}

	/**
	 * Fetch incoming emails from Outlook.
	 *
	 * @param array $args Query arguments.
	 * @return array|WP_Error Emails or error.
	 */
	public function fetch_emails( $args = array() ) {
		if ( ! $this->access_token ) {
			return new WP_Error( 'no_token', __( 'Outlook authentication required', 'nonprofitsuite' ) );
		}

		$defaults = array(
			'mailbox'     => 'inbox',
			'limit'       => 50,
			'unread_only' => false,
			'since_date'  => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query parameters
		$query_params = array(
			'$top'     => $args['limit'],
			'$orderby' => 'receivedDateTime DESC',
		);

		// Build filter
		$filters = array();

		if ( $args['unread_only'] ) {
			$filters[] = 'isRead eq false';
		}

		if ( $args['since_date'] ) {
			$filters[] = "receivedDateTime ge {$args['since_date']}";
		}

		if ( ! empty( $filters ) ) {
			$query_params['$filter'] = implode( ' and ', $filters );
		}

		$url = add_query_arg( $query_params, "{$this->api_base}/me/mailFolders/{$args['mailbox']}/messages" );

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['value'] ) ) {
			return array();
		}

		// Parse messages
		$emails = array();
		foreach ( $body['value'] as $message ) {
			$emails[] = $this->parse_message( $message );
		}

		return $emails;
	}

	/**
	 * Parse Outlook message to standard format.
	 *
	 * @param array $message Outlook message object.
	 * @return array Parsed message.
	 */
	private function parse_message( $message ) {
		return array(
			'message_id'   => $message['id'],
			'from_address' => $message['from']['emailAddress']['address'],
			'to'           => isset( $message['toRecipients'][0] ) ? $message['toRecipients'][0]['emailAddress']['address'] : '',
			'subject'      => $message['subject'],
			'body_text'    => $message['body']['contentType'] === 'text' ? $message['body']['content'] : '',
			'body_html'    => $message['body']['contentType'] === 'html' ? $message['body']['content'] : '',
			'date'         => $message['receivedDateTime'],
			'is_read'      => $message['isRead'],
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
		// Graph API doesn't provide detailed delivery tracking
		return array(
			'status'  => 'sent',
			'message' => 'Outlook does not provide detailed delivery tracking',
		);
	}

	/**
	 * Validate configuration.
	 *
	 * @return bool|WP_Error Validation result.
	 */
	public function validate_config() {
		if ( empty( $this->config['client_id'] ) || empty( $this->config['client_secret'] ) ) {
			return new WP_Error( 'missing_credentials', __( 'Outlook OAuth credentials are required', 'nonprofitsuite' ) );
		}

		if ( empty( $this->config['refresh_token'] ) ) {
			return new WP_Error( 'not_authorized', __( 'Outlook account not authorized', 'nonprofitsuite' ) );
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
			'attachment_limit' => 150 * 1024 * 1024, // 150MB
			'recipient_limit'  => 500,
		);
	}

	/**
	 * Get adapter name.
	 *
	 * @return string Name.
	 */
	public function get_name() {
		return 'Outlook';
	}

	/**
	 * Get provider key.
	 *
	 * @return string Provider key.
	 */
	public function get_provider() {
		return 'outlook';
	}

	/**
	 * Setup webhook subscription for push notifications.
	 *
	 * @param string $notification_url Webhook URL to receive notifications.
	 * @return array|WP_Error Subscription response.
	 */
	public function setup_webhook( $notification_url ) {
		if ( ! $this->access_token ) {
			return new WP_Error( 'no_token', __( 'Outlook authentication required', 'nonprofitsuite' ) );
		}

		$subscription = array(
			'changeType'        => 'created',
			'notificationUrl'   => $notification_url,
			'resource'          => '/me/mailFolders(\'inbox\')/messages',
			'expirationDateTime' => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 3 * 24 * 60 * 60 ) ), // 3 days
			'clientState'       => wp_generate_uuid4(),
		);

		$response = wp_remote_post( "{$this->api_base}/subscriptions", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $subscription ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
