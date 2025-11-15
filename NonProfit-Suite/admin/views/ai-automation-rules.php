<?php
/**
 * AI Automation Rules View
 *
 * Displays and manages AI automation rules.
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

// Get automation rules
$table = $wpdb->prefix . 'ns_ai_automation_rules';
$rules = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE organization_id = %d ORDER BY created_at DESC",
		$organization_id
	),
	ARRAY_A
);

// Get active AI providers
$settings_table   = $wpdb->prefix . 'ns_ai_settings';
$active_providers = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT provider, model_name FROM {$settings_table} WHERE organization_id = %d AND is_active = 1",
		$organization_id
	),
	ARRAY_A
);

?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Automation Rules', 'nonprofitsuite' ); ?></h1>
	<button type="button" class="page-title-action" id="new-rule-btn">
		<?php esc_html_e( 'Add New Rule', 'nonprofitsuite' ); ?>
	</button>
	<hr class="wp-header-end">

	<?php if ( empty( $active_providers ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No AI providers are configured. Please configure at least one provider in', 'nonprofitsuite' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-ai-settings' ) ); ?>">
					<?php esc_html_e( 'AI Settings', 'nonprofitsuite' ); ?>
				</a>.
			</p>
		</div>
	<?php endif; ?>

	<div class="ns-automation-rules">
		<?php if ( empty( $rules ) ) : ?>
			<div class="no-items">
				<p><?php esc_html_e( 'No automation rules yet. Create your first rule to automate tasks with AI!', 'nonprofitsuite' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Rule Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'AI Action', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Follow-up Action', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Executions', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rules as $rule ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $rule['rule_name'] ); ?></strong>
							</td>
							<td>
								<span class="badge trigger-badge">
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $rule['trigger_type'] ) ) ); ?>
								</span>
							</td>
							<td>
								<span class="badge ai-action-badge">
									<?php echo esc_html( ucfirst( $rule['ai_action'] ) ); ?>
								</span>
							</td>
							<td>
								<span class="badge action-badge">
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $rule['action_type'] ) ) ); ?>
								</span>
							</td>
							<td>
								<span class="provider-badge <?php echo esc_attr( $rule['provider'] ); ?>">
									<?php echo esc_html( ucfirst( $rule['provider'] ) ); ?>
								</span>
							</td>
							<td>
								<?php echo esc_html( number_format( $rule['execution_count'] ) ); ?>
								<?php if ( $rule['last_executed_at'] ) : ?>
									<br>
									<small><?php echo esc_html( human_time_diff( strtotime( $rule['last_executed_at'] ), current_time( 'timestamp' ) ) ); ?> ago</small>
								<?php endif; ?>
							</td>
							<td>
								<label class="switch">
									<input type="checkbox"
										class="toggle-rule"
										data-id="<?php echo esc_attr( $rule['id'] ); ?>"
										<?php checked( $rule['is_active'], 1 ); ?>>
									<span class="slider"></span>
								</label>
							</td>
							<td>
								<button class="button button-small edit-rule" data-id="<?php echo esc_attr( $rule['id'] ); ?>">
									<?php esc_html_e( 'Edit', 'nonprofitsuite' ); ?>
								</button>
								<button class="button button-small delete-rule" data-id="<?php echo esc_attr( $rule['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Rule Modal -->
<div id="rule-modal" class="ns-modal" style="display: none;">
	<div class="ns-modal-content ns-modal-large">
		<span class="ns-modal-close">&times;</span>
		<h2 id="modal-title"><?php esc_html_e( 'New Automation Rule', 'nonprofitsuite' ); ?></h2>

		<form id="rule-form">
			<input type="hidden" id="rule-id" value="">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rule-name"><?php esc_html_e( 'Rule Name', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="text" id="rule-name" class="regular-text" required>
						<p class="description"><?php esc_html_e( 'A descriptive name for this automation rule.', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="trigger-type"><?php esc_html_e( 'Trigger Event', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="trigger-type" class="regular-text" required>
							<option value=""><?php esc_html_e( 'Select Trigger', 'nonprofitsuite' ); ?></option>
							<option value="form_submitted"><?php esc_html_e( 'Form Submitted', 'nonprofitsuite' ); ?></option>
							<option value="task_created"><?php esc_html_e( 'Task Created', 'nonprofitsuite' ); ?></option>
							<option value="email_received"><?php esc_html_e( 'Email Received', 'nonprofitsuite' ); ?></option>
							<option value="document_uploaded"><?php esc_html_e( 'Document Uploaded', 'nonprofitsuite' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'What event should trigger this automation?', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ai-provider"><?php esc_html_e( 'AI Provider', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="ai-provider" required>
							<option value=""><?php esc_html_e( 'Select Provider', 'nonprofitsuite' ); ?></option>
							<?php foreach ( $active_providers as $provider ) : ?>
								<option value="<?php echo esc_attr( $provider['provider'] ); ?>">
									<?php echo esc_html( ucfirst( $provider['provider'] ) ); ?> (<?php echo esc_html( $provider['model_name'] ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ai-action"><?php esc_html_e( 'AI Action', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="ai-action" required>
							<option value=""><?php esc_html_e( 'Select AI Action', 'nonprofitsuite' ); ?></option>
							<option value="summarize"><?php esc_html_e( 'Summarize Content', 'nonprofitsuite' ); ?></option>
							<option value="categorize"><?php esc_html_e( 'Categorize Content', 'nonprofitsuite' ); ?></option>
							<option value="extract"><?php esc_html_e( 'Extract Data', 'nonprofitsuite' ); ?></option>
							<option value="respond"><?php esc_html_e( 'Generate Response', 'nonprofitsuite' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'What should the AI do with the content?', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr id="ai-prompt-row">
					<th scope="row">
						<label for="ai-prompt"><?php esc_html_e( 'AI Prompt', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<textarea id="ai-prompt" rows="4" class="large-text"></textarea>
						<p class="description"><?php esc_html_e( 'Additional instructions for the AI. Use {content} as a placeholder for the triggered content.', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="action-type"><?php esc_html_e( 'Follow-up Action', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="action-type" required>
							<option value=""><?php esc_html_e( 'Select Action', 'nonprofitsuite' ); ?></option>
							<option value="create_task"><?php esc_html_e( 'Create Task', 'nonprofitsuite' ); ?></option>
							<option value="send_email"><?php esc_html_e( 'Send Email', 'nonprofitsuite' ); ?></option>
							<option value="update_field"><?php esc_html_e( 'Update Field', 'nonprofitsuite' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'What should happen with the AI result?', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="is-active"><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="is-active" value="1">
							<?php esc_html_e( 'Active', 'nonprofitsuite' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Rule', 'nonprofitsuite' ); ?>
				</button>
				<button type="button" class="button" id="cancel-rule-btn">
					<?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<style>
.ns-automation-rules {
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

.trigger-badge {
	background: #f0f0f1;
	color: #2c3338;
}

.ai-action-badge {
	background: #e0f2fe;
	color: #0369a1;
}

.action-badge {
	background: #fef3c7;
	color: #92400e;
}

.provider-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.provider-badge.openai {
	background: #10a37f;
	color: #fff;
}

.provider-badge.anthropic {
	background: #e85d04;
	color: #fff;
}

.provider-badge.google {
	background: #4285f4;
	color: #fff;
}

/* Toggle Switch */
.switch {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 24px;
}

.switch input {
	opacity: 0;
	width: 0;
	height: 0;
}

.slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: .4s;
	border-radius: 24px;
}

.slider:before {
	position: absolute;
	content: "";
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: .4s;
	border-radius: 50%;
}

input:checked + .slider {
	background-color: #2271b1;
}

input:checked + .slider:before {
	transform: translateX(26px);
}

.ns-modal-large {
	max-width: 800px;
}
</style>

<script>
jQuery(document).ready(function($) {
	// New rule button
	$('#new-rule-btn').on('click', function() {
		$('#rule-form')[0].reset();
		$('#rule-id').val('');
		$('#modal-title').text('<?php esc_html_e( 'New Automation Rule', 'nonprofitsuite' ); ?>');
		$('#rule-modal').show();
	});

	// Close modal
	$('.ns-modal-close, #cancel-rule-btn').on('click', function() {
		$('#rule-modal').hide();
	});

	// Save rule
	$('#rule-form').on('submit', function(e) {
		e.preventDefault();

		const data = {
			action: 'ns_ai_save_automation_rule',
			nonce: nsAI.nonce,
			organization_id: <?php echo absint( $organization_id ); ?>,
			rule_id: $('#rule-id').val(),
			rule_name: $('#rule-name').val(),
			trigger_type: $('#trigger-type').val(),
			trigger_config: {},
			provider: $('#ai-provider').val(),
			ai_action: $('#ai-action').val(),
			ai_prompt: $('#ai-prompt').val(),
			action_type: $('#action-type').val(),
			action_config: {},
			is_active: $('#is-active').is(':checked') ? 1 : 0
		};

		$.ajax({
			url: nsAI.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	// Edit rule
	$('.edit-rule').on('click', function() {
		const ruleId = $(this).data('id');
		// Load rule data and populate form
		// For now, just show modal
		$('#modal-title').text('<?php esc_html_e( 'Edit Automation Rule', 'nonprofitsuite' ); ?>');
		$('#rule-id').val(ruleId);
		$('#rule-modal').show();
	});

	// Delete rule
	$('.delete-rule').on('click', function() {
		if (!confirm('Are you sure you want to delete this automation rule?')) {
			return;
		}

		const ruleId = $(this).data('id');

		$.ajax({
			url: nsAI.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_ai_delete_automation_rule',
				nonce: nsAI.nonce,
				rule_id: ruleId
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

	// Toggle rule
	$('.toggle-rule').on('change', function() {
		const ruleId = $(this).data('id');
		const isActive = $(this).is(':checked') ? 1 : 0;

		$.ajax({
			url: nsAI.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_ai_toggle_automation_rule',
				nonce: nsAI.nonce,
				rule_id: ruleId,
				is_active: isActive
			},
			success: function(response) {
				if (!response.success) {
					alert(response.data.message);
				}
			}
		});
	});
});
</script>
