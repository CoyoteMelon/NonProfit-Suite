<?php
/**
 * AI Settings View
 *
 * Configure AI provider settings (OpenAI, Anthropic, Google AI).
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get organization ID (simplified - in production, get from user's context)
$organization_id = 1;

// Get current settings
$settings_table = $wpdb->prefix . 'ns_ai_settings';
$settings       = $wpdb->get_results(
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
	<h1><?php esc_html_e( 'AI Provider Settings', 'nonprofitsuite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Configure your AI provider credentials and preferences. You can enable multiple providers and switch between them for different use cases.', 'nonprofitsuite' ); ?>
	</p>

	<div class="ns-ai-settings">
		<!-- OpenAI Settings -->
		<div class="provider-section">
			<h2>
				<span class="provider-logo openai">OpenAI</span>
				GPT-4, GPT-3.5
			</h2>

			<form class="provider-form" data-provider="openai">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="openai-api-key"><?php esc_html_e( 'API Key', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="password"
								id="openai-api-key"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['openai']['api_key'] ?? '' ); ?>"
								placeholder="sk-...">
							<p class="description">
								<?php esc_html_e( 'Get your API key from', 'nonprofitsuite' ); ?>
								<a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="openai-model"><?php esc_html_e( 'Default Model', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select id="openai-model">
								<option value="gpt-4" <?php selected( $provider_settings['openai']['model_name'] ?? '', 'gpt-4' ); ?>>
									GPT-4 (Most capable, higher cost)
								</option>
								<option value="gpt-4-32k" <?php selected( $provider_settings['openai']['model_name'] ?? '', 'gpt-4-32k' ); ?>>
									GPT-4 32K (Extended context)
								</option>
								<option value="gpt-3.5-turbo" <?php selected( $provider_settings['openai']['model_name'] ?? '', 'gpt-3.5-turbo' ); ?>>
									GPT-3.5 Turbo (Fast, cost-effective)
								</option>
								<option value="gpt-3.5-turbo-16k" <?php selected( $provider_settings['openai']['model_name'] ?? '', 'gpt-3.5-turbo-16k' ); ?>>
									GPT-3.5 Turbo 16K (Extended context)
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="openai-budget"><?php esc_html_e( 'Monthly Budget', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="openai-budget"
								class="small-text"
								step="0.01"
								min="0"
								value="<?php echo esc_attr( $provider_settings['openai']['monthly_budget'] ?? '100' ); ?>">
							USD
							<?php if ( isset( $provider_settings['openai'] ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Current month spend:', 'nonprofitsuite' ); ?>
									<strong>$<?php echo esc_html( number_format( $provider_settings['openai']['current_month_spend'], 2 ) ); ?></strong>
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
									id="openai-active"
									<?php checked( $provider_settings['openai']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable OpenAI', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="openai">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Anthropic Settings -->
		<div class="provider-section">
			<h2>
				<span class="provider-logo anthropic">Anthropic</span>
				Claude 3 (Opus, Sonnet, Haiku)
			</h2>

			<form class="provider-form" data-provider="anthropic">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="anthropic-api-key"><?php esc_html_e( 'API Key', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="password"
								id="anthropic-api-key"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['anthropic']['api_key'] ?? '' ); ?>"
								placeholder="sk-ant-...">
							<p class="description">
								<?php esc_html_e( 'Get your API key from', 'nonprofitsuite' ); ?>
								<a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="anthropic-model"><?php esc_html_e( 'Default Model', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select id="anthropic-model">
								<option value="claude-3-opus-20240229" <?php selected( $provider_settings['anthropic']['model_name'] ?? '', 'claude-3-opus-20240229' ); ?>>
									Claude 3 Opus (Most capable)
								</option>
								<option value="claude-3-sonnet-20240229" <?php selected( $provider_settings['anthropic']['model_name'] ?? 'claude-3-sonnet-20240229', 'claude-3-sonnet-20240229' ); ?>>
									Claude 3 Sonnet (Balanced performance)
								</option>
								<option value="claude-3-haiku-20240307" <?php selected( $provider_settings['anthropic']['model_name'] ?? '', 'claude-3-haiku-20240307' ); ?>>
									Claude 3 Haiku (Fastest, most cost-effective)
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="anthropic-budget"><?php esc_html_e( 'Monthly Budget', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="anthropic-budget"
								class="small-text"
								step="0.01"
								min="0"
								value="<?php echo esc_attr( $provider_settings['anthropic']['monthly_budget'] ?? '100' ); ?>">
							USD
							<?php if ( isset( $provider_settings['anthropic'] ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Current month spend:', 'nonprofitsuite' ); ?>
									<strong>$<?php echo esc_html( number_format( $provider_settings['anthropic']['current_month_spend'], 2 ) ); ?></strong>
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
									id="anthropic-active"
									<?php checked( $provider_settings['anthropic']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Anthropic', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="anthropic">
						<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Google AI Settings -->
		<div class="provider-section">
			<h2>
				<span class="provider-logo google">Google AI</span>
				Gemini Pro
			</h2>

			<form class="provider-form" data-provider="google">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="google-api-key"><?php esc_html_e( 'API Key', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="password"
								id="google-api-key"
								class="regular-text"
								value="<?php echo esc_attr( $provider_settings['google']['api_key'] ?? '' ); ?>"
								placeholder="AIza...">
							<p class="description">
								<?php esc_html_e( 'Get your API key from', 'nonprofitsuite' ); ?>
								<a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="google-model"><?php esc_html_e( 'Default Model', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select id="google-model">
								<option value="gemini-pro" <?php selected( $provider_settings['google']['model_name'] ?? 'gemini-pro', 'gemini-pro' ); ?>>
									Gemini Pro (Text generation)
								</option>
								<option value="gemini-pro-vision" <?php selected( $provider_settings['google']['model_name'] ?? '', 'gemini-pro-vision' ); ?>>
									Gemini Pro Vision (Text + image)
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="google-budget"><?php esc_html_e( 'Monthly Budget', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="google-budget"
								class="small-text"
								step="0.01"
								min="0"
								value="<?php echo esc_attr( $provider_settings['google']['monthly_budget'] ?? '100' ); ?>">
							USD
							<?php if ( isset( $provider_settings['google'] ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Current month spend:', 'nonprofitsuite' ); ?>
									<strong>$<?php echo esc_html( number_format( $provider_settings['google']['current_month_spend'], 2 ) ); ?></strong>
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
									id="google-active"
									<?php checked( $provider_settings['google']['is_active'] ?? 0, 1 ); ?>>
								<?php esc_html_e( 'Enable Google AI', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-secondary test-connection" data-provider="google">
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
.ns-ai-settings {
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

.provider-logo.openai {
	background: #10a37f;
	color: #fff;
}

.provider-logo.anthropic {
	background: #e85d04;
	color: #fff;
}

.provider-logo.google {
	background: #4285f4;
	color: #fff;
}

.test-connection.testing {
	opacity: 0.6;
	cursor: not-allowed;
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
			action: 'ns_ai_save_settings',
			nonce: nsAI.nonce,
			organization_id: <?php echo absint( $organization_id ); ?>,
			provider: provider,
			api_key: $form.find(`#${provider}-api-key`).val(),
			model_name: $form.find(`#${provider}-model`).val(),
			monthly_budget: $form.find(`#${provider}-budget`).val(),
			is_active: $form.find(`#${provider}-active`).is(':checked') ? 1 : 0
		};

		$.ajax({
			url: nsAI.ajax_url,
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
		const $form = $btn.closest('.provider-form');

		// Remove existing status
		$btn.siblings('.connection-status').remove();

		const data = {
			action: 'ns_ai_test_connection',
			nonce: nsAI.nonce,
			organization_id: <?php echo absint( $organization_id ); ?>,
			provider: provider,
			api_key: $form.find(`#${provider}-api-key`).val(),
			model: $form.find(`#${provider}-model`).val()
		};

		$btn.prop('disabled', true).addClass('testing').text('Testing...');

		$.ajax({
			url: nsAI.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {
				$btn.prop('disabled', false).removeClass('testing').text('Test Connection');

				if (response.success) {
					$btn.after('<span class="connection-status success">✓ ' + response.data.message + '</span>');
				} else {
					$btn.after('<span class="connection-status error">✗ ' + response.data.message + '</span>');
				}

				// Remove status after 5 seconds
				setTimeout(function() {
					$('.connection-status').fadeOut(function() {
						$(this).remove();
					});
				}, 5000);
			},
			error: function() {
				$btn.prop('disabled', false).removeClass('testing').text('Test Connection');
				$btn.after('<span class="connection-status error">✗ Connection failed</span>');
			}
		});
	});
});
</script>
