<?php
/**
 * Marketing Adapter Interface
 *
 * Defines the contract for marketing providers (Mailchimp, Constant Contact, Brevo, etc.)
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
 * Marketing Adapter Interface
 *
 * All marketing adapters must implement this interface.
 */
interface NonprofitSuite_Marketing_Adapter_Interface {

	/**
	 * Add subscriber to list/audience
	 *
	 * @param array $subscriber_data Subscriber data
	 *                               - email: Email address (required)
	 *                               - first_name: First name (optional)
	 *                               - last_name: Last name (optional)
	 *                               - list_id: List/audience ID (required)
	 *                               - tags: Array of tags (optional)
	 *                               - custom_fields: Custom field data (optional)
	 *                               - double_opt_in: Require double opt-in (optional)
	 * @return array|WP_Error Subscriber data with keys: subscriber_id
	 */
	public function add_subscriber( $subscriber_data );

	/**
	 * Remove subscriber from list
	 *
	 * @param string $list_id  List identifier
	 * @param string $email    Subscriber email
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function remove_subscriber( $list_id, $email );

	/**
	 * Update subscriber data
	 *
	 * @param string $list_id         List identifier
	 * @param string $email           Subscriber email
	 * @param array  $subscriber_data Updated subscriber data
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function update_subscriber( $list_id, $email, $subscriber_data );

	/**
	 * Get lists/audiences
	 *
	 * @param array $args Query arguments
	 *                    - limit: Maximum number (optional)
	 *                    - offset: Pagination offset (optional)
	 * @return array|WP_Error Array of lists or WP_Error on failure
	 */
	public function get_lists( $args = array() );

	/**
	 * Create a list/audience
	 *
	 * @param array $list_data List data
	 *                         - name: List name (required)
	 *                         - description: List description (optional)
	 * @return array|WP_Error List data with keys: list_id
	 */
	public function create_list( $list_data );

	/**
	 * Create email campaign
	 *
	 * @param array $campaign_data Campaign data
	 *                             - subject: Email subject (required)
	 *                             - from_name: From name (required)
	 *                             - from_email: From email (required)
	 *                             - reply_to: Reply-to email (optional)
	 *                             - list_id: Target list ID (required)
	 *                             - html_content: HTML email content (required)
	 *                             - text_content: Plain text content (optional)
	 *                             - segment: List segment filter (optional)
	 * @return array|WP_Error Campaign data with keys: campaign_id
	 */
	public function create_campaign( $campaign_data );

	/**
	 * Send email campaign
	 *
	 * @param string $campaign_id Campaign identifier
	 * @param array  $args        Send arguments
	 *                            - schedule_time: Schedule for future send (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function send_campaign( $campaign_id, $args = array() );

	/**
	 * Get campaign statistics
	 *
	 * @param string $campaign_id Campaign identifier
	 * @return array|WP_Error Statistics or WP_Error on failure
	 *                        - sent: Number sent
	 *                        - opens: Number of opens
	 *                        - clicks: Number of clicks
	 *                        - unsubscribes: Number of unsubscribes
	 */
	public function get_campaign_stats( $campaign_id );

	/**
	 * Add tags to subscriber
	 *
	 * @param string $list_id List identifier
	 * @param string $email   Subscriber email
	 * @param array  $tags    Array of tag names
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function add_tags( $list_id, $email, $tags );

	/**
	 * Remove tags from subscriber
	 *
	 * @param string $list_id List identifier
	 * @param string $email   Subscriber email
	 * @param array  $tags    Array of tag names
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function remove_tags( $list_id, $email, $tags );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Mailchimp", "Constant Contact")
	 */
	public function get_provider_name();
}
