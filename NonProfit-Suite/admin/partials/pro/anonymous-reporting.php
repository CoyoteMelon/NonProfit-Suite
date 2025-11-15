<?php
/**
 * Anonymous Reporting Admin Interface
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check permissions
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'nonprofitsuite' ) );
}

// Handle form submissions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ns_anonymous_report_nonce'] ) && wp_verify_nonce( $_POST['ns_anonymous_report_nonce'], 'ns_update_report' ) ) {
	$report_id = isset( $_POST['report_id'] ) ? intval( $_POST['report_id'] ) : 0;

	$update_data = array();

	if ( isset( $_POST['status'] ) ) {
		$update_data['status'] = sanitize_text_field( $_POST['status'] );
	}

	if ( isset( $_POST['priority'] ) ) {
		$update_data['priority'] = sanitize_text_field( $_POST['priority'] );
	}

	if ( isset( $_POST['assigned_to'] ) ) {
		$update_data['assigned_to'] = intval( $_POST['assigned_to'] );
	}

	if ( isset( $_POST['investigation_notes'] ) ) {
		$update_data['investigation_notes'] = wp_kses_post( $_POST['investigation_notes'] );
	}

	if ( isset( $_POST['resolution'] ) ) {
		$update_data['resolution'] = wp_kses_post( $_POST['resolution'] );
	}

	if ( isset( $_POST['followup_required'] ) ) {
		$update_data['followup_required'] = intval( $_POST['followup_required'] );
	}

	NonprofitSuite_Anonymous_Reporting::update_report( $report_id, $update_data );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Report updated successfully.', 'nonprofitsuite' ) . '</p></div>';
}

// Get dashboard data
$dashboard_data = NonprofitSuite_Anonymous_Reporting::get_dashboard_data();
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$report_id = isset( $_GET['report_id'] ) ? intval( $_GET['report_id'] ) : 0;

if ( $action === 'view' && $report_id ) {
	$report = NonprofitSuite_Anonymous_Reporting::get_report( $report_id );
	if ( is_wp_error( $report ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $report->get_error_message() ) . '</p></div>';
		$action = 'list';
	}
}
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Anonymous Reporting', 'nonprofitsuite' ); ?></h1>

	<?php if ( $action === 'list' ) : ?>

		<!-- Dashboard Stats -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
			<div class="ns-card" style="padding: 20px;">
				<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'New Reports', 'nonprofitsuite' ); ?></h3>
				<p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $dashboard_data['new_reports'] ?? 0 ); ?></p>
			</div>

			<div class="ns-card" style="padding: 20px;">
				<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Under Investigation', 'nonprofitsuite' ); ?></h3>
				<p style="margin: 0; font-size: 32px; font-weight: bold; color: #f0b429;"><?php echo esc_html( $dashboard_data['investigating'] ?? 0 ); ?></p>
			</div>

			<div class="ns-card" style="padding: 20px;">
				<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'High Priority', 'nonprofitsuite' ); ?></h3>
				<p style="margin: 0; font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html( $dashboard_data['high_priority'] ?? 0 ); ?></p>
			</div>
		</div>

		<!-- Recent Reports -->
		<div class="ns-card">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php esc_html_e( 'Recent Reports', 'nonprofitsuite' ); ?></h2>
			</div>

			<div class="ns-table-container">
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Report #', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Submitted', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $dashboard_data['recent_reports'] ) ) : ?>
							<?php foreach ( $dashboard_data['recent_reports'] as $report ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $report->report_number ); ?></strong></td>
									<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->category ) ) ); ?></td>
									<td><?php echo NonprofitSuite_Utilities::get_priority_badge( $report->priority ); ?></td>
									<td><?php echo NonprofitSuite_Utilities::get_status_badge( $report->status ); ?></td>
									<td><?php echo esc_html( NonprofitSuite_Utilities::format_datetime( $report->created_at ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-anonymous-reporting&action=view&report_id=' . $report->id ) ); ?>" class="ns-button ns-button-sm">
											<?php esc_html_e( 'View', 'nonprofitsuite' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="6" style="text-align: center; padding: 40px;">
									<?php esc_html_e( 'No reports submitted yet.', 'nonprofitsuite' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Instructions -->
		<div class="ns-card" style="margin-top: 20px;">
			<h3><?php esc_html_e( 'Public Reporting Link', 'nonprofitsuite' ); ?></h3>
			<p><?php esc_html_e( 'Share this link with your community to allow anonymous reporting:', 'nonprofitsuite' ); ?></p>
			<input type="text" readonly value="<?php echo esc_url( home_url( '/anonymous-report/' ) ); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" onclick="this.select();">
			<p class="description"><?php esc_html_e( 'Note: You will need to create a page or configure a custom endpoint to handle public submissions.', 'nonprofitsuite' ); ?></p>
		</div>

	<?php elseif ( $action === 'view' && isset( $report ) ) : ?>

		<!-- Report Details -->
		<div class="ns-card">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php printf( esc_html__( 'Report: %s', 'nonprofitsuite' ), esc_html( $report->report_number ) ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-anonymous-reporting' ) ); ?>" class="ns-button ns-button-outline">
					<?php esc_html_e( '← Back to List', 'nonprofitsuite' ); ?>
				</a>
			</div>

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
				<!-- Report Information -->
				<div>
					<h3><?php esc_html_e( 'Report Details', 'nonprofitsuite' ); ?></h3>

					<div style="margin-bottom: 20px;">
						<strong><?php esc_html_e( 'Category:', 'nonprofitsuite' ); ?></strong>
						<p><?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->category ) ) ); ?></p>
					</div>

					<?php if ( $report->incident_date ) : ?>
						<div style="margin-bottom: 20px;">
							<strong><?php esc_html_e( 'Incident Date:', 'nonprofitsuite' ); ?></strong>
							<p><?php echo esc_html( NonprofitSuite_Utilities::format_date( $report->incident_date ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $report->location ) : ?>
						<div style="margin-bottom: 20px;">
							<strong><?php esc_html_e( 'Location:', 'nonprofitsuite' ); ?></strong>
							<p><?php echo esc_html( $report->location ); ?></p>
						</div>
					<?php endif; ?>

					<div style="margin-bottom: 20px;">
						<strong><?php esc_html_e( 'Description:', 'nonprofitsuite' ); ?></strong>
						<div style="background: #f9fafb; padding: 15px; border-radius: 4px; margin-top: 5px;">
							<?php echo wp_kses_post( $report->description ); ?>
						</div>
					</div>

					<?php if ( $report->witnesses ) : ?>
						<div style="margin-bottom: 20px;">
							<strong><?php esc_html_e( 'Witnesses:', 'nonprofitsuite' ); ?></strong>
							<div style="background: #f9fafb; padding: 15px; border-radius: 4px; margin-top: 5px;">
								<?php echo wp_kses_post( $report->witnesses ); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $report->evidence_description ) : ?>
						<div style="margin-bottom: 20px;">
							<strong><?php esc_html_e( 'Evidence Description:', 'nonprofitsuite' ); ?></strong>
							<div style="background: #f9fafb; padding: 15px; border-radius: 4px; margin-top: 5px;">
								<?php echo wp_kses_post( $report->evidence_description ); ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<!-- Update Form -->
				<div>
					<h3><?php esc_html_e( 'Update Report', 'nonprofitsuite' ); ?></h3>

					<form method="post">
						<?php wp_nonce_field( 'ns_update_report', 'ns_anonymous_report_nonce' ); ?>
						<input type="hidden" name="report_id" value="<?php echo esc_attr( $report->id ); ?>">

						<div style="margin-bottom: 15px;">
							<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
							<select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<option value="submitted" <?php selected( $report->status, 'submitted' ); ?>><?php esc_html_e( 'Submitted', 'nonprofitsuite' ); ?></option>
								<option value="investigating" <?php selected( $report->status, 'investigating' ); ?>><?php esc_html_e( 'Investigating', 'nonprofitsuite' ); ?></option>
								<option value="resolved" <?php selected( $report->status, 'resolved' ); ?>><?php esc_html_e( 'Resolved', 'nonprofitsuite' ); ?></option>
								<option value="closed" <?php selected( $report->status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'nonprofitsuite' ); ?></option>
							</select>
						</div>

						<div style="margin-bottom: 15px;">
							<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></label>
							<select name="priority" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<option value="low" <?php selected( $report->priority, 'low' ); ?>><?php esc_html_e( 'Low', 'nonprofitsuite' ); ?></option>
								<option value="medium" <?php selected( $report->priority, 'medium' ); ?>><?php esc_html_e( 'Medium', 'nonprofitsuite' ); ?></option>
								<option value="high" <?php selected( $report->priority, 'high' ); ?>><?php esc_html_e( 'High', 'nonprofitsuite' ); ?></option>
							</select>
						</div>

						<div style="margin-bottom: 15px;">
							<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Assign To', 'nonprofitsuite' ); ?></label>
							<select name="assigned_to" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<option value=""><?php esc_html_e( 'Unassigned', 'nonprofitsuite' ); ?></option>
								<?php
								$users = get_users( array( 'role__in' => array( 'administrator', 'editor' ) ) );
								foreach ( $users as $user ) {
									echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( $report->assigned_to, $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
								}
								?>
							</select>
						</div>

						<div style="margin-bottom: 15px;">
							<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Investigation Notes', 'nonprofitsuite' ); ?></label>
							<textarea name="investigation_notes" rows="5" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea( $report->investigation_notes ?? '' ); ?></textarea>
						</div>

						<div style="margin-bottom: 15px;">
							<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Resolution', 'nonprofitsuite' ); ?></label>
							<textarea name="resolution" rows="5" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea( $report->resolution ?? '' ); ?></textarea>
						</div>

						<div style="margin-bottom: 15px;">
							<label style="display: flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="followup_required" value="1" <?php checked( $report->followup_required, 1 ); ?>>
								<?php esc_html_e( 'Follow-up Required', 'nonprofitsuite' ); ?>
							</label>
						</div>

						<button type="submit" class="ns-button ns-button-primary" style="width: 100%;">
							<?php esc_html_e( 'Update Report', 'nonprofitsuite' ); ?>
						</button>
					</form>

					<div style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 4px;">
						<p style="margin: 0; font-size: 12px; color: #666;">
							<strong><?php esc_html_e( 'Submitted:', 'nonprofitsuite' ); ?></strong><br>
							<?php echo esc_html( NonprofitSuite_Utilities::format_datetime( $report->created_at ) ); ?>
						</p>
						<?php if ( $report->resolved_date ) : ?>
							<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
								<strong><?php esc_html_e( 'Resolved:', 'nonprofitsuite' ); ?></strong><br>
								<?php echo esc_html( NonprofitSuite_Utilities::format_datetime( $report->resolved_date ) ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

	<?php endif; ?>
</div>
