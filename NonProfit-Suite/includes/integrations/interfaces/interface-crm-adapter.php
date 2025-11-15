<?php
/**
 * CRM Adapter Interface
 *
 * Defines the contract for CRM providers (Salesforce, HubSpot, Bloomerang, etc.)
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
 * CRM Adapter Interface
 *
 * All CRM adapters must implement this interface.
 */
interface NonprofitSuite_CRM_Adapter_Interface {

	/**
	 * Sync a contact to CRM
	 *
	 * @param array $contact_data Contact data
	 *                            - email: Email address (required)
	 *                            - first_name: First name (optional)
	 *                            - last_name: Last name (optional)
	 *                            - phone: Phone number (optional)
	 *                            - address: Address array (optional)
	 *                            - organization: Organization name (optional)
	 *                            - tags: Array of tags (optional)
	 *                            - custom_fields: Custom field data (optional)
	 * @return array|WP_Error Contact data with keys: contact_id
	 */
	public function sync_contact( $contact_data );

	/**
	 * Get contact from CRM
	 *
	 * @param string $contact_id Contact identifier
	 * @return array|WP_Error Contact data or WP_Error on failure
	 */
	public function get_contact( $contact_id );

	/**
	 * Search contacts
	 *
	 * @param array $args Search arguments
	 *                    - email: Search by email (optional)
	 *                    - name: Search by name (optional)
	 *                    - tag: Filter by tag (optional)
	 *                    - limit: Maximum number (optional)
	 * @return array|WP_Error Array of contacts or WP_Error on failure
	 */
	public function search_contacts( $args = array() );

	/**
	 * Add a note/interaction to contact
	 *
	 * @param string $contact_id Contact identifier
	 * @param array  $note_data  Note data
	 *                           - subject: Note subject (optional)
	 *                           - body: Note content (required)
	 *                           - type: Note type (optional)
	 *                           - timestamp: Interaction timestamp (optional)
	 * @return array|WP_Error Note data with keys: note_id
	 */
	public function add_note( $contact_id, $note_data );

	/**
	 * Create a campaign
	 *
	 * @param array $campaign_data Campaign data
	 *                             - name: Campaign name (required)
	 *                             - type: Campaign type (optional)
	 *                             - goal: Campaign goal amount (optional)
	 *                             - start_date: Start date (optional)
	 *                             - end_date: End date (optional)
	 * @return array|WP_Error Campaign data with keys: campaign_id
	 */
	public function create_campaign( $campaign_data );

	/**
	 * Add contact to campaign
	 *
	 * @param string $campaign_id Campaign identifier
	 * @param string $contact_id  Contact identifier
	 * @param array  $args        Additional arguments
	 *                            - status: Campaign member status (optional)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function add_to_campaign( $campaign_id, $contact_id, $args = array() );

	/**
	 * Track a donation in CRM
	 *
	 * @param array $donation_data Donation data
	 *                             - contact_id: Contact identifier (required)
	 *                             - amount: Donation amount (required)
	 *                             - date: Donation date (required)
	 *                             - campaign_id: Campaign identifier (optional)
	 *                             - type: Donation type (optional)
	 *                             - payment_method: Payment method (optional)
	 * @return array|WP_Error Donation data with keys: donation_id
	 */
	public function track_donation( $donation_data );

	/**
	 * Get contact engagement score
	 *
	 * @param string $contact_id Contact identifier
	 * @return array|WP_Error Engagement data or WP_Error on failure
	 *                        - score: Engagement score
	 *                        - last_interaction: Last interaction date
	 *                        - total_interactions: Total number of interactions
	 */
	public function get_engagement_score( $contact_id );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Salesforce", "HubSpot")
	 */
	public function get_provider_name();
}
