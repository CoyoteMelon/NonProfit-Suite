<?php
/**
 * Analytics Settings View
 *
 * Configure analytics provider settings.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1;

$settings_table = $wpdb->prefix . 'ns_analytics_settings';
$settings       = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$settings_table} WHERE organization_id = %d",
		$organization_id
	),
	ARRAY_A
);

$provider_settings = array();
foreach ( $settings as $setting ) {
	$provider_settings[ $setting['provider'] ] = $setting;
}

?>

<div class="wrap">
	<h1><?php esc_html_e( 'Analytics Provider Settings', 'nonprofitsuite' ); ?></h1>

	<div class="ns-analytics-settings">
		<!-- Google Analytics -->
		<div class="provider-section">
			<h2><span class="provider-logo ga">Google Analytics</span> GA4</h2>

			<form class="provider-form" data-provider="google_analytics">
				<table class="form-table">
					<tr>
						<th><label for="ga-measurement-id"><?php esc_html_e( 'Measurement ID', 'nonprofitsuite' ); ?></label></th>
						<td>
							<input type="text" id="ga-measurement-id" class="regular-text"
								value="<?php echo esc_attr( $provider_settings['google_analytics']['tracking_id'] ?? '' ); ?>"
								placeholder="G-XXXXXXXXXX">
						</td>
					</tr>
					<tr>
						<th><label for="ga-api-secret"><?php esc_html_e( 'API Secret', 'nonprofitsuite' ); ?></label></th>
						<td>
							<input type="password" id="ga-api-secret" class="regular-text"
								value="<?php echo esc_attr( $provider_settings['google_analytics']['api_key'] ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="ga-property-id"><?php esc_html_e( 'Property ID', 'nonprofitsuite' ); ?></label></th>
						<td>
							<input type="text" id="ga-property-id" class="regular-text"
								value="<?php echo esc_attr( $provider_settings['google_analytics']['property_id'] ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="ga-active"
									<?php checked( $provider_settings['google_analytics']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Google Analytics', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="google_analytics">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Mixpanel -->
		<div class="provider-section">
			<h2><span class="provider-logo mixpanel">Mixpanel</span> Product Analytics</h2>

			<form class="provider-form" data-provider="mixpanel">
				<table class="form-table">
					<tr>
						<th><label for="mp-token"><?php esc_html_e( 'Project Token', 'nonprofitsuite' ); ?></label></th>
						<td>
							<input type="text" id="mp-token" class="regular-text"
								value="<?php echo esc_attr( $provider_settings['mixpanel']['tracking_id'] ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="mp-api-secret"><?php esc_html_e( 'API Secret', 'nonprofitsuite' ); ?></label></th>
						<td>
							<input type="password" id="mp-api-secret" class="regular-text"
								value="<?php echo esc_attr( $provider_settings['mixpanel']['api_secret'] ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="mp-active"
									<?php checked( $provider_settings['mixpanel']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Mixpanel', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="mixpanel">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Segment -->
		<div class="provider-section">
			<h2><span class="provider-logo segment">Segment</span> Customer Data Platform</h2>

			<form class="provider-form" data-provider="segment">
				<table class="form-table">
					<tr>
						<th><label for="seg-write-key"><?php esc_html_e( 'Write Key', 'nonprofitsuite' ); ?></label></th>
						<td>
							<input type="password" id="seg-write-key" class="regular-text"
								value="<?php echo esc_attr( $provider_settings['segment']['api_key'] ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="seg-active"
									<?php checked( $provider_settings['segment']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Segment', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="segment">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>
</div>

<style>
.provider-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	margin-bottom: 20px;
}

.provider-logo {
	display: inline-block;
	padding: 5px 12px;
	border-radius: 4px;
	font-size: 14px;
	font-weight: 700;
	color: #fff;
}

.provider-logo.ga {
	background: #ea4335;
}

.provider-logo.mixpanel {
	background: #7856ff;
}

.provider-logo.segment {
	background: #52bd95;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.provider-form').on('submit', function(e) {
		e.preventDefault();

		const provider = $(this).data('provider');
		const $form = $(this);

		let data = {
			action: 'ns_analytics_save_settings',
			nonce: nsAnalytics.nonce,
			organization_id: <?php echo absint( $organization_id ); ?>,
			provider: provider,
			is_active: $form.find(`#${provider.replace('_', '-')}-active`).is(':checked') ? 1 : 0
		};

		// Add provider-specific fields
		if (provider === 'google_analytics') {
			data.tracking_id = $('#ga-measurement-id').val();
			data.api_key = $('#ga-api-secret').val();
			data.property_id = $('#ga-property-id').val();
		} else if (provider === 'mixpanel') {
			data.tracking_id = $('#mp-token').val();
			data.api_secret = $('#mp-api-secret').val();
		} else if (provider === 'segment') {
			data.api_key = $('#seg-write-key').val();
		}

		$.ajax({
			url: nsAnalytics.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {
				alert(response.success ? response.data.message : 'Error: ' + response.data.message);
			}
		});
	});

	$('.test-connection').on('click', function() {
		const provider = $(this).data('provider');

		$(this).prop('disabled', true).text('Testing...');

		$.ajax({
			url: nsAnalytics.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_analytics_test_connection',
				nonce: nsAnalytics.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				provider: provider
			},
			success: function(response) {
				alert(response.success ? response.data.message : 'Error: ' + response.data.message);
				$('.test-connection[data-provider="' + provider + '"]').prop('disabled', false).text('Test Connection');
			}
		});
	});
});
</script>
