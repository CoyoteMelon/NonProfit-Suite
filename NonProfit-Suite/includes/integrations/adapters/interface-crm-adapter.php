<?php
/**
 * CRM Adapter Interface
 *
 * Defines the contract for all CRM integration adapters.
 * Allows NonprofitSuite to work with multiple CRM systems through a unified interface.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/integrations/adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NonprofitSuite_CRM_Adapter {

	/**
	 * Get the CRM provider name.
	 *
	 * @return string Provider name (salesforce, hubspot, bloomerang, etc).
	 */
	public function get_provider_name();

	/**
	 * Get the display name for this CRM.
	 *
	 * @return string Display name (e.g., "Salesforce Nonprofit Cloud").
	 */
	public function get_display_name();

	/**
	 * Check if this adapter uses OAuth authentication.
	 *
	 * @return bool True if OAuth is required, false for API key auth.
	 */
	public function uses_oauth();

	/**
	 * Get the OAuth authorization URL.
	 *
	 * @param array $args Optional arguments (redirect_uri, state, etc).
	 * @return string|null Authorization URL or null if not using OAuth.
	 */
	public function get_oauth_url( $args = array() );

	/**
	 * Exchange OAuth authorization code for access token.
	 *
	 * @param string $code Authorization code.
	 * @param array  $args Optional arguments (redirect_uri, etc).
	 * @return array|WP_Error Token data or error.
	 */
	public function exchange_oauth_code( $code, $args = array() );

	/**
	 * Refresh an expired OAuth token.
	 *
	 * @param string $refresh_token The refresh token.
	 * @return array|WP_Error New token data or error.
	 */
	public function refresh_oauth_token( $refresh_token );

	/**
	 * Test the API connection.
	 *
	 * @param array $credentials API credentials (api_key, api_secret, oauth_token, etc).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection( $credentials );

	/**
	 * Get supported entity types.
	 *
	 * @return array List of supported entity types (contact, donation, membership, activity, etc).
	 */
	public function get_supported_entities();

	/**
	 * Get field schema for an entity type.
	 *
	 * Returns the CRM's field definitions for mapping.
	 *
	 * @param string $entity_type Entity type (contact, donation, etc).
	 * @return array|WP_Error Array of field definitions or error.
	 */
	public function get_entity_fields( $entity_type );

	/**
	 * Push a contact to the CRM.
	 *
	 * @param array $contact_data Contact data from NonprofitSuite.
	 * @param array $field_mappings Field mappings configuration.
	 * @return array|WP_Error Result with crm_id or error.
	 */
	public function push_contact( $contact_data, $field_mappings );

	/**
	 * Pull a contact from the CRM.
	 *
	 * @param string $crm_id CRM contact ID.
	 * @param array  $field_mappings Field mappings configuration.
	 * @return array|WP_Error Contact data or error.
	 */
	public function pull_contact( $crm_id, $field_mappings );

	/**
	 * Update a contact in the CRM.
	 *
	 * @param string $crm_id CRM contact ID.
	 * @param array  $contact_data Contact data from NonprofitSuite.
	 * @param array  $field_mappings Field mappings configuration.
	 * @return bool|WP_Error True on success or error.
	 */
	public function update_contact( $crm_id, $contact_data, $field_mappings );

	/**
	 * Delete a contact from the CRM.
	 *
	 * @param string $crm_id CRM contact ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public function delete_contact( $crm_id );

	/**
	 * Push a donation to the CRM.
	 *
	 * @param array $donation_data Donation data from NonprofitSuite.
	 * @param array $field_mappings Field mappings configuration.
	 * @return array|WP_Error Result with crm_id or error.
	 */
	public function push_donation( $donation_data, $field_mappings );

	/**
	 * Push a membership to the CRM.
	 *
	 * @param array $membership_data Membership data from NonprofitSuite.
	 * @param array $field_mappings Field mappings configuration.
	 * @return array|WP_Error Result with crm_id or error.
	 */
	public function push_membership( $membership_data, $field_mappings );

	/**
	 * Push an activity/interaction to the CRM.
	 *
	 * @param array $activity_data Activity data from NonprofitSuite.
	 * @param array $field_mappings Field mappings configuration.
	 * @return array|WP_Error Result with crm_id or error.
	 */
	public function push_activity( $activity_data, $field_mappings );

	/**
	 * Search for entities in the CRM.
	 *
	 * @param string $entity_type Entity type to search.
	 * @param array  $criteria Search criteria.
	 * @param array  $args Optional arguments (limit, offset, etc).
	 * @return array|WP_Error Search results or error.
	 */
	public function search( $entity_type, $criteria, $args = array() );

	/**
	 * Get changes from CRM since last sync.
	 *
	 * Used for pull synchronization.
	 *
	 * @param string   $entity_type Entity type.
	 * @param datetime $since Get changes since this date/time.
	 * @param array    $args Optional arguments.
	 * @return array|WP_Error Changed entities or error.
	 */
	public function get_changes_since( $entity_type, $since, $args = array() );

	/**
	 * Batch operations support.
	 *
	 * Push multiple entities in one API call for efficiency.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $entities Array of entities to push.
	 * @param array  $field_mappings Field mappings configuration.
	 * @return array|WP_Error Results array or error.
	 */
	public function batch_push( $entity_type, $entities, $field_mappings );

	/**
	 * Get the rate limit status.
	 *
	 * @return array|null Rate limit info (limit, remaining, reset_time) or null if not applicable.
	 */
	public function get_rate_limit_status();
}
