<?php
/**
 * Grants (PRO) View - Full Featured Interface
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$grants = NonprofitSuite_Grants::get_grants();
$dashboard_data = NonprofitSuite_Grants::get_dashboard_data();
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Grant Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<!-- Quick Actions -->
	<div class="ns-actions-bar">
		<button class="ns-button ns-button-primary" id="ns-add-grant">
			<span class="dashicons dashicons-plus-alt"></span>
			<?php esc_html_e( 'Add Grant', 'nonprofitsuite' ); ?>
		</button>
		<button class="ns-button ns-button-secondary" id="ns-export-grants">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Export', 'nonprofitsuite' ); ?>
		</button>
	</div>

	<!-- Tab Navigation -->
	<nav class="ns-tabs">
		<a href="?page=nonprofitsuite-grants&tab=dashboard" class="ns-tab <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Dashboard', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-grants&tab=pipeline" class="ns-tab <?php echo $active_tab === 'pipeline' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Grant Pipeline', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-grants&tab=awarded" class="ns-tab <?php echo $active_tab === 'awarded' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Awarded Grants', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-grants&tab=reporting" class="ns-tab <?php echo $active_tab === 'reporting' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Reporting', 'nonprofitsuite' ); ?>
		</a>
	</nav>

	<!-- Tab: Dashboard -->
	<?php if ( $active_tab === 'dashboard' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-dashboard-grid">
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Total Awarded (YTD)', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value success">$<?php echo number_format( $dashboard_data['total_awarded'] ?? 0, 2 ); ?></div>
				</div>
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-chart-area"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Pending Applications', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value"><?php echo number_format( $dashboard_data['pending_count'] ?? 0 ); ?></div>
				</div>
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Success Rate', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value"><?php echo number_format( $dashboard_data['success_rate'] ?? 0, 1 ); ?>%</div>
				</div>
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Upcoming Deadlines', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value warning"><?php echo number_format( $dashboard_data['upcoming_deadlines'] ?? 0 ); ?></div>
				</div>
			</div>

			<!-- Grants by Status -->
			<div class="ns-card">
				<h2 class="ns-card-title"><?php esc_html_e( 'Grants by Status', 'nonprofitsuite' ); ?></h2>
				<?php
				$status_grants = array(
					'prospect' => NonprofitSuite_Grants::get_grants( array( 'status' => 'prospect' ) ),
					'in_progress' => NonprofitSuite_Grants::get_grants( array( 'status' => 'in_progress' ) ),
					'submitted' => NonprofitSuite_Grants::get_grants( array( 'status' => 'submitted' ) ),
					'awarded' => NonprofitSuite_Grants::get_grants( array( 'status' => 'awarded' ) ),
					'rejected' => NonprofitSuite_Grants::get_grants( array( 'status' => 'rejected' ) ),
				);
				?>
				<div class="ns-status-grid">
					<?php foreach ( array( 'prospect', 'in_progress', 'submitted', 'awarded', 'rejected' ) as $status ) : ?>
						<div class="ns-status-column">
							<div class="ns-status-header ns-status-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?>
								<span class="ns-status-count"><?php echo count( $status_grants[ $status ] ?? array() ); ?></span>
							</div>
							<div class="ns-status-items">
								<?php if ( ! empty( $status_grants[ $status ] ) ) : foreach ( array_slice( $status_grants[ $status ], 0, 3 ) as $grant ) : ?>
									<div class="ns-status-item">
										<strong><?php echo esc_html( $grant->grant_name ); ?></strong>
										<span class="ns-amount">$<?php echo number_format( $grant->grant_amount, 0 ); ?></span>
										<?php if ( $grant->application_deadline ) : ?>
											<small><?php echo esc_html( NonprofitSuite_Utilities::format_date( $grant->application_deadline ) ); ?></small>
										<?php endif; ?>
									</div>
								<?php endforeach; endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Pipeline -->
	<?php if ( $active_tab === 'pipeline' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'Grant Pipeline', 'nonprofitsuite' ); ?></h2>
				</div>

				<?php if ( ! empty( $grants ) && ! is_wp_error( $grants ) ) : ?>
					<table class="ns-table ns-table-hover">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Grant Name', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Funder', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Deadline', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $grants as $grant ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $grant->grant_name ); ?></strong></td>
									<td><?php echo esc_html( $grant->funder_name ?? '—' ); ?></td>
									<td><strong>$<?php echo number_format( $grant->grant_amount, 2 ); ?></strong></td>
									<td><?php echo $grant->application_deadline ? esc_html( NonprofitSuite_Utilities::format_date( $grant->application_deadline ) ) : '—'; ?></td>
									<td><?php echo NonprofitSuite_Utilities::get_status_badge( $grant->status ); ?></td>
									<td>
										<button class="ns-button-icon ns-edit-grant" data-id="<?php echo esc_attr( $grant->id ); ?>">
											<span class="dashicons dashicons-edit"></span>
										</button>
										<button class="ns-button-icon ns-delete-grant" data-id="<?php echo esc_attr( $grant->id ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="ns-empty-state"><?php esc_html_e( 'No grants found. Add your first grant to get started!', 'nonprofitsuite' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Awarded -->
	<?php if ( $active_tab === 'awarded' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'Awarded Grants', 'nonprofitsuite' ); ?></h2>
				</div>

				<?php
				$awarded_grants = NonprofitSuite_Grants::get_grants( array( 'status' => 'awarded' ) );
				if ( ! empty( $awarded_grants ) && ! is_wp_error( $awarded_grants ) ) : ?>
					<table class="ns-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Grant Name', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Award Amount', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Award Date', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'End Date', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Next Report Due', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $awarded_grants as $grant ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $grant->grant_name ); ?></strong></td>
									<td><strong>$<?php echo number_format( $grant->awarded_amount ?? $grant->grant_amount, 2 ); ?></strong></td>
									<td><?php echo $grant->award_date ? esc_html( NonprofitSuite_Utilities::format_date( $grant->award_date ) ) : '—'; ?></td>
									<td><?php echo $grant->grant_start_date ? esc_html( NonprofitSuite_Utilities::format_date( $grant->grant_start_date ) ) : '—'; ?></td>
									<td><?php echo $grant->grant_end_date ? esc_html( NonprofitSuite_Utilities::format_date( $grant->grant_end_date ) ) : '—'; ?></td>
									<td><?php echo $grant->next_report_due ? esc_html( NonprofitSuite_Utilities::format_date( $grant->next_report_due ) ) : '—'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="ns-empty-state"><?php esc_html_e( 'No awarded grants yet.', 'nonprofitsuite' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Reporting -->
	<?php if ( $active_tab === 'reporting' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<h2 class="ns-card-title"><?php esc_html_e( 'Grant Reporting', 'nonprofitsuite' ); ?></h2>
				<p class="ns-help-text"><?php esc_html_e( 'Track and submit grant reports', 'nonprofitsuite' ); ?></p>
				<div class="ns-empty-state">
					<p><?php esc_html_e( 'No reports due at this time.', 'nonprofitsuite' ); ?></p>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Modal: Add/Edit Grant -->
<div id="ns-grant-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2><?php esc_html_e( 'Add Grant', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-grant-form">
			<input type="hidden" name="action" value="ns_save_grant">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_grant_save' ); ?>">

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Grant Name', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
				<input type="text" name="grant_name" required>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Funder Name', 'nonprofitsuite' ); ?></label>
					<input type="text" name="funder_name">
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Grant Amount', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="number" step="0.01" name="grant_amount" placeholder="0.00" required>
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Application Deadline', 'nonprofitsuite' ); ?></label>
					<input type="date" name="application_deadline">
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
					<select name="status">
						<option value="prospect"><?php esc_html_e( 'Prospect', 'nonprofitsuite' ); ?></option>
						<option value="in_progress"><?php esc_html_e( 'In Progress', 'nonprofitsuite' ); ?></option>
						<option value="submitted"><?php esc_html_e( 'Submitted', 'nonprofitsuite' ); ?></option>
						<option value="awarded"><?php esc_html_e( 'Awarded', 'nonprofitsuite' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></label>
				<textarea name="grant_description" rows="3"></textarea>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Grant', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#ns-add-grant, .ns-edit-grant').on('click', function() {
		$('#ns-grant-form')[0].reset();
		$('#ns-grant-modal').fadeIn();
	});

	$('.ns-modal-close').on('click', function() {
		$(this).closest('.ns-modal').fadeOut();
	});

	$('.ns-modal').on('click', function(e) {
		if ($(e.target).hasClass('ns-modal')) {
			$(this).fadeOut();
		}
	});

	$('#ns-grant-form').on('submit', function(e) {
		e.preventDefault();
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: $(this).serialize(),
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Grant saved successfully!', 'nonprofitsuite' ); ?>');
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error saving grant', 'nonprofitsuite' ); ?>');
				}
			}
		});
	});

	$('.ns-delete-grant').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this grant?', 'nonprofitsuite' ); ?>')) return;
		// AJAX delete logic here
	});

	$('#ns-export-grants').on('click', function() {
		var format = prompt('<?php esc_html_e( 'Export format (csv/pdf/excel):', 'nonprofitsuite' ); ?>', 'csv');
		if (format) {
			window.location.href = '?page=nonprofitsuite-grants&action=export&format=' + format;
		}
	});
});
</script>

<style>
.ns-status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
.ns-status-column { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; overflow: hidden; }
.ns-status-header { padding: 12px 15px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
.ns-status-header.ns-status-prospect { background: #e0e8ff; color: #003d7a; }
.ns-status-header.ns-status-in_progress { background: #fff7e0; color: #8b5a00; }
.ns-status-header.ns-status-submitted { background: #e0f8ff; color: #006d8b; }
.ns-status-header.ns-status-awarded { background: #d7f8e0; color: #00593d; }
.ns-status-header.ns-status-rejected { background: #ffe0e0; color: #8b0000; }
.ns-status-count { background: rgba(255,255,255,0.8); padding: 2px 8px; border-radius: 12px; font-size: 12px; }
.ns-status-items { padding: 10px; min-height: 100px; }
.ns-status-item { padding: 10px; margin-bottom: 8px; background: #f9f9f9; border-radius: 4px; }
.ns-status-item strong { display: block; margin-bottom: 4px; }
.ns-status-item .ns-amount { display: block; color: #2271b1; font-weight: 600; }
.ns-status-item small { color: #646970; font-size: 11px; }
</style>
