<?php
/**
 * Volunteers (PRO) View - Full Featured Interface
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get data
$volunteers = NonprofitSuite_Volunteers::get_volunteers();
$dashboard_data = NonprofitSuite_Volunteers::get_dashboard_data();

// Active tab
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
$volunteer_id = isset( $_GET['volunteer_id'] ) ? intval( $_GET['volunteer_id'] ) : 0;

// If viewing a specific volunteer
$volunteer_detail = null;
if ( $volunteer_id ) {
	$volunteer_detail = NonprofitSuite_Volunteers::get_volunteer( $volunteer_id );
	$volunteer_hours = NonprofitSuite_Volunteers::get_volunteer_hours( $volunteer_id );
}
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Volunteer Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! $volunteer_id ) : ?>
		<!-- Quick Actions -->
		<div class="ns-actions-bar">
			<button class="ns-button ns-button-primary" id="ns-add-volunteer">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e( 'Add Volunteer', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button ns-button-secondary" id="ns-log-hours">
				<span class="dashicons dashicons-clock"></span>
				<?php esc_html_e( 'Log Hours', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button ns-button-secondary" id="ns-export-volunteers">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<!-- Tab Navigation -->
		<nav class="ns-tabs">
			<a href="?page=nonprofitsuite-volunteers&tab=dashboard" class="ns-tab <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Dashboard', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-volunteers&tab=volunteers" class="ns-tab <?php echo $active_tab === 'volunteers' ? 'active' : ''; ?>">
				<?php esc_html_e( 'All Volunteers', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-volunteers&tab=hours" class="ns-tab <?php echo $active_tab === 'hours' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Hour Logs', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-volunteers&tab=schedule" class="ns-tab <?php echo $active_tab === 'schedule' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Schedule', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-volunteers&tab=check-in" class="ns-tab <?php echo $active_tab === 'check-in' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Check-In', 'nonprofitsuite' ); ?>
			</a>
		</nav>

		<!-- Tab: Dashboard -->
		<?php if ( $active_tab === 'dashboard' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-dashboard-grid">
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-groups"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Active Volunteers', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value"><?php echo number_format( $dashboard_data['active_volunteers'] ?? 0 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-clock"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Total Hours (YTD)', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value success"><?php echo number_format( $dashboard_data['total_hours'] ?? 0, 1 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Avg Hours/Volunteer', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value"><?php echo number_format( $dashboard_data['avg_hours'] ?? 0, 1 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Value (@ $28.54/hr)', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value">$<?php echo number_format( ( $dashboard_data['total_hours'] ?? 0 ) * 28.54, 2 ); ?></div>
					</div>
				</div>

				<!-- Recent Hour Logs -->
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Recent Hour Logs', 'nonprofitsuite' ); ?></h2>
						<a href="?page=nonprofitsuite-volunteers&tab=hours" class="ns-button-small"><?php esc_html_e( 'View All', 'nonprofitsuite' ); ?></a>
					</div>

					<?php
					$recent_hours = NonprofitSuite_Volunteers::get_recent_hour_logs( 10 );
					if ( ! empty( $recent_hours ) && ! is_wp_error( $recent_hours ) ) : ?>
						<table class="ns-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Volunteer', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Activity', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Hours', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_hours as $log ) : ?>
									<tr>
										<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $log->log_date ) ); ?></td>
										<td>
											<a href="?page=nonprofitsuite-volunteers&volunteer_id=<?php echo esc_attr( $log->volunteer_id ); ?>">
												<?php echo esc_html( $log->volunteer_name ?? 'Volunteer #' . $log->volunteer_id ); ?>
											</a>
										</td>
										<td><?php echo esc_html( $log->activity ); ?></td>
										<td><strong><?php echo number_format( $log->hours, 1 ); ?> hrs</strong></td>
										<td><?php echo NonprofitSuite_Utilities::get_status_badge( $log->approval_status ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No volunteer hours logged yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: All Volunteers -->
		<?php if ( $active_tab === 'volunteers' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'All Volunteers', 'nonprofitsuite' ); ?></h2>
					</div>

					<?php if ( ! empty( $volunteers ) && ! is_wp_error( $volunteers ) ) : ?>
						<table class="ns-table ns-table-hover">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Volunteer', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Contact', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Skills', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Total Hours', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Last Activity', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $volunteers as $volunteer ) : ?>
									<tr>
										<td>
											<strong>
												<a href="?page=nonprofitsuite-volunteers&volunteer_id=<?php echo esc_attr( $volunteer->id ); ?>">
													<?php echo esc_html( $volunteer->volunteer_name ?? 'Volunteer #' . $volunteer->id ); ?>
												</a>
											</strong>
										</td>
										<td>
											<?php if ( ! empty( $volunteer->email ) ) : ?>
												<a href="mailto:<?php echo esc_attr( $volunteer->email ); ?>"><?php echo esc_html( $volunteer->email ); ?></a>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $volunteer->skills ?? '—' ); ?></td>
										<td><strong><?php echo number_format( $volunteer->total_hours, 1 ); ?> hrs</strong></td>
										<td><?php echo $volunteer->last_activity_date ? esc_html( NonprofitSuite_Utilities::format_date( $volunteer->last_activity_date ) ) : '—'; ?></td>
										<td><?php echo NonprofitSuite_Utilities::get_status_badge( $volunteer->volunteer_status ); ?></td>
										<td>
											<a href="?page=nonprofitsuite-volunteers&volunteer_id=<?php echo esc_attr( $volunteer->id ); ?>" class="ns-button-icon" title="<?php esc_attr_e( 'View Details', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-visibility"></span>
											</a>
											<button class="ns-button-icon ns-edit-volunteer" data-id="<?php echo esc_attr( $volunteer->id ); ?>" title="<?php esc_attr_e( 'Edit', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-edit"></span>
											</button>
											<button class="ns-button-icon ns-quick-log-hours" data-id="<?php echo esc_attr( $volunteer->id ); ?>" title="<?php esc_attr_e( 'Log Hours', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-clock"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No volunteers found. Add your first volunteer to get started!', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Hour Logs -->
		<?php if ( $active_tab === 'hours' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Volunteer Hour Logs', 'nonprofitsuite' ); ?></h2>
						<button class="ns-button ns-button-primary" id="ns-log-hours-inline">
							<?php esc_html_e( 'Log Hours', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<?php
					$all_hours = NonprofitSuite_Volunteers::get_recent_hour_logs( 100 );
					if ( ! empty( $all_hours ) && ! is_wp_error( $all_hours ) ) : ?>
						<table class="ns-table ns-table-hover">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Volunteer', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Activity', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Hours', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Supervisor', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $all_hours as $log ) : ?>
									<tr>
										<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $log->log_date ) ); ?></td>
										<td>
											<a href="?page=nonprofitsuite-volunteers&volunteer_id=<?php echo esc_attr( $log->volunteer_id ); ?>">
												<?php echo esc_html( $log->volunteer_name ?? 'Volunteer #' . $log->volunteer_id ); ?>
											</a>
										</td>
										<td><?php echo esc_html( $log->activity ); ?></td>
										<td><strong><?php echo number_format( $log->hours, 1 ); ?> hrs</strong></td>
										<td><?php echo esc_html( $log->supervisor_name ?? '—' ); ?></td>
										<td>
											<?php if ( $log->approval_status === 'pending' ) : ?>
												<button class="ns-button-small ns-approve-hours" data-id="<?php echo esc_attr( $log->id ); ?>">
													<?php esc_html_e( 'Approve', 'nonprofitsuite' ); ?>
												</button>
											<?php else : ?>
												<?php echo NonprofitSuite_Utilities::get_status_badge( $log->approval_status ); ?>
											<?php endif; ?>
										</td>
										<td>
											<button class="ns-button-icon ns-edit-hours" data-id="<?php echo esc_attr( $log->id ); ?>">
												<span class="dashicons dashicons-edit"></span>
											</button>
											<button class="ns-button-icon ns-delete-hours" data-id="<?php echo esc_attr( $log->id ); ?>">
												<span class="dashicons dashicons-trash"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No hours logged yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Schedule -->
		<?php if ( $active_tab === 'schedule' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Volunteer Schedule', 'nonprofitsuite' ); ?></h2>
						<button class="ns-button ns-button-primary"><?php esc_html_e( 'Create Shift', 'nonprofitsuite' ); ?></button>
					</div>

					<p class="ns-help-text"><?php esc_html_e( 'Manage volunteer shifts and schedules', 'nonprofitsuite' ); ?></p>

					<div class="ns-empty-state">
						<p><?php esc_html_e( 'No shifts scheduled yet.', 'nonprofitsuite' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Check-In -->
		<?php if ( $active_tab === 'check-in' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Volunteer Check-In', 'nonprofitsuite' ); ?></h2>
					</div>

					<p class="ns-help-text"><?php esc_html_e( 'Quick check-in system for volunteer activities', 'nonprofitsuite' ); ?></p>

					<div class="ns-checkin-form">
						<div class="ns-form-group">
							<label><?php esc_html_e( 'Select Volunteer', 'nonprofitsuite' ); ?></label>
							<select id="ns-checkin-volunteer" class="ns-input-large">
								<option value=""><?php esc_html_e( 'Select Volunteer', 'nonprofitsuite' ); ?></option>
								<?php if ( ! empty( $volunteers ) ) : foreach ( $volunteers as $volunteer ) : ?>
									<option value="<?php echo esc_attr( $volunteer->id ); ?>">
										<?php echo esc_html( $volunteer->volunteer_name ?? 'Volunteer #' . $volunteer->id ); ?>
									</option>
								<?php endforeach; endif; ?>
							</select>
						</div>

						<div class="ns-form-group">
							<label><?php esc_html_e( 'Activity', 'nonprofitsuite' ); ?></label>
							<input type="text" id="ns-checkin-activity" class="ns-input-large" placeholder="<?php esc_attr_e( 'What are they doing?', 'nonprofitsuite' ); ?>">
						</div>

						<button id="ns-do-checkin" class="ns-button ns-button-primary ns-button-large">
							<?php esc_html_e( 'Check In', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<div id="ns-checkin-result" style="margin-top: 20px;"></div>
				</div>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<!-- Volunteer Detail View -->
		<?php if ( $volunteer_detail ) : ?>
			<div class="ns-detail-view">
				<div class="ns-detail-header">
					<a href="?page=nonprofitsuite-volunteers&tab=volunteers" class="ns-button ns-button-text">
						<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back to Volunteers', 'nonprofitsuite' ); ?>
					</a>
					<h2><?php echo esc_html( $volunteer_detail->volunteer_name ?? 'Volunteer #' . $volunteer_detail->id ); ?></h2>
					<div class="ns-detail-actions">
						<button class="ns-button ns-button-primary ns-quick-log-hours" data-id="<?php echo esc_attr( $volunteer_detail->id ); ?>">
							<?php esc_html_e( 'Log Hours', 'nonprofitsuite' ); ?>
						</button>
						<button class="ns-button ns-button-secondary ns-edit-volunteer" data-id="<?php echo esc_attr( $volunteer_detail->id ); ?>">
							<?php esc_html_e( 'Edit Volunteer', 'nonprofitsuite' ); ?>
						</button>
					</div>
				</div>

				<div class="ns-detail-grid">
					<div class="ns-card">
						<h3><?php esc_html_e( 'Volunteer Information', 'nonprofitsuite' ); ?></h3>
						<table class="ns-detail-table">
							<tr>
								<th><?php esc_html_e( 'Email:', 'nonprofitsuite' ); ?></th>
								<td><?php echo $volunteer_detail->email ? '<a href="mailto:' . esc_attr( $volunteer_detail->email ) . '">' . esc_html( $volunteer_detail->email ) . '</a>' : '—'; ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Phone:', 'nonprofitsuite' ); ?></th>
								<td><?php echo esc_html( $volunteer_detail->phone ?? '—' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Skills:', 'nonprofitsuite' ); ?></th>
								<td><?php echo esc_html( $volunteer_detail->skills ?? '—' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Availability:', 'nonprofitsuite' ); ?></th>
								<td><?php echo esc_html( $volunteer_detail->availability ?? '—' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Status:', 'nonprofitsuite' ); ?></th>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $volunteer_detail->volunteer_status ); ?></td>
							</tr>
						</table>
					</div>

					<div class="ns-card">
						<h3><?php esc_html_e( 'Service Summary', 'nonprofitsuite' ); ?></h3>
						<div class="ns-summary-stats">
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Total Hours', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value"><?php echo number_format( $volunteer_detail->total_hours, 1 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'This Year', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value"><?php echo number_format( $volunteer_detail->ytd_hours ?? 0, 1 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Value ($28.54/hr)', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value">$<?php echo number_format( $volunteer_detail->total_hours * 28.54, 2 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Last Activity', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value">
									<?php echo $volunteer_detail->last_activity_date ? esc_html( NonprofitSuite_Utilities::format_date( $volunteer_detail->last_activity_date ) ) : '—'; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Activity History -->
				<div class="ns-card">
					<h3><?php esc_html_e( 'Activity History', 'nonprofitsuite' ); ?></h3>
					<?php if ( ! empty( $volunteer_hours ) && ! is_wp_error( $volunteer_hours ) ) : ?>
						<table class="ns-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Activity', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Hours', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Supervisor', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $volunteer_hours as $log ) : ?>
									<tr>
										<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $log->log_date ) ); ?></td>
										<td><?php echo esc_html( $log->activity ); ?></td>
										<td><strong><?php echo number_format( $log->hours, 1 ); ?> hrs</strong></td>
										<td><?php echo esc_html( $log->supervisor_name ?? '—' ); ?></td>
										<td><?php echo NonprofitSuite_Utilities::get_status_badge( $log->approval_status ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No activities logged for this volunteer yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="ns-card">
				<p class="ns-empty-state"><?php esc_html_e( 'Volunteer not found.', 'nonprofitsuite' ); ?></p>
				<a href="?page=nonprofitsuite-volunteers&tab=volunteers" class="ns-button ns-button-primary"><?php esc_html_e( 'Back to Volunteers', 'nonprofitsuite' ); ?></a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<!-- Modal: Add/Edit Volunteer -->
<div id="ns-volunteer-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2><?php esc_html_e( 'Add Volunteer', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-volunteer-form">
			<input type="hidden" name="action" value="ns_save_volunteer">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_volunteer_save' ); ?>">

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Volunteer Name', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="text" name="volunteer_name" required>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Email', 'nonprofitsuite' ); ?></label>
					<input type="email" name="email">
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Phone', 'nonprofitsuite' ); ?></label>
					<input type="tel" name="phone">
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
					<select name="volunteer_status">
						<option value="active"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></option>
						<option value="inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Skills', 'nonprofitsuite' ); ?></label>
				<input type="text" name="skills" placeholder="<?php esc_attr_e( 'e.g., Event planning, Graphics, Fundraising', 'nonprofitsuite' ); ?>">
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Availability', 'nonprofitsuite' ); ?></label>
				<textarea name="availability" rows="2" placeholder="<?php esc_attr_e( 'e.g., Weekends, Evenings', 'nonprofitsuite' ); ?>"></textarea>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Volunteer', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Modal: Log Hours -->
<div id="ns-hours-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2><?php esc_html_e( 'Log Volunteer Hours', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-hours-form">
			<input type="hidden" name="action" value="ns_save_hours">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_hours_save' ); ?>">

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Volunteer', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<select name="volunteer_id" id="hours-volunteer" required>
						<option value=""><?php esc_html_e( 'Select Volunteer', 'nonprofitsuite' ); ?></option>
						<?php if ( ! empty( $volunteers ) ) : foreach ( $volunteers as $volunteer ) : ?>
							<option value="<?php echo esc_attr( $volunteer->id ); ?>">
								<?php echo esc_html( $volunteer->volunteer_name ?? 'Volunteer #' . $volunteer->id ); ?>
							</option>
						<?php endforeach; endif; ?>
					</select>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="date" name="log_date" id="hours-date" required>
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Hours', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="number" step="0.5" name="hours" placeholder="0.0" required>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Supervisor', 'nonprofitsuite' ); ?></label>
					<input type="text" name="supervisor_name">
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Activity', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
				<input type="text" name="activity" required>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Notes', 'nonprofitsuite' ); ?></label>
				<textarea name="notes" rows="3"></textarea>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Log Hours', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#ns-add-volunteer, .ns-edit-volunteer').on('click', function() {
		$('#ns-volunteer-form')[0].reset();
		$('#ns-volunteer-modal').fadeIn();
	});

	$('#ns-log-hours, #ns-log-hours-inline, .ns-quick-log-hours').on('click', function() {
		var volunteerId = $(this).data('id');
		$('#ns-hours-form')[0].reset();
		$('#hours-date').val(new Date().toISOString().split('T')[0]);
		if (volunteerId) {
			$('#hours-volunteer').val(volunteerId);
		}
		$('#ns-hours-modal').fadeIn();
	});

	$('.ns-modal-close').on('click', function() {
		$(this).closest('.ns-modal').fadeOut();
	});

	$('.ns-modal').on('click', function(e) {
		if ($(e.target).hasClass('ns-modal')) {
			$(this).fadeOut();
		}
	});

	$('#ns-volunteer-form, #ns-hours-form').on('submit', function(e) {
		e.preventDefault();
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: $(this).serialize(),
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Saved successfully!', 'nonprofitsuite' ); ?>');
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error saving', 'nonprofitsuite' ); ?>');
				}
			}
		});
	});

	$('#ns-do-checkin').on('click', function() {
		var volunteerId = $('#ns-checkin-volunteer').val();
		var activity = $('#ns-checkin-activity').val();

		if (!volunteerId || !activity) {
			alert('<?php esc_html_e( 'Please select volunteer and enter activity', 'nonprofitsuite' ); ?>');
			return;
		}

		$(this).text('<?php esc_html_e( 'Checking in...', 'nonprofitsuite' ); ?>').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_volunteer_checkin',
				volunteer_id: volunteerId,
				activity: activity,
				nonce: '<?php echo wp_create_nonce( 'ns_checkin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					$('#ns-checkin-result').html('<div class="ns-success-message">✓ ' + response.data.message + '</div>');
					$('#ns-checkin-volunteer').val('');
					$('#ns-checkin-activity').val('');
				} else {
					$('#ns-checkin-result').html('<div class="ns-error-message">✗ ' + (response.data.message || '<?php esc_html_e( 'Error during check-in', 'nonprofitsuite' ); ?>') + '</div>');
				}
				$('#ns-do-checkin').text('<?php esc_html_e( 'Check In', 'nonprofitsuite' ); ?>').prop('disabled', false);
			}
		});
	});

	$('.ns-approve-hours').on('click', function() {
		var logId = $(this).data('id');
		if (!confirm('<?php esc_html_e( 'Approve these hours?', 'nonprofitsuite' ); ?>')) return;

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_approve_hours',
				log_id: logId,
				nonce: '<?php echo wp_create_nonce( 'ns_hours_approve' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				}
			}
		});
	});
});
</script>

<style>
.ns-checkin-form { max-width: 500px; margin: 20px auto; text-align: center; }
.ns-input-large { width: 100%; padding: 10px; font-size: 16px; border: 1px solid #dcdcde; border-radius: 4px; }
.ns-button-large { padding: 15px 30px; font-size: 16px; }
.ns-success-message { padding: 15px; background: #d7f8e0; color: #00593d; border-radius: 4px; margin-top: 15px; }
.ns-error-message { padding: 15px; background: #ffe0e0; color: #8b0000; border-radius: 4px; margin-top: 15px; }
</style>
