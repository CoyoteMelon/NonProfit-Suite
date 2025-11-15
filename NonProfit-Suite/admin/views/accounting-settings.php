<?php
/**
 * Accounting Settings Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Accounting Settings', 'nonprofitsuite' ); ?></h1>
	<p><?php esc_html_e( 'Configure how NonprofitSuite handles accounting. CPA-friendly - use the built-in ledger, export to external systems, or both.', 'nonprofitsuite' ); ?></p>

	<div class="ns-accounting-settings">
		<form id="ns-accounting-settings-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Accounting Mode', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<label>
							<input type="radio" name="accounting_mode" value="builtin" <?php checked( $accounting_mode, 'builtin' ); ?>>
							<?php esc_html_e( 'Use Built-in Accounting System', 'nonprofitsuite' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Maintain full chart of accounts and journal entries in NonprofitSuite', 'nonprofitsuite' ); ?></p>
						<br>

						<label>
							<input type="radio" name="accounting_mode" value="external" <?php checked( $accounting_mode, 'external' ); ?>>
							<?php esc_html_e( 'Export to External Accounting Software', 'nonprofitsuite' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Export transactions to QuickBooks, Xero, Wave, etc.', 'nonprofitsuite' ); ?></p>
						<br>

						<label>
							<input type="radio" name="accounting_mode" value="both" <?php checked( $accounting_mode, 'both' ); ?>>
							<?php esc_html_e( 'Both - Maintain records and export', 'nonprofitsuite' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Keep full records internally and export as needed for CPA review', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr id="auto_entries_row">
					<th scope="row">
						<label for="auto_entries"><?php esc_html_e( 'Automatic Journal Entries', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="auto_entries" id="auto_entries" value="yes" <?php checked( $auto_entries, 'yes' ); ?>>
							<?php esc_html_e( 'Automatically create journal entries from payment transactions', 'nonprofitsuite' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When payments are received, automatically post to accounting ledger', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Default Accounts', 'nonprofitsuite' ); ?></h2>
			<p><?php esc_html_e( 'Map payment transactions to accounting accounts', 'nonprofitsuite' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="cash_account"><?php esc_html_e( 'Cash/Bank Account', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select name="cash_account" id="cash_account" class="regular-text">
							<option value=""><?php esc_html_e( 'Select account...', 'nonprofitsuite' ); ?></option>
							<?php
							$cash_account = get_option( 'ns_accounting_cash_account' );
							$accounts = NonprofitSuite_Accounting_Manager::get_accounts( 1, array( 'account_type' => 'asset' ) );
							foreach ( $accounts as $account ) :
								?>
								<option value="<?php echo esc_attr( $account['id'] ); ?>" <?php selected( $cash_account, $account['id'] ); ?>>
									<?php echo esc_html( $account['account_number'] . ' - ' . $account['account_name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Debit this account when payments are received', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="revenue_account"><?php esc_html_e( 'Revenue Account', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select name="revenue_account" id="revenue_account" class="regular-text">
							<option value=""><?php esc_html_e( 'Select account...', 'nonprofitsuite' ); ?></option>
							<?php
							$revenue_account = get_option( 'ns_accounting_revenue_account' );
							$accounts = NonprofitSuite_Accounting_Manager::get_accounts( 1, array( 'account_type' => 'revenue' ) );
							foreach ( $accounts as $account ) :
								?>
								<option value="<?php echo esc_attr( $account['id'] ); ?>" <?php selected( $revenue_account, $account['id'] ); ?>>
									<?php echo esc_html( $account['account_number'] . ' - ' . $account['account_name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Credit this account for donation income', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="fee_account"><?php esc_html_e( 'Fee Expense Account', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select name="fee_account" id="fee_account" class="regular-text">
							<option value=""><?php esc_html_e( 'Select account...', 'nonprofitsuite' ); ?></option>
							<?php
							$fee_account = get_option( 'ns_accounting_fee_account' );
							$accounts = NonprofitSuite_Accounting_Manager::get_accounts( 1, array( 'account_type' => 'expense' ) );
							foreach ( $accounts as $account ) :
								?>
								<option value="<?php echo esc_attr( $account['id'] ); ?>" <?php selected( $fee_account, $account['id'] ); ?>>
									<?php echo esc_html( $account['account_number'] . ' - ' . $account['account_name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Debit this account for payment processing fees', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?></button>
			</p>
		</form>

		<div class="ns-info-box">
			<h3><?php esc_html_e( 'CPA-Friendly Accounting', 'nonprofitsuite' ); ?></h3>
			<p><?php esc_html_e( 'NonprofitSuite gives you complete flexibility:', 'nonprofitsuite' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Start without accounting - Add it later when you need it', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Use built-in ledger - Full double-entry accounting system', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Export to QuickBooks, Xero, Wave - Standard formats for CPAs', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Switch anytime - Change your approach as you grow', 'nonprofitsuite' ); ?></li>
			</ul>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Toggle automatic entries based on mode
	$('input[name="accounting_mode"]').on('change', function() {
		if ($(this).val() === 'external') {
			$('#auto_entries_row').hide();
		} else {
			$('#auto_entries_row').show();
		}
	}).trigger('change');

	// Save settings
	$('#ns-accounting-settings-form').on('submit', function(e) {
		e.preventDefault();

		const data = {
			action: 'ns_save_accounting_settings',
			nonce: '<?php echo wp_create_nonce( 'ns_accounting' ); ?>',
			accounting_mode: $('input[name="accounting_mode"]:checked').val(),
			auto_entries: $('#auto_entries').is(':checked') ? 'yes' : 'no',
			cash_account: $('#cash_account').val(),
			revenue_account: $('#revenue_account').val(),
			fee_account: $('#fee_account').val()
		};

		$.post(ajaxurl, data, function(response) {
			if (response.success) {
				alert(response.data.message);
			} else {
				alert('Error: ' + response.data.message);
			}
		});
	});
});
</script>

<style>
.ns-accounting-settings {
	max-width: 900px;
}

.ns-info-box {
	background: #f0f9ff;
	border-left: 4px solid #0073aa;
	padding: 20px;
	margin-top: 30px;
}

.ns-info-box h3 {
	margin-top: 0;
}

.ns-info-box ul {
	margin: 10px 0 0 20px;
}
</style>
