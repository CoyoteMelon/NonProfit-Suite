<?php
/**
 * Bank Accounts Admin Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Bank Accounts', 'nonprofitsuite' ); ?></h1>
	<p><?php esc_html_e( 'Manage bank accounts for automated fund sweeping and transfers.', 'nonprofitsuite' ); ?></p>

	<div class="ns-bank-accounts-container">
		<!-- Configured Bank Accounts -->
		<div class="ns-bank-accounts-list">
			<h2><?php esc_html_e( 'Configured Bank Accounts', 'nonprofitsuite' ); ?></h2>

			<?php if ( empty( $bank_accounts ) ) : ?>
				<p><?php esc_html_e( 'No bank accounts configured yet.', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Account Name', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Current Balance', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Minimum Buffer', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $bank_accounts as $account ) : ?>
							<tr data-account-id="<?php echo esc_attr( $account['id'] ); ?>">
								<td>
									<strong><?php echo esc_html( $account['account_name'] ); ?></strong>
									<?php
									$details = json_decode( $account['account_details'], true );
									if ( isset( $details['account_number'] ) ) {
										echo '<br><small>****' . esc_html( substr( $details['account_number'], -4 ) ) . '</small>';
									}
									?>
								</td>
								<td>
									<?php
									$type_labels = array(
										'online_payments' => __( 'Online Payments', 'nonprofitsuite' ),
										'operating'       => __( 'Operating', 'nonprofitsuite' ),
										'reserve'         => __( 'Reserve', 'nonprofitsuite' ),
										'payroll'         => __( 'Payroll', 'nonprofitsuite' ),
									);
									echo esc_html( $type_labels[ $account['account_type'] ] ?? $account['account_type'] );
									?>
								</td>
								<td>$<?php echo esc_html( number_format( $account['current_balance'], 2 ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $account['minimum_buffer'], 2 ) ); ?></td>
								<td>
									<button class="button button-small ns-edit-account" data-id="<?php echo esc_attr( $account['id'] ); ?>">
										<?php esc_html_e( 'Edit', 'nonprofitsuite' ); ?>
									</button>
									<button class="button button-small ns-delete-account" data-id="<?php echo esc_attr( $account['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Add New Bank Account -->
		<div class="ns-add-bank-account">
			<h2><?php esc_html_e( 'Add Bank Account', 'nonprofitsuite' ); ?></h2>

			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Important:', 'nonprofitsuite' ); ?></strong>
					<?php esc_html_e( 'Create a separate "Online Payments" account for fraud protection. Funds will automatically sweep from payment processors to this account, then to your protected operating accounts.', 'nonprofitsuite' ); ?>
				</p>
			</div>

			<form id="ns-bank-account-form">
				<input type="hidden" name="account_id" id="account_id" value="0">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="account_name"><?php esc_html_e( 'Account Name', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text" name="account_name" id="account_name" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Internal name for this account', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="account_type"><?php esc_html_e( 'Account Type', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="account_type" id="account_type" class="regular-text" required>
								<option value="online_payments"><?php esc_html_e( 'Online Payments (Recommended for processor sweeps)', 'nonprofitsuite' ); ?></option>
								<option value="operating"><?php esc_html_e( 'Operating (Main organizational funds)', 'nonprofitsuite' ); ?></option>
								<option value="reserve"><?php esc_html_e( 'Reserve (Savings/Contingency)', 'nonprofitsuite' ); ?></option>
								<option value="payroll"><?php esc_html_e( 'Payroll (Employee payments)', 'nonprofitsuite' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="bank_name"><?php esc_html_e( 'Bank Name', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text" name="bank_name" id="bank_name" class="regular-text">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="account_number"><?php esc_html_e( 'Account Number', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="password" name="account_number" id="account_number" class="regular-text">
							<p class="description"><?php esc_html_e( 'Stored securely and encrypted', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="routing_number"><?php esc_html_e( 'Routing Number', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text" name="routing_number" id="routing_number" class="regular-text">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="current_balance"><?php esc_html_e( 'Current Balance', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="current_balance" id="current_balance" value="0" step="0.01" class="regular-text">
							<p class="description"><?php esc_html_e( 'Current account balance (for tracking)', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="minimum_buffer"><?php esc_html_e( 'Minimum Buffer', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="minimum_buffer" id="minimum_buffer" value="100" step="0.01" class="regular-text">
							<p class="description"><?php esc_html_e( 'Minimum amount to keep in account for chargebacks/refunds', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Bank Account', 'nonprofitsuite' ); ?></button>
					<button type="button" class="button ns-cancel-account"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Save bank account
	$('#ns-bank-account-form').on('submit', function(e) {
		e.preventDefault();

		const accountDetails = {
			bank_name: $('#bank_name').val(),
			account_number: $('#account_number').val(),
			routing_number: $('#routing_number').val()
		};

		const data = {
			action: 'ns_save_bank_account',
			nonce: nsPaymentAdmin.nonce,
			account_id: $('#account_id').val(),
			account_name: $('#account_name').val(),
			account_type: $('#account_type').val(),
			account_details: JSON.stringify(accountDetails),
			current_balance: $('#current_balance').val(),
			minimum_buffer: $('#minimum_buffer').val()
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

	// Cancel
	$('.ns-cancel-account').on('click', function() {
		$('#ns-bank-account-form')[0].reset();
		$('#account_id').val('0');
	});
});
</script>

<style>
.ns-bank-accounts-container {
	margin-top: 20px;
}

.ns-bank-accounts-list,
.ns-add-bank-account {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
</style>
