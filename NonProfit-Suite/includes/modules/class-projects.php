<?php
/**
 * Project Management Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */

defined( 'ABSPATH' ) or exit;

class NonprofitSuite_Projects {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Project Management module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	// PROJECT MANAGEMENT

	public static function create_project( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage projects' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_projects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'name' => sanitize_text_field( $data['name'] ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'status' => 'planning',
				'priority' => isset( $data['priority'] ) ? sanitize_text_field( $data['priority'] ) : 'medium',
				'start_date' => isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null,
				'target_completion' => isset( $data['target_completion'] ) ? sanitize_text_field( $data['target_completion'] ) : null,
				'budget' => isset( $data['budget'] ) ? floatval( $data['budget'] ) : null,
				'project_manager' => isset( $data['project_manager'] ) ? absint( $data['project_manager'] ) : null,
				'program_id' => isset( $data['program_id'] ) ? absint( $data['program_id'] ) : null,
				'grant_id' => isset( $data['grant_id'] ) ? absint( $data['grant_id'] ) : null,
				'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'projects' );
		return $wpdb->insert_id;
	}

	public static function get_project( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_projects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for individual projects
		$cache_key = NonprofitSuite_Cache::item_key( 'project', $id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, name, description, status, priority, start_date, target_completion,
				        actual_completion, budget, spent, project_manager, program_id, grant_id,
				        progress_percentage, notes, created_at
				 FROM {$table}
				 WHERE id = %d",
				$id
			) );
		}, 300 );
	}

	public static function get_projects( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_projects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$defaults = array(
			'status' => null,
			'manager' => null,
			'program' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";

		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		if ( $args['manager'] ) {
			$where .= $wpdb->prepare( " AND project_manager = %d", $args['manager'] );
		}

		if ( $args['program'] ) {
			$where .= $wpdb->prepare( " AND program_id = %d", $args['program'] );
		}

		// Use caching for project lists
		$cache_key = NonprofitSuite_Cache::list_key( 'projects', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, name, description, status, priority, start_date, target_completion,
			               actual_completion, budget, spent, project_manager, program_id, grant_id,
			               progress_percentage, notes, created_at
			        FROM {$table} {$where}
			        ORDER BY target_completion ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function update_project( $id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage projects' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_projects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$update_data = array();
		$update_format = array();

		$allowed_fields = array(
			'name' => '%s',
			'description' => '%s',
			'status' => '%s',
			'priority' => '%s',
			'start_date' => '%s',
			'target_completion' => '%s',
			'actual_completion' => '%s',
			'budget' => '%f',
			'spent' => '%f',
			'project_manager' => '%d',
			'notes' => '%s',
		);

		foreach ( $allowed_fields as $field => $format ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $format === '%s' ) {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
				} elseif ( $format === '%f' ) {
					$update_data[ $field ] = floatval( $data[ $field ] );
				} elseif ( $format === '%d' ) {
					$update_data[ $field ] = absint( $data[ $field ] );
				}
				$update_format[] = $format;
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'projects' );
		}

		return $result;
	}

	public static function calculate_progress( $id ) {
		global $wpdb;
		$milestones_table = $wpdb->prefix . 'ns_project_milestones';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return 0;

		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$milestones_table} WHERE project_id = %d",
			$id
		) );

		if ( $total == 0 ) {
			return 0;
		}

		$completed = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$milestones_table} WHERE project_id = %d AND status = 'completed'",
			$id
		) );

		$percentage = round( ( $completed / $total ) * 100 );

		// Update project progress
		$projects_table = $wpdb->prefix . 'ns_projects';
		$wpdb->update(
			$projects_table,
			array( 'progress_percentage' => $percentage ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		return $percentage;
	}

	public static function get_projects_by_status( $status ) {
		return self::get_projects( array( 'status' => $status ) );
	}

	public static function get_overdue_projects() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_projects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for overdue projects
		$cache_key = NonprofitSuite_Cache::list_key( 'projects_overdue', array() );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table ) {
			return $wpdb->get_results(
				"SELECT id, name, description, status, priority, start_date, target_completion,
				        project_manager, program_id, grant_id, progress_percentage, created_at
				 FROM {$table}
				 WHERE status IN ('planning', 'in_progress')
				 AND target_completion < CURDATE()
				 ORDER BY target_completion ASC"
			);
		}, 300 );
	}

	// MILESTONE MANAGEMENT

	public static function add_milestone( $project_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage projects' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_project_milestones';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Get next order number
		$max_order = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(order_num) FROM {$table} WHERE project_id = %d",
			$project_id
		) );

		$next_order = $max_order ? $max_order + 1 : 1;

		$wpdb->insert(
			$table,
			array(
				'project_id' => absint( $project_id ),
				'title' => sanitize_text_field( $data['title'] ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'target_date' => sanitize_text_field( $data['target_date'] ),
				'status' => 'pending',
				'assigned_to' => isset( $data['assigned_to'] ) ? absint( $data['assigned_to'] ) : null,
				'order_num' => $next_order,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		$milestone_id = $wpdb->insert_id;

		// Recalculate project progress
		self::calculate_progress( $project_id );

		NonprofitSuite_Cache::invalidate_module( 'project_milestones' );
		return $milestone_id;
	}

	public static function get_milestones( $project_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_project_milestones';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for project milestones
		$cache_key = NonprofitSuite_Cache::list_key( 'project_milestones', array( 'project_id' => $project_id ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $project_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, project_id, title, description, target_date, completed_date,
				        status, assigned_to, order_num, created_at
				 FROM {$table}
				 WHERE project_id = %d
				 ORDER BY order_num ASC",
				$project_id
			) );
		}, 300 );
	}

	public static function complete_milestone( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_project_milestones';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$milestone = $wpdb->get_row( $wpdb->prepare(
			"SELECT project_id FROM {$table} WHERE id = %d",
			$id
		) );

		if ( ! $milestone ) {
			return new WP_Error( 'milestone_not_found', __( 'Milestone not found.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$table,
			array(
				'status' => 'completed',
				'completed_date' => current_time( 'mysql', false ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Recalculate project progress
		self::calculate_progress( $milestone->project_id );

		NonprofitSuite_Cache::invalidate_module( 'project_milestones' );
		return $result !== false;
	}

	public static function get_project_statuses() {
		return array(
			'planning' => __( 'Planning', 'nonprofitsuite' ),
			'in_progress' => __( 'In Progress', 'nonprofitsuite' ),
			'on_hold' => __( 'On Hold', 'nonprofitsuite' ),
			'completed' => __( 'Completed', 'nonprofitsuite' ),
			'cancelled' => __( 'Cancelled', 'nonprofitsuite' ),
		);
	}

	public static function get_priorities() {
		return array(
			'low' => __( 'Low', 'nonprofitsuite' ),
			'medium' => __( 'Medium', 'nonprofitsuite' ),
			'high' => __( 'High', 'nonprofitsuite' ),
			'critical' => __( 'Critical', 'nonprofitsuite' ),
		);
	}
}
