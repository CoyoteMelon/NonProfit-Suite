<?php
/**
 * Marketing Platform Adapter Interface
 *
 * Defines the contract for all marketing platform integrations.
 * Supports email marketing, SMS, and campaign management.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NonprofitSuite_Marketing_Adapter {

	/**
	 * Get the platform name.
	 *
	 * @return string Platform name (mailchimp, constant_contact, twilio, etc).
	 */
	public function get_platform_name();

	/**
	 * Get the display name.
	 *
	 * @return string Display name (e.g., "Mailchimp", "Constant Contact").
	 */
	public function get_display_name();

	/**
	 * Check if this adapter uses OAuth.
	 *
	 * @return bool True if OAuth is required, false for API key auth.
	 */
	public function uses_oauth();

	/**
	 * Get supported campaign types.
	 *
	 * @return array List of supported types (email, sms, social, etc).
	 */
	public function get_supported_types();

	/**
	 * Test the API connection.
	 *
	 * @param array $credentials API credentials.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection( $credentials );

	/**
	 * Create or sync an audience/list.
	 *
	 * @param array $segment_data Segment data from NonprofitSuite.
	 * @return array|WP_Error Result with platform_list_id or error.
	 */
	public function create_audience( $segment_data );

	/**
	 * Add contacts to an audience/list.
	 *
	 * @param string $platform_list_id Platform list ID.
	 * @param array  $contacts Array of contact data.
	 * @return array|WP_Error Result with success count or error.
	 */
	public function add_contacts_to_audience( $platform_list_id, $contacts );

	/**
	 * Remove a contact from an audience.
	 *
	 * @param string $platform_list_id Platform list ID.
	 * @param string $email Email address.
	 * @return bool|WP_Error True on success or error.
	 */
	public function remove_contact_from_audience( $platform_list_id, $email );

	/**
	 * Create a campaign.
	 *
	 * @param array $campaign_data Campaign data.
	 * @return array|WP_Error Result with platform_campaign_id or error.
	 */
	public function create_campaign( $campaign_data );

	/**
	 * Send a campaign.
	 *
	 * @param string $platform_campaign_id Platform campaign ID.
	 * @param array  $args Optional arguments (send_time, test_emails, etc).
	 * @return bool|WP_Error True on success or error.
	 */
	public function send_campaign( $platform_campaign_id, $args = array() );

	/**
	 * Get campaign statistics.
	 *
	 * @param string $platform_campaign_id Platform campaign ID.
	 * @return array|WP_Error Campaign stats or error.
	 */
	public function get_campaign_stats( $platform_campaign_id );

	/**
	 * Send a transactional message (single email/SMS).
	 *
	 * @param array $message_data Message data (to, from, subject, content).
	 * @return array|WP_Error Result with message_id or error.
	 */
	public function send_transactional( $message_data );

	/**
	 * Get unsubscribed contacts.
	 *
	 * @param string $platform_list_id Platform list ID.
	 * @param array  $args Optional arguments (since, limit, etc).
	 * @return array|WP_Error Array of unsubscribed emails or error.
	 */
	public function get_unsubscribed( $platform_list_id, $args = array() );

	/**
	 * Track campaign events (opens, clicks, bounces).
	 *
	 * @param string $platform_campaign_id Platform campaign ID.
	 * @param array  $args Optional arguments (event_type, since, limit).
	 * @return array|WP_Error Array of events or error.
	 */
	public function get_campaign_events( $platform_campaign_id, $args = array() );

	/**
	 * Get rate limit status.
	 *
	 * @return array|null Rate limit info or null if not applicable.
	 */
	public function get_rate_limit_status();
}
