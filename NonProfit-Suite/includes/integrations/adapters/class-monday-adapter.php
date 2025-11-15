<?php
/**
 * Monday.com Adapter
 *
 * Handles integration with Monday.com API (GraphQL).
 * Uses API token for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_Monday_Adapter implements NS_Project_Management_Adapter {
	private $api_token;
	private $api_base = 'https://api.monday.com/v2';

	/**
	 * Constructor.
	 *
	 * @param string $api_token Monday.com API token.
	 */
	public function __construct( $api_token ) {
		$this->api_token = $api_token;
	}

	/**
	 * Create a new project (board) in Monday.com.
	 */
	public function create_project( $project_data ) {
		$query = 'mutation ($name: String!, $kind: BoardKind!, $description: String) {
			create_board (board_name: $name, board_kind: $kind, description: $description) {
				id
				name
			}
		}';

		$variables = array(
			'name'        => $project_data['project_name'],
			'kind'        => 'public',
			'description' => $project_data['description'] ?? '',
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'provider_project_id' => $result['data']['create_board']['id'],
			'name'                => $result['data']['create_board']['name'],
		);
	}

	/**
	 * Update an existing project (board).
	 */
	public function update_project( $provider_project_id, $project_data ) {
		$query = 'mutation ($boardId: Int!, $attributes: BoardAttributes!) {
			update_board (board_id: $boardId, board_attribute: $attributes) {
				id
			}
		}';

		$attributes = array();

		if ( isset( $project_data['project_name'] ) ) {
			$attributes['name'] = $project_data['project_name'];
		}

		if ( isset( $project_data['description'] ) ) {
			$attributes['description'] = $project_data['description'];
		}

		$variables = array(
			'boardId'    => (int) $provider_project_id,
			'attributes' => $attributes,
		);

		$result = $this->graphql_request( $query, $variables );

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
		$query = 'mutation ($boardId: Int!) {
			delete_board (board_id: $boardId) {
				id
			}
		}';

		$variables = array(
			'boardId' => (int) $provider_project_id,
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get project (board) details.
	 */
	public function get_project( $provider_project_id ) {
		$query = 'query ($boardId: [Int]) {
			boards (ids: $boardId) {
				id
				name
				description
				state
			}
		}';

		$variables = array(
			'boardId' => array( (int) $provider_project_id ),
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['data']['boards'] ) ) {
			return new WP_Error( 'board_not_found', 'Board not found.' );
		}

		return $this->parse_board( $result['data']['boards'][0] );
	}

	/**
	 * Parse Monday.com board into our format.
	 */
	private function parse_board( $board ) {
		$status = 'active';
		if ( $board['state'] === 'archived' ) {
			$status = 'completed';
		}

		return array(
			'provider_project_id' => $board['id'],
			'project_name'        => $board['name'],
			'description'         => $board['description'] ?? '',
			'project_status'      => $status,
		);
	}

	/**
	 * Get all projects (boards).
	 */
	public function get_projects( $args = array() ) {
		$query = 'query {
			boards (limit: 50) {
				id
				name
				description
				state
			}
		}';

		$result = $this->graphql_request( $query );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$projects = array();
		foreach ( $result['data']['boards'] as $board ) {
			$projects[] = $this->parse_board( $board );
		}

		return $projects;
	}

	/**
	 * Create a new task (item) in Monday.com.
	 */
	public function create_task( $provider_project_id, $task_data ) {
		// First, get the first group on the board
		$groups_query = 'query ($boardId: [Int]) {
			boards (ids: $boardId) {
				groups {
					id
				}
			}
		}';

		$groups_result = $this->graphql_request(
			$groups_query,
			array( 'boardId' => array( (int) $provider_project_id ) )
		);

		if ( is_wp_error( $groups_result ) || empty( $groups_result['data']['boards'][0]['groups'] ) ) {
			return new WP_Error( 'no_groups', 'No groups found on this board.' );
		}

		$group_id = $groups_result['data']['boards'][0]['groups'][0]['id'];

		$query = 'mutation ($boardId: Int!, $groupId: String!, $itemName: String!) {
			create_item (board_id: $boardId, group_id: $groupId, item_name: $itemName) {
				id
				name
			}
		}';

		$variables = array(
			'boardId'  => (int) $provider_project_id,
			'groupId'  => $group_id,
			'itemName' => $task_data['task_name'],
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'provider_task_id' => $result['data']['create_item']['id'],
			'task_name'        => $result['data']['create_item']['name'],
		);
	}

	/**
	 * Update an existing task (item).
	 */
	public function update_task( $provider_task_id, $task_data ) {
		$query = 'mutation ($itemId: Int!, $columnValues: JSON!) {
			change_multiple_column_values (item_id: $itemId, board_id: 0, column_values: $columnValues) {
				id
			}
		}';

		$column_values = array();

		// Monday.com uses column IDs, which vary by board
		// This is simplified; in practice, would need to map field names to column IDs

		$variables = array(
			'itemId'       => (int) $provider_task_id,
			'columnValues' => wp_json_encode( $column_values ),
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'task_id' => $provider_task_id,
		);
	}

	/**
	 * Delete a task (item).
	 */
	public function delete_task( $provider_task_id ) {
		$query = 'mutation ($itemId: Int!) {
			delete_item (item_id: $itemId) {
				id
			}
		}';

		$variables = array(
			'itemId' => (int) $provider_task_id,
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get task (item) details.
	 */
	public function get_task( $provider_task_id ) {
		$query = 'query ($itemId: [Int]) {
			items (ids: $itemId) {
				id
				name
				state
				column_values {
					id
					text
				}
			}
		}';

		$variables = array(
			'itemId' => array( (int) $provider_task_id ),
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['data']['items'] ) ) {
			return new WP_Error( 'item_not_found', 'Item not found.' );
		}

		return $this->parse_item( $result['data']['items'][0] );
	}

	/**
	 * Parse Monday.com item into our format.
	 */
	private function parse_item( $item ) {
		$status = 'todo';
		if ( $item['state'] === 'archived' ) {
			$status = 'completed';
		}

		return array(
			'provider_task_id' => $item['id'],
			'task_name'        => $item['name'],
			'task_status'      => $status,
		);
	}

	/**
	 * Get all tasks (items) for a project (board).
	 */
	public function get_tasks( $provider_project_id, $args = array() ) {
		$query = 'query ($boardId: [Int]) {
			boards (ids: $boardId) {
				items {
					id
					name
					state
				}
			}
		}';

		$variables = array(
			'boardId' => array( (int) $provider_project_id ),
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tasks = array();
		if ( ! empty( $result['data']['boards'][0]['items'] ) ) {
			foreach ( $result['data']['boards'][0]['items'] as $item ) {
				$tasks[] = $this->parse_item( $item );
			}
		}

		return $tasks;
	}

	/**
	 * Add a comment to a task (item).
	 */
	public function add_task_comment( $provider_task_id, $comment, $user_id ) {
		$query = 'mutation ($itemId: Int!, $text: String!) {
			create_update (item_id: $itemId, body: $text) {
				id
				text_body
			}
		}';

		$variables = array(
			'itemId' => (int) $provider_task_id,
			'text'   => $comment,
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'comment_id' => $result['data']['create_update']['id'],
			'text'       => $result['data']['create_update']['text_body'],
		);
	}

	/**
	 * Add a member to a project (board).
	 */
	public function add_project_member( $provider_project_id, $member_email, $role ) {
		// Monday.com requires user ID, not email
		return new WP_Error( 'not_implemented', 'Adding members requires user ID lookup.' );
	}

	/**
	 * Remove a member from a project (board).
	 */
	public function remove_project_member( $provider_project_id, $member_id ) {
		return new WP_Error( 'not_supported', 'Removing members not directly supported via API.' );
	}

	/**
	 * Get project (board) members.
	 */
	public function get_project_members( $provider_project_id ) {
		$query = 'query ($boardId: [Int]) {
			boards (ids: $boardId) {
				subscribers {
					id
					name
					email
				}
			}
		}';

		$variables = array(
			'boardId' => array( (int) $provider_project_id ),
		);

		$result = $this->graphql_request( $query, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$members = array();
		if ( ! empty( $result['data']['boards'][0]['subscribers'] ) ) {
			foreach ( $result['data']['boards'][0]['subscribers'] as $subscriber ) {
				$members[] = array(
					'member_id' => $subscriber['id'],
					'name'      => $subscriber['name'],
					'email'     => $subscriber['email'] ?? '',
				);
			}
		}

		return $members;
	}

	/**
	 * Get changes since a timestamp.
	 */
	public function get_changes_since( $since ) {
		// Monday.com doesn't have a simple "changes since" endpoint
		return array(
			'projects' => array(),
			'tasks'    => array(),
		);
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$query = 'query {
			me {
				id
				name
			}
		}';

		$result = $this->graphql_request( $query );
		return ! is_wp_error( $result );
	}

	/**
	 * Make a GraphQL request to Monday.com.
	 */
	private function graphql_request( $query, $variables = null ) {
		$url = $this->api_base;

		$body = array(
			'query' => $query,
		);

		if ( $variables !== null ) {
			$body['variables'] = $variables;
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => $this->api_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'api_error',
				$data['error_message'] ?? 'Unknown API error',
				array( 'status' => $code )
			);
		}

		if ( isset( $data['errors'] ) ) {
			return new WP_Error(
				'graphql_error',
				$data['errors'][0]['message'] ?? 'GraphQL query error'
			);
		}

		return $data;
	}
}
