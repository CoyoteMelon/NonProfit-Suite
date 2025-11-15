<?php
/**
 * Tasks Module
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Tasks {

	public static function get( $task_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_tasks';

		// Use caching for individual tasks
		$cache_key = NonprofitSuite_Cache::item_key( 'task', $task_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $task_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, title, description, assigned_to, status, priority,
				        due_date, completed_date, created_by, created_at
				 FROM {$table}
				 WHERE id = %d",
				$task_id
			) );
		}, 300 );
	}

	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_tasks';

		// Parse pagination arguments
		$defaults = array(
			'assigned_to' => 0,
			'status' => '',
			'priority' => '',
		);
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

			$where = "WHERE 1=1";
		if ( $args['assigned_to'] > 0 ) {
			$where .= $wpdb->prepare( " AND assigned_to = %d", $args['assigned_to'] );
		}
		if ( $args['status'] ) {
			// Handle comma-separated status values securely
			if ( strpos( $args['status'], ',' ) !== false ) {
				$statuses = array_map( 'sanitize_text_field', explode( ',', $args['status'] ) );
				$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
				$where .= $wpdb->prepare( " AND status IN ($placeholders)", $statuses );
			} else {
				$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
			}
		}
		if ( $args['priority'] ) {
			$where .= $wpdb->prepare( " AND priority = %s", $args['priority'] );
		}

		// Use caching for task lists
		$cache_key = NonprofitSuite_Cache::list_key( 'tasks', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			$query = "SELECT id, title, description, assigned_to, status, priority,
			                 due_date, completed_date, created_by, created_at
			          FROM {$table} {$where}
			          ORDER BY {$orderby}
			          " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $query );
		}, 300 );
	}

	public static function create( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage tasks' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_tasks';

		$task_data = array(
			'title' => sanitize_text_field( $data['title'] ),
			'description' => sanitize_textarea_field( $data['description'] ),
			'assigned_to' => isset( $data['assigned_to'] ) ? absint( $data['assigned_to'] ) : null,
			'created_by' => get_current_user_id(),
			'due_date' => isset( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : null,
			'priority' => isset( $data['priority'] ) ? sanitize_text_field( $data['priority'] ) : 'medium',
			'status' => 'not_started',
			'source_type' => isset( $data['source_type'] ) ? sanitize_text_field( $data['source_type'] ) : null,
			'source_id' => isset( $data['source_id'] ) ? absint( $data['source_id'] ) : null,
		);

		$wpdb->insert( $table, $task_data );
		return $wpdb->insert_id;
	}

	public function update_status( $task_id, $status ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_tasks';

		$update_data = array( 'status' => sanitize_text_field( $status ) );

		if ( $status === 'completed' ) {
			$update_data['completed_at'] = current_time( 'mysql' );
		}

		return $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $task_id ),
			array( '%s', '%s' ),
			array( '%d' )
		) !== false;
	}

	public function add_comment( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_task_comments';

		$wpdb->insert(
			$table,
			array(
				'task_id' => absint( $data['task_id'] ),
				'user_id' => get_current_user_id(),
				'comment' => sanitize_textarea_field( $data['comment'] ),
			),
			array( '%d', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	public static function get_my_tasks( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		return self::get_all( array(
			'assigned_to' => $user_id,
			'status' => 'not_started,in_progress',
		) );
	}
}
