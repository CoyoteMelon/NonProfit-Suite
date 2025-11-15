<?php
/**
 * Form Adapter Interface
 *
 * Defines the contract for form/survey platform integrations.
 * All form adapters must implement this interface.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

interface NS_Form_Adapter {
	/**
	 * Create a new form in the external platform.
	 *
	 * @param array $form_data Form configuration data.
	 * @param array $fields Array of field configurations.
	 * @return array|WP_Error Form data including provider_form_id, or WP_Error on failure.
	 */
	public function create_form( $form_data, $fields );

	/**
	 * Update an existing form in the external platform.
	 *
	 * @param string $provider_form_id External platform form ID.
	 * @param array  $form_data Form configuration data.
	 * @param array  $fields Array of field configurations.
	 * @return array|WP_Error Updated form data, or WP_Error on failure.
	 */
	public function update_form( $provider_form_id, $form_data, $fields );

	/**
	 * Delete a form from the external platform.
	 *
	 * @param string $provider_form_id External platform form ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_form( $provider_form_id );

	/**
	 * Get form details from the external platform.
	 *
	 * @param string $provider_form_id External platform form ID.
	 * @return array|WP_Error Form details, or WP_Error on failure.
	 */
	public function get_form( $provider_form_id );

	/**
	 * Get submissions for a form from the external platform.
	 *
	 * @param string $provider_form_id External platform form ID.
	 * @param array  $args Optional query arguments (limit, offset, filters).
	 * @return array|WP_Error Array of submissions, or WP_Error on failure.
	 */
	public function get_submissions( $provider_form_id, $args = array() );

	/**
	 * Get a single submission from the external platform.
	 *
	 * @param string $provider_form_id External platform form ID.
	 * @param string $provider_submission_id External platform submission ID.
	 * @return array|WP_Error Submission data, or WP_Error on failure.
	 */
	public function get_submission( $provider_form_id, $provider_submission_id );

	/**
	 * Get form analytics/statistics from the external platform.
	 *
	 * @param string $provider_form_id External platform form ID.
	 * @return array|WP_Error Analytics data (views, submissions, completion rate, etc).
	 */
	public function get_form_analytics( $provider_form_id );

	/**
	 * Get embed code for displaying the form.
	 *
	 * @param string $provider_form_id External platform form ID.
	 * @param array  $options Embed options (width, height, style, etc).
	 * @return string|WP_Error Embed HTML code, or WP_Error on failure.
	 */
	public function get_embed_code( $provider_form_id, $options = array() );

	/**
	 * Validate webhook signature for secure webhook processing.
	 *
	 * @param string $payload Webhook payload.
	 * @param string $signature Webhook signature from headers.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_webhook_signature( $payload, $signature );

	/**
	 * Process webhook data from the external platform.
	 *
	 * @param array $webhook_data Parsed webhook data.
	 * @return array|WP_Error Processed submission data, or WP_Error on failure.
	 */
	public function process_webhook( $webhook_data );

	/**
	 * Test the API connection.
	 *
	 * @return bool|WP_Error True if connection successful, WP_Error on failure.
	 */
	public function test_connection();
}
