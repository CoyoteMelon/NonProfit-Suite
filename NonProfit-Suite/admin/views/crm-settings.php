<?php
/**
 * CRM Settings Page
 *
 * Configure CRM integrations for an organization.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$available_providers = array(
	'salesforce' => 'Salesforce Nonprofit Cloud',
	'hubspot'    => 'HubSpot CRM',
	'bloomerang' => 'Bloomerang',
);

?>

<div class="wrap">
	<h1>CRM Integrations</h1>
	<p>Configure your CRM integrations to sync contact, donation, and activity data.</p>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $available_providers as $provider_key => $provider_name ) : ?>
			<a href="#<?php echo esc_attr( $provider_key ); ?>" class="nav-tab <?php echo empty( $active_tab ) || $active_tab === $provider_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $provider_name ); ?>
			</a>
			<?php $active_tab = $provider_key; ?>
		<?php endforeach; ?>
	</h2>

	<?php foreach ( $available_providers as $provider_key => $provider_name ) : ?>
		<?php
		$provider_settings = isset( $active_providers[ $provider_key ] ) ? $active_providers[ $provider_key ] : array();
		$is_active = ! empty( $provider_settings ) && $provider_settings['is_active'];
		?>

		<div id="<?php echo esc_attr( $provider_key ); ?>" class="ns-crm-tab-content" style="<?php echo $provider_key !== array_key_first( $available_providers ) ? 'display:none;' : ''; ?>">
			<form class="ns-crm-settings-form" data-provider="<?php echo esc_attr( $provider_key ); ?>">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label>CRM Mode</label>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( $provider_key ); ?>[crm_mode]" value="disabled" <?php checked( isset( $provider_settings['crm_mode'] ) ? $provider_settings['crm_mode'] : 'disabled', 'disabled' ); ?>>
									Disabled - Use only NonprofitSuite database
								</label><br>
								<label>
									<input type="radio" name="<?php echo esc_attr( $provider_key ); ?>[crm_mode]" value="builtin" <?php checked( isset( $provider_settings['crm_mode'] ) ? $provider_settings['crm_mode'] : '', 'builtin' ); ?>>
									Built-in - Use NonprofitSuite as primary CRM
								</label><br>
								<label>
									<input type="radio" name="<?php echo esc_attr( $provider_key ); ?>[crm_mode]" value="external" <?php checked( isset( $provider_settings['crm_mode'] ) ? $provider_settings['crm_mode'] : '', 'external' ); ?>>
									External - Sync to <?php echo esc_html( $provider_name ); ?>
								</label><br>
								<label>
									<input type="radio" name="<?php echo esc_attr( $provider_key ); ?>[crm_mode]" value="both" <?php checked( isset( $provider_settings['crm_mode'] ) ? $provider_settings['crm_mode'] : '', 'both' ); ?>>
									Both - Maintain data in both systems
								</label>
							</fieldset>
							<p class="description">Choose how you want to use this CRM. You can switch anytime.</p>
						</td>
					</tr>

					<?php if ( $provider_key === 'salesforce' ) : ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $provider_key ); ?>_instance_url">Instance URL</label>
							</th>
							<td>
								<input type="url" id="<?php echo esc_attr( $provider_key ); ?>_instance_url" name="<?php echo esc_attr( $provider_key ); ?>[api_url]" value="<?php echo esc_attr( $provider_settings['api_url'] ?? '' ); ?>" class="regular-text">
								<p class="description">Your Salesforce instance URL (e.g., https://yourorg.my.salesforce.com)</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $provider_key ); ?>_client_id">Client ID</label>
							</th>
							<td>
								<input type="text" id="<?php echo esc_attr( $provider_key ); ?>_client_id" name="<?php echo esc_attr( $provider_key ); ?>[extra][client_id]" value="<?php echo esc_attr( json_decode( $provider_settings['settings'] ?? '{}', true )['client_id'] ?? '' ); ?>" class="regular-text">
								<p class="description">Connected App Client ID from Salesforce</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $provider_key ); ?>_client_secret">Client Secret</label>
							</th>
							<td>
								<input type="password" id="<?php echo esc_attr( $provider_key ); ?>_client_secret" name="<?php echo esc_attr( $provider_key ); ?>[extra][client_secret]" value="<?php echo esc_attr( json_decode( $provider_settings['settings'] ?? '{}', true )['client_secret'] ?? '' ); ?>" class="regular-text">
								<p class="description">Connected App Client Secret from Salesforce</p>
							</td>
						</tr>
					<?php elseif ( $provider_key === 'hubspot' ) : ?>
						<tr>
							<th scope="row">
								<label>Authentication Type</label>
							</th>
							<td>
								<select name="<?php echo esc_attr( $provider_key ); ?>[extra][auth_type]">
									<option value="api_key" <?php selected( json_decode( $provider_settings['settings'] ?? '{}', true )['auth_type'] ?? 'api_key', 'api_key' ); ?>>API Key</option>
									<option value="oauth" <?php selected( json_decode( $provider_settings['settings'] ?? '{}', true )['auth_type'] ?? '', 'oauth' ); ?>>OAuth 2.0</option>
								</select>
							</td>
						</tr>
						<tr class="hubspot-api-key">
							<th scope="row">
								<label for="<?php echo esc_attr( $provider_key ); ?>_api_key">API Key</label>
							</th>
							<td>
								<input type="text" id="<?php echo esc_attr( $provider_key ); ?>_api_key" name="<?php echo esc_attr( $provider_key ); ?>[api_key]" value="<?php echo esc_attr( $provider_settings['api_key'] ?? '' ); ?>" class="regular-text">
								<p class="description">Your HubSpot API key</p>
							</td>
						</tr>
					<?php elseif ( $provider_key === 'bloomerang' ) : ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $provider_key ); ?>_api_key">API Key</label>
							</th>
							<td>
								<input type="text" id="<?php echo esc_attr( $provider_key ); ?>_api_key" name="<?php echo esc_attr( $provider_key ); ?>[api_key]" value="<?php echo esc_attr( $provider_settings['api_key'] ?? '' ); ?>" class="regular-text">
								<p class="description">Your Bloomerang API key</p>
							</td>
						</tr>
					<?php endif; ?>

					<tr>
						<th scope="row">
							<label>Sync Direction</label>
						</th>
						<td>
							<select name="<?php echo esc_attr( $provider_key ); ?>[sync_direction]">
								<option value="push" <?php selected( $provider_settings['sync_direction'] ?? 'push', 'push' ); ?>>Push (NS → CRM)</option>
								<option value="pull" <?php selected( $provider_settings['sync_direction'] ?? '', 'pull' ); ?>>Pull (CRM → NS)</option>
								<option value="bidirectional" <?php selected( $provider_settings['sync_direction'] ?? '', 'bidirectional' ); ?>>Bidirectional (Both ways)</option>
							</select>
							<p class="description">Choose sync direction</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label>Sync Frequency</label>
						</th>
						<td>
							<select name="<?php echo esc_attr( $provider_key ); ?>[sync_frequency]">
								<option value="realtime" <?php selected( $provider_settings['sync_frequency'] ?? 'hourly', 'realtime' ); ?>>Real-time (as changes occur)</option>
								<option value="hourly" <?php selected( $provider_settings['sync_frequency'] ?? 'hourly', 'hourly' ); ?>>Hourly</option>
								<option value="daily" <?php selected( $provider_settings['sync_frequency'] ?? '', 'daily' ); ?>>Daily</option>
								<option value="manual" <?php selected( $provider_settings['sync_frequency'] ?? '', 'manual' ); ?>>Manual only</option>
							</select>
							<p class="description">How often to sync data</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label>Active</label>
						</th>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $provider_key ); ?>[is_active]" value="1" <?php checked( $is_active, true ); ?>>
							<span>Enable this CRM integration</span>
						</td>
					</tr>
				</table>

				<input type="hidden" name="organization_id" value="<?php echo esc_attr( $org_id ); ?>">

				<p class="submit">
					<button type="button" class="button ns-test-connection" data-provider="<?php echo esc_attr( $provider_key ); ?>">Test Connection</button>
					<button type="submit" class="button button-primary">Save Settings</button>
					<?php if ( $is_active ) : ?>
						<button type="button" class="button ns-run-sync" data-provider="<?php echo esc_attr( $provider_key ); ?>">Run Sync Now</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-crm-field-mappings&provider=' . $provider_key . '&organization_id=' . $org_id ) ); ?>" class="button">Configure Field Mappings</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-crm-sync-log&organization_id=' . $org_id ) ); ?>" class="button">View Sync Log</a>
					<?php endif; ?>
				</p>
			</form>
		</div>
	<?php endforeach; ?>
</div>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.ns-crm-tab-content').hide();
		$($(this).attr('href')).show();
	});

	// Test connection
	$('.ns-test-connection').on('click', function() {
		var button = $(this);
		var provider = button.data('provider');
		var orgId = $('input[name="organization_id"]').val();

		button.prop('disabled', true).text('Testing...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_test_crm_connection',
				provider: provider,
				organization_id: orgId,
				nonce: '<?php echo wp_create_nonce( 'ns_crm_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
				} else {
					alert('Connection failed: ' + response.data.message);
				}
				button.prop('disabled', false).text('Test Connection');
			},
			error: function() {
				alert('Connection test failed.');
				button.prop('disabled', false).text('Test Connection');
			}
		});
	});

	// Save settings
	$('.ns-crm-settings-form').on('submit', function(e) {
		e.preventDefault();
		var form = $(this);
		var provider = form.data('provider');
		var formData = form.serializeArray();

		var settings = {};
		formData.forEach(function(field) {
			if (field.name !== 'organization_id' && field.name !== 'provider') {
				var match = field.name.match(/\[([^\]]+)\]/g);
				if (match) {
					var keys = match.map(function(k) { return k.replace(/[\[\]]/g, ''); });
					var current = settings;
					for (var i = 0; i < keys.length - 1; i++) {
						if (!current[keys[i]]) current[keys[i]] = {};
						current = current[keys[i]];
					}
					current[keys[keys.length - 1]] = field.value;
				}
			}
		});

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_save_crm_settings',
				provider: provider,
				organization_id: form.find('input[name="organization_id"]').val(),
				settings: settings,
				nonce: '<?php echo wp_create_nonce( 'ns_crm_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert('Save failed: ' + response.data.message);
				}
			}
		});
	});

	// Run sync
	$('.ns-run-sync').on('click', function() {
		var button = $(this);
		var provider = button.data('provider');
		var orgId = $('input[name="organization_id"]').val();

		if (!confirm('Are you sure you want to run a sync now? This may take several minutes.')) {
			return;
		}

		button.prop('disabled', true).text('Syncing...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_run_manual_sync',
				provider: provider,
				organization_id: orgId,
				nonce: '<?php echo wp_create_nonce( 'ns_crm_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
				} else {
					alert('Sync failed: ' + response.data.message);
				}
				button.prop('disabled', false).text('Run Sync Now');
			},
			error: function() {
				alert('Sync failed.');
				button.prop('disabled', false).text('Run Sync Now');
			}
		});
	});
});
</script>

<style>
.ns-crm-tab-content {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	border-top: none;
}
</style>
