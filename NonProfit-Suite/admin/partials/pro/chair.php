<?php
/**
 * Board Chair Dashboard (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$metrics = NonprofitSuite_Chair::get_dashboard_metrics();
$committees = NonprofitSuite_Chair::get_committee_status_report();
$priorities = NonprofitSuite_Chair::get_strategic_priorities();
$actions = NonprofitSuite_Chair::get_upcoming_board_actions();
$recent_notes = NonprofitSuite_Chair::get_notes( array( 'limit' => 5 ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Board Chair Dashboard', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<!-- Executive Summary Cards -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Board Attendance', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: <?php echo $metrics['governance']['attendance_rate'] >= 80 ? '#10b981' : '#f59e0b'; ?>;">
				<?php echo absint( $metrics['governance']['attendance_rate'] ); ?>%
			</p>
			<p class="ns-text-sm ns-text-muted" style="margin: 10px 0 0 0;"><?php esc_html_e( 'Last 3 Meetings', 'nonprofitsuite' ); ?></p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Cash Position', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
				$<?php echo number_format( $metrics['financial']['cash_position'], 0 ); ?>
			</p>
			<p class="ns-text-sm ns-text-muted" style="margin: 10px 0 0 0;"><?php esc_html_e( 'As of Today', 'nonprofitsuite' ); ?></p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Projects', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
				<?php echo absint( $metrics['strategic']['active_projects'] ); ?>
			</p>
			<p class="ns-text-sm ns-text-muted" style="margin: 10px 0 0 0;">
				<?php echo absint( $metrics['strategic']['projects_on_schedule'] ); ?> <?php esc_html_e( 'On Schedule', 'nonprofitsuite' ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px; <?php echo count( $actions ) > 5 ? 'background: #fee2e2;' : ''; ?>">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Action Items', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #ef4444;">
				<?php echo count( $actions ); ?>
			</p>
			<p class="ns-text-sm ns-text-muted" style="margin: 10px 0 0 0;"><?php esc_html_e( 'Require Attention', 'nonprofitsuite' ); ?></p>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<button class="ns-button ns-button-primary" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings' ) ); ?>';">
				<?php esc_html_e( 'Schedule Board Meeting', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Create Executive Session', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-treasury' ) ); ?>';">
				<?php esc_html_e( 'Review Financial Dashboard', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-board-development' ) ); ?>';">
				<?php esc_html_e( 'Board Development Pipeline', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>

	<!-- Two Column Layout -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<!-- Committee Status -->
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Committee Status', 'nonprofitsuite' ); ?></h2>

			<?php if ( ! empty( $committees ) && ! is_wp_error( $committees ) ) : ?>
				<table class="ns-table ns-table-compact">
					<tbody>
						<?php foreach ( $committees as $committee ) : ?>
							<tr>
								<td><?php echo esc_html( $committee->committee_name ); ?></td>
								<td style="text-align: right;">
									<?php if ( $committee->activity_status === 'active' ) : ?>
										<span style="color: #10b981;">✅ <?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></span>
									<?php elseif ( $committee->activity_status === 'inactive' ) : ?>
										<span style="color: #f59e0b;">⚠️ <?php esc_html_e( 'No Meeting This Quarter', 'nonprofitsuite' ); ?></span>
									<?php else : ?>
										<span style="color: #ef4444;">❌ <?php esc_html_e( 'No Meetings', 'nonprofitsuite' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No committees found.', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Strategic Priorities -->
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Strategic Priorities', 'nonprofitsuite' ); ?></h2>

			<?php if ( ! empty( $priorities ) && ! is_wp_error( $priorities ) ) : ?>
				<ul style="list-style: none; padding: 0; margin: 0;">
					<?php foreach ( array_slice( $priorities, 0, 5 ) as $priority ) : ?>
						<li style="padding: 10px 0; border-bottom: 1px solid #e0e0e0;">
							<strong><?php echo esc_html( $priority->title ); ?></strong>
							<div style="margin-top: 8px;">
								<div style="background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
									<div style="background: <?php echo $priority->progress >= 75 ? '#10b981' : '#2563eb'; ?>; height: 100%; width: <?php echo absint( $priority->progress ); ?>%;"></div>
								</div>
								<span class="ns-text-sm ns-text-muted"><?php echo absint( $priority->progress ); ?>% <?php esc_html_e( 'Complete', 'nonprofitsuite' ); ?></span>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No strategic priorities found.', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Items Requiring Chair Attention -->
	<?php if ( ! empty( $actions ) ) : ?>
		<div class="ns-card" style="margin-bottom: 20px;">
			<h2 class="ns-card-title"><?php esc_html_e( 'Items Requiring Your Attention', 'nonprofitsuite' ); ?></h2>

			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Due/Expiring', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $actions, 0, 10 ) as $action ) :
						$is_overdue = strtotime( $action['date'] ) < time();
						?>
						<tr>
							<td><strong><?php echo esc_html( $action['title'] ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $action['type'] ) ) ); ?></td>
							<td>
								<span class="<?php echo $is_overdue ? 'ns-text-danger' : ''; ?>">
									<?php echo esc_html( date( 'M j, Y', strtotime( $action['date'] ) ) ); ?>
									<?php if ( $is_overdue ) : ?>
										<br><strong><?php esc_html_e( 'OVERDUE', 'nonprofitsuite' ); ?></strong>
									<?php endif; ?>
								</span>
							</td>
							<td>
								<?php
								$priority_colors = array( 'high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#6b7280' );
								$color = isset( $priority_colors[ $action['priority'] ] ) ? $priority_colors[ $action['priority'] ] : '#6b7280';
								?>
								<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
									<?php echo esc_html( ucfirst( $action['priority'] ) ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- Chair's Private Notes -->
	<div class="ns-card">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
			<h2 style="margin: 0;"><?php esc_html_e( "Chair's Private Notes", 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				+ <?php esc_html_e( 'Add Note', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $recent_notes ) && ! is_wp_error( $recent_notes ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Title/Content', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_notes as $note ) : ?>
						<tr>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $note->note_type ) ) ); ?></td>
							<td>
								<?php if ( $note->title ) : ?>
									<strong><?php echo esc_html( $note->title ); ?></strong><br>
								<?php endif; ?>
								<span class="ns-text-sm"><?php echo esc_html( wp_trim_words( $note->content, 20 ) ); ?></span>
							</td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $note->created_at ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No notes yet. Add your first private note to track important observations and conversations.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
