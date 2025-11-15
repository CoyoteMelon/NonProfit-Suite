<?php
/**
 * Payment Processors Admin Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Payment Processors', 'nonprofitsuite' ); ?></h1>
	<p><?php esc_html_e( 'Configure payment processors to accept donations and payments.', 'nonprofitsuite' ); ?></p>

	<div class="ns-processors-container">
		<!-- Configured Processors -->
		<div class="ns-processors-list">
			<h2><?php esc_html_e( 'Configured Processors', 'nonprofitsuite' ); ?></h2>

			<?php if ( empty( $processors ) ) : ?>
				<p><?php esc_html_e( 'No payment processors configured yet.', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Processor', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Preferred', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Limits', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $processors as $processor ) : ?>
							<tr data-processor-id="<?php echo esc_attr( $processor['id'] ); ?>">
								<td><strong><?php echo esc_html( $processor['processor_name'] ); ?></strong></td>
								<td><?php echo esc_html( ucfirst( $processor['processor_type'] ) ); ?></td>
								<td>
									<?php if ( $processor['is_active'] ) : ?>
										<span class="ns-status-badge ns-status-active"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></span>
									<?php else : ?>
										<span class="ns-status-badge ns-status-inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $processor['is_preferred'] ) : ?>
										<span class="dashicons dashicons-star-filled"></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									if ( $processor['min_amount'] > 0 || $processor['max_amount'] > 0 ) {
										echo '$' . esc_html( $processor['min_amount'] ) . ' - $' . esc_html( $processor['max_amount'] );
									} else {
										esc_html_e( 'No limits', 'nonprofitsuite' );
									}
									?>
								</td>
								<td>
									<button class="button button-small ns-edit-processor" data-id="<?php echo esc_attr( $processor['id'] ); ?>">
										<?php esc_html_e( 'Edit', 'nonprofitsuite' ); ?>
									</button>
									<button class="button button-small ns-test-processor" data-id="<?php echo esc_attr( $processor['id'] ); ?>">
										<?php esc_html_e( 'Test', 'nonprofitsuite' ); ?>
									</button>
									<button class="button button-small ns-delete-processor" data-id="<?php echo esc_attr( $processor['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Add New Processor -->
		<div class="ns-add-processor">
			<h2><?php esc_html_e( 'Add Payment Processor', 'nonprofitsuite' ); ?></h2>

			<form id="ns-processor-form">
				<input type="hidden" name="processor_id" id="processor_id" value="0">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="processor_type"><?php esc_html_e( 'Processor Type', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="processor_type" id="processor_type" class="regular-text" required>
								<option value=""><?php esc_html_e( 'Select a processor...', 'nonprofitsuite' ); ?></option>
								<?php foreach ( $available_processors as $key => $proc ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>">
										<?php echo esc_html( $proc['name'] ); ?> - <?php echo esc_html( $proc['description'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="processor_name"><?php esc_html_e( 'Display Name', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text" name="processor_name" id="processor_name" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Name shown to donors', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr class="ns-credentials-section" style="display: none;">
						<th scope="row">
							<label><?php esc_html_e( 'Credentials', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<div id="ns-credential-fields"></div>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="display_order"><?php esc_html_e( 'Display Order', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="display_order" id="display_order" value="0" class="small-text">
							<p class="description"><?php esc_html_e( 'Order shown to donors (lower = higher)', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="min_amount"><?php esc_html_e( 'Minimum Amount', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="min_amount" id="min_amount" value="0" step="0.01" class="small-text">
							<p class="description"><?php esc_html_e( 'Minimum payment amount (0 for no limit)', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="max_amount"><?php esc_html_e( 'Maximum Amount', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="max_amount" id="max_amount" value="0" step="0.01" class="small-text">
							<p class="description"><?php esc_html_e( 'Maximum payment amount (0 for no limit)', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="is_active" id="is_active" value="1" checked>
								<?php esc_html_e( 'Active (accept payments)', 'nonprofitsuite' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="is_preferred" id="is_preferred" value="1">
								<?php esc_html_e( 'Preferred processor (shown first)', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Processor', 'nonprofitsuite' ); ?></button>
					<button type="button" class="button ns-cancel-processor"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				</p>

				<div class="ns-webhook-info" style="display: none;">
					<h3><?php esc_html_e( 'Webhook Configuration', 'nonprofitsuite' ); ?></h3>
					<p><?php esc_html_e( 'Add this webhook URL to your processor dashboard:', 'nonprofitsuite' ); ?></p>
					<input type="text" readonly id="webhook_url" class="regular-text">
					<button type="button" class="button ns-copy-webhook"><?php esc_html_e( 'Copy', 'nonprofitsuite' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Processor type specific credential fields
	const processorFields = <?php echo wp_json_encode( $available_processors ); ?>;

	$('#processor_type').on('change', function() {
		const type = $(this).val();
		if (type && processorFields[type]) {
			const fields = processorFields[type].fields;
			let html = '';
			fields.forEach(field => {
				if (field.type === 'checkbox') {
					html += `<label><input type="checkbox" name="credential_${field.key}" id="credential_${field.key}"> ${field.label}</label><br>`;
				} else if (field.type === 'select') {
					html += `<label>${field.label}</label><select name="credential_${field.key}" id="credential_${field.key}">`;
					field.options.forEach(opt => {
						html += `<option value="${opt}">${opt}</option>`;
					});
					html += '</select><br>';
				} else {
					html += `<label>${field.label}</label><input type="${field.type}" name="credential_${field.key}" id="credential_${field.key}" class="regular-text"><br>`;
				}
			});
			$('#ns-credential-fields').html(html);
			$('.ns-credentials-section').show();
		} else {
			$('.ns-credentials-section').hide();
		}
	});

	// Save processor
	$('#ns-processor-form').on('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(this);
		const credentials = {};

		// Collect credentials
		$('[name^="credential_"]').each(function() {
			const key = $(this).attr('name').replace('credential_', '');
			const val = $(this).is(':checkbox') ? $(this).is(':checked') : $(this).val();
			credentials[key] = val;
		});

		const data = {
			action: 'ns_save_processor',
			nonce: nsPaymentAdmin.nonce,
			processor_id: $('#processor_id').val(),
			processor_type: $('#processor_type').val(),
			processor_name: $('#processor_name').val(),
			credentials: JSON.stringify(credentials),
			is_active: $('#is_active').is(':checked') ? 1 : 0,
			is_preferred: $('#is_preferred').is(':checked') ? 1 : 0,
			display_order: $('#display_order').val(),
			min_amount: $('#min_amount').val(),
			max_amount: $('#max_amount').val()
		};

		$.post(nsPaymentAdmin.ajaxurl, data, function(response) {
			if (response.success) {
				alert(response.data.message);
				if (response.data.webhook_url) {
					$('#webhook_url').val(response.data.webhook_url);
					$('.ns-webhook-info').show();
				}
				location.reload();
			} else {
				alert('Error: ' + response.data.message);
			}
		});
	});

	// Test processor
	$(document).on('click', '.ns-test-processor', function() {
		const processorId = $(this).data('id');
		$.post(nsPaymentAdmin.ajaxurl, {
			action: 'ns_test_processor',
			nonce: nsPaymentAdmin.nonce,
			processor_id: processorId
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
			} else {
				alert('Error: ' + response.data.message);
			}
		});
	});

	// Delete processor
	$(document).on('click', '.ns-delete-processor', function() {
		if (!confirm('Are you sure you want to delete this processor?')) return;

		const processorId = $(this).data('id');
		$.post(nsPaymentAdmin.ajaxurl, {
			action: 'ns_delete_processor',
			nonce: nsPaymentAdmin.nonce,
			processor_id: processorId
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
				location.reload();
			} else {
				alert('Error: ' + response.data.message);
			}
		});
	});

	// Copy webhook URL
	$(document).on('click', '.ns-copy-webhook', function() {
		$('#webhook_url').select();
		document.execCommand('copy');
		alert('Webhook URL copied to clipboard!');
	});
});
</script>

<style>
.ns-processors-container {
	margin-top: 20px;
}

.ns-processors-list,
.ns-add-processor {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-status-badge {
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.ns-status-active {
	background: #d4edda;
	color: #155724;
}

.ns-status-inactive {
	background: #f8d7da;
	color: #721c24;
}

.ns-webhook-info {
	margin-top: 20px;
	padding: 15px;
	background: #f0f0f1;
	border-left: 4px solid #2271b1;
}
</style>
