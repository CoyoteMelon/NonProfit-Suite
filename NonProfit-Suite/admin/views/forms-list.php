<?php
/**
 * Forms List View
 *
 * Displays all forms with their statistics and management options.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1; // TODO: Get from current user context
$table           = $wpdb->prefix . 'ns_forms';

// Get all forms
$forms = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE organization_id = %d ORDER BY created_at DESC",
		$organization_id
	),
	ARRAY_A
);
?>

<div class="wrap">
	<h1 class="wp-heading-inline">Forms & Surveys</h1>
	<a href="#" class="page-title-action">Add New Form</a>
	<hr class="wp-header-end">

	<?php if ( empty( $forms ) ) : ?>
		<div class="notice notice-info">
			<p>No forms found. Create your first form to get started!</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Form Name</th>
					<th>Type</th>
					<th>Provider</th>
					<th>Status</th>
					<th>Submissions</th>
					<th>Created</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $forms as $form ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $form['form_name'] ); ?></strong>
							<?php if ( ! empty( $form['description'] ) ) : ?>
								<br><small><?php echo esc_html( wp_trim_words( $form['description'], 15 ) ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( ucfirst( $form['form_type'] ) ); ?></td>
						<td>
							<?php
							$provider_labels = array(
								'builtin'      => 'Built-in',
								'google_forms' => 'Google Forms',
								'typeform'     => 'Typeform',
								'jotform'      => 'JotForm',
							);
							echo esc_html( $provider_labels[ $form['provider'] ] ?? $form['provider'] );
							?>
						</td>
						<td>
							<span class="ns-status-badge ns-status-<?php echo esc_attr( $form['status'] ); ?>">
								<?php echo esc_html( ucfirst( $form['status'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $form['submission_count'] ); ?></td>
						<td><?php echo esc_html( wp_date( 'M j, Y', strtotime( $form['created_at'] ) ) ); ?></td>
						<td>
							<?php if ( $form['provider'] !== 'builtin' && ! empty( $form['form_url'] ) ) : ?>
								<a href="<?php echo esc_url( $form['form_url'] ); ?>" target="_blank" class="button button-small">View Form</a>
							<?php endif; ?>
							
							<button class="button button-small ns-sync-submissions" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
								Sync
							</button>
							
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-form-submissions&form_id=' . $form['id'] ) ); ?>" class="button button-small">
								View Submissions
							</a>

							<?php if ( $form['provider'] === 'builtin' ) : ?>
								<br><br>
								<strong>Shortcode:</strong> <code>[ns_form id="<?php echo esc_attr( $form['id'] ); ?>"]</code>
							<?php elseif ( ! empty( $form['embed_code'] ) ) : ?>
								<br><br>
								<button class="button button-small ns-copy-embed" data-embed="<?php echo esc_attr( htmlspecialchars( $form['embed_code'] ) ); ?>">
									Copy Embed Code
								</button>
							<?php endif; ?>
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
.ns-status-active {
	background: #d4edda;
	color: #155724;
}
.ns-status-draft {
	background: #fff3cd;
	color: #856404;
}
.ns-status-closed {
	background: #f8d7da;
	color: #721c24;
}
.ns-status-archived {
	background: #e2e3e5;
	color: #383d41;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Sync submissions
	$('.ns-sync-submissions').on('click', function() {
		var btn = $(this);
		var formId = btn.data('form-id');
		
		btn.prop('disabled', true).text('Syncing...');
		
		$.post(ajaxurl, {
			action: 'ns_sync_form_submissions',
			nonce: '<?php echo wp_create_nonce( 'ns_forms_admin' ); ?>',
			form_id: formId
		}, function(response) {
			if (response.success) {
				alert(response.data);
				location.reload();
			} else {
				alert('Error: ' + response.data);
			}
			btn.prop('disabled', false).text('Sync');
		});
	});

	// Copy embed code
	$('.ns-copy-embed').on('click', function() {
		var embed = $(this).data('embed');
		var $temp = $('<textarea>');
		$('body').append($temp);
		$temp.val(embed).select();
		document.execCommand('copy');
		$temp.remove();
		alert('Embed code copied to clipboard!');
	});
});
</script>
