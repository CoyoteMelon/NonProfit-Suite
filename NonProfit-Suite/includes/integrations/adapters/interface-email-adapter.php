<?php
/**
 * Email Adapter Interface
 *
 * Defines the contract for all email service integrations.
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
 * Interface NonprofitSuite_Email_Adapter
 *
 * Standard interface for all email provider adapters.
 */
interface NonprofitSuite_Email_Adapter {

	/**
	 * Send an email.
	 *
	 * @param array $email_data {
	 *     Email data.
	 *
	 *     @type string       $from_email    Sender email address.
	 *     @type string       $from_name     Sender name.
	 *     @type array|string $to            Recipient(s). Array of emails or single email string.
	 *     @type array        $cc            Optional. CC recipients.
	 *     @type array        $bcc           Optional. BCC recipients.
	 *     @type string       $reply_to      Optional. Reply-to address.
	 *     @type string       $subject       Email subject.
	 *     @type string       $body_html     HTML body content.
	 *     @type string       $body_text     Plain text body content.
	 *     @type array        $attachments   Optional. Array of attachment file paths.
	 *     @type array        $headers       Optional. Custom headers.
	 *     @type string       $template_id   Optional. Template identifier for provider.
	 *     @type array        $template_vars Optional. Variables for template.
	 *     @type array        $metadata      Optional. Custom metadata for tracking.
	 * }
	 *
	 * @return array|WP_Error {
	 *     Send result on success, WP_Error on failure.
	 *
	 *     @type string $message_id Unique message identifier.
	 *     @type string $status     Send status (sent, queued, failed).
	 *     @type array  $metadata   Additional provider metadata.
	 * }
	 */
	public function send( $email_data );

	/**
	 * Get email delivery status.
	 *
	 * @param string $message_id Message identifier from send().
	 * @return array|WP_Error {
	 *     Status information.
	 *
	 *     @type string $status      Current status (sent, delivered, bounced, failed).
	 *     @type string $delivered_at Delivery timestamp if delivered.
	 *     @type string $opened_at    Open timestamp if opened (if tracking enabled).
	 *     @type int    $click_count  Number of clicks (if tracking enabled).
	 *     @type array  $events       Event log.
	 * }
	 */
	public function get_status( $message_id );

	/**
	 * Fetch incoming emails.
	 *
	 * For providers that support inbox access (Gmail, Outlook).
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $mailbox      Mailbox/folder name. Default 'INBOX'.
	 *     @type int    $limit        Maximum emails to fetch. Default 50.
	 *     @type string $since_date   Fetch emails since this date.
	 *     @type bool   $unread_only  Fetch only unread emails. Default false.
	 *     @type string $from         Filter by sender email.
	 *     @type string $to           Filter by recipient email.
	 *     @type string $subject      Filter by subject (partial match).
	 * }
	 *
	 * @return array|WP_Error Array of email objects or WP_Error.
	 */
	public function fetch_emails( $args = array() );

	/**
	 * Validate adapter configuration.
	 *
	 * @return bool|WP_Error True if valid, WP_Error with details if invalid.
	 */
	public function validate_config();

	/**
	 * Get adapter capabilities.
	 *
	 * @return array {
	 *     Adapter capabilities.
	 *
	 *     @type bool $send             Can send emails.
	 *     @type bool $receive          Can receive/fetch emails.
	 *     @type bool $templates        Supports provider templates.
	 *     @type bool $tracking         Supports open/click tracking.
	 *     @type bool $attachments      Supports attachments.
	 *     @type bool $html             Supports HTML emails.
	 *     @type bool $scheduled_send   Supports scheduled/delayed sending.
	 *     @type int  $attachment_limit Max attachment size in bytes.
	 *     @type int  $recipient_limit  Max recipients per email.
	 * }
	 */
	public function get_capabilities();

	/**
	 * Get adapter name.
	 *
	 * @return string Adapter name (e.g., 'Gmail', 'SMTP', 'SendGrid').
	 */
	public function get_name();

	/**
	 * Get adapter provider key.
	 *
	 * @return string Provider key (e.g., 'gmail', 'smtp', 'sendgrid').
	 */
	public function get_provider();
}
