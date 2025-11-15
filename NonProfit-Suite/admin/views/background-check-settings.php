<?php
/**
 * Background Check Settings View
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$settings_table = $wpdb->prefix . 'ns_background_check_settings';

// Get existing settings
$settings = $wpdb->get_results( "SELECT * FROM $settings_table ORDER BY is_active DESC, provider" );

?>

<div class="wrap ns-background-check-settings">
	<h1><?php esc_html_e( 'Background Check Settings', 'nonprofitsuite' ); ?></h1>

	<div class="notice notice-warning">
		<p><strong><?php esc_html_e( 'FCRA Compliance Required', 'nonprofitsuite' ); ?></strong></p>
		<p><?php esc_html_e( 'Background checks must comply with the Fair Credit Reporting Act (FCRA). Ensure you obtain proper consent before ordering checks and follow adverse action procedures if taking negative action based on results.', 'nonprofitsuite' ); ?></p>
	</div>

	<div class="ns-settings-grid">
		<!-- Provider Configuration -->
		<div class="ns-settings-main">
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Provider Configuration', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<form id="background-check-settings-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="provider"><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<select name="provider" id="provider" class="regular-text" required>
										<option value=""><?php esc_html_e( 'Select Provider', 'nonprofitsuite' ); ?></option>
										<option value="checkr">Checkr (Recommended)</option>
										<option value="sterling">Sterling</option>
										<option value="goodhire">GoodHire</option>
										<option value="accurate">Accurate</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'Choose your background check provider.', 'nonprofitsuite' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="api_key"><?php esc_html_e( 'API Key', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<input type="text" name="api_key" id="api_key" class="regular-text" required>
									<p class="description">
										<?php esc_html_e( 'Your API key from the provider.', 'nonprofitsuite' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="api_secret"><?php esc_html_e( 'API Secret', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<input type="text" name="api_secret" id="api_secret" class="regular-text">
									<p class="description">
										<?php esc_html_e( 'API secret (if required by provider).', 'nonprofitsuite' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="webhook_secret"><?php esc_html_e( 'Webhook Secret', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<input type="text" name="webhook_secret" id="webhook_secret" class="regular-text">
									<p class="description">
										<?php esc_html_e( 'Webhook secret for validating status updates.', 'nonprofitsuite' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<h3><?php esc_html_e( 'Default Packages', 'nonprofitsuite' ); ?></h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="default_volunteer_package"><?php esc_html_e( 'Volunteer Package', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<select name="default_volunteer_package" id="default_volunteer_package" class="regular-text">
										<option value="basic">Basic - Criminal Records ($35)</option>
										<option value="standard">Standard - Criminal + MVR ($50)</option>
										<option value="premium">Premium - Full Package ($75)</option>
									</select>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="default_staff_package"><?php esc_html_e( 'Staff Package', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<select name="default_staff_package" id="default_staff_package" class="regular-text">
										<option value="basic">Basic - Criminal Records ($35)</option>
										<option value="standard" selected>Standard - Criminal + MVR ($50)</option>
										<option value="premium">Premium - Full Package ($75)</option>
									</select>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="default_board_package"><?php esc_html_e( 'Board Member Package', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<select name="default_board_package" id="default_board_package" class="regular-text">
										<option value="basic">Basic - Criminal Records ($35)</option>
										<option value="standard">Standard - Criminal + MVR ($50)</option>
										<option value="premium" selected>Premium - Full Package ($75)</option>
									</select>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="button" id="test-connection" class="button">
								<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
							</button>
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
							</button>
						</p>

						<div id="connection-status" style="display:none; margin-top: 10px;"></div>
					</form>
				</div>
			</div>

			<!-- Provider Comparison -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Provider Comparison', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Pricing', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Turnaround', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Best For', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>Checkr ✓</strong></td>
								<td>$35-75 per check</td>
								<td>1-5 days</td>
								<td>Modern API, excellent support</td>
							</tr>
							<tr>
								<td><strong>Sterling</strong></td>
								<td>$45-85 per check</td>
								<td>2-7 days</td>
								<td>Enterprise organizations</td>
							</tr>
							<tr>
								<td><strong>GoodHire</strong></td>
								<td>$30-70 per check</td>
								<td>1-3 days</td>
								<td>Small to medium nonprofits</td>
							</tr>
							<tr>
								<td><strong>Accurate</strong></td>
								<td>$25-60 per check</td>
								<td>3-7 days</td>
								<td>Budget-conscious organizations</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Webhook Configuration -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Webhook Configuration', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<p class="description">
						<?php esc_html_e( 'Configure this webhook URL in your provider dashboard to receive real-time status updates:', 'nonprofitsuite' ); ?>
					</p>
					<p>
						<code style="display: block; padding: 10px; background: #f0f0f1; border-radius: 3px;">
							<?php echo esc_url( rest_url( 'nonprofitsuite/v1/background-check-webhook/checkr' ) ); ?>
						</code>
					</p>
					<p class="description">
						<?php esc_html_e( 'Replace "checkr" with your provider name if different.', 'nonprofitsuite' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Sidebar -->
		<div class="ns-settings-sidebar">
			<!-- Active Providers -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Active Providers', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<?php if ( empty( $settings ) ) : ?>
						<p class="description">
							<?php esc_html_e( 'No providers configured yet.', 'nonprofitsuite' ); ?>
						</p>
					<?php else : ?>
						<ul class="ns-provider-list">
							<?php foreach ( $settings as $setting ) : ?>
								<li class="<?php echo $setting->is_active ? 'active' : 'inactive'; ?>">
									<span class="provider-name">
										<?php echo esc_html( ucfirst( $setting->provider ) ); ?>
									</span>
									<span class="provider-status">
										<?php echo $setting->is_active ? '✓ Active' : '○ Inactive'; ?>
									</span>
									<div class="package-info">
										<small>
											Volunteer: <?php echo esc_html( ucfirst( $setting->default_volunteer_package ?? 'basic' ) ); ?><br>
											Staff: <?php echo esc_html( ucfirst( $setting->default_staff_package ?? 'standard' ) ); ?><br>
											Board: <?php echo esc_html( ucfirst( $setting->default_board_package ?? 'premium' ) ); ?>
										</small>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<!-- FCRA Compliance -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'FCRA Compliance', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<p><strong><?php esc_html_e( 'Required Steps:', 'nonprofitsuite' ); ?></strong></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php esc_html_e( 'Provide disclosure to candidate', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( 'Obtain written authorization', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( 'Wait for consent before ordering', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( 'Pre-adverse action notice (if negative)', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( '7-day dispute period', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( 'Final adverse action notice', 'nonprofitsuite' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Documentation -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Documentation', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<ul class="ns-doc-links">
						<li><a href="https://docs.checkr.com/" target="_blank">Checkr API Docs</a></li>
						<li><a href="https://www.ftc.gov/enforcement/rules/rulemaking-regulatory-reform-proceedings/fair-credit-reporting-act" target="_blank">FCRA Guidelines</a></li>
						<li><a href="https://www.consumerfinance.gov/compliance/compliance-resources/other-applicable-requirements/fair-credit-reporting-act/" target="_blank">CFPB FCRA Resources</a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.ns-settings-grid {
	display: grid;
	grid-template-columns: 1fr 300px;
	gap: 20px;
	margin-top: 20px;
}

.ns-provider-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.ns-provider-list li {
	padding: 10px;
	border-left: 3px solid #ddd;
	margin-bottom: 10px;
}

.ns-provider-list li.active {
	border-left-color: #46b450;
	background: #f0f9f1;
}

.provider-status {
	float: right;
	font-size: 0.9em;
}

.package-info {
	margin-top: 8px;
	padding-top: 8px;
	border-top: 1px solid #ddd;
}

.ns-doc-links {
	list-style: none;
	margin: 0;
	padding: 0;
}

.ns-doc-links li {
	margin-bottom: 8px;
}

#connection-status.success {
	color: #46b450;
	font-weight: bold;
}

#connection-status.error {
	color: #dc3232;
	font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Test Connection
	$('#test-connection').on('click', function() {
		const button = $(this);
		const status = $('#connection-status');

		button.prop('disabled', true).text('Testing...');
		status.hide().removeClass('success error');

		$.ajax({
			url: nsBackgroundCheck.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_background_check_test_connection',
				nonce: nsBackgroundCheck.nonce,
				provider: $('#provider').val(),
				api_key: $('#api_key').val(),
				api_secret: $('#api_secret').val()
			},
			success: function(response) {
				if (response.success) {
					status.addClass('success').text('✓ ' + response.data.message).show();
				} else {
					status.addClass('error').text('✗ ' + response.data.message).show();
				}
			},
			error: function() {
				status.addClass('error').text('✗ Connection test failed').show();
			},
			complete: function() {
				button.prop('disabled', false).text('Test Connection');
			}
		});
	});

	// Save Settings
	$('#background-check-settings-form').on('submit', function(e) {
		e.preventDefault();

		const form = $(this);
		const submitBtn = form.find('button[type="submit"]');
		const originalText = submitBtn.text();

		submitBtn.prop('disabled', true).text('Saving...');

		$.ajax({
			url: nsBackgroundCheck.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_background_check_save_settings',
				nonce: nsBackgroundCheck.nonce,
				provider: $('#provider').val(),
				api_key: $('#api_key').val(),
				api_secret: $('#api_secret').val(),
				webhook_secret: $('#webhook_secret').val(),
				default_volunteer_package: $('#default_volunteer_package').val(),
				default_staff_package: $('#default_staff_package').val(),
				default_board_package: $('#default_board_package').val(),
				organization_id: 1 // TODO: Get from current org
			},
			success: function(response) {
				if (response.success) {
					alert('Settings saved successfully!');
					location.reload();
				} else {
					alert('Error: ' + response.data.message);
				}
			},
			error: function() {
				alert('Failed to save settings');
			},
			complete: function() {
				submitBtn.prop('disabled', false).text(originalText);
			}
		});
	});
});
</script>
