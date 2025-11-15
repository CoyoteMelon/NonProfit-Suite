<?php
/**
 * State Compliance (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$dashboard_data = NonprofitSuite_State_Compliance::get_dashboard_data();
$states_count = isset( $dashboard_data['states_count'] ) ? $dashboard_data['states_count'] : 0;
$pending_requirements = isset( $dashboard_data['pending_requirements'] ) ? $dashboard_data['pending_requirements'] : 0;
$overdue_requirements = isset( $dashboard_data['overdue_requirements'] ) ? $dashboard_data['overdue_requirements'] : 0;
$upcoming_deadlines = isset( $dashboard_data['upcoming_deadlines'] ) ? $dashboard_data['upcoming_deadlines'] : array();

$state_operations = NonprofitSuite_State_Compliance::get_state_operations();
$compliance_rate = NonprofitSuite_State_Compliance::calculate_compliance_rate();

$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'State Compliance', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Manage multi-state operations, track compliance requirements, monitor deadlines, and maintain good standing across jurisdictions.', 'nonprofitsuite' ); ?>
	</p>

	<?php if ( $overdue_requirements > 0 ) : ?>
		<div class="ns-alert ns-alert-danger">
			<strong><?php esc_html_e( 'Urgent:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d compliance requirement is overdue', '%d compliance requirements are overdue', $overdue_requirements, 'nonprofitsuite' ), $overdue_requirements ); ?>
		</div>
	<?php endif; ?>

	<!-- Summary Stats -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active States', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
				<?php echo absint( $states_count ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Pending Requirements', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: <?php echo $pending_requirements > 0 ? '#f59e0b' : '#10b981'; ?>;">
				<?php echo absint( $pending_requirements ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Overdue', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: <?php echo $overdue_requirements > 0 ? '#ef4444' : '#10b981'; ?>;">
				<?php echo absint( $overdue_requirements ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Compliance Rate', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: <?php echo $compliance_rate >= 80 ? '#10b981' : '#f59e0b'; ?>;">
				<?php echo absint( $compliance_rate ); ?>%
			</p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div style="border-bottom: 2px solid #e0e0e0; margin-bottom: 20px;">
		<div style="display: flex; gap: 10px; margin-bottom: -2px;">
			<a href="?page=nonprofitsuite-state-compliance&tab=overview"
			   class="ns-tab-button<?php echo $tab === 'overview' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'overview' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'overview' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Overview', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-state-compliance&tab=states"
			   class="ns-tab-button<?php echo $tab === 'states' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'states' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'states' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'State Operations', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-state-compliance&tab=requirements"
			   class="ns-tab-button<?php echo $tab === 'requirements' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'requirements' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'requirements' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Requirements', 'nonprofitsuite' ); ?>
			</a>
		</div>
	</div>

	<?php if ( $tab === 'overview' ) : ?>
		<!-- Overview Tab -->

		<!-- Quick Actions -->
		<div class="ns-card" style="margin-bottom: 20px;">
			<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
			<div style="display: flex; gap: 10px; flex-wrap: wrap;">
				<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					+ <?php esc_html_e( 'Add State Operation', 'nonprofitsuite' ); ?>
				</button>
				<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					<?php esc_html_e( 'Update Requirement', 'nonprofitsuite' ); ?>
				</button>
				<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
				</button>
			</div>
		</div>

		<!-- Upcoming Deadlines -->
		<?php if ( ! empty( $upcoming_deadlines ) ) : ?>
			<div class="ns-card" style="margin-bottom: 20px;">
				<h2 class="ns-card-title"><?php esc_html_e( 'Upcoming Deadlines (Next 30 Days)', 'nonprofitsuite' ); ?></h2>

				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'State', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Requirement', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Due Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Fee', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $upcoming_deadlines as $requirement ) : ?>
							<?php
							$days_until = ceil( ( strtotime( $requirement->due_date ) - time() ) / 86400 );
							$is_urgent = $days_until <= 7;
							?>
							<tr>
								<td><strong><?php echo esc_html( $requirement->state_name ); ?></strong></td>
								<td><?php echo esc_html( $requirement->requirement_name ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $requirement->requirement_type ) ) ); ?></td>
								<td>
									<span class="<?php echo $is_urgent ? 'ns-text-danger' : ''; ?>">
										<?php echo esc_html( date( 'M j, Y', strtotime( $requirement->due_date ) ) ); ?>
										<br><span class="ns-text-sm"><?php echo absint( $days_until ); ?> <?php esc_html_e( 'days', 'nonprofitsuite' ); ?></span>
									</span>
								</td>
								<td>
									<?php if ( $requirement->fee_amount ) : ?>
										$<?php echo number_format( $requirement->fee_amount, 0 ); ?>
									<?php else : ?>
										<span class="ns-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
										<?php esc_html_e( 'Mark Complete', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<!-- State Map (Placeholder) -->
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Operations Map', 'nonprofitsuite' ); ?></h2>
			<div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 4px;">
				<p class="ns-text-muted"><?php esc_html_e( 'Interactive state operations map coming soon', 'nonprofitsuite' ); ?></p>
			</div>
		</div>

	<?php elseif ( $tab === 'states' ) : ?>
		<!-- State Operations Tab -->

		<div class="ns-card">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php esc_html_e( 'State Operations', 'nonprofitsuite' ); ?></h2>
				<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					+ <?php esc_html_e( 'Add State', 'nonprofitsuite' ); ?>
				</button>
			</div>

			<?php if ( ! empty( $state_operations ) && ! is_wp_error( $state_operations ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'State', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Operation Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Registration #', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Annual Report Due', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Requirements', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $state_operations as $operation ) : ?>
							<?php
							$requirements = NonprofitSuite_State_Compliance::get_requirements( $operation->id );
							$pending_count = count( array_filter( $requirements, function( $r ) {
								return $r->status !== 'completed';
							} ) );
							?>
							<tr>
								<td><strong><?php echo esc_html( $operation->state_name ); ?></strong><br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( $operation->state_code ); ?></span></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $operation->operation_type ) ) ); ?></td>
								<td><?php echo $operation->registration_number ? esc_html( $operation->registration_number ) : '<span class="ns-text-muted">-</span>'; ?></td>
								<td>
									<?php
									$status_colors = array( 'active' => '#10b981', 'inactive' => '#6b7280', 'pending' => '#f59e0b' );
									$status_color = isset( $status_colors[ $operation->status ] ) ? $status_colors[ $operation->status ] : '#6b7280';
									?>
									<span style="color: <?php echo esc_attr( $status_color ); ?>; font-weight: 600;">
										<?php echo esc_html( ucfirst( $operation->status ) ); ?>
									</span>
								</td>
								<td><?php echo $operation->annual_report_due ? esc_html( date( 'M j', strtotime( $operation->annual_report_due ) ) ) : '<span class="ns-text-muted">-</span>'; ?></td>
								<td>
									<?php echo count( $requirements ); ?> <?php esc_html_e( 'total', 'nonprofitsuite' ); ?>
									<?php if ( $pending_count > 0 ) : ?>
										<br><span style="color: #f59e0b; font-weight: 600;"><?php echo $pending_count; ?> <?php esc_html_e( 'pending', 'nonprofitsuite' ); ?></span>
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
				<p><?php esc_html_e( 'No state operations yet. Add states where your organization operates to track compliance requirements.', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<!-- Requirements Tab -->

		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'All Compliance Requirements', 'nonprofitsuite' ); ?></h2>

			<?php
			$all_requirements = NonprofitSuite_State_Compliance::get_pending_requirements();
			?>

			<?php if ( ! empty( $all_requirements ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'State', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Requirement', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Due Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_requirements as $requirement ) : ?>
							<?php
							$is_overdue = $requirement->due_date && strtotime( $requirement->due_date ) < time();
							?>
							<tr>
								<td><?php echo esc_html( $requirement->state_code ); ?></td>
								<td>
									<strong><?php echo esc_html( $requirement->requirement_name ); ?></strong>
									<?php if ( $requirement->agency ) : ?>
										<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( $requirement->agency ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $requirement->requirement_type ) ) ); ?></td>
								<td><?php echo $requirement->frequency ? esc_html( ucfirst( $requirement->frequency ) ) : '<span class="ns-text-muted">-</span>'; ?></td>
								<td>
									<?php if ( $requirement->due_date ) : ?>
										<span class="<?php echo $is_overdue ? 'ns-text-danger' : ''; ?>">
											<?php echo esc_html( date( 'M j, Y', strtotime( $requirement->due_date ) ) ); ?>
											<?php if ( $is_overdue ) : ?>
												<br><strong><?php esc_html_e( 'OVERDUE', 'nonprofitsuite' ); ?></strong>
											<?php endif; ?>
										</span>
									<?php else : ?>
										<span class="ns-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$req_status_colors = array( 'pending' => '#f59e0b', 'in_progress' => '#2563eb', 'completed' => '#10b981' );
									$req_color = isset( $req_status_colors[ $requirement->status ] ) ? $req_status_colors[ $requirement->status ] : '#6b7280';
									?>
									<span style="color: <?php echo esc_attr( $req_color ); ?>; font-weight: 600;">
										<?php echo esc_html( ucfirst( str_replace( '_', ' ', $requirement->status ) ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( $requirement->website_url ) : ?>
										<a href="<?php echo esc_url( $requirement->website_url ); ?>" target="_blank" class="ns-button ns-button-sm">
											<?php esc_html_e( 'File Online', 'nonprofitsuite' ); ?>
										</a>
									<?php endif; ?>
									<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
										<?php esc_html_e( 'Update', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No pending requirements. All compliance items are up to date!', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
