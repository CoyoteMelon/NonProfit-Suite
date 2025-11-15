<?php
/**
 * Asana Adapter
 *
 * Handles integration with Asana API.
 * Uses personal access token for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_Asana_Adapter implements NS_Project_Management_Adapter {
	private $access_token;
	private $workspace_id;
	private $api_base = 'https://app.asana.com/api/1.0';

	/**
	 * Constructor.
	 *
	 * @param string $access_token Asana personal access token.
	 * @param string $workspace_id Asana workspace GID.
	 */
	public function __construct( $access_token, $workspace_id = '' ) {
		$this->access_token = $access_token;
		$this->workspace_id = $workspace_id;
	}

	/**
	 * Create a new project in Asana.
	 */
	public function create_project( $project_data ) {
		$body = array(
			'data' => array(
				'name'      => $project_data['project_name'],
				'notes'     => $project_data['description'] ?? '',
				'workspace' => $this->workspace_id,
			),
		);

		if ( ! empty( $project_data['start_date'] ) ) {
			$body['data']['start_on'] = $project_data['start_date'];
		}

		if ( ! empty( $project_data['end_date'] ) ) {
			$body['data']['due_on'] = $project_data['end_date'];
		}

		if ( ! empty( $project_data['color'] ) ) {
			$body['data']['color'] = $this->map_color_to_asana( $project_data['color'] );
		}

		$result = $this->api_request( 'POST', '/projects', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'provider_project_id' => $result['data']['gid'],
			'name'                => $result['data']['name'],
		);
	}

	/**
	 * Map hex color to Asana color name.
	 */
	private function map_color_to_asana( $hex_color ) {
		$color_map = array(
			'#e8384f' => 'dark-red',
			'#fd612c' => 'dark-orange',
			'#fd9a00' => 'dark-orange',
			'#fcc30b' => 'dark-yellow',
			'#6a67ce' => 'dark-purple',
			'#4573d2' => 'dark-blue',
			'#00aaf5' => 'dark-blue',
			'#62d26f' => 'dark-green',
		);

		return $color_map[ $hex_color ] ?? 'dark-blue';
	}

	/**
	 * Update an existing project.
	 */
	public function update_project( $provider_project_id, $project_data ) {
		$body = array(
			'data' => array(),
		);

		if ( isset( $project_data['project_name'] ) ) {
			$body['data']['name'] = $project_data['project_name'];
		}

		if ( isset( $project_data['description'] ) ) {
			$body['data']['notes'] = $project_data['description'];
		}

		if ( isset( $project_data['project_status'] ) ) {
			// Map our status to Asana status
			$status_map = array(
				'active'     => 'on_track',
				'on_hold'    => 'at_risk',
				'completed'  => 'complete',
				'cancelled'  => 'off_track',
			);
			$body['data']['current_status'] = $status_map[ $project_data['project_status'] ] ?? 'on_track';
		}

		$result = $this->api_request( 'PUT', "/projects/{$provider_project_id}", $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'    => true,
			'project_id' => $provider_project_id,
		);
	}

	/**
	 * Delete a project.
	 */
	public function delete_project( $provider_project_id ) {
		$result = $this->api_request( 'DELETE', "/projects/{$provider_project_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get project details.
	 */
	public function get_project( $provider_project_id ) {
		$result = $this->api_request( 'GET', "/projects/{$provider_project_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_project( $result['data'] );
	}

	/**
	 * Parse Asana project into our format.
	 */
	private function parse_project( $project ) {
		return array(
			'provider_project_id' => $project['gid'],
			'project_name'        => $project['name'],
			'description'         => $project['notes'] ?? '',
			'start_date'          => $project['start_on'] ?? null,
			'end_date'            => $project['due_on'] ?? null,
			'color'               => $project['color'] ?? null,
		);
	}

	/**
	 * Get all projects.
	 */
	public function get_projects( $args = array() ) {
		$endpoint = "/workspaces/{$this->workspace_id}/projects";
		$result   = $this->api_request( 'GET', $endpoint );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$projects = array();
		foreach ( $result['data'] as $project ) {
			// Get full project details
			$full_project = $this->get_project( $project['gid'] );
			if ( ! is_wp_error( $full_project ) ) {
				$projects[] = $full_project;
			}
		}

		return $projects;
	}

	/**
	 * Create a new task.
	 */
	public function create_task( $provider_project_id, $task_data ) {
		$body = array(
			'data' => array(
				'name'     => $task_data['task_name'],
				'notes'    => $task_data['description'] ?? '',
				'projects' => array( $provider_project_id ),
			),
		);

		if ( ! empty( $task_data['due_date'] ) ) {
			$body['data']['due_on'] = gmdate( 'Y-m-d', strtotime( $task_data['due_date'] ) );
		}

		if ( ! empty( $task_data['assigned_to_email'] ) ) {
			// Would need to look up Asana user by email first
			// Simplified for now
		}

		$result = $this->api_request( 'POST', '/tasks', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'provider_task_id' => $result['data']['gid'],
			'task_name'        => $result['data']['name'],
		);
	}

	/**
	 * Update an existing task.
	 */
	public function update_task( $provider_task_id, $task_data ) {
		$body = array(
			'data' => array(),
		);

		if ( isset( $task_data['task_name'] ) ) {
			$body['data']['name'] = $task_data['task_name'];
		}

		if ( isset( $task_data['description'] ) ) {
			$body['data']['notes'] = $task_data['description'];
		}

		if ( isset( $task_data['task_status'] ) ) {
			$body['data']['completed'] = ( $task_data['task_status'] === 'completed' );
		}

		if ( isset( $task_data['due_date'] ) ) {
			$body['data']['due_on'] = gmdate( 'Y-m-d', strtotime( $task_data['due_date'] ) );
		}

		$result = $this->api_request( 'PUT', "/tasks/{$provider_task_id}", $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'task_id' => $provider_task_id,
		);
	}

	/**
	 * Delete a task.
	 */
	public function delete_task( $provider_task_id ) {
		$result = $this->api_request( 'DELETE', "/tasks/{$provider_task_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get task details.
	 */
	public function get_task( $provider_task_id ) {
		$result = $this->api_request( 'GET', "/tasks/{$provider_task_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_task( $result['data'] );
	}

	/**
	 * Parse Asana task into our format.
	 */
	private function parse_task( $task ) {
		$status = 'todo';
		if ( $task['completed'] ) {
			$status = 'completed';
		}

		return array(
			'provider_task_id' => $task['gid'],
			'task_name'        => $task['name'],
			'description'      => $task['notes'] ?? '',
			'task_status'      => $status,
			'due_date'         => $task['due_on'] ?? null,
			'completed_at'     => $task['completed_at'] ?? null,
			'assignee'         => $task['assignee']['email'] ?? null,
		);
	}

	/**
	 * Get all tasks for a project.
	 */
	public function get_tasks( $provider_project_id, $args = array() ) {
		$endpoint = "/projects/{$provider_project_id}/tasks";
		$result   = $this->api_request( 'GET', $endpoint );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tasks = array();
		foreach ( $result['data'] as $task ) {
			// Get full task details
			$full_task = $this->get_task( $task['gid'] );
			if ( ! is_wp_error( $full_task ) ) {
				$tasks[] = $full_task;
			}
		}

		return $tasks;
	}

	/**
	 * Add a comment to a task.
	 */
	public function add_task_comment( $provider_task_id, $comment, $user_id ) {
		$body = array(
			'data' => array(
				'text' => $comment,
			),
		);

		$result = $this->api_request( 'POST', "/tasks/{$provider_task_id}/stories", $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'comment_id' => $result['data']['gid'],
			'text'       => $result['data']['text'],
		);
	}

	/**
	 * Add a member to a project.
	 */
	public function add_project_member( $provider_project_id, $member_email, $role ) {
		// Asana requires user GID, not email
		// Would need to search for user first
		return new WP_Error( 'not_implemented', 'Adding members requires user GID lookup.' );
	}

	/**
	 * Remove a member from a project.
	 */
	public function remove_project_member( $provider_project_id, $member_id ) {
		$body = array(
			'data' => array(
				'members' => array( $member_id ),
			),
		);

		$result = $this->api_request( 'POST', "/projects/{$provider_project_id}/removeMembers", $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get project members.
	 */
	public function get_project_members( $provider_project_id ) {
		$result = $this->api_request( 'GET', "/projects/{$provider_project_id}/members" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$members = array();
		foreach ( $result['data'] as $member ) {
			$members[] = array(
				'member_id' => $member['gid'],
				'name'      => $member['name'],
				'email'     => $member['email'] ?? '',
			);
		}

		return $members;
	}

	/**
	 * Get changes since a timestamp.
	 */
	public function get_changes_since( $since ) {
		// Asana doesn't have a simple "changes since" endpoint
		// Would need to query events API or use webhooks
		return array(
			'projects' => array(),
			'tasks'    => array(),
		);
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$result = $this->api_request( 'GET', '/users/me' );
		return ! is_wp_error( $result );
	}

	/**
	 * Make an API request to Asana.
	 */
	private function api_request( $method, $endpoint, $body = null ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// DELETE requests return 200 with empty body
		if ( $code === 200 && empty( $body ) ) {
			return true;
		}

		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$error_message = 'Unknown API error';
			if ( isset( $data['errors'][0]['message'] ) ) {
				$error_message = $data['errors'][0]['message'];
			}

			return new WP_Error(
				'api_error',
				$error_message,
				array( 'status' => $code )
			);
		}

		return $data;
	}
}
