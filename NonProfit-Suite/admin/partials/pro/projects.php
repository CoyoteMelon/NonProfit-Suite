<?php
/**
 * Project Management (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$active_projects = NonprofitSuite_Projects::get_projects( array( 'status' => 'active', 'limit' => 50 ) );
$overdue_projects = NonprofitSuite_Projects::get_overdue_projects();
$all_projects = NonprofitSuite_Projects::get_projects( array( 'limit' => 100 ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Project Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! empty( $overdue_projects ) && ! is_wp_error( $overdue_projects ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Overdue Projects:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d project is overdue', '%d projects are overdue', count( $overdue_projects ), 'nonprofitsuite' ), count( $overdue_projects ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Active Projects Grid -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Active Projects', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add project feature coming soon');">
				<?php esc_html_e( 'Add Project', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $active_projects ) && ! is_wp_error( $active_projects ) ) : ?>
			<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 20px;">
				<?php foreach ( $active_projects as $project ) :
					$progress = NonprofitSuite_Projects::calculate_progress( $project->id );
					$is_overdue = strtotime( $project->target_end_date ) < time() && $project->status !== 'completed';

					$status_colors = array(
						'planning' => '#6b7280',
						'active' => '#2563eb',
						'on_hold' => '#f59e0b',
						'completed' => '#10b981',
						'cancelled' => '#ef4444',
					);
					$status_color = isset( $status_colors[ $project->status ] ) ? $status_colors[ $project->status ] : '#6b7280';
					?>
					<div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; background: #fff; <?php echo $is_overdue ? 'border-left: 4px solid #ef4444;' : ''; ?>">
						<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
							<h3 style="margin: 0; font-size: 18px; font-weight: 600;">
								<?php echo esc_html( $project->name ); ?>
							</h3>
							<span style="background: <?php echo esc_attr( $status_color ); ?>; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $project->status ) ) ); ?>
							</span>
						</div>

						<?php if ( $project->description ) : ?>
							<p class="ns-text-sm ns-text-muted" style="margin: 10px 0;">
								<?php echo esc_html( wp_trim_words( $project->description, 20 ) ); ?>
							</p>
						<?php endif; ?>

						<!-- Progress Bar -->
						<div style="margin: 15px 0;">
							<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
								<span class="ns-text-sm" style="font-weight: 500;"><?php esc_html_e( 'Progress', 'nonprofitsuite' ); ?></span>
								<span class="ns-text-sm" style="font-weight: 600;"><?php echo absint( $progress ); ?>%</span>
							</div>
							<div style="background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
								<div style="background: <?php echo $progress >= 100 ? '#10b981' : '#2563eb'; ?>; height: 100%; width: <?php echo absint( $progress ); ?>%; transition: width 0.3s;"></div>
							</div>
						</div>

						<!-- Project Details -->
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
							<div>
								<div class="ns-text-sm ns-text-muted"><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></div>
								<div class="ns-text-sm" style="font-weight: 500;">
									<?php echo esc_html( date( 'M j, Y', strtotime( $project->start_date ) ) ); ?>
								</div>
							</div>
							<div>
								<div class="ns-text-sm ns-text-muted"><?php esc_html_e( 'Target End', 'nonprofitsuite' ); ?></div>
								<div class="ns-text-sm" style="font-weight: 500; <?php echo $is_overdue ? 'color: #ef4444;' : ''; ?>">
									<?php echo esc_html( date( 'M j, Y', strtotime( $project->target_end_date ) ) ); ?>
									<?php if ( $is_overdue ) : ?>
										<span style="font-weight: 600;">(<?php esc_html_e( 'OVERDUE', 'nonprofitsuite' ); ?>)</span>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<?php if ( $project->budget ) : ?>
							<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
								<div style="display: flex; justify-content: space-between; align-items: center;">
									<div>
										<div class="ns-text-sm ns-text-muted"><?php esc_html_e( 'Budget', 'nonprofitsuite' ); ?></div>
										<div style="font-weight: 600; font-size: 18px;">$<?php echo number_format( $project->budget, 2 ); ?></div>
									</div>
									<?php if ( $project->actual_cost ) : ?>
										<div style="text-align: right;">
											<div class="ns-text-sm ns-text-muted"><?php esc_html_e( 'Spent', 'nonprofitsuite' ); ?></div>
											<div style="font-weight: 600; font-size: 18px; <?php echo $project->actual_cost > $project->budget ? 'color: #ef4444;' : 'color: #10b981;'; ?>">
												$<?php echo number_format( $project->actual_cost, 2 ); ?>
											</div>
										</div>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>

						<button class="ns-button ns-button-sm" onclick="alert('View project details feature coming soon');" style="margin-top: 15px; width: 100%;">
							<?php esc_html_e( 'View Details', 'nonprofitsuite' ); ?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No active projects found. Add your first project to start tracking progress!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- All Projects Table -->
	<div class="ns-card">
		<h2 class="ns-card-title"><?php esc_html_e( 'All Projects', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $all_projects ) && ! is_wp_error( $all_projects ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Project Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Progress', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Target End', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Budget', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_projects as $project ) :
						$progress = NonprofitSuite_Projects::calculate_progress( $project->id );
						$is_overdue = strtotime( $project->target_end_date ) < time() && $project->status !== 'completed';
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $project->name ); ?></strong>
								<?php if ( $project->description ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( wp_trim_words( $project->description, 10 ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $project->status ); ?></td>
							<td>
								<div style="display: flex; align-items: center; gap: 10px;">
									<div style="flex: 1; background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden; min-width: 60px;">
										<div style="background: <?php echo $progress >= 100 ? '#10b981' : '#2563eb'; ?>; height: 100%; width: <?php echo absint( $progress ); ?>%;"></div>
									</div>
									<span style="font-weight: 600; min-width: 40px;"><?php echo absint( $progress ); ?>%</span>
								</div>
							</td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $project->start_date ) ) ); ?></td>
							<td>
								<span class="<?php echo $is_overdue ? 'ns-text-danger' : ''; ?>">
									<?php echo esc_html( date( 'M j, Y', strtotime( $project->target_end_date ) ) ); ?>
									<?php if ( $is_overdue ) : ?>
										<br><strong class="ns-text-sm"><?php esc_html_e( 'OVERDUE', 'nonprofitsuite' ); ?></strong>
									<?php endif; ?>
								</span>
							</td>
							<td>
								<?php if ( $project->budget ) : ?>
									$<?php echo number_format( $project->budget, 2 ); ?>
									<?php if ( $project->actual_cost ) : ?>
										<br><span class="ns-text-sm <?php echo $project->actual_cost > $project->budget ? 'ns-text-danger' : 'ns-text-muted'; ?>">
											$<?php echo number_format( $project->actual_cost, 2 ); ?> <?php esc_html_e( 'spent', 'nonprofitsuite' ); ?>
										</span>
									<?php endif; ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$priority_colors = array(
									'low' => '#6b7280',
									'medium' => '#f59e0b',
									'high' => '#ef4444',
								);
								$priority_color = isset( $priority_colors[ $project->priority ] ) ? $priority_colors[ $project->priority ] : '#6b7280';
								?>
								<span style="color: <?php echo esc_attr( $priority_color ); ?>; font-weight: 600;">
									<?php echo esc_html( ucfirst( $project->priority ) ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No projects found.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
