<?php
/**
 * Projects List View
 *
 * Displays all projects with their statistics and management options.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1; // TODO: Get from current user context
$table           = $wpdb->prefix . 'ns_projects';

// Get all projects
$projects = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT p.*, u.display_name as owner_name FROM {$table} p
		LEFT JOIN {$wpdb->users} u ON p.owner_id = u.ID
		WHERE p.organization_id = %d AND p.is_archived = 0
		ORDER BY p.created_at DESC",
		$organization_id
	),
	ARRAY_A
);

// Calculate progress for each project
require_once NS_PLUGIN_DIR . 'includes/helpers/class-project-manager.php';
$manager = NS_Project_Manager::get_instance();
?>

<div class="wrap">
	<h1 class="wp-heading-inline">Projects</h1>
	<a href="#" class="page-title-action">Add New Project</a>
	<hr class="wp-header-end">

	<?php if ( empty( $projects ) ) : ?>
		<div class="notice notice-info">
			<p>No projects found. Create your first project to get started!</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Project Name</th>
					<th>Status</th>
					<th>Priority</th>
					<th>Owner</th>
					<th>Progress</th>
					<th>Provider</th>
					<th>Dates</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $projects as $project ) : 
					$progress = $manager->calculate_project_progress( $project['id'] );
				?>
					<tr>
						<td>
							<strong><?php echo esc_html( $project['project_name'] ); ?></strong>
							<?php if ( ! empty( $project['description'] ) ) : ?>
								<br><small><?php echo esc_html( wp_trim_words( $project['description'], 10 ) ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<span class="ns-status-badge ns-status-<?php echo esc_attr( $project['project_status'] ); ?>">
								<?php echo esc_html( ucwords( str_replace( '_', ' ', $project['project_status'] ) ) ); ?>
							</span>
						</td>
						<td>
							<span class="ns-priority-<?php echo esc_attr( $project['priority'] ); ?>">
								<?php echo esc_html( ucfirst( $project['priority'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $project['owner_name'] ?: 'N/A' ); ?></td>
						<td>
							<div class="ns-progress-bar">
								<div class="ns-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
							</div>
							<small><?php echo esc_html( $progress ); ?>%</small>
						</td>
						<td>
							<?php
							$provider_labels = array(
								'builtin' => 'Built-in',
								'asana'   => 'Asana',
								'trello'  => 'Trello',
								'monday'  => 'Monday.com',
							);
							echo esc_html( $provider_labels[ $project['provider'] ] ?? $project['provider'] );
							?>
						</td>
						<td>
							<?php if ( $project['start_date'] || $project['end_date'] ) : ?>
								<small>
									<?php echo $project['start_date'] ? esc_html( wp_date( 'M j', strtotime( $project['start_date'] ) ) ) : '...'; ?>
									→
									<?php echo $project['end_date'] ? esc_html( wp_date( 'M j, Y', strtotime( $project['end_date'] ) ) ) : '...'; ?>
								</small>
							<?php else : ?>
								<small>—</small>
			<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-tasks&project_id=' . $project['id'] ) ); ?>" class="button button-small">
								View Tasks
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<style>
.ns-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}
.ns-status-planning { background: #fff3cd; color: #856404; }
.ns-status-active { background: #d4edda; color: #155724; }
.ns-status-on_hold { background: #f8d7da; color: #721c24; }
.ns-status-completed { background: #d1ecf1; color: #0c5460; }
.ns-status-cancelled { background: #e2e3e5; color: #383d41; }

.ns-priority-low { color: #28a745; }
.ns-priority-medium { color: #ffc107; }
.ns-priority-high { color: #fd7e14; }
.ns-priority-critical { color: #dc3545; font-weight: bold; }

.ns-progress-bar {
	width: 100px;
	height: 8px;
	background: #e9ecef;
	border-radius: 4px;
	overflow: hidden;
	display: inline-block;
	margin-right: 5px;
}
.ns-progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #28a745, #20c997);
}
</style>
