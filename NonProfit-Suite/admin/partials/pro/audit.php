<?php
/**
 * Audit Module (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$dashboard_data = NonprofitSuite_Audit::get_dashboard_data();
$current_audit = isset( $dashboard_data['current_audit'] ) ? $dashboard_data['current_audit'] : null;
$pending_requests = isset( $dashboard_data['pending_requests'] ) ? $dashboard_data['pending_requests'] : 0;
$open_findings = isset( $dashboard_data['open_findings'] ) ? $dashboard_data['open_findings'] : 0;

$recent_audits = NonprofitSuite_Audit::get_audits( array( 'limit' => 10 ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Audit Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Manage financial audits, track auditor requests, monitor findings, and maintain audit documentation.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Summary Stats -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Current Audit', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 28px; font-weight: 600; color: #2563eb;">
				<?php echo $current_audit ? esc_html( $current_audit->audit_year ) : '-'; ?>
			</p>
			<?php if ( $current_audit ) : ?>
				<p class="ns-text-sm ns-text-muted" style="margin: 10px 0 0 0;">
					<?php echo esc_html( ucfirst( str_replace( '_', ' ', $current_audit->status ) ) ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Pending Requests', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: <?php echo $pending_requests > 0 ? '#f59e0b' : '#10b981'; ?>;">
				<?php echo absint( $pending_requests ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Open Findings', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: <?php echo $open_findings > 0 ? '#ef4444' : '#10b981'; ?>;">
				<?php echo absint( $open_findings ); ?>
			</p>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				+ <?php esc_html_e( 'Start New Audit', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Record Request', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Add Finding', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>

	<!-- Recent Audits -->
	<div class="ns-card">
		<h2 class="ns-card-title"><?php esc_html_e( 'Audit History', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $recent_audits ) && ! is_wp_error( $recent_audits ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Year', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Auditor', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Opinion', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Report Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_audits as $audit ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $audit->audit_year ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $audit->audit_type ) ) ); ?></td>
							<td><?php echo $audit->auditor_firm ? esc_html( $audit->auditor_firm ) : '<span class="ns-text-muted">-</span>'; ?></td>
							<td>
								<?php
								$status_colors = array(
									'planning' => '#6b7280',
									'fieldwork' => '#2563eb',
									'review' => '#f59e0b',
									'completed' => '#10b981',
								);
								$color = isset( $status_colors[ $audit->status ] ) ? $status_colors[ $audit->status ] : '#6b7280';
								?>
								<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
									<?php echo esc_html( ucfirst( $audit->status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $audit->opinion ) : ?>
									<?php
									$opinion_colors = array(
										'unqualified' => '#10b981',
										'qualified' => '#f59e0b',
										'adverse' => '#ef4444',
										'disclaimer' => '#6b7280',
									);
									$opinion_color = isset( $opinion_colors[ $audit->opinion ] ) ? $opinion_colors[ $audit->opinion ] : '#6b7280';
									?>
									<span style="color: <?php echo esc_attr( $opinion_color ); ?>; font-weight: 600;">
										<?php echo esc_html( ucfirst( $audit->opinion ) ); ?>
									</span>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td><?php echo $audit->report_date ? esc_html( date( 'M j, Y', strtotime( $audit->report_date ) ) ) : '<span class="ns-text-muted">-</span>'; ?></td>
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
			<p><?php esc_html_e( 'No audits recorded yet. Start your first audit to track auditor requests and findings.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
