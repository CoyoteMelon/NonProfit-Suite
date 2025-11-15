<?php
/**
 * Sweep Schedules Admin Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Sweep Schedules', 'nonprofitsuite' ); ?></h1>
	<p><?php esc_html_e( 'Configure automated fund sweeping from payment processors to your bank accounts.', 'nonprofitsuite' ); ?></p>

	<div class="ns-sweep-schedules-container">
		<!-- Configured Schedules -->
		<div class="ns-sweep-schedules-list">
			<h2><?php esc_html_e( 'Configured Sweep Schedules', 'nonprofitsuite' ); ?></h2>

			<?php if ( empty( $sweep_schedules ) ) : ?>
				<p><?php esc_html_e( 'No sweep schedules configured yet.', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Destination', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Amount Rules', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Next Run', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sweep_schedules as $schedule ) : ?>
							<tr data-schedule-id="<?php echo esc_attr( $schedule['id'] ); ?>">
								<td>
									<?php
									if ( 'processor' === $schedule['source_type'] ) {
										$source = $wpdb->get_var( $wpdb->prepare( "SELECT processor_name FROM {$wpdb->prefix}ns_payment_processors WHERE id = %d", $schedule['source_id'] ) );
										echo esc_html( $source ) . ' <small>(Processor)</small>';
									} else {
										$source = $wpdb->get_var( $wpdb->prepare( "SELECT account_name FROM {$wpdb->prefix}ns_bank_accounts WHERE id = %d", $schedule['source_id'] ) );
										echo esc_html( $source ) . ' <small>(Bank)</small>';
									}
									?>
								</td>
								<td>
									<?php
									$dest = $wpdb->get_var( $wpdb->prepare( "SELECT account_name FROM {$wpdb->prefix}ns_bank_accounts WHERE id = %d", $schedule['destination_account_id'] ) );
									echo esc_html( $dest );
									?>
								</td>
								<td>
									<?php
									echo esc_html( ucfirst( $schedule['sweep_frequency'] ) );
									if ( $schedule['schedule_time'] ) {
										echo '<br><small>' . esc_html( $schedule['schedule_time'] ) . '</small>';
									}
									?>
								</td>
								<td>
									<?php
									echo 'Min: $' . esc_html( number_format( $schedule['minimum_amount'], 2 ) ) . '<br>';
									echo 'Buffer: $' . esc_html( number_format( $schedule['leave_buffer_amount'], 2 ) ) . '<br>';
									echo 'Sweep: ' . esc_html( $schedule['sweep_percentage'] ) . '%';
									?>
								</td>
								<td>
									<?php if ( $schedule['is_active'] ) : ?>
										<span class="ns-status-badge ns-status-active"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></span>
									<?php else : ?>
										<span class="ns-status-badge ns-status-inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									if ( $schedule['next_run_at'] ) {
										echo esc_html( date( 'M j, Y g:i A', strtotime( $schedule['next_run_at'] ) ) );
									} else {
										echo '—';
									}
									?>
								</td>
								<td>
									<button class="button button-small ns-test-sweep" data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
										<?php esc_html_e( 'Test', 'nonprofitsuite' ); ?>
									</button>
									<button class="button button-small ns-edit-sweep" data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
										<?php esc_html_e( 'Edit', 'nonprofitsuite' ); ?>
									</button>
									<button class="button button-small ns-delete-sweep" data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Add New Sweep Schedule -->
		<div class="ns-add-sweep-schedule">
			<h2><?php esc_html_e( 'Add Sweep Schedule', 'nonprofitsuite' ); ?></h2>

			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Recommended Setup:', 'nonprofitsuite' ); ?></strong>
				</p>
				<ol>
					<li><?php esc_html_e( 'Schedule 1: Nightly sweep from payment processors → Online Payments account', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Schedule 2: Daily sweep from Online Payments → Operating account (leave buffer for chargebacks)', 'nonprofitsuite' ); ?></li>
				</ol>
			</div>

			<form id="ns-sweep-schedule-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="source_type"><?php esc_html_e( 'Source Type', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="source_type" id="source_type" class="regular-text" required>
								<option value="processor"><?php esc_html_e( 'Payment Processor', 'nonprofitsuite' ); ?></option>
								<option value="bank_account"><?php esc_html_e( 'Bank Account', 'nonprofitsuite' ); ?></option>
							</select>
						</td>
					</tr>

					<tr id="processor_source_row">
						<th scope="row">
							<label for="processor_id"><?php esc_html_e( 'Payment Processor', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="processor_id" id="processor_id" class="regular-text">
								<option value=""><?php esc_html_e( 'Select processor...', 'nonprofitsuite' ); ?></option>
								<?php foreach ( $processors as $proc ) : ?>
									<option value="<?php echo esc_attr( $proc['id'] ); ?>">
										<?php echo esc_html( $proc['processor_name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr id="bank_source_row" style="display: none;">
						<th scope="row">
							<label for="bank_account_id"><?php esc_html_e( 'Source Bank Account', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="bank_account_id" id="bank_account_id" class="regular-text">
								<option value=""><?php esc_html_e( 'Select account...', 'nonprofitsuite' ); ?></option>
								<?php foreach ( $bank_accounts as $account ) : ?>
									<option value="<?php echo esc_attr( $account['id'] ); ?>">
										<?php echo esc_html( $account['account_name'] ) . ' (' . esc_html( ucfirst( $account['account_type'] ) ) . ')'; ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="destination_account_id"><?php esc_html_e( 'Destination Account', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="destination_account_id" id="destination_account_id" class="regular-text" required>
								<option value=""><?php esc_html_e( 'Select account...', 'nonprofitsuite' ); ?></option>
								<?php foreach ( $bank_accounts as $account ) : ?>
									<option value="<?php echo esc_attr( $account['id'] ); ?>">
										<?php echo esc_html( $account['account_name'] ) . ' (' . esc_html( ucfirst( $account['account_type'] ) ) . ')'; ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sweep_frequency"><?php esc_html_e( 'Frequency', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="sweep_frequency" id="sweep_frequency" class="regular-text" required>
								<option value="nightly"><?php esc_html_e( 'Nightly', 'nonprofitsuite' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Daily', 'nonprofitsuite' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'nonprofitsuite' ); ?></option>
								<option value="monthly"><?php esc_html_e( 'Monthly', 'nonprofitsuite' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="schedule_time"><?php esc_html_e( 'Schedule Time', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="time" name="schedule_time" id="schedule_time" value="02:00" class="regular-text">
							<p class="description"><?php esc_html_e( 'Time of day to run sweep (24-hour format)', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="minimum_amount"><?php esc_html_e( 'Minimum Amount', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="minimum_amount" id="minimum_amount" value="10" step="0.01" class="regular-text">
							<p class="description"><?php esc_html_e( 'Only sweep if balance exceeds this amount', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="leave_buffer_amount"><?php esc_html_e( 'Leave Buffer Amount', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="leave_buffer_amount" id="leave_buffer_amount" value="100" step="0.01" class="regular-text">
							<p class="description"><?php esc_html_e( 'Keep this amount in source for chargebacks/refunds', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sweep_percentage"><?php esc_html_e( 'Sweep Percentage', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="sweep_percentage" id="sweep_percentage" value="100" min="1" max="100" class="regular-text">
							<p class="description"><?php esc_html_e( 'Percentage of available balance to sweep (after buffer)', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="is_active" id="is_active" value="1" checked>
								<?php esc_html_e( 'Active (run automatically)', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Sweep Schedule', 'nonprofitsuite' ); ?></button>
					<button type="button" class="button ns-cancel-sweep"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Toggle source fields
	$('#source_type').on('change', function() {
		if ($(this).val() === 'processor') {
			$('#processor_source_row').show();
			$('#bank_source_row').hide();
		} else {
			$('#processor_source_row').hide();
			$('#bank_source_row').show();
		}
	});

	// Save sweep schedule
	$('#ns-sweep-schedule-form').on('submit', function(e) {
		e.preventDefault();

		const sourceType = $('#source_type').val();
		const sourceId = sourceType === 'processor' ? $('#processor_id').val() : $('#bank_account_id').val();

		const data = {
			action: 'ns_save_sweep_schedule',
			nonce: nsPaymentAdmin.nonce,
			source_type: sourceType,
			source_id: sourceId,
			destination_account_id: $('#destination_account_id').val(),
			sweep_frequency: $('#sweep_frequency').val(),
			schedule_time: $('#schedule_time').val(),
			minimum_amount: $('#minimum_amount').val(),
			leave_buffer_amount: $('#leave_buffer_amount').val(),
			sweep_percentage: $('#sweep_percentage').val(),
			is_active: $('#is_active').is(':checked') ? 1 : 0
		};

		$.post(nsPaymentAdmin.ajaxurl, data, function(response) {
			if (response.success) {
				alert(response.data.message);
				location.reload();
			} else {
				alert('Error: ' + response.data.message);
			}
		});
	});

	// Test sweep
	$(document).on('click', '.ns-test-sweep', function() {
		if (!confirm('This will execute a test sweep. Continue?')) return;

		const scheduleId = $(this).data('id');
		$.post(nsPaymentAdmin.ajaxurl, {
			action: 'ns_test_sweep',
			nonce: nsPaymentAdmin.nonce,
			schedule_id: scheduleId
		}, function(response) {
			if (response.success) {
				alert('Sweep executed successfully!\n' + JSON.stringify(response.data.result, null, 2));
			} else {
				alert('Error: ' + response.data.message);
			}
		});
	});
});
</script>

<style>
.ns-sweep-schedules-container {
	margin-top: 20px;
}

.ns-sweep-schedules-list,
.ns-add-sweep-schedule {
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
</style>
