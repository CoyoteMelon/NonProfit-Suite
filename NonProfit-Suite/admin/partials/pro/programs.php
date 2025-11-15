<?php
/**
 * Programs (PRO) View - Full Featured Interface
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$programs = NonprofitSuite_Programs::get_programs();
$dashboard_data = NonprofitSuite_Programs::get_dashboard_data();
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
$program_id = isset( $_GET['program_id'] ) ? intval( $_GET['program_id'] ) : 0;

// If viewing a specific program
$program_detail = null;
if ( $program_id ) {
	$program_detail = NonprofitSuite_Programs::get_program( $program_id );
	$program_participants = NonprofitSuite_Programs::get_program_participants( $program_id );
}
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Programs & Operations', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! $program_id ) : ?>
		<!-- Quick Actions -->
		<div class="ns-actions-bar">
			<button class="ns-button ns-button-primary" id="ns-add-program">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e( 'Add Program', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button ns-button-secondary" id="ns-export-programs">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<!-- Tab Navigation -->
		<nav class="ns-tabs">
			<a href="?page=nonprofitsuite-programs&tab=dashboard" class="ns-tab <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Dashboard', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-programs&tab=programs" class="ns-tab <?php echo $active_tab === 'programs' ? 'active' : ''; ?>">
				<?php esc_html_e( 'All Programs', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-programs&tab=enrollment" class="ns-tab <?php echo $active_tab === 'enrollment' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Enrollment', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-programs&tab=attendance" class="ns-tab <?php echo $active_tab === 'attendance' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Attendance', 'nonprofitsuite' ); ?>
			</a>
		</nav>

		<!-- Tab: Dashboard -->
		<?php if ( $active_tab === 'dashboard' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-dashboard-grid">
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-admin-multisite"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Active Programs', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value"><?php echo number_format( $dashboard_data['active_programs'] ?? 0 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-groups"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Total Participants', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value success"><?php echo number_format( $dashboard_data['total_participants'] ?? 0 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Avg Attendance Rate', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value"><?php echo number_format( $dashboard_data['avg_attendance'] ?? 0, 1 ); ?>%</div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Total Budget', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value">$<?php echo number_format( $dashboard_data['total_budget'] ?? 0, 2 ); ?></div>
					</div>
				</div>

				<!-- Program Overview -->
				<div class="ns-card">
					<h2 class="ns-card-title"><?php esc_html_e( 'Program Overview', 'nonprofitsuite' ); ?></h2>
					<?php if ( ! empty( $programs ) && ! is_wp_error( $programs ) ) : ?>
						<div class="ns-program-grid">
							<?php foreach ( array_slice( $programs, 0, 6 ) as $program ) : ?>
								<div class="ns-program-card">
									<div class="ns-program-header">
										<h3><a href="?page=nonprofitsuite-programs&program_id=<?php echo esc_attr( $program->id ); ?>"><?php echo esc_html( $program->program_name ); ?></a></h3>
										<span class="ns-badge ns-badge-<?php echo esc_attr( $program->status ); ?>"><?php echo esc_html( ucfirst( $program->status ) ); ?></span>
									</div>
									<div class="ns-program-meta">
										<div class="ns-program-stat">
											<span class="dashicons dashicons-groups"></span>
											<strong><?php echo number_format( $program->participant_count ?? 0 ); ?></strong> participants
										</div>
										<div class="ns-program-stat">
											<span class="dashicons dashicons-money-alt"></span>
											<strong>$<?php echo number_format( $program->budget, 0 ); ?></strong> budget
										</div>
									</div>
									<p class="ns-program-type"><?php echo esc_html( ucfirst( $program->program_type ) ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
						<div style="text-align: center; margin-top: 20px;">
							<a href="?page=nonprofitsuite-programs&tab=programs" class="ns-button ns-button-secondary"><?php esc_html_e( 'View All Programs', 'nonprofitsuite' ); ?></a>
						</div>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No programs found.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: All Programs -->
		<?php if ( $active_tab === 'programs' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'All Programs', 'nonprofitsuite' ); ?></h2>
					</div>

					<?php if ( ! empty( $programs ) && ! is_wp_error( $programs ) ) : ?>
						<table class="ns-table ns-table-hover">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Program Name', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Participants', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Budget', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $programs as $program ) : ?>
									<tr>
										<td>
											<strong>
												<a href="?page=nonprofitsuite-programs&program_id=<?php echo esc_attr( $program->id ); ?>">
													<?php echo esc_html( $program->program_name ); ?>
												</a>
											</strong>
										</td>
										<td><?php echo esc_html( ucfirst( $program->program_type ) ); ?></td>
										<td><?php echo number_format( $program->participant_count ?? 0 ); ?></td>
										<td><strong>$<?php echo number_format( $program->budget, 2 ); ?></strong></td>
										<td><?php echo $program->start_date ? esc_html( NonprofitSuite_Utilities::format_date( $program->start_date ) ) : '—'; ?></td>
										<td><?php echo NonprofitSuite_Utilities::get_status_badge( $program->status ); ?></td>
										<td>
											<a href="?page=nonprofitsuite-programs&program_id=<?php echo esc_attr( $program->id ); ?>" class="ns-button-icon" title="<?php esc_attr_e( 'View Details', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-visibility"></span>
											</a>
											<button class="ns-button-icon ns-edit-program" data-id="<?php echo esc_attr( $program->id ); ?>" title="<?php esc_attr_e( 'Edit', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-edit"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No programs found. Add your first program to get started!', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Enrollment -->
		<?php if ( $active_tab === 'enrollment' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Program Enrollment', 'nonprofitsuite' ); ?></h2>
						<button class="ns-button ns-button-primary" id="ns-enroll-participant">
							<?php esc_html_e( 'Enroll Participant', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<p class="ns-help-text"><?php esc_html_e( 'Manage participant enrollment across all programs', 'nonprofitsuite' ); ?></p>

					<div class="ns-empty-state">
						<p><?php esc_html_e( 'No enrollment data available.', 'nonprofitsuite' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Attendance -->
		<?php if ( $active_tab === 'attendance' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Attendance Tracking', 'nonprofitsuite' ); ?></h2>
						<button class="ns-button ns-button-primary" id="ns-take-attendance">
							<?php esc_html_e( 'Take Attendance', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<p class="ns-help-text"><?php esc_html_e( 'Track attendance for program sessions', 'nonprofitsuite' ); ?></p>

					<div class="ns-empty-state">
						<p><?php esc_html_e( 'No attendance records yet.', 'nonprofitsuite' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<!-- Program Detail View -->
		<?php if ( $program_detail ) : ?>
			<div class="ns-detail-view">
				<div class="ns-detail-header">
					<a href="?page=nonprofitsuite-programs&tab=programs" class="ns-button ns-button-text">
						<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back to Programs', 'nonprofitsuite' ); ?>
					</a>
					<h2><?php echo esc_html( $program_detail->program_name ); ?></h2>
					<div class="ns-detail-actions">
						<button class="ns-button ns-button-primary" id="ns-enroll-participant-detail">
							<?php esc_html_e( 'Enroll Participant', 'nonprofitsuite' ); ?>
						</button>
						<button class="ns-button ns-button-secondary ns-edit-program" data-id="<?php echo esc_attr( $program_detail->id ); ?>">
							<?php esc_html_e( 'Edit Program', 'nonprofitsuite' ); ?>
						</button>
					</div>
				</div>

				<div class="ns-detail-grid">
					<div class="ns-card">
						<h3><?php esc_html_e( 'Program Information', 'nonprofitsuite' ); ?></h3>
						<table class="ns-detail-table">
							<tr>
								<th><?php esc_html_e( 'Type:', 'nonprofitsuite' ); ?></th>
								<td><?php echo esc_html( ucfirst( $program_detail->program_type ) ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Budget:', 'nonprofitsuite' ); ?></th>
								<td>$<?php echo number_format( $program_detail->budget, 2 ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Start Date:', 'nonprofitsuite' ); ?></th>
								<td><?php echo $program_detail->start_date ? esc_html( NonprofitSuite_Utilities::format_date( $program_detail->start_date ) ) : '—'; ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'End Date:', 'nonprofitsuite' ); ?></th>
								<td><?php echo $program_detail->end_date ? esc_html( NonprofitSuite_Utilities::format_date( $program_detail->end_date ) ) : '—'; ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Status:', 'nonprofitsuite' ); ?></th>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $program_detail->status ); ?></td>
							</tr>
						</table>
					</div>

					<div class="ns-card">
						<h3><?php esc_html_e( 'Program Statistics', 'nonprofitsuite' ); ?></h3>
						<div class="ns-summary-stats">
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Participants', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value"><?php echo number_format( $program_detail->participant_count ?? 0 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Capacity', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value"><?php echo number_format( $program_detail->capacity ?? 0 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Attendance Rate', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value"><?php echo number_format( $program_detail->attendance_rate ?? 0, 1 ); ?>%</div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Total Sessions', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value"><?php echo number_format( $program_detail->session_count ?? 0 ); ?></div>
							</div>
						</div>
					</div>
				</div>

				<!-- Participants -->
				<div class="ns-card">
					<h3><?php esc_html_e( 'Enrolled Participants', 'nonprofitsuite' ); ?></h3>
					<?php if ( ! empty( $program_participants ) && ! is_wp_error( $program_participants ) ) : ?>
						<table class="ns-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Participant Name', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Enrollment Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Sessions Attended', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Attendance Rate', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $program_participants as $participant ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $participant->participant_name ); ?></strong></td>
										<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $participant->enrollment_date ) ); ?></td>
										<td><?php echo number_format( $participant->sessions_attended ?? 0 ); ?></td>
										<td><?php echo number_format( $participant->attendance_rate ?? 0, 1 ); ?>%</td>
										<td><?php echo NonprofitSuite_Utilities::get_status_badge( $participant->status ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No participants enrolled yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="ns-card">
				<p class="ns-empty-state"><?php esc_html_e( 'Program not found.', 'nonprofitsuite' ); ?></p>
				<a href="?page=nonprofitsuite-programs&tab=programs" class="ns-button ns-button-primary"><?php esc_html_e( 'Back to Programs', 'nonprofitsuite' ); ?></a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<!-- Modal: Add/Edit Program -->
<div id="ns-program-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2><?php esc_html_e( 'Add Program', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-program-form">
			<input type="hidden" name="action" value="ns_save_program">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_program_save' ); ?>">

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Program Name', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
				<input type="text" name="program_name" required>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Program Type', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<select name="program_type" required>
						<option value=""><?php esc_html_e( 'Select Type', 'nonprofitsuite' ); ?></option>
						<option value="education"><?php esc_html_e( 'Education', 'nonprofitsuite' ); ?></option>
						<option value="health"><?php esc_html_e( 'Health', 'nonprofitsuite' ); ?></option>
						<option value="youth"><?php esc_html_e( 'Youth Services', 'nonprofitsuite' ); ?></option>
						<option value="community"><?php esc_html_e( 'Community Services', 'nonprofitsuite' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Budget', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="number" step="0.01" name="budget" placeholder="0.00" required>
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></label>
					<input type="date" name="start_date">
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'End Date', 'nonprofitsuite' ); ?></label>
					<input type="date" name="end_date">
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Capacity', 'nonprofitsuite' ); ?></label>
					<input type="number" name="capacity" placeholder="<?php esc_attr_e( 'Max participants', 'nonprofitsuite' ); ?>">
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
					<select name="status">
						<option value="active"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></option>
						<option value="planning"><?php esc_html_e( 'Planning', 'nonprofitsuite' ); ?></option>
						<option value="completed"><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></option>
						<option value="inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></label>
				<textarea name="program_description" rows="3"></textarea>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Program', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#ns-add-program, .ns-edit-program').on('click', function() {
		$('#ns-program-form')[0].reset();
		$('#ns-program-modal').fadeIn();
	});

	$('.ns-modal-close').on('click', function() {
		$(this).closest('.ns-modal').fadeOut();
	});

	$('.ns-modal').on('click', function(e) {
		if ($(e.target).hasClass('ns-modal')) {
			$(this).fadeOut();
		}
	});

	$('#ns-program-form').on('submit', function(e) {
		e.preventDefault();
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: $(this).serialize(),
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Program saved successfully!', 'nonprofitsuite' ); ?>');
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error saving program', 'nonprofitsuite' ); ?>');
				}
			}
		});
	});

	$('#ns-export-programs').on('click', function() {
		var format = prompt('<?php esc_html_e( 'Export format (csv/pdf/excel):', 'nonprofitsuite' ); ?>', 'csv');
		if (format) {
			window.location.href = '?page=nonprofitsuite-programs&action=export&format=' + format;
		}
	});
});
</script>

<style>
.ns-program-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
.ns-program-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; transition: box-shadow 0.2s; }
.ns-program-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.ns-program-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
.ns-program-header h3 { margin: 0; font-size: 18px; }
.ns-program-header h3 a { text-decoration: none; color: #2271b1; }
.ns-program-header h3 a:hover { color: #135e96; }
.ns-program-meta { display: flex; gap: 20px; margin-bottom: 10px; }
.ns-program-stat { display: flex; align-items: center; gap: 5px; color: #646970; font-size: 14px; }
.ns-program-stat .dashicons { font-size: 16px; }
.ns-program-type { margin: 0; padding: 5px 10px; background: #f6f7f7; border-radius: 4px; display: inline-block; font-size: 12px; color: #646970; }
</style>
