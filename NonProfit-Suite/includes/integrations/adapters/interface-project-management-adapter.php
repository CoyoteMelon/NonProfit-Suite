<?php
/**
 * Project Management Adapter Interface
 *
 * Defines the contract for project management platform integrations.
 * All project management adapters must implement this interface.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

interface NS_Project_Management_Adapter {
	/**
	 * Create a new project in the external platform.
	 *
	 * @param array $project_data Project configuration data.
	 * @return array|WP_Error Project data including provider_project_id, or WP_Error on failure.
	 */
	public function create_project( $project_data );

	/**
	 * Update an existing project in the external platform.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @param array  $project_data Project configuration data.
	 * @return array|WP_Error Updated project data, or WP_Error on failure.
	 */
	public function update_project( $provider_project_id, $project_data );

	/**
	 * Delete a project from the external platform.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_project( $provider_project_id );

	/**
	 * Get project details from the external platform.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @return array|WP_Error Project details, or WP_Error on failure.
	 */
	public function get_project( $provider_project_id );

	/**
	 * Get all projects from the external platform.
	 *
	 * @param array $args Optional query arguments (status, limit, etc.).
	 * @return array|WP_Error Array of projects, or WP_Error on failure.
	 */
	public function get_projects( $args = array() );

	/**
	 * Create a new task in the external platform.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @param array  $task_data Task data.
	 * @return array|WP_Error Task data including provider_task_id, or WP_Error on failure.
	 */
	public function create_task( $provider_project_id, $task_data );

	/**
	 * Update an existing task in the external platform.
	 *
	 * @param string $provider_task_id External platform task ID.
	 * @param array  $task_data Task data.
	 * @return array|WP_Error Updated task data, or WP_Error on failure.
	 */
	public function update_task( $provider_task_id, $task_data );

	/**
	 * Delete a task from the external platform.
	 *
	 * @param string $provider_task_id External platform task ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_task( $provider_task_id );

	/**
	 * Get task details from the external platform.
	 *
	 * @param string $provider_task_id External platform task ID.
	 * @return array|WP_Error Task details, or WP_Error on failure.
	 */
	public function get_task( $provider_task_id );

	/**
	 * Get all tasks for a project from the external platform.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @param array  $args Optional query arguments (status, assignee, etc.).
	 * @return array|WP_Error Array of tasks, or WP_Error on failure.
	 */
	public function get_tasks( $provider_project_id, $args = array() );

	/**
	 * Add a comment to a task.
	 *
	 * @param string $provider_task_id External platform task ID.
	 * @param string $comment Comment text.
	 * @param int    $user_id WordPress user ID of commenter.
	 * @return array|WP_Error Comment data, or WP_Error on failure.
	 */
	public function add_task_comment( $provider_task_id, $comment, $user_id );

	/**
	 * Add a member to a project.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @param string $member_email Member email address.
	 * @param string $role Member role.
	 * @return array|WP_Error Member data, or WP_Error on failure.
	 */
	public function add_project_member( $provider_project_id, $member_email, $role );

	/**
	 * Remove a member from a project.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @param string $member_id External platform member ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function remove_project_member( $provider_project_id, $member_id );

	/**
	 * Get project members from the external platform.
	 *
	 * @param string $provider_project_id External platform project ID.
	 * @return array|WP_Error Array of members, or WP_Error on failure.
	 */
	public function get_project_members( $provider_project_id );

	/**
	 * Get changes since a specific timestamp for synchronization.
	 *
	 * @param string $since ISO 8601 timestamp.
	 * @return array|WP_Error Array of changed projects and tasks, or WP_Error on failure.
	 */
	public function get_changes_since( $since );

	/**
	 * Test the API connection.
	 *
	 * @return bool|WP_Error True if connection successful, WP_Error on failure.
	 */
	public function test_connection();
}
