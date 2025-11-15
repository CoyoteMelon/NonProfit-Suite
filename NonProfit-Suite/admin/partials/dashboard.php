<?php
/**
 * Dashboard view
 *
 * @package NonprofitSuite
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$org_name = get_option( 'nonprofitsuite_organization_name', get_bloginfo( 'name' ) );
$upcoming_meetings = NonprofitSuite_Meetings::get_upcoming( 5 );
$my_tasks = NonprofitSuite_Tasks::get_my_tasks();

// Get stats
global $wpdb;
$total_meetings = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_meetings" );
$total_tasks = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_tasks WHERE status != 'completed'" );
$total_documents = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_documents" );
$total_people = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_people WHERE status = 'active'" );
?>

<div class="wrap ns-container">
	<h1><?php printf( esc_html__( 'Welcome to %s', 'nonprofitsuite' ), esc_html( $org_name ) ); ?></h1>

	<!-- Stats Dashboard -->
	<div class="ns-dashboard-grid">
		<div class="ns-stat-card">
			<div class="ns-stat-label"><?php esc_html_e( 'Total Meetings', 'nonprofitsuite' ); ?></div>
			<div class="ns-stat-value"><?php echo esc_html( $total_meetings ); ?></div>
		</div>

		<div class="ns-stat-card">
			<div class="ns-stat-label"><?php esc_html_e( 'Active Tasks', 'nonprofitsuite' ); ?></div>
			<div class="ns-stat-value"><?php echo esc_html( $total_tasks ); ?></div>
		</div>

		<div class="ns-stat-card">
			<div class="ns-stat-label"><?php esc_html_e( 'Documents', 'nonprofitsuite' ); ?></div>
			<div class="ns-stat-value"><?php echo esc_html( $total_documents ); ?></div>
		</div>

		<div class="ns-stat-card">
			<div class="ns-stat-label"><?php esc_html_e( 'Active People', 'nonprofitsuite' ); ?></div>
			<div class="ns-stat-value"><?php echo esc_html( $total_people ); ?></div>
		</div>
	</div>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Upcoming Meetings', 'nonprofitsuite' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings&action=add' ) ); ?>" class="ns-button ns-button-primary">
				<?php esc_html_e( 'Add Meeting', 'nonprofitsuite' ); ?>
			</a>
		</div>

		<?php if ( ! empty( $upcoming_meetings ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Meeting', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Location', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $upcoming_meetings as $meeting ) : ?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Meeting', 'nonprofitsuite' ); ?>">
								<strong><?php echo esc_html( $meeting->title ); ?></strong>
							</td>
							<td data-label="<?php esc_attr_e( 'Type', 'nonprofitsuite' ); ?>">
								<?php echo esc_html( ucfirst( $meeting->meeting_type ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Date', 'nonprofitsuite' ); ?>">
								<?php echo esc_html( NonprofitSuite_Utilities::format_datetime( $meeting->meeting_date ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Location', 'nonprofitsuite' ); ?>">
								<?php echo esc_html( $meeting->location ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Actions', 'nonprofitsuite' ); ?>">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings&action=edit&id=' . $meeting->id ) ); ?>">
									<?php esc_html_e( 'Edit', 'nonprofitsuite' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No upcoming meetings scheduled.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'My Tasks', 'nonprofitsuite' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-tasks' ) ); ?>" class="ns-button ns-button-outline">
				<?php esc_html_e( 'View All Tasks', 'nonprofitsuite' ); ?>
			</a>
		</div>

		<?php if ( ! empty( $my_tasks ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Task', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Due Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $my_tasks as $task ) : ?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Task', 'nonprofitsuite' ); ?>">
								<strong><?php echo esc_html( $task->title ); ?></strong>
							</td>
							<td data-label="<?php esc_attr_e( 'Due Date', 'nonprofitsuite' ); ?>">
								<?php echo esc_html( NonprofitSuite_Utilities::format_date( $task->due_date ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Priority', 'nonprofitsuite' ); ?>">
								<?php echo NonprofitSuite_Utilities::get_priority_badge( $task->priority ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Status', 'nonprofitsuite' ); ?>">
								<?php echo NonprofitSuite_Utilities::get_status_badge( $task->status ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No tasks assigned to you.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
