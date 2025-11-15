<?php
/**
 * Project Management Settings View
 *
 * Configure project management provider integrations.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1; // TODO: Get from current user context
$table           = $wpdb->prefix . 'ns_project_management_settings';

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
	<h1>Project Management Settings</h1>
	<p>Configure your project management tool integrations. Connect to external platforms or use the built-in system.</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'ns_pm_settings', 'ns_pm_settings_nonce' ); ?>

		<h2>Built-in Project Management</h2>
		<p>Use the NonprofitSuite built-in project management system without any external dependencies.</p>
		<p><em>Built-in project management is always available and doesn't require any configuration.</em></p>

		<hr>

		<h2>Asana</h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label>
						<input type="checkbox" name="asana_enabled" value="1" <?php checked( isset( $settings['asana'] ) ); ?>>
						Enable Asana Integration
					</label>
				</th>
			</tr>
			<tr>
				<th scope="row">
					<label for="asana_access_token">Personal Access Token</label>
				</th>
				<td>
					<input type="text" id="asana_access_token" name="asana_access_token" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['asana']['oauth_token'] ?? '' ); ?>">
					<p class="description">Get your token from <a href="https://app.asana.com/0/my-apps" target="_blank">Asana Developer Console</a>.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="asana_workspace_id">Workspace GID</label>
				</th>
				<td>
					<input type="text" id="asana_workspace_id" name="asana_workspace_id" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['asana']['workspace_id'] ?? '' ); ?>">
					<p class="description">Your Asana workspace ID.</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" class="button ns-test-connection" data-provider="asana">
						Test Connection
					</button>
					<button type="button" class="button ns-sync-projects" data-provider="asana">
						Sync Projects
					</button>
					<span class="ns-connection-status"></span>
				</td>
			</tr>
		</table>

		<hr>

		<h2>Trello</h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label>
						<input type="checkbox" name="trello_enabled" value="1" <?php checked( isset( $settings['trello'] ) ); ?>>
						Enable Trello Integration
					</label>
				</th>
			</tr>
			<tr>
				<th scope="row">
					<label for="trello_api_key">API Key</label>
				</th>
				<td>
					<input type="text" id="trello_api_key" name="trello_api_key" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['trello']['api_key'] ?? '' ); ?>">
					<p class="description">Get your API key from <a href="https://trello.com/app-key" target="_blank">Trello Developer API Keys</a>.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="trello_api_token">API Token</label>
				</th>
				<td>
					<input type="text" id="trello_api_token" name="trello_api_token" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['trello']['api_secret'] ?? '' ); ?>">
					<p class="description">Generate a token from the API key page.</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" class="button ns-test-connection" data-provider="trello">
						Test Connection
					</button>
					<button type="button" class="button ns-sync-projects" data-provider="trello">
						Sync Boards
					</button>
					<span class="ns-connection-status"></span>
				</td>
			</tr>
		</table>

		<hr>

		<h2>Monday.com</h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label>
						<input type="checkbox" name="monday_enabled" value="1" <?php checked( isset( $settings['monday'] ) ); ?>>
						Enable Monday.com Integration
					</label>
				</th>
			</tr>
			<tr>
				<th scope="row">
					<label for="monday_api_token">API Token</label>
				</th>
				<td>
					<input type="text" id="monday_api_token" name="monday_api_token" 
						class="regular-text" 
						value="<?php echo esc_attr( $settings['monday']['api_key'] ?? '' ); ?>">
					<p class="description">Get your API token from <a href="https://monday.com/developers/v2#authentication-section" target="_blank">Monday.com API settings</a>.</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<button type="button" class="button ns-test-connection" data-provider="monday">
						Test Connection
					</button>
					<button type="button" class="button ns-sync-projects" data-provider="monday">
						Sync Boards
					</button>
					<span class="ns-connection-status"></span>
				</td>
			</tr>
		</table>

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
			action: 'ns_test_pm_connection',
			nonce: '<?php echo wp_create_nonce( 'ns_pm_admin' ); ?>',
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

	$('.ns-sync-projects').on('click', function() {
		var btn = $(this);
		var provider = btn.data('provider');
		var statusSpan = btn.siblings('.ns-connection-status');
		
		btn.prop('disabled', true).text('Syncing...');
		statusSpan.html('');
		
		$.post(ajaxurl, {
			action: 'ns_sync_projects',
			nonce: '<?php echo wp_create_nonce( 'ns_pm_admin' ); ?>',
			provider: provider
		}, function(response) {
			if (response.success) {
				statusSpan.html('<span style="color: green;">✓ ' + response.data + '</span>');
			} else {
				statusSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
			}
			btn.prop('disabled', false).text('Sync Projects');
		});
	});
});
</script>
