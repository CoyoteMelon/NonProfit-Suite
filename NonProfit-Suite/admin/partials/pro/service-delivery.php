<?php
/**
 * Service Delivery (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$dashboard_data = NonprofitSuite_Service_Delivery::get_dashboard_data();
$active_clients = isset( $dashboard_data['active_clients'] ) ? $dashboard_data['active_clients'] : 0;
$services_this_month = isset( $dashboard_data['services_this_month'] ) ? $dashboard_data['services_this_month'] : 0;
$high_risk_clients = isset( $dashboard_data['high_risk_clients'] ) ? $dashboard_data['high_risk_clients'] : 0;
$recent_clients = isset( $dashboard_data['recent_clients'] ) ? $dashboard_data['recent_clients'] : array();
$follow_ups = NonprofitSuite_Service_Delivery::get_follow_ups_needed();

$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'clients';
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Service Delivery', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Manage client cases, track service delivery, monitor goals, and measure outcomes with privacy-first design.', 'nonprofitsuite' ); ?>
	</p>

	<?php if ( ! empty( $follow_ups ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Follow-Ups Needed:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d service requires follow-up', '%d services require follow-up', count( $follow_ups ), 'nonprofitsuite' ), count( $follow_ups ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Summary Stats -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Clients', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
				<?php echo absint( $active_clients ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Services This Month', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
				<?php echo absint( $services_this_month ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'High Risk Clients', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #ef4444;">
				<?php echo absint( $high_risk_clients ); ?>
			</p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div style="border-bottom: 2px solid #e0e0e0; margin-bottom: 20px;">
		<div style="display: flex; gap: 10px; margin-bottom: -2px;">
			<a href="?page=nonprofitsuite-service-delivery&tab=clients"
			   class="ns-tab-button<?php echo $tab === 'clients' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'clients' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'clients' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Clients', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-service-delivery&tab=services"
			   class="ns-tab-button<?php echo $tab === 'services' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'services' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'services' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Service Records', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-service-delivery&tab=followups"
			   class="ns-tab-button<?php echo $tab === 'followups' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'followups' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'followups' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Follow-Ups', 'nonprofitsuite' ); ?>
				<?php if ( ! empty( $follow_ups ) ) : ?>
					<span style="background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px;">
						<?php echo count( $follow_ups ); ?>
					</span>
				<?php endif; ?>
			</a>
		</div>
	</div>

	<?php if ( $tab === 'clients' ) : ?>
		<!-- Clients Tab -->

		<!-- Quick Actions -->
		<div class="ns-card" style="margin-bottom: 20px;">
			<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
			<div style="display: flex; gap: 10px; flex-wrap: wrap;">
				<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					+ <?php esc_html_e( 'New Client Intake', 'nonprofitsuite' ); ?>
				</button>
				<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					<?php esc_html_e( 'Record Service', 'nonprofitsuite' ); ?>
				</button>
				<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					<?php esc_html_e( 'Export Report', 'nonprofitsuite' ); ?>
				</button>
			</div>
		</div>

		<!-- Recent Clients -->
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Recent Clients', 'nonprofitsuite' ); ?></h2>

			<?php if ( ! empty( $recent_clients ) && ! is_wp_error( $recent_clients ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Client #', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Name', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Primary Need', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Risk Level', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Intake Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_clients as $client ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $client->client_number ); ?></strong></td>
								<td><?php echo esc_html( $client->first_name . ' ' . $client->last_name ); ?></td>
								<td><?php echo $client->primary_need ? esc_html( ucfirst( str_replace( '_', ' ', $client->primary_need ) ) ) : '<span class="ns-text-muted">-</span>'; ?></td>
								<td>
									<?php if ( $client->risk_level ) : ?>
										<?php
										$risk_colors = array( 'high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#10b981' );
										$color = isset( $risk_colors[ $client->risk_level ] ) ? $risk_colors[ $client->risk_level ] : '#6b7280';
										?>
										<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
											<?php echo esc_html( ucfirst( $client->risk_level ) ); ?>
										</span>
									<?php else : ?>
										<span class="ns-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$status_colors = array( 'active' => '#10b981', 'inactive' => '#6b7280', 'completed' => '#2563eb' );
									$status_color = isset( $status_colors[ $client->status ] ) ? $status_colors[ $client->status ] : '#6b7280';
									?>
									<span style="color: <?php echo esc_attr( $status_color ); ?>; font-weight: 600;">
										<?php echo esc_html( ucfirst( $client->status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $client->intake_date ) ) ); ?></td>
								<td>
									<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
										<?php esc_html_e( 'View Case', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No clients yet. Add your first client to start tracking service delivery.', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>

	<?php elseif ( $tab === 'services' ) : ?>
		<!-- Service Records Tab -->
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Service Records', 'nonprofitsuite' ); ?></h2>
			<p><?php esc_html_e( 'Service records tracking coming soon. Record individual services, track outcomes, and measure impact.', 'nonprofitsuite' ); ?></p>
		</div>

	<?php else : ?>
		<!-- Follow-Ups Tab -->
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Services Needing Follow-Up', 'nonprofitsuite' ); ?></h2>

			<?php if ( ! empty( $follow_ups ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Client', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Service Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Service Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Follow-Up Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $follow_ups as $service ) : ?>
							<?php
							$is_overdue = $service->follow_up_date && strtotime( $service->follow_up_date ) < time();
							?>
							<tr>
								<td><?php echo esc_html( $service->first_name . ' ' . $service->last_name ); ?></td>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $service->service_date ) ) ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $service->service_type ) ) ); ?></td>
								<td>
									<span class="<?php echo $is_overdue ? 'ns-text-danger' : ''; ?>">
										<?php echo $service->follow_up_date ? esc_html( date( 'M j, Y', strtotime( $service->follow_up_date ) ) ) : esc_html__( 'Not Set', 'nonprofitsuite' ); ?>
										<?php if ( $is_overdue ) : ?>
											<br><strong><?php esc_html_e( 'OVERDUE', 'nonprofitsuite' ); ?></strong>
										<?php endif; ?>
									</span>
								</td>
								<td>
									<span style="color: #f59e0b; font-weight: 600;">
										<?php esc_html_e( 'Follow-Up Needed', 'nonprofitsuite' ); ?>
									</span>
								</td>
								<td>
									<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
										<?php esc_html_e( 'Complete', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No follow-ups needed at this time. All services are up to date!', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
