<?php
/**
 * Tasks View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$tasks = NonprofitSuite_Tasks::get_my_tasks();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'My Tasks', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<?php if ( ! empty( $tasks ) ) : ?>
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
					<?php foreach ( $tasks as $task ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $task->title ); ?></strong><br><?php echo esc_html( $task->description ); ?></td>
							<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $task->due_date ) ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_priority_badge( $task->priority ); ?></td>
							<td>
								<select class="ns-task-status-select" data-task-id="<?php echo esc_attr( $task->id ); ?>">
									<option value="not_started" <?php selected( $task->status, 'not_started' ); ?>><?php esc_html_e( 'Not Started', 'nonprofitsuite' ); ?></option>
									<option value="in_progress" <?php selected( $task->status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'nonprofitsuite' ); ?></option>
									<option value="completed" <?php selected( $task->status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></option>
								</select>
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
