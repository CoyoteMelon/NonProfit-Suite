<?php
/**
 * SMS Settings View
 *
 * Configure SMS provider settings (Twilio, Plivo, Vonage).
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

// Get current settings
$settings_table = $wpdb->prefix . 'ns_sms_settings';
$settings = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$settings_table} WHERE organization_id = %d",
		$organization_id
	),
	ARRAY_A
);

// Organize settings by provider
$provider_settings = array();
foreach ( $settings as $setting ) {
	$provider_settings[ $setting['provider'] ] = $setting;
}

?>

<div class="wrap">
	<h1><?php esc_html_e( 'SMS Provider Settings', 'nonprofitsuite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Configure your SMS provider credentials. You can enable multiple providers for redundancy or load balancing.', 'nonprofitsuite' ); ?>
	</p>

	<div class="ns-sms-settings">
		<!-- Twilio Settings -->
		<div class="provider-section">
			<h2>
				<span class="provider-logo twilio">Twilio</span>
				Programmable SMS
			</h2>

			<form class="provider-form" data-provider="twilio">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="twilio-account-sid"><?php esc_html_e( 'Account SID', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="twilio-account-sid"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['twilio']['account_sid'] ?? '' ); ?>"
								placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
							<p class="description">
								<?php esc_html_e( 'Get your Account SID from', 'nonprofitsuite' ); ?>
								<a href="https://console.twilio.com/" target="_blank">console.twilio.com</a>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="twilio-auth-token"><?php esc_html_e( 'Auth Token', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="password"
								id="twilio-auth-token"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['twilio']['api_key'] ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="twilio-phone"><?php esc_html_e( 'Phone Number', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="twilio-phone"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['twilio']['phone_number'] ?? '' ); ?>"
								placeholder="+1234567890">
							<p class="description"><?php esc_html_e( 'Your Twilio phone number in E.164 format.', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="twilio-limit"><?php esc_html_e( 'Monthly SMS Limit', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="twilio-limit"
								class="small-text"
								min="0"
								value="<?php echo esc_attr( $provider_settings['twilio']['monthly_limit'] ?? '10000' ); ?>">
							<?php esc_html_e( 'messages', 'nonprofitsuite' ); ?>
							<?php if ( isset( $provider_settings['twilio'] ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Used this month:', 'nonprofitsuite' ); ?>
									<strong><?php echo esc_html( number_format( $provider_settings['twilio']['current_month_count'] ) ); ?></strong>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status', 'nonprofitsuite' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox"
									id="twilio-active"
									<?php checked( $provider_settings['twilio']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Twilio', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="twilio">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="button" class="button button-secondary send-test" data-provider="twilio">
						<?php esc_html_e( 'Send Test SMS', 'nonprofitsuite' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Plivo Settings -->
		<div class="provider-section">
			<h2>
				<span class="provider-logo plivo">Plivo</span>
				SMS API
			</h2>

			<form class="provider-form" data-provider="plivo">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="plivo-auth-id"><?php esc_html_e( 'Auth ID', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="plivo-auth-id"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['plivo']['account_sid'] ?? '' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Get your Auth ID from', 'nonprofitsuite' ); ?>
								<a href="https://console.plivo.com/" target="_blank">console.plivo.com</a>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="plivo-auth-token"><?php esc_html_e( 'Auth Token', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="password"
								id="plivo-auth-token"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['plivo']['api_key'] ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="plivo-phone"><?php esc_html_e( 'Phone Number', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="plivo-phone"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['plivo']['phone_number'] ?? '' ); ?>"
								placeholder="+1234567890">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="plivo-limit"><?php esc_html_e( 'Monthly SMS Limit', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="plivo-limit"
								class="small-text"
								min="0"
								value="<?php echo esc_attr( $provider_settings['plivo']['monthly_limit'] ?? '10000' ); ?>">
							<?php esc_html_e( 'messages', 'nonprofitsuite' ); ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status', 'nonprofitsuite' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox"
									id="plivo-active"
									<?php checked( $provider_settings['plivo']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Plivo', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="plivo">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="button" class="button button-secondary send-test" data-provider="plivo">
						<?php esc_html_e( 'Send Test SMS', 'nonprofitsuite' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Vonage Settings -->
		<div class="provider-section">
			<h2>
				<span class="provider-logo vonage">Vonage</span>
				SMS API (formerly Nexmo)
			</h2>

			<form class="provider-form" data-provider="vonage">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="vonage-api-key"><?php esc_html_e( 'API Key', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="vonage-api-key"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['vonage']['api_key'] ?? '' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Get your API Key from', 'nonprofitsuite' ); ?>
								<a href="https://dashboard.nexmo.com/" target="_blank">dashboard.nexmo.com</a>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="vonage-api-secret"><?php esc_html_e( 'API Secret', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="password"
								id="vonage-api-secret"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['vonage']['api_secret'] ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="vonage-phone"><?php esc_html_e( 'Phone Number', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="vonage-phone"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['vonage']['phone_number'] ?? '' ); ?>"
								placeholder="+1234567890">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="vonage-limit"><?php esc_html_e( 'Monthly SMS Limit', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="vonage-limit"
								class="small-text"
								min="0"
								value="<?php echo esc_attr( $provider_settings['vonage']['monthly_limit'] ?? '10000' ); ?>">
							<?php esc_html_e( 'messages', 'nonprofitsuite' ); ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status', 'nonprofitsuite' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox"
									id="vonage-active"
									<?php checked( $provider_settings['vonage']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Vonage', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="vonage">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="button" class="button button-secondary send-test" data-provider="vonage">
						<?php esc_html_e( 'Send Test SMS', 'nonprofitsuite' ); ?>
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
.ns-sms-settings {
	margin-top: 20px;
}

.provider-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	padding: 20px;
	margin-bottom: 20px;
}

.provider-section h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #ddd;
	display: flex;
	align-items: center;
	gap: 10px;
}

.provider-logo {
	display: inline-block;
	padding: 5px 12px;
	border-radius: 4px;
	font-size: 14px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.provider-logo.twilio {
	background: #f22f46;
	color: #fff;
}

.provider-logo.plivo {
	background: #00b388;
	color: #fff;
}

.provider-logo.vonage {
	background: #000;
	color: #fff;
}

.connection-status {
	margin-left: 10px;
	font-weight: 600;
}

.connection-status.success {
	color: #46b450;
}

.connection-status.error {
	color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Save provider settings
	$('.provider-form').on('submit', function(e) {
		e.preventDefault();

		const provider = $(this).data('provider');
		const $form = $(this);

		const data = {
			action: 'ns_sms_save_settings',
			nonce: nsSMS.nonce,
			organization_id: <?php echo absint( $organization_id ); ?>,
			provider: provider,
			account_sid: $form.find(`#${provider}-auth-id, #${provider}-account-sid`).val(),
			api_key: $form.find(`#${provider}-auth-token, #${provider}-api-key`).val(),
			api_secret: $form.find(`#${provider}-api-secret`).val() || '',
			phone_number: $form.find(`#${provider}-phone`).val(),
			monthly_limit: $form.find(`#${provider}-limit`).val(),
			is_active: $form.find(`#${provider}-active`).is(':checked') ? 1 : 0
		};

		$.ajax({
			url: nsSMS.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {
				if (response.success) {
					alert('Settings saved successfully!');
				} else {
					alert('Error: ' + response.data.message);
				}
			}
		});
	});

	// Test connection
	$('.test-connection').on('click', function() {
		const provider = $(this).data('provider');
		const $btn = $(this);

		$btn.siblings('.connection-status').remove();
		$btn.prop('disabled', true).text('Testing...');

		$.ajax({
			url: nsSMS.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_sms_test_connection',
				nonce: nsSMS.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				provider: provider
			},
			success: function(response) {
				$btn.prop('disabled', false).text('Test Connection');

				if (response.success) {
					$btn.after('<span class="connection-status success">✓ ' + response.data.message + '</span>');
				} else {
					$btn.after('<span class="connection-status error">✗ ' + response.data.message + '</span>');
				}

				setTimeout(function() {
					$('.connection-status').fadeOut(function() {
						$(this).remove();
					});
				}, 5000);
			}
		});
	});

	// Send test message
	$('.send-test').on('click', function() {
		const provider = $(this).data('provider');
		const phone = prompt('Enter phone number to send test SMS (E.164 format, e.g., +12345678900):');

		if (!phone) return;

		const message = 'This is a test message from NonprofitSuite.';

		$.ajax({
			url: nsSMS.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_sms_send_test',
				nonce: nsSMS.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				provider: provider,
				to: phone,
				message: message
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
				} else {
					alert('Error: ' + response.data.message);
				}
			}
		});
	});
});
</script>
