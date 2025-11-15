<?php
/**
 * SMTP Email Adapter
 *
 * Default email adapter using WordPress wp_mail() with SMTP configuration.
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
 * NonprofitSuite_SMTP_Email_Adapter Class
 *
 * SMTP email adapter implementation.
 */
class NonprofitSuite_SMTP_Email_Adapter implements NonprofitSuite_Email_Adapter {

	/**
	 * SMTP configuration.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array $config SMTP configuration.
	 */
	public function __construct( $config = array() ) {
		$this->config = wp_parse_args( $config, array(
			'host'       => get_option( 'ns_smtp_host', '' ),
			'port'       => get_option( 'ns_smtp_port', 587 ),
			'encryption' => get_option( 'ns_smtp_encryption', 'tls' ),
			'username'   => get_option( 'ns_smtp_username', '' ),
			'password'   => get_option( 'ns_smtp_password', '' ),
			'from_email' => get_option( 'ns_smtp_from_email', get_option( 'admin_email' ) ),
			'from_name'  => get_option( 'ns_smtp_from_name', get_bloginfo( 'name' ) ),
		) );

		// Configure PHPMailer
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
	}

	/**
	 * Configure PHPMailer with SMTP settings.
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( $phpmailer ) {
		if ( empty( $this->config['host'] ) ) {
			return; // Use default mail()
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $this->config['host'];
		$phpmailer->Port       = $this->config['port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $this->config['username'];
		$phpmailer->Password   = $this->config['password'];
		$phpmailer->SMTPSecure = $this->config['encryption'];
		$phpmailer->From       = $this->config['from_email'];
		$phpmailer->FromName   = $this->config['from_name'];
	}

	/**
	 * Send an email.
	 *
	 * @param array $email_data Email data.
	 * @return array|WP_Error Send result.
	 */
	public function send( $email_data ) {
		$to = $email_data['to'];
		$subject = $email_data['subject'];
		$message = isset( $email_data['body_html'] ) ? $email_data['body_html'] : $email_data['body_text'];

		// Build headers
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( isset( $email_data['from_email'] ) ) {
			$from_name = isset( $email_data['from_name'] ) ? $email_data['from_name'] : '';
			$headers[] = "From: {$from_name} <{$email_data['from_email']}>";
		}

		if ( isset( $email_data['reply_to'] ) ) {
			$headers[] = "Reply-To: {$email_data['reply_to']}";
		}

		if ( isset( $email_data['cc'] ) ) {
			foreach ( (array) $email_data['cc'] as $cc ) {
				$headers[] = "Cc: {$cc}";
			}
		}

		if ( isset( $email_data['bcc'] ) ) {
			foreach ( (array) $email_data['bcc'] as $bcc ) {
				$headers[] = "Bcc: {$bcc}";
			}
		}

		// Attachments
		$attachments = isset( $email_data['attachments'] ) ? $email_data['attachments'] : array();

		// Send
		$result = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! $result ) {
			return new WP_Error( 'email_failed', __( 'Failed to send email', 'nonprofitsuite' ) );
		}

		// Log the email
		NonprofitSuite_Email_Routing::log_email( array_merge( $email_data, array(
			'adapter'  => 'smtp',
			'status'   => 'sent',
			'sent_at'  => current_time( 'mysql' ),
		) ) );

		return array(
			'message_id' => wp_generate_uuid4(),
			'status'     => 'sent',
			'metadata'   => array(),
		);
	}

	/**
	 * Get email status.
	 *
	 * @param string $message_id Message ID.
	 * @return array|WP_Error Status information.
	 */
	public function get_status( $message_id ) {
		// SMTP doesn't provide delivery tracking
		return new WP_Error( 'not_supported', __( 'Status tracking not available for SMTP', 'nonprofitsuite' ) );
	}

	/**
	 * Fetch incoming emails.
	 *
	 * @param array $args Query arguments.
	 * @return array|WP_Error Emails or error.
	 */
	public function fetch_emails( $args = array() ) {
		// SMTP is send-only
		return new WP_Error( 'not_supported', __( 'SMTP does not support receiving emails', 'nonprofitsuite' ) );
	}

	/**
	 * Validate configuration.
	 *
	 * @return bool|WP_Error Validation result.
	 */
	public function validate_config() {
		if ( empty( $this->config['host'] ) ) {
			return new WP_Error( 'missing_host', __( 'SMTP host is required', 'nonprofitsuite' ) );
		}

		if ( empty( $this->config['username'] ) || empty( $this->config['password'] ) ) {
			return new WP_Error( 'missing_credentials', __( 'SMTP credentials are required', 'nonprofitsuite' ) );
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
			'receive'          => false,
			'templates'        => false,
			'tracking'         => false,
			'attachments'      => true,
			'html'             => true,
			'scheduled_send'   => false,
			'attachment_limit' => 25 * 1024 * 1024, // 25MB
			'recipient_limit'  => 100,
		);
	}

	/**
	 * Get adapter name.
	 *
	 * @return string Name.
	 */
	public function get_name() {
		return 'SMTP';
	}

	/**
	 * Get provider key.
	 *
	 * @return string Provider key.
	 */
	public function get_provider() {
		return 'smtp';
	}
}
