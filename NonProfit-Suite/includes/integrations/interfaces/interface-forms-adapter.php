<?php
/**
 * Forms Adapter Interface
 *
 * Defines the contract for form providers (Google Forms, Typeform, JotForm, etc.)
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
 * Forms Adapter Interface
 *
 * All form adapters must implement this interface.
 */
interface NonprofitSuite_Forms_Adapter_Interface {

	/**
	 * Create a form
	 *
	 * @param array $form_data Form data
	 *                         - title: Form title (required)
	 *                         - description: Form description (optional)
	 *                         - fields: Array of field definitions (required)
	 *                         - settings: Form settings (optional)
	 * @return array|WP_Error Form data with keys: form_id, url
	 */
	public function create_form( $form_data );

	/**
	 * Update a form
	 *
	 * @param string $form_id   Form identifier
	 * @param array  $form_data Updated form data
	 * @return array|WP_Error Updated form data or WP_Error on failure
	 */
	public function update_form( $form_id, $form_data );

	/**
	 * Delete a form
	 *
	 * @param string $form_id Form identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_form( $form_id );

	/**
	 * Get form details
	 *
	 * @param string $form_id Form identifier
	 * @return array|WP_Error Form data or WP_Error on failure
	 */
	public function get_form( $form_id );

	/**
	 * List forms
	 *
	 * @param array $args Query arguments
	 *                    - limit: Maximum number (optional)
	 *                    - offset: Pagination offset (optional)
	 * @return array|WP_Error Array of forms or WP_Error on failure
	 */
	public function list_forms( $args = array() );

	/**
	 * Get form responses/submissions
	 *
	 * @param string $form_id Form identifier
	 * @param array  $args    Query arguments
	 *                        - limit: Maximum number (optional)
	 *                        - since: Get responses since date (optional)
	 *                        - until: Get responses until date (optional)
	 * @return array|WP_Error Array of responses or WP_Error on failure
	 */
	public function get_responses( $form_id, $args = array() );

	/**
	 * Get a single response
	 *
	 * @param string $form_id     Form identifier
	 * @param string $response_id Response identifier
	 * @return array|WP_Error Response data or WP_Error on failure
	 */
	public function get_response( $form_id, $response_id );

	/**
	 * Get form statistics
	 *
	 * @param string $form_id Form identifier
	 * @return array|WP_Error Statistics or WP_Error on failure
	 *                        - total_responses: Total number of responses
	 *                        - completion_rate: Completion rate percentage
	 *                        - average_time: Average completion time
	 */
	public function get_form_stats( $form_id );

	/**
	 * Get embed code for form
	 *
	 * @param string $form_id Form identifier
	 * @param array  $args    Embed arguments
	 *                        - width: Width (optional)
	 *                        - height: Height (optional)
	 * @return string|WP_Error Embed code or WP_Error on failure
	 */
	public function get_embed_code( $form_id, $args = array() );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Google Forms", "Typeform")
	 */
	public function get_provider_name();
}
