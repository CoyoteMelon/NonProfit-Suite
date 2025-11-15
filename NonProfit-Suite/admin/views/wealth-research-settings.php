<?php
/**
 * Wealth Research Settings View
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$settings_table = $wpdb->prefix . 'ns_wealth_research_settings';

// Get existing settings
$settings = $wpdb->get_results( "SELECT * FROM $settings_table ORDER BY is_active DESC, provider" );

?>

<div class="wrap ns-wealth-research-settings">
	<h1><?php esc_html_e( 'Wealth Research Settings', 'nonprofitsuite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Configure wealth research providers to screen donors and identify major gift prospects.', 'nonprofitsuite' ); ?>
	</p>

	<div class="ns-settings-grid">
		<!-- Provider Configuration -->
		<div class="ns-settings-main">
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Provider Configuration', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<form id="wealth-research-settings-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="provider"><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<select name="provider" id="provider" class="regular-text" required>
										<option value=""><?php esc_html_e( 'Select Provider', 'nonprofitsuite' ); ?></option>
										<option value="wealthengine">WealthEngine</option>
										<option value="donorsearch">DonorSearch</option>
										<option value="blackbaud">Blackbaud Target Analytics</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'Choose your wealth research provider.', 'nonprofitsuite' ); ?>
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
									<label for="api_endpoint"><?php esc_html_e( 'API Endpoint', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<input type="url" name="api_endpoint" id="api_endpoint" class="regular-text" placeholder="https://api.provider.com">
									<p class="description">
										<?php esc_html_e( 'Custom API endpoint (optional, uses default if empty).', 'nonprofitsuite' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="monthly_limit"><?php esc_html_e( 'Monthly Limit', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<input type="number" name="monthly_limit" id="monthly_limit" class="small-text" min="0" value="0">
									<p class="description">
										<?php esc_html_e( 'Maximum API calls per month (0 for unlimited).', 'nonprofitsuite' ); ?>
									</p>
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
								<th><?php esc_html_e( 'Specialties', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Best For', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>WealthEngine</strong></td>
								<td>$1-5 per lookup</td>
								<td>Comprehensive wealth data, affinity scoring</td>
								<td>Flexible pay-per-use</td>
							</tr>
							<tr>
								<td><strong>DonorSearch</strong></td>
								<td>$2-8 per search</td>
								<td>Philanthropic history, political donations</td>
								<td>Historical giving data</td>
							</tr>
							<tr>
								<td><strong>Blackbaud</strong></td>
								<td>Enterprise licensing</td>
								<td>Raiser's Edge integration</td>
								<td>Enterprise organizations</td>
							</tr>
						</tbody>
					</table>
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
									<?php if ( $setting->monthly_limit > 0 ) : ?>
										<div class="usage-meter">
											<span><?php echo esc_html( $setting->current_month_usage ); ?> / <?php echo esc_html( $setting->monthly_limit ); ?> calls</span>
											<progress value="<?php echo esc_attr( $setting->current_month_usage ); ?>" max="<?php echo esc_attr( $setting->monthly_limit ); ?>"></progress>
										</div>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<!-- Documentation -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Documentation', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<ul class="ns-doc-links">
						<li><a href="https://api.wealthengine.com/" target="_blank">WealthEngine API Docs</a></li>
						<li><a href="https://www.donorsearch.net/api-docs" target="_blank">DonorSearch API Docs</a></li>
						<li><a href="https://developer.blackbaud.com/" target="_blank">Blackbaud Developer Portal</a></li>
					</ul>
				</div>
			</div>

			<!-- Best Practices -->
			<div class="postbox">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Best Practices', 'nonprofitsuite' ); ?></h2>
				</div>
				<div class="inside">
					<ul>
						<li><?php esc_html_e( 'Screen prospects before major asks', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( 'Respect data privacy and ethical use', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( 'Set monthly limits to control costs', 'nonprofitsuite' ); ?></li>
						<li><?php esc_html_e( 'Cache results to avoid redundant lookups', 'nonprofitsuite' ); ?></li>
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

.usage-meter {
	margin-top: 5px;
	font-size: 0.85em;
}

.usage-meter progress {
	width: 100%;
	height: 6px;
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
			url: nsWealthResearch.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_wealth_research_test_connection',
				nonce: nsWealthResearch.nonce,
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
	$('#wealth-research-settings-form').on('submit', function(e) {
		e.preventDefault();

		const form = $(this);
		const submitBtn = form.find('button[type="submit"]');
		const originalText = submitBtn.text();

		submitBtn.prop('disabled', true).text('Saving...');

		$.ajax({
			url: nsWealthResearch.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_wealth_research_save_settings',
				nonce: nsWealthResearch.nonce,
				provider: $('#provider').val(),
				api_key: $('#api_key').val(),
				api_secret: $('#api_secret').val(),
				api_endpoint: $('#api_endpoint').val(),
				monthly_limit: $('#monthly_limit').val(),
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
