<?php
/**
 * SMS Campaigns View
 *
 * Manages SMS campaigns and bulk messaging.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get organization ID
$organization_id = 1;

// Get campaigns
$campaigns_table = $wpdb->prefix . 'ns_sms_campaigns';
$campaigns = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$campaigns_table} WHERE organization_id = %d ORDER BY created_at DESC",
		$organization_id
	),
	ARRAY_A
);

// Get active providers
$settings_table = $wpdb->prefix . 'ns_sms_settings';
$providers = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT provider, phone_number FROM {$settings_table} WHERE organization_id = %d AND is_active = 1",
		$organization_id
	),
	ARRAY_A
);

?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'SMS Campaigns', 'nonprofitsuite' ); ?></h1>
	<button type="button" class="page-title-action" id="new-campaign-btn">
		<?php esc_html_e( 'New Campaign', 'nonprofitsuite' ); ?>
	</button>
	<hr class="wp-header-end">

	<?php if ( empty( $providers ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No SMS providers are configured. Please configure at least one provider in', 'nonprofitsuite' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-sms-settings' ) ); ?>">
					<?php esc_html_e( 'SMS Settings', 'nonprofitsuite' ); ?>
				</a>.
			</p>
		</div>
	<?php endif; ?>

	<div class="ns-campaigns">
		<?php if ( empty( $campaigns ) ) : ?>
			<div class="no-items">
				<p><?php esc_html_e( 'No campaigns yet. Create your first SMS campaign!', 'nonprofitsuite' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Target', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Recipients', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Sent/Delivered', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Cost', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $campaign['campaign_name'] ); ?></strong>
								<?php if ( $campaign['scheduled_at'] ) : ?>
									<br>
									<small>
										<?php esc_html_e( 'Scheduled:', 'nonprofitsuite' ); ?>
										<?php echo esc_html( date( 'M j, Y g:i a', strtotime( $campaign['scheduled_at'] ) ) ); ?>
									</small>
								<?php endif; ?>
							</td>
							<td>
								<span class="badge campaign-type">
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $campaign['campaign_type'] ) ) ); ?>
								</span>
							</td>
							<td>
								<span class="badge target-segment">
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $campaign['target_segment'] ) ) ); ?>
								</span>
							</td>
							<td>
								<span class="status-badge <?php echo esc_attr( $campaign['status'] ); ?>">
									<?php echo esc_html( ucfirst( $campaign['status'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( number_format( $campaign['total_recipients'] ) ); ?></td>
							<td>
								<?php echo esc_html( number_format( $campaign['total_sent'] ) ); ?> /
								<?php echo esc_html( number_format( $campaign['total_delivered'] ) ); ?>
							</td>
							<td>$<?php echo esc_html( number_format( $campaign['total_cost'], 2 ) ); ?></td>
							<td>
								<?php if ( $campaign['status'] === 'draft' || $campaign['status'] === 'scheduled' ) : ?>
									<button class="button button-small send-campaign" data-id="<?php echo esc_attr( $campaign['id'] ); ?>">
										<?php esc_html_e( 'Send Now', 'nonprofitsuite' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- New Campaign Modal -->
<div id="campaign-modal" class="ns-modal" style="display: none;">
	<div class="ns-modal-content">
		<span class="ns-modal-close">&times;</span>
		<h2><?php esc_html_e( 'New SMS Campaign', 'nonprofitsuite' ); ?></h2>

		<form id="campaign-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="campaign-name"><?php esc_html_e( 'Campaign Name', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="text" id="campaign-name" class="regular-text" required>
						<p class="description"><?php esc_html_e( 'Internal name for this campaign.', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="message-body"><?php esc_html_e( 'Message', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<textarea id="message-body" rows="5" class="large-text" maxlength="1600" required></textarea>
						<p class="description">
							<span id="char-count">0</span> / 1600 characters
							(<span id="segment-count">0</span> segments)
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="provider"><?php esc_html_e( 'SMS Provider', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="provider" required>
							<option value=""><?php esc_html_e( 'Select Provider', 'nonprofitsuite' ); ?></option>
							<?php foreach ( $providers as $provider ) : ?>
								<option value="<?php echo esc_attr( $provider['provider'] ); ?>">
									<?php echo esc_html( ucfirst( $provider['provider'] ) ); ?> (<?php echo esc_html( $provider['phone_number'] ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="target-segment"><?php esc_html_e( 'Target Audience', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="target-segment" required>
							<option value="all"><?php esc_html_e( 'All Contacts', 'nonprofitsuite' ); ?></option>
							<option value="donors"><?php esc_html_e( 'Donors', 'nonprofitsuite' ); ?></option>
							<option value="volunteers"><?php esc_html_e( 'Volunteers', 'nonprofitsuite' ); ?></option>
							<option value="members"><?php esc_html_e( 'Members', 'nonprofitsuite' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="scheduled-at"><?php esc_html_e( 'Schedule', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="datetime-local" id="scheduled-at">
						<p class="description"><?php esc_html_e( 'Leave empty to save as draft.', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Create Campaign', 'nonprofitsuite' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<style>
.ns-campaigns {
	margin-top: 20px;
	background: #fff;
	padding: 20px;
}

.badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
}

.campaign-type {
	background: #e0f2fe;
	color: #0369a1;
}

.target-segment {
	background: #f3e8ff;
	color: #6b21a8;
}

.ns-modal {
	display: none;
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	overflow: auto;
	background-color: rgba(0,0,0,0.4);
}

.ns-modal-content {
	background-color: #fefefe;
	margin: 5% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 80%;
	max-width: 800px;
	box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.ns-modal-close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
}

.ns-modal-close:hover {
	color: #000;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Character and segment counter
	$('#message-body').on('input', function() {
		const text = $(this).val();
		const length = text.length;
		let segments = 1;

		if (length > 160) {
			segments = Math.ceil(length / 153);
		}

		$('#char-count').text(length);
		$('#segment-count').text(segments);
	});

	// Open campaign modal
	$('#new-campaign-btn').on('click', function() {
		$('#campaign-modal').show();
	});

	// Close modal
	$('.ns-modal-close').on('click', function() {
		$('#campaign-modal').hide();
	});

	// Create campaign
	$('#campaign-form').on('submit', function(e) {
		e.preventDefault();

		$.ajax({
			url: nsSMS.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_sms_create_campaign',
				nonce: nsSMS.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				campaign_name: $('#campaign-name').val(),
				message_body: $('#message-body').val(),
				provider: $('#provider').val(),
				target_segment: $('#target-segment').val(),
				scheduled_at: $('#scheduled-at').val()
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	// Send campaign
	$('.send-campaign').on('click', function() {
		if (!confirm('Are you sure you want to send this campaign?')) {
			return;
		}

		const campaignId = $(this).data('id');
		const $btn = $(this);

		$btn.prop('disabled', true).text('Sending...');

		$.ajax({
			url: nsSMS.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_sms_send_campaign',
				nonce: nsSMS.nonce,
				campaign_id: campaignId
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
					$btn.prop('disabled', false).text('Send Now');
				}
			}
		});
	});
});
</script>
