<?php
/**
 * Secretary Dashboard (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$summary = NonprofitSuite_Secretary::get_dashboard_summary();
$overdue_tasks = NonprofitSuite_Secretary::get_overdue_tasks();
$pending_minutes = NonprofitSuite_Secretary::get_pending_minutes_reviews();
$upcoming_meetings = NonprofitSuite_Secretary::get_upcoming_meetings( 14 );
$recent_tasks = NonprofitSuite_Secretary::get_tasks( array( 'limit' => 20, 'orderby' => 'due_date', 'order' => 'ASC' ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Secretary Dashboard', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Centralized oversight for the Secretary role - meeting management, official records, and document authority.', 'nonprofitsuite' ); ?>
	</p>

	<?php if ( ! empty( $overdue_tasks ) && ! is_wp_error( $overdue_tasks ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Overdue Tasks:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d task is overdue', '%d tasks are overdue', count( $overdue_tasks ), 'nonprofitsuite' ), count( $overdue_tasks ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Summary Cards -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'Pending Tasks', 'nonprofitsuite' ); ?></h3>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;"><?php echo absint( $summary['pending'] ); ?></p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px; <?php echo ! empty( $summary['overdue'] ) ? 'background: #fee2e2;' : ''; ?>">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'Overdue', 'nonprofitsuite' ); ?></h3>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #ef4444;"><?php echo absint( $summary['overdue'] ); ?></p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'This Week', 'nonprofitsuite' ); ?></h3>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;"><?php echo absint( $summary['this_week'] ); ?></p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></h3>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #6b7280;"><?php echo absint( $summary['completed'] ); ?></p>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Review Pending Minutes', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Certify Board Action', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'File Document', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings' ) ); ?>';">
				<?php esc_html_e( 'View Meeting Calendar', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>

	<!-- Minutes Awaiting Review -->
	<?php if ( ! empty( $pending_minutes ) && ! is_wp_error( $pending_minutes ) ) : ?>
		<div class="ns-card" style="margin-bottom: 20px;">
			<h2 class="ns-card-title"><?php esc_html_e( 'Minutes Awaiting Review', 'nonprofitsuite' ); ?></h2>

			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Meeting', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Days Pending', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $pending_minutes, 0, 5 ) as $meeting ) :
						$days_pending = round( ( time() - strtotime( $meeting->meeting_date ) ) / 86400 );
						?>
						<tr>
							<td><strong><?php echo esc_html( $meeting->title ); ?></strong></td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $meeting->meeting_date ) ) ); ?></td>
							<td>
								<span class="<?php echo $days_pending > 7 ? 'ns-text-danger' : ''; ?>">
									<?php echo absint( $days_pending ); ?> <?php esc_html_e( 'days', 'nonprofitsuite' ); ?>
								</span>
							</td>
							<td>
								<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
									<?php esc_html_e( 'Review', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- Secretary Tasks -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Secretary Tasks', 'nonprofitsuite' ); ?></h2>

		<div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
			<select id="filter-task-type" style="padding: 8px;">
				<option value=""><?php esc_html_e( 'All Types', 'nonprofitsuite' ); ?></option>
				<option value="minutes_review"><?php esc_html_e( 'Minutes Review', 'nonprofitsuite' ); ?></option>
				<option value="document_filing"><?php esc_html_e( 'Document Filing', 'nonprofitsuite' ); ?></option>
				<option value="certification"><?php esc_html_e( 'Certification', 'nonprofitsuite' ); ?></option>
				<option value="meeting_preparation"><?php esc_html_e( 'Meeting Preparation', 'nonprofitsuite' ); ?></option>
			</select>

			<select id="filter-status" style="padding: 8px;">
				<option value=""><?php esc_html_e( 'All Status', 'nonprofitsuite' ); ?></option>
				<option value="pending"><?php esc_html_e( 'Pending', 'nonprofitsuite' ); ?></option>
				<option value="in_progress"><?php esc_html_e( 'In Progress', 'nonprofitsuite' ); ?></option>
				<option value="completed"><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></option>
			</select>

			<select id="filter-priority" style="padding: 8px;">
				<option value=""><?php esc_html_e( 'All Priorities', 'nonprofitsuite' ); ?></option>
				<option value="urgent"><?php esc_html_e( 'Urgent', 'nonprofitsuite' ); ?></option>
				<option value="high"><?php esc_html_e( 'High', 'nonprofitsuite' ); ?></option>
				<option value="medium"><?php esc_html_e( 'Medium', 'nonprofitsuite' ); ?></option>
				<option value="low"><?php esc_html_e( 'Low', 'nonprofitsuite' ); ?></option>
			</select>
		</div>

		<?php if ( ! empty( $recent_tasks ) && ! is_wp_error( $recent_tasks ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Task', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Due Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_tasks as $task ) :
						$is_overdue = $task->due_date && strtotime( $task->due_date ) < time() && $task->status !== 'completed';
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $task->title ); ?></strong>
								<?php if ( $task->description ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( wp_trim_words( $task->description, 15 ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $task->task_type ) ) ); ?></td>
							<td>
								<?php
								$priority_colors = array(
									'urgent' => '#ef4444',
									'high' => '#f59e0b',
									'medium' => '#6b7280',
									'low' => '#9ca3af',
								);
								$color = isset( $priority_colors[ $task->priority ] ) ? $priority_colors[ $task->priority ] : '#6b7280';
								?>
								<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
									<?php echo esc_html( ucfirst( $task->priority ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $task->due_date ) : ?>
									<span class="<?php echo $is_overdue ? 'ns-text-danger' : ''; ?>">
										<?php echo esc_html( date( 'M j, Y', strtotime( $task->due_date ) ) ); ?>
										<?php if ( $is_overdue ) : ?>
											<br><strong><?php esc_html_e( 'OVERDUE', 'nonprofitsuite' ); ?></strong>
										<?php endif; ?>
									</span>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $task->status ); ?></td>
							<td>
								<?php if ( $task->status !== 'completed' ) : ?>
									<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
										<?php esc_html_e( 'Complete', 'nonprofitsuite' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No tasks found.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Upcoming Meetings -->
	<?php if ( ! empty( $upcoming_meetings ) && ! is_wp_error( $upcoming_meetings ) ) : ?>
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Upcoming Meetings', 'nonprofitsuite' ); ?></h2>

			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Meeting', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date & Time', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Days Until', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $upcoming_meetings as $meeting ) :
						$days_until = round( ( strtotime( $meeting->meeting_date ) - time() ) / 86400 );
						?>
						<tr>
							<td><strong><?php echo esc_html( $meeting->title ); ?></strong></td>
							<td>
								<?php echo esc_html( date( 'M j, Y', strtotime( $meeting->meeting_date ) ) ); ?>
								<?php if ( $meeting->start_time ) : ?>
									<?php echo esc_html( date( 'g:i A', strtotime( $meeting->start_time ) ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $meeting->meeting_type ) ) ); ?></td>
							<td>
								<span class="<?php echo $days_until <= 3 ? 'ns-text-warning' : ''; ?>">
									<?php echo absint( $days_until ); ?> <?php esc_html_e( 'days', 'nonprofitsuite' ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
