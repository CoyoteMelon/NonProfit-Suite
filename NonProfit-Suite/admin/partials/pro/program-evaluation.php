<?php
/**
 * Program Evaluation (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$dashboard_data = NonprofitSuite_Program_Evaluation::get_dashboard_data();
$active_evaluations = isset( $dashboard_data['active_evaluations'] ) ? $dashboard_data['active_evaluations'] : 0;
$total_responses = isset( $dashboard_data['total_responses'] ) ? $dashboard_data['total_responses'] : 0;
$recent_evaluations = isset( $dashboard_data['recent_evaluations'] ) ? $dashboard_data['recent_evaluations'] : array();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Program Evaluation', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Measure program effectiveness, track outcomes, collect data, and demonstrate impact to stakeholders.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Summary Stats -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Evaluations', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
				<?php echo absint( $active_evaluations ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Total Responses', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
				<?php echo absint( $total_responses ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Programs Evaluated', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #6b7280;">
				<?php echo count( $recent_evaluations ); ?>
			</p>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				+ <?php esc_html_e( 'New Evaluation', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Add Metrics', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Record Data', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>

	<!-- Evaluation Frameworks -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Evaluation Frameworks', 'nonprofitsuite' ); ?></h2>
		<p><?php esc_html_e( 'NonprofitSuite supports multiple evaluation methodologies:', 'nonprofitsuite' ); ?></p>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
			<div style="padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
				<strong><?php esc_html_e( 'Logic Model', 'nonprofitsuite' ); ?></strong>
				<p class="ns-text-sm ns-text-muted" style="margin: 5px 0 0 0;">
					<?php esc_html_e( 'Inputs → Activities → Outputs → Outcomes → Impact', 'nonprofitsuite' ); ?>
				</p>
			</div>
			<div style="padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
				<strong><?php esc_html_e( 'Theory of Change', 'nonprofitsuite' ); ?></strong>
				<p class="ns-text-sm ns-text-muted" style="margin: 5px 0 0 0;">
					<?php esc_html_e( 'Map pathways from activities to long-term goals', 'nonprofitsuite' ); ?>
				</p>
			</div>
			<div style="padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
				<strong><?php esc_html_e( 'Outcome Mapping', 'nonprofitsuite' ); ?></strong>
				<p class="ns-text-sm ns-text-muted" style="margin: 5px 0 0 0;">
					<?php esc_html_e( 'Focus on behavioral changes in key stakeholders', 'nonprofitsuite' ); ?>
				</p>
			</div>
			<div style="padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
				<strong><?php esc_html_e( 'Most Significant Change', 'nonprofitsuite' ); ?></strong>
				<p class="ns-text-sm ns-text-muted" style="margin: 5px 0 0 0;">
					<?php esc_html_e( 'Participatory approach collecting stories', 'nonprofitsuite' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Recent Evaluations -->
	<div class="ns-card">
		<h2 class="ns-card-title"><?php esc_html_e( 'Recent Evaluations', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $recent_evaluations ) && ! is_wp_error( $recent_evaluations ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Evaluation', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Framework', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Participants', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Period', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_evaluations as $evaluation ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $evaluation->evaluation_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $evaluation->evaluation_type ) ) ); ?></td>
							<td><?php echo $evaluation->framework ? esc_html( $evaluation->framework ) : '<span class="ns-text-muted">-</span>'; ?></td>
							<td>
								<?php
								$status_colors = array(
									'planning' => '#6b7280',
									'active' => '#2563eb',
									'data_collection' => '#10b981',
									'analysis' => '#f59e0b',
									'completed' => '#10b981',
								);
								$color = isset( $status_colors[ $evaluation->status ] ) ? $status_colors[ $evaluation->status ] : '#6b7280';
								?>
								<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $evaluation->status ) ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $evaluation->actual_participants ) : ?>
									<?php echo absint( $evaluation->actual_participants ); ?>
									<?php if ( $evaluation->target_participants ) : ?>
										/ <?php echo absint( $evaluation->target_participants ); ?>
									<?php endif; ?>
								<?php elseif ( $evaluation->target_participants ) : ?>
									<?php esc_html_e( 'Target:', 'nonprofitsuite' ); ?> <?php echo absint( $evaluation->target_participants ); ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( date( 'M j, Y', strtotime( $evaluation->start_date ) ) ); ?>
								<?php if ( $evaluation->end_date ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php esc_html_e( 'to', 'nonprofitsuite' ); ?> <?php echo esc_html( date( 'M j, Y', strtotime( $evaluation->end_date ) ) ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
									<?php esc_html_e( 'View Details', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No evaluations created yet. Start your first program evaluation to measure impact and demonstrate outcomes.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
