<?php
/**
 * Tasks List View
 *
 * Displays tasks across projects or for a specific project.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1; // TODO: Get from current user context
$project_id      = isset( $_GET['project_id'] ) ? intval( $_GET['project_id'] ) : 0;
$table           = $wpdb->prefix . 'ns_tasks';

// Build query
if ( $project_id ) {
	$tasks = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.*, u.display_name as assignee_name FROM {$table} t
			LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
			WHERE t.project_id = %d
			ORDER BY t.due_date ASC, t.created_at DESC",
			$project_id
		),
		ARRAY_A
	);

	// Get project name
	$projects_table = $wpdb->prefix . 'ns_projects';
	$project = $wpdb->get_row(
		$wpdb->prepare( "SELECT project_name FROM {$projects_table} WHERE id = %d", $project_id ),
		ARRAY_A
	);
} else {
	$tasks = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.*, p.project_name, u.display_name as assignee_name 
			FROM {$table} t
			LEFT JOIN {$wpdb->prefix}ns_projects p ON t.project_id = p.id
			LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
			WHERE t.organization_id = %d
			ORDER BY t.due_date ASC, t.created_at DESC
			LIMIT 100",
			$organization_id
		),
		ARRAY_A
	);
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php
		if ( $project_id && isset( $project ) ) {
			echo 'Tasks for: ' . esc_html( $project['project_name'] );
		} else {
			echo 'All Tasks';
		}
		?>
	</h1>
	<a href="#" class="page-title-action">Add New Task</a>
	
	<?php if ( $project_id ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-projects' ) ); ?>" class="page-title-action">← Back to Projects</a>
	<?php endif; ?>
	
	<hr class="wp-header-end">

	<?php if ( empty( $tasks ) ) : ?>
		<div class="notice notice-info">
			<p>No tasks found.</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<?php if ( ! $project_id ) : ?>
						<th>Project</th>
					<?php endif; ?>
					<th>Task Name</th>
					<th>Status</th>
					<th>Priority</th>
					<th>Assigned To</th>
					<th>Due Date</th>
					<th>Progress</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tasks as $task ) : ?>
					<tr>
						<?php if ( ! $project_id ) : ?>
							<td><?php echo esc_html( $task['project_name'] ?? 'N/A' ); ?></td>
						<?php endif; ?>
						<td>
							<strong><?php echo esc_html( $task['task_name'] ); ?></strong>
							<?php if ( $task['task_type'] !== 'task' ) : ?>
								<br><small class="ns-task-type"><?php echo esc_html( ucfirst( $task['task_type'] ) ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<span class="ns-task-status ns-task-status-<?php echo esc_attr( $task['task_status'] ); ?>">
								<?php echo esc_html( ucwords( str_replace( '_', ' ', $task['task_status'] ) ) ); ?>
							</span>
						</td>
						<td>
							<span class="ns-priority-<?php echo esc_attr( $task['priority'] ); ?>">
								<?php echo esc_html( ucfirst( $task['priority'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $task['assignee_name'] ?: 'Unassigned' ); ?></td>
						<td>
							<?php if ( $task['due_date'] ) : ?>
								<?php 
								$due_date = strtotime( $task['due_date'] );
								$is_overdue = $due_date < time() && $task['task_status'] !== 'completed';
								?>
								<span class="<?php echo $is_overdue ? 'ns-overdue' : ''; ?>">
									<?php echo esc_html( wp_date( 'M j, Y', $due_date ) ); ?>
								</span>
							<?php else : ?>
								<small>—</small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $task['progress_percent'] ); ?>%</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<style>
.ns-task-status {
	display: inline-block;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
}
.ns-task-status-todo { background: #e2e3e5; color: #383d41; }
.ns-task-status-in_progress { background: #cfe2ff; color: #084298; }
.ns-task-status-in_review { background: #e7f3ff; color: #055160; }
.ns-task-status-blocked { background: #f8d7da; color: #721c24; }
.ns-task-status-completed { background: #d1e7dd; color: #0f5132; }
.ns-task-status-cancelled { background: #e2e3e5; color: #383d41; }

.ns-overdue { color: #dc3545; font-weight: bold; }
.ns-task-type { color: #6c757d; }
</style>
