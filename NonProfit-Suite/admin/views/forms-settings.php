<?php
/**
 * Forms Settings View
 *
 * Configure form provider integrations.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1; // TODO: Get from current user context
$table           = $wpdb->prefix . 'ns_forms_settings';

// Get existing settings
$settings = array();
$results  = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE organization_id = %d",
		$organization_id
	),
	ARRAY_A
);

foreach ( $results as $row ) {
	$settings[ $row['provider'] ] = $row;
}
?>

<div class="wrap">
	<h1>Forms & Surveys Settings</h1>
	<p>Configure your form provider integrations. Connect to external platforms or use the built-in form builder.</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'ns_forms_settings', 'ns_forms_settings_nonce' ); ?>

		<h2>Built-in Forms</h2>
		<p>Use the NonprofitSuite built-in form builder to create custom forms without any external dependencies.</p>
		<p><em>Built-in forms are always available and don't require any configuration.</em></p>

		<hr>

		<h2>Google Forms</h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label>
						<input type="checkbox" name="google_forms_enabled" value="1" <?php checked( isset( $settings['google_forms'] ) ); ?>>
						Enable Google Forms Integration
					</label>
				</th>
			</tr>
			<tr>
				<th scope="row">
					<label for="google_forms_access_token">Access Token</label>
				</th>
				<td>
					<input type="text" id="google_forms_access_token" name="google_forms_access_token" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['google_forms']['oauth_token'] ?? '' ); ?>">
					<p class="description">OAuth 2.0 access token for Google Forms API.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="google_forms_refresh_token">Refresh Token</label>
				</th>
				<td>
					<input type="text" id="google_forms_refresh_token" name="google_forms_refresh_token" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['google_forms']['oauth_refresh_token'] ?? '' ); ?>">
					<p class="description">OAuth 2.0 refresh token for token renewal.</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" class="button ns-test-connection" data-provider="google_forms">
						Test Connection
					</button>
					<span class="ns-connection-status"></span>
				</td>
			</tr>
		</table>

		<hr>

		<h2>Typeform</h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label>
						<input type="checkbox" name="typeform_enabled" value="1" <?php checked( isset( $settings['typeform'] ) ); ?>>
						Enable Typeform Integration
					</label>
				</th>
			</tr>
			<tr>
				<th scope="row">
					<label for="typeform_api_key">Personal Access Token</label>
				</th>
				<td>
					<input type="text" id="typeform_api_key" name="typeform_api_key" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['typeform']['api_key'] ?? '' ); ?>">
					<p class="description">Get your token from <a href="https://admin.typeform.com/account#/section/tokens" target="_blank">Typeform Account Settings</a>.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="typeform_webhook_secret">Webhook Secret</label>
				</th>
				<td>
					<input type="text" id="typeform_webhook_secret" name="typeform_webhook_secret" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['typeform']['webhook_secret'] ?? '' ); ?>">
					<p class="description">Optional webhook secret for signature validation.</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" class="button ns-test-connection" data-provider="typeform">
						Test Connection
					</button>
					<span class="ns-connection-status"></span>
				</td>
			</tr>
		</table>

		<hr>

		<h2>JotForm</h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label>
						<input type="checkbox" name="jotform_enabled" value="1" <?php checked( isset( $settings['jotform'] ) ); ?>>
						Enable JotForm Integration
					</label>
				</th>
			</tr>
			<tr>
				<th scope="row">
					<label for="jotform_api_key">API Key</label>
				</th>
				<td>
					<input type="text" id="jotform_api_key" name="jotform_api_key" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['jotform']['api_key'] ?? '' ); ?>">
					<p class="description">Get your API key from <a href="https://www.jotform.com/myaccount/api" target="_blank">JotForm API Settings</a>.</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" class="button ns-test-connection" data-provider="jotform">
						Test Connection
					</button>
					<span class="ns-connection-status"></span>
				</td>
			</tr>
		</table>

		<hr>

		<h2>Webhook URL</h2>
		<p>Use this webhook URL when configuring webhooks in your form providers:</p>
		<p><code><?php echo esc_html( rest_url( 'nonprofitsuite/v1/forms/webhook/{provider}/{form_id}' ) ); ?></code></p>
		<p class="description">Replace <code>{provider}</code> with the provider name (typeform, jotform) and <code>{form_id}</code> with your form ID.</p>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
		</p>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	$('.ns-test-connection').on('click', function() {
		var btn = $(this);
		var provider = btn.data('provider');
		var statusSpan = btn.siblings('.ns-connection-status');
		
		btn.prop('disabled', true).text('Testing...');
		statusSpan.html('');
		
		$.post(ajaxurl, {
			action: 'ns_test_form_connection',
			nonce: '<?php echo wp_create_nonce( 'ns_forms_admin' ); ?>',
			provider: provider
		}, function(response) {
			if (response.success) {
				statusSpan.html('<span style="color: green;">✓ ' + response.data + '</span>');
			} else {
				statusSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
			}
			btn.prop('disabled', false).text('Test Connection');
		});
	});
});
</script>
