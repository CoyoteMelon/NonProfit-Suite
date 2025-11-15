<?php
/**
 * Form Submissions View
 *
 * Displays submissions for a specific form or all forms.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1; // TODO: Get from current user context
$form_id         = isset( $_GET['form_id'] ) ? intval( $_GET['form_id'] ) : 0;

// Get form details if specific form
$form = null;
if ( $form_id ) {
	$forms_table = $wpdb->prefix . 'ns_forms';
	$form        = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$forms_table} WHERE id = %d", $form_id ),
		ARRAY_A
	);
}

// Get submissions
$submissions_table = $wpdb->prefix . 'ns_form_submissions';
$data_table        = $wpdb->prefix . 'ns_form_submission_data';

if ( $form_id ) {
	$submissions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$submissions_table} WHERE form_id = %d ORDER BY submitted_at DESC LIMIT 100",
			$form_id
		),
		ARRAY_A
	);
} else {
	$submissions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.*, f.form_name 
			FROM {$submissions_table} s
			LEFT JOIN {$wpdb->prefix}ns_forms f ON s.form_id = f.id
			WHERE s.organization_id = %d 
			ORDER BY s.submitted_at DESC 
			LIMIT 100",
			$organization_id
		),
		ARRAY_A
	);
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php
		if ( $form ) {
			echo 'Submissions for: ' . esc_html( $form['form_name'] );
		} else {
			echo 'All Form Submissions';
		}
		?>
	</h1>
	
	<?php if ( $form ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-forms' ) ); ?>" class="page-title-action">← Back to Forms</a>
	<?php endif; ?>
	
	<hr class="wp-header-end">

	<?php if ( empty( $submissions ) ) : ?>
		<div class="notice notice-info">
			<p>No submissions found.</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th width="50">ID</th>
					<?php if ( ! $form_id ) : ?>
						<th>Form</th>
					<?php endif; ?>
					<th>Status</th>
					<th>IP Address</th>
					<th>Submitted At</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $submissions as $submission ) : ?>
					<tr>
						<td><?php echo esc_html( $submission['id'] ); ?></td>
						<?php if ( ! $form_id ) : ?>
							<td><?php echo esc_html( $submission['form_name'] ?? 'N/A' ); ?></td>
						<?php endif; ?>
						<td>
							<span class="ns-status-badge ns-status-<?php echo esc_attr( $submission['submission_status'] ); ?>">
								<?php echo esc_html( ucfirst( $submission['submission_status'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $submission['ip_address'] ); ?></td>
						<td><?php echo esc_html( wp_date( 'M j, Y g:i a', strtotime( $submission['submitted_at'] ) ) ); ?></td>
						<td>
							<button class="button button-small ns-view-submission" data-submission-id="<?php echo esc_attr( $submission['id'] ); ?>">
								View Details
							</button>
						</td>
					</tr>

					<!-- Hidden row for submission details -->
					<tr class="ns-submission-details" id="submission-<?php echo esc_attr( $submission['id'] ); ?>" style="display: none;">
						<td colspan="<?php echo $form_id ? 5 : 6; ?>">
							<div style="padding: 20px; background: #f9f9f9;">
								<h3>Submission Details</h3>
								<?php
								// Get submission data
								$submission_data = $wpdb->get_results(
									$wpdb->prepare(
										"SELECT * FROM {$data_table} WHERE submission_id = %d",
										$submission['id']
									),
									ARRAY_A
								);

								if ( ! empty( $submission_data ) ) :
									?>
									<table class="form-table">
										<?php foreach ( $submission_data as $data ) : ?>
											<tr>
												<th><?php echo esc_html( $data['field_name'] ); ?></th>
												<td>
													<?php
													if ( ! empty( $data['file_url'] ) ) {
														echo '<a href="' . esc_url( $data['file_url'] ) . '" target="_blank">View File</a>';
													} else {
														echo esc_html( $data['field_value'] );
													}
													?>
												</td>
											</tr>
										<?php endforeach; ?>
									</table>
								<?php else : ?>
									<p>No data available.</p>
								<?php endif; ?>

								<h4>Metadata</h4>
								<table class="form-table">
									<tr>
										<th>User Agent</th>
										<td><code><?php echo esc_html( $submission['user_agent'] ); ?></code></td>
									</tr>
									<tr>
										<th>Referrer</th>
										<td><?php echo esc_html( $submission['referrer'] ?: 'N/A' ); ?></td>
									</tr>
									<?php if ( $submission['provider_submission_id'] ) : ?>
										<tr>
											<th>Provider Submission ID</th>
											<td><code><?php echo esc_html( $submission['provider_submission_id'] ); ?></code></td>
										</tr>
									<?php endif; ?>
								</table>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<style>
.ns-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}
.ns-status-completed {
	background: #d4edda;
	color: #155724;
}
.ns-status-partial {
	background: #fff3cd;
	color: #856404;
}
.ns-status-spam {
	background: #f8d7da;
	color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.ns-view-submission').on('click', function() {
		var submissionId = $(this).data('submission-id');
		var detailsRow = $('#submission-' + submissionId);
		
		if (detailsRow.is(':visible')) {
			detailsRow.hide();
			$(this).text('View Details');
		} else {
			$('.ns-submission-details').hide();
			$('.ns-view-submission').text('View Details');
			detailsRow.show();
			$(this).text('Hide Details');
		}
	});
});
</script>
