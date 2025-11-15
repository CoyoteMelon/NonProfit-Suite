<?php
/**
 * Trello Adapter
 *
 * Handles integration with Trello API.
 * Uses API key and token for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_Trello_Adapter implements NS_Project_Management_Adapter {
	private $api_key;
	private $api_token;
	private $api_base = 'https://api.trello.com/1';

	/**
	 * Constructor.
	 *
	 * @param string $api_key Trello API key.
	 * @param string $api_token Trello API token.
	 */
	public function __construct( $api_key, $api_token ) {
		$this->api_key   = $api_key;
		$this->api_token = $api_token;
	}

	/**
	 * Create a new project (board) in Trello.
	 */
	public function create_project( $project_data ) {
		$params = array(
			'name' => $project_data['project_name'],
			'desc' => $project_data['description'] ?? '',
		);

		$result = $this->api_request( 'POST', '/boards', $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'provider_project_id' => $result['id'],
			'name'                => $result['name'],
			'url'                 => $result['url'],
		);
	}

	/**
	 * Update an existing project (board).
	 */
	public function update_project( $provider_project_id, $project_data ) {
		$params = array();

		if ( isset( $project_data['project_name'] ) ) {
			$params['name'] = $project_data['project_name'];
		}

		if ( isset( $project_data['description'] ) ) {
			$params['desc'] = $project_data['description'];
		}

		if ( isset( $project_data['project_status'] ) && $project_data['project_status'] === 'completed' ) {
			$params['closed'] = 'true';
		}

		$result = $this->api_request( 'PUT', "/boards/{$provider_project_id}", $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'    => true,
			'project_id' => $provider_project_id,
		);
	}

	/**
	 * Delete a project (board).
	 */
	public function delete_project( $provider_project_id ) {
		$result = $this->api_request( 'DELETE', "/boards/{$provider_project_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get project (board) details.
	 */
	public function get_project( $provider_project_id ) {
		$result = $this->api_request( 'GET', "/boards/{$provider_project_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_board( $result );
	}

	/**
	 * Parse Trello board into our format.
	 */
	private function parse_board( $board ) {
		$status = 'active';
		if ( $board['closed'] ) {
			$status = 'completed';
		}

		return array(
			'provider_project_id' => $board['id'],
			'project_name'        => $board['name'],
			'description'         => $board['desc'] ?? '',
			'project_status'      => $status,
			'url'                 => $board['url'],
		);
	}

	/**
	 * Get all projects (boards).
	 */
	public function get_projects( $args = array() ) {
		// Get boards for the authenticated user
		$result = $this->api_request( 'GET', '/members/me/boards' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$projects = array();
		foreach ( $result as $board ) {
			$projects[] = $this->parse_board( $board );
		}

		return $projects;
	}

	/**
	 * Create a new task (card) in Trello.
	 */
	public function create_task( $provider_project_id, $task_data ) {
		// First, get the first list on the board
		$lists = $this->api_request( 'GET', "/boards/{$provider_project_id}/lists" );

		if ( is_wp_error( $lists ) || empty( $lists ) ) {
			return new WP_Error( 'no_lists', 'No lists found on this board.' );
		}

		$list_id = $lists[0]['id'];

		$params = array(
			'idList' => $list_id,
			'name'   => $task_data['task_name'],
			'desc'   => $task_data['description'] ?? '',
		);

		if ( ! empty( $task_data['due_date'] ) ) {
			$params['due'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $task_data['due_date'] ) );
		}

		$result = $this->api_request( 'POST', '/cards', $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'provider_task_id' => $result['id'],
			'task_name'        => $result['name'],
			'url'              => $result['url'],
		);
	}

	/**
	 * Update an existing task (card).
	 */
	public function update_task( $provider_task_id, $task_data ) {
		$params = array();

		if ( isset( $task_data['task_name'] ) ) {
			$params['name'] = $task_data['task_name'];
		}

		if ( isset( $task_data['description'] ) ) {
			$params['desc'] = $task_data['description'];
		}

		if ( isset( $task_data['task_status'] ) && $task_data['task_status'] === 'completed' ) {
			$params['dueComplete'] = 'true';
		}

		if ( isset( $task_data['due_date'] ) ) {
			$params['due'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $task_data['due_date'] ) );
		}

		$result = $this->api_request( 'PUT', "/cards/{$provider_task_id}", $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'task_id' => $provider_task_id,
		);
	}

	/**
	 * Delete a task (card).
	 */
	public function delete_task( $provider_task_id ) {
		$result = $this->api_request( 'DELETE', "/cards/{$provider_task_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get task (card) details.
	 */
	public function get_task( $provider_task_id ) {
		$result = $this->api_request( 'GET', "/cards/{$provider_task_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_card( $result );
	}

	/**
	 * Parse Trello card into our format.
	 */
	private function parse_card( $card ) {
		$status = 'todo';
		if ( $card['dueComplete'] ) {
			$status = 'completed';
		} elseif ( ! empty( $card['due'] ) ) {
			$status = 'in_progress';
		}

		return array(
			'provider_task_id' => $card['id'],
			'task_name'        => $card['name'],
			'description'      => $card['desc'] ?? '',
			'task_status'      => $status,
			'due_date'         => $card['due'] ?? null,
			'url'              => $card['url'],
			'labels'           => array_column( $card['labels'] ?? array(), 'name' ),
		);
	}

	/**
	 * Get all tasks (cards) for a project (board).
	 */
	public function get_tasks( $provider_project_id, $args = array() ) {
		$result = $this->api_request( 'GET', "/boards/{$provider_project_id}/cards" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tasks = array();
		foreach ( $result as $card ) {
			$tasks[] = $this->parse_card( $card );
		}

		return $tasks;
	}

	/**
	 * Add a comment to a task (card).
	 */
	public function add_task_comment( $provider_task_id, $comment, $user_id ) {
		$params = array(
			'text' => $comment,
		);

		$result = $this->api_request( 'POST', "/cards/{$provider_task_id}/actions/comments", $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'comment_id' => $result['id'],
			'text'       => $result['data']['text'],
		);
	}

	/**
	 * Add a member to a project (board).
	 */
	public function add_project_member( $provider_project_id, $member_email, $role ) {
		// Trello doesn't support adding by email via API
		// Would need to get member ID first
		return new WP_Error( 'not_supported', 'Adding members by email not supported in Trello API.' );
	}

	/**
	 * Remove a member from a project (board).
	 */
	public function remove_project_member( $provider_project_id, $member_id ) {
		$result = $this->api_request( 'DELETE', "/boards/{$provider_project_id}/members/{$member_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get project (board) members.
	 */
	public function get_project_members( $provider_project_id ) {
		$result = $this->api_request( 'GET', "/boards/{$provider_project_id}/members" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$members = array();
		foreach ( $result as $member ) {
			$members[] = array(
				'member_id' => $member['id'],
				'username'  => $member['username'],
				'name'      => $member['fullName'] ?? $member['username'],
			);
		}

		return $members;
	}

	/**
	 * Get changes since a timestamp.
	 */
	public function get_changes_since( $since ) {
		// Trello doesn't have a simple "changes since" endpoint
		// Would need to use webhooks
		return array(
			'projects' => array(),
			'tasks'    => array(),
		);
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$result = $this->api_request( 'GET', '/members/me' );
		return ! is_wp_error( $result );
	}

	/**
	 * Make an API request to Trello.
	 */
	private function api_request( $method, $endpoint, $params = array() ) {
		// Add authentication to params
		$params['key']   = $this->api_key;
		$params['token'] = $this->api_token;

		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
		);

		if ( $method === 'GET' || $method === 'DELETE' ) {
			$url .= '?' . http_build_query( $params );
		} else {
			$args['body'] = $params;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// DELETE requests may return empty body
		if ( $code === 200 && empty( $body ) ) {
			return true;
		}

		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'api_error',
				is_string( $data ) ? $data : ( $data['message'] ?? 'Unknown API error' ),
				array( 'status' => $code )
			);
		}

		return $data;
	}
}
