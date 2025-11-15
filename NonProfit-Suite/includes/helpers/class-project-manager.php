<?php
/**
 * Project Manager
 *
 * Central coordinator for project management operations across different providers.
 * Manages projects, tasks, and adapter coordination.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

class NS_Project_Manager {
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Hook into WordPress events
		add_action( 'save_post', array( $this, 'maybe_sync_task' ), 10, 2 );
	}

	/**
	 * Get project management adapter for a specific provider.
	 *
	 * @param string $provider Provider name (asana, trello, monday).
	 * @param int    $organization_id Organization ID.
	 * @return NS_Project_Management_Adapter|WP_Error Adapter instance or error.
	 */
	public function get_adapter( $provider, $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_project_management_settings';
		$settings = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND provider = %s AND is_active = 1",
				$organization_id,
				$provider
			),
			ARRAY_A
		);

		if ( ! $settings ) {
			return new WP_Error( 'no_settings', 'No active settings found for this provider.' );
		}

		require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/interface-project-management-adapter.php';

		switch ( $provider ) {
			case 'asana':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-asana-adapter.php';
				return new NS_Asana_Adapter(
					$settings['oauth_token'],
					$settings['workspace_id']
				);

			case 'trello':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-trello-adapter.php';
				return new NS_Trello_Adapter(
					$settings['api_key'],
					$settings['api_secret']
				);

			case 'monday':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-monday-adapter.php';
				return new NS_Monday_Adapter( $settings['api_key'] );

			default:
				return new WP_Error( 'unsupported_provider', 'Unsupported project management provider.' );
		}
	}

	/**
	 * Create a new project.
	 *
	 * @param array $project_data Project configuration.
	 * @return int|WP_Error Project ID or error.
	 */
	public function create_project( $project_data ) {
		global $wpdb;

		$provider = $project_data['provider'] ?? 'builtin';

		// For external providers, create the project via adapter
		if ( $provider !== 'builtin' ) {
			$adapter = $this->get_adapter( $provider, $project_data['organization_id'] );

			if ( is_wp_error( $adapter ) ) {
				return $adapter;
			}

			$result = $adapter->create_project( $project_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$project_data['provider_project_id'] = $result['provider_project_id'];
		}

		// Save project to database
		$table = $wpdb->prefix . 'ns_projects';
		$wpdb->insert(
			$table,
			array(
				'organization_id'     => $project_data['organization_id'],
				'project_name'        => $project_data['project_name'],
				'project_key'         => $project_data['project_key'] ?? null,
				'description'         => $project_data['description'] ?? '',
				'project_status'      => $project_data['project_status'] ?? 'planning',
				'priority'            => $project_data['priority'] ?? 'medium',
				'provider'            => $provider,
				'provider_project_id' => $project_data['provider_project_id'] ?? null,
				'start_date'          => $project_data['start_date'] ?? null,
				'end_date'            => $project_data['end_date'] ?? null,
				'budget'              => $project_data['budget'] ?? null,
				'owner_id'            => $project_data['owner_id'] ?? get_current_user_id(),
				'parent_project_id'   => $project_data['parent_project_id'] ?? null,
				'color'               => $project_data['color'] ?? null,
				'settings'            => ! empty( $project_data['settings'] ) ? wp_json_encode( $project_data['settings'] ) : null,
				'created_by'          => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d' )
		);

		$project_id = $wpdb->insert_id;

		do_action( 'ns_project_created', $project_id, $project_data );

		return $project_id;
	}

	/**
	 * Create a new task.
	 *
	 * @param array $task_data Task data.
	 * @return int|WP_Error Task ID or error.
	 */
	public function create_task( $task_data ) {
		global $wpdb;

		// Get project details
		$projects_table = $wpdb->prefix . 'ns_projects';
		$project        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$projects_table} WHERE id = %d", $task_data['project_id'] ),
			ARRAY_A
		);

		if ( ! $project ) {
			return new WP_Error( 'project_not_found', 'Project not found.' );
		}

		// For external providers, create the task via adapter
		if ( $project['provider'] !== 'builtin' ) {
			$adapter = $this->get_adapter( $project['provider'], $project['organization_id'] );

			if ( is_wp_error( $adapter ) ) {
				return $adapter;
			}

			$result = $adapter->create_task( $project['provider_project_id'], $task_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$task_data['provider_task_id'] = $result['provider_task_id'];
		}

		// Save task to database
		$table = $wpdb->prefix . 'ns_tasks';
		$wpdb->insert(
			$table,
			array(
				'organization_id'  => $project['organization_id'],
				'project_id'       => $task_data['project_id'],
				'task_name'        => $task_data['task_name'],
				'description'      => $task_data['description'] ?? '',
				'task_status'      => $task_data['task_status'] ?? 'todo',
				'priority'         => $task_data['priority'] ?? 'medium',
				'provider_task_id' => $task_data['provider_task_id'] ?? null,
				'assigned_to'      => $task_data['assigned_to'] ?? null,
				'parent_task_id'   => $task_data['parent_task_id'] ?? null,
				'start_date'       => $task_data['start_date'] ?? null,
				'due_date'         => $task_data['due_date'] ?? null,
				'estimated_hours'  => $task_data['estimated_hours'] ?? null,
				'task_type'        => $task_data['task_type'] ?? 'task',
				'labels'           => $task_data['labels'] ?? null,
				'custom_fields'    => ! empty( $task_data['custom_fields'] ) ? wp_json_encode( $task_data['custom_fields'] ) : null,
				'created_by'       => get_current_user_id(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		$task_id = $wpdb->insert_id;

		do_action( 'ns_task_created', $task_id, $task_data );

		return $task_id;
	}

	/**
	 * Update a task.
	 *
	 * @param int   $task_id Task ID.
	 * @param array $task_data Updated task data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_task( $task_id, $task_data ) {
		global $wpdb;

		$tasks_table = $wpdb->prefix . 'ns_tasks';
		$task        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tasks_table} WHERE id = %d", $task_id ),
			ARRAY_A
		);

		if ( ! $task ) {
			return new WP_Error( 'task_not_found', 'Task not found.' );
		}

		// Get project for provider info
		$projects_table = $wpdb->prefix . 'ns_projects';
		$project        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$projects_table} WHERE id = %d", $task['project_id'] ),
			ARRAY_A
		);

		// Update in external provider if applicable
		if ( $project['provider'] !== 'builtin' && ! empty( $task['provider_task_id'] ) ) {
			$adapter = $this->get_adapter( $project['provider'], $project['organization_id'] );

			if ( ! is_wp_error( $adapter ) ) {
				$adapter->update_task( $task['provider_task_id'], $task_data );
			}
		}

		// Update in local database
		$update_data = array();
		$update_format = array();

		$allowed_fields = array(
			'task_name'       => '%s',
			'description'     => '%s',
			'task_status'     => '%s',
			'priority'        => '%s',
			'assigned_to'     => '%d',
			'due_date'        => '%s',
			'estimated_hours' => '%s',
			'actual_hours'    => '%s',
			'progress_percent' => '%d',
			'labels'          => '%s',
		);

		foreach ( $allowed_fields as $field => $format ) {
			if ( isset( $task_data[ $field ] ) ) {
				$update_data[ $field ]   = $task_data[ $field ];
				$update_format[] = $format;
			}
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update(
				$tasks_table,
				$update_data,
				array( 'id' => $task_id ),
				$update_format,
				array( '%d' )
			);
		}

		do_action( 'ns_task_updated', $task_id, $task_data );

		return true;
	}

	/**
	 * Sync projects from external provider.
	 *
	 * @param int $organization_id Organization ID.
	 * @param string $provider Provider name.
	 * @return int Number of projects synced.
	 */
	public function sync_projects( $organization_id, $provider ) {
		$adapter = $this->get_adapter( $provider, $organization_id );

		if ( is_wp_error( $adapter ) ) {
			return 0;
		}

		$projects = $adapter->get_projects();

		if ( is_wp_error( $projects ) ) {
			return 0;
		}

		$synced_count = 0;

		foreach ( $projects as $project ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ns_projects';

			// Check if project already exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE provider = %s AND provider_project_id = %s",
					$provider,
					$project['provider_project_id']
				)
			);

			if ( ! $exists ) {
				$project_data = array(
					'organization_id'     => $organization_id,
					'project_name'        => $project['project_name'],
					'description'         => $project['description'] ?? '',
					'provider'            => $provider,
					'provider_project_id' => $project['provider_project_id'],
				);

				$this->create_project( $project_data );
				$synced_count++;
			}
		}

		return $synced_count;
	}

	/**
	 * Maybe sync task when a post is saved (for WordPress-based workflows).
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 */
	public function maybe_sync_task( $post_id, $post ) {
		// This is a placeholder for future integration
		// Could sync custom post types as tasks
	}

	/**
	 * Get project progress based on tasks.
	 *
	 * @param int $project_id Project ID.
	 * @return float Progress percentage.
	 */
	public function calculate_project_progress( $project_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_tasks';
		$total_tasks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE project_id = %d",
				$project_id
			)
		);

		if ( ! $total_tasks ) {
			return 0;
		}

		$completed_tasks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE project_id = %d AND task_status = 'completed'",
				$project_id
			)
		);

		return round( ( $completed_tasks / $total_tasks ) * 100, 2 );
	}

	/**
	 * Add member to project.
	 *
	 * @param int    $project_id Project ID.
	 * @param int    $user_id WordPress user ID.
	 * @param string $role Member role.
	 * @return bool Success.
	 */
	public function add_project_member( $project_id, $user_id, $role = 'member' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_project_members';
		
		$result = $wpdb->insert(
			$table,
			array(
				'project_id' => $project_id,
				'user_id'    => $user_id,
				'role'       => $role,
			),
			array( '%d', '%d', '%s' )
		);

		return (bool) $result;
	}
}

// Initialize the project manager
NS_Project_Manager::get_instance();
