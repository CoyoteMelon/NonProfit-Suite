<?php
/**
 * Project Management Adapter Interface
 *
 * Defines the contract for project management providers (Asana, Trello, Monday.com, etc.)
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
 * Project Adapter Interface
 *
 * All project management adapters must implement this interface.
 */
interface NonprofitSuite_Project_Adapter_Interface {

	/**
	 * Create a project/board
	 *
	 * @param array $project_data Project data
	 *                            - name: Project name (required)
	 *                            - description: Project description (optional)
	 *                            - team_id: Team/workspace ID (optional)
	 *                            - privacy: public, private (optional)
	 * @return array|WP_Error Project data with keys: project_id, url
	 */
	public function create_project( $project_data );

	/**
	 * Update a project
	 *
	 * @param string $project_id   Project identifier
	 * @param array  $project_data Updated project data
	 * @return array|WP_Error Updated project data or WP_Error on failure
	 */
	public function update_project( $project_id, $project_data );

	/**
	 * Delete a project
	 *
	 * @param string $project_id Project identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_project( $project_id );

	/**
	 * Get project details
	 *
	 * @param string $project_id Project identifier
	 * @return array|WP_Error Project data or WP_Error on failure
	 */
	public function get_project( $project_id );

	/**
	 * List projects
	 *
	 * @param array $args Query arguments
	 *                    - team_id: Filter by team (optional)
	 *                    - archived: Include archived (optional)
	 *                    - limit: Maximum number (optional)
	 * @return array|WP_Error Array of projects or WP_Error on failure
	 */
	public function list_projects( $args = array() );

	/**
	 * Create a task
	 *
	 * @param array $task_data Task data
	 *                         - name: Task name (required)
	 *                         - description: Task description (optional)
	 *                         - project_id: Project identifier (required)
	 *                         - assignee: User ID or email (optional)
	 *                         - due_date: Due date (optional)
	 *                         - priority: Priority level (optional)
	 *                         - tags: Array of tags (optional)
	 * @return array|WP_Error Task data with keys: task_id, url
	 */
	public function create_task( $task_data );

	/**
	 * Update a task
	 *
	 * @param string $task_id   Task identifier
	 * @param array  $task_data Updated task data
	 * @return array|WP_Error Updated task data or WP_Error on failure
	 */
	public function update_task( $task_id, $task_data );

	/**
	 * Delete a task
	 *
	 * @param string $task_id Task identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_task( $task_id );

	/**
	 * Get task details
	 *
	 * @param string $task_id Task identifier
	 * @return array|WP_Error Task data or WP_Error on failure
	 */
	public function get_task( $task_id );

	/**
	 * List tasks
	 *
	 * @param array $args Query arguments
	 *                    - project_id: Filter by project (optional)
	 *                    - assignee: Filter by assignee (optional)
	 *                    - completed: Filter by completion status (optional)
	 *                    - limit: Maximum number (optional)
	 * @return array|WP_Error Array of tasks or WP_Error on failure
	 */
	public function list_tasks( $args = array() );

	/**
	 * Add comment to task
	 *
	 * @param string $task_id Task identifier
	 * @param string $comment Comment text
	 * @return array|WP_Error Comment data with keys: comment_id
	 */
	public function add_comment( $task_id, $comment );

	/**
	 * Assign task to user
	 *
	 * @param string $task_id Task identifier
	 * @param string $user_id User identifier or email
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function assign_task( $task_id, $user_id );

	/**
	 * Complete/close a task
	 *
	 * @param string $task_id Task identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function complete_task( $task_id );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Asana", "Trello")
	 */
	public function get_provider_name();
}
