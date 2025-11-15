<?php
/**
 * Marketing Campaigns Dashboard
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap">
	<h1>Marketing Campaigns</h1>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-marketing-settings&organization_id=' . $org_id ) ); ?>" class="button">Settings</a>
		<button type="button" class="button button-primary" id="ns-create-campaign">Create Campaign</button>
	</p>

	<?php if ( ! empty( $campaigns ) ) : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Campaign Name</th>
					<th>Type</th>
					<th>Platform</th>
					<th>Status</th>
					<th>Recipients</th>
					<th>Sent</th>
					<th>Opened</th>
					<th>Clicked</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $campaign ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $campaign['campaign_name'] ); ?></strong></td>
						<td><?php echo esc_html( ucfirst( $campaign['campaign_type'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $campaign['platform'] ) ); ?></td>
						<td>
							<span class="ns-status-badge <?php echo esc_attr( $campaign['status'] ); ?>">
								<?php echo esc_html( ucfirst( $campaign['status'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $campaign['total_recipients'] ); ?></td>
						<td><?php echo esc_html( $campaign['total_sent'] ); ?></td>
						<td>
							<?php echo esc_html( $campaign['total_opened'] ); ?>
							<?php if ( $campaign['total_sent'] > 0 ) : ?>
								(<?php echo esc_html( round( $campaign['total_opened'] / $campaign['total_sent'] * 100, 1 ) ); ?>%)
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $campaign['total_clicked'] ); ?>
							<?php if ( $campaign['total_opened'] > 0 ) : ?>
								(<?php echo esc_html( round( $campaign['total_clicked'] / $campaign['total_opened'] * 100, 1 ) ); ?>%)
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $campaign['status'] === 'draft' ) : ?>
								<button type="button" class="button ns-send-campaign" data-campaign-id="<?php echo esc_attr( $campaign['id'] ); ?>">Send Now</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p>No campaigns yet. Create your first campaign to get started!</p>
	<?php endif; ?>
</div>

<style>
.ns-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 500;
}

.ns-status-badge.draft {
	background: #f0f0f1;
	color: #646970;
}

.ns-status-badge.scheduled {
	background: #fff3cd;
	color: #856404;
}

.ns-status-badge.sending {
	background: #cfe2ff;
	color: #084298;
}

.ns-status-badge.sent {
	background: #d4edda;
	color: #155724;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.ns-send-campaign').on('click', function() {
		var button = $(this);
		var campaignId = button.data('campaign-id');

		if (!confirm('Are you sure you want to send this campaign now?')) {
			return;
		}

		button.prop('disabled', true).text('Sending...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_send_campaign',
				campaign_id: campaignId,
				nonce: '<?php echo wp_create_nonce( 'ns_marketing_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert('Send failed: ' + response.data.message);
					button.prop('disabled', false).text('Send Now');
				}
			},
			error: function() {
				alert('Send failed due to a network error.');
				button.prop('disabled', false).text('Send Now');
			}
		});
	});
});
</script>
