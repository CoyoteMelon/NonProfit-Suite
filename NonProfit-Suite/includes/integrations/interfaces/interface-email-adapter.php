<?php
/**
 * Email Adapter Interface
 *
 * Defines the contract for email providers (Gmail, Outlook, SendGrid, etc.)
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
 * Email Adapter Interface
 *
 * All email adapters must implement this interface.
 */
interface NonprofitSuite_Email_Adapter_Interface {

	/**
	 * Send an email
	 *
	 * @param array $args Email arguments
	 *                    - to: Recipient email(s) - string or array (required)
	 *                    - subject: Email subject (required)
	 *                    - message: Email body (required)
	 *                    - from: From email (optional)
	 *                    - from_name: From name (optional)
	 *                    - reply_to: Reply-to email (optional)
	 *                    - cc: CC recipients - string or array (optional)
	 *                    - bcc: BCC recipients - string or array (optional)
	 *                    - attachments: Array of file paths (optional)
	 *                    - html: Whether message is HTML (optional, default true)
	 *                    - template_id: Email template ID (optional)
	 *                    - tags: Email tags for tracking (optional)
	 * @return array|WP_Error Result with keys: message_id, status
	 */
	public function send( $args );

	/**
	 * Send bulk emails
	 *
	 * @param array $emails Array of email argument arrays (same format as send())
	 * @return array|WP_Error Result with keys: sent_count, failed_count, errors
	 */
	public function send_bulk( $emails );

	/**
	 * Get email delivery status
	 *
	 * @param string $message_id Message identifier from send()
	 * @return array|WP_Error Status array or WP_Error on failure
	 *                        - status: delivered, bounced, deferred, etc.
	 *                        - timestamp: Delivery timestamp
	 *                        - opens: Number of opens (if tracking enabled)
	 *                        - clicks: Number of clicks (if tracking enabled)
	 */
	public function get_status( $message_id );

	/**
	 * Create email template
	 *
	 * @param array $template_data Template data
	 *                             - name: Template name (required)
	 *                             - subject: Subject line with variables (optional)
	 *                             - html: HTML content (optional)
	 *                             - text: Plain text content (optional)
	 * @return array|WP_Error Template data with keys: template_id
	 */
	public function create_template( $template_data );

	/**
	 * Update email template
	 *
	 * @param string $template_id   Template identifier
	 * @param array  $template_data Updated template data
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function update_template( $template_id, $template_data );

	/**
	 * Delete email template
	 *
	 * @param string $template_id Template identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_template( $template_id );

	/**
	 * List email templates
	 *
	 * @param array $args List arguments
	 *                    - limit: Maximum number of templates (optional)
	 *                    - offset: Pagination offset (optional)
	 * @return array|WP_Error Array of templates or WP_Error on failure
	 */
	public function list_templates( $args = array() );

	/**
	 * Get email statistics
	 *
	 * @param array $args Statistics arguments
	 *                    - start_date: Start date (optional)
	 *                    - end_date: End date (optional)
	 *                    - tag: Filter by tag (optional)
	 * @return array|WP_Error Statistics array or WP_Error on failure
	 *                        - sent: Number sent
	 *                        - delivered: Number delivered
	 *                        - bounced: Number bounced
	 *                        - opened: Number opened
	 *                        - clicked: Number clicked
	 */
	public function get_statistics( $args = array() );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Gmail", "SendGrid")
	 */
	public function get_provider_name();
}
