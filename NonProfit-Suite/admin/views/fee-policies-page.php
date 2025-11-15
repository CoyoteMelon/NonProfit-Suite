<?php
/**
 * Fee Policies Admin Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Fee Policies', 'nonprofitsuite' ); ?></h1>
	<p><?php esc_html_e( 'Configure how payment processing fees are handled for each processor.', 'nonprofitsuite' ); ?></p>

	<div class="ns-fee-policies-container">
		<!-- Configured Policies -->
		<div class="ns-fee-policies-list">
			<h2><?php esc_html_e( 'Configured Fee Policies', 'nonprofitsuite' ); ?></h2>

			<?php if ( empty( $fee_policies ) ) : ?>
				<p><?php esc_html_e( 'No fee policies configured yet.', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Processor', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Payment Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Policy', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Fees', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Incentive Message', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fee_policies as $policy ) : ?>
							<tr>
								<td>
									<?php
									$processor = $wpdb->get_var( $wpdb->prepare( "SELECT processor_name FROM {$wpdb->prefix}ns_payment_processors WHERE id = %d", $policy['processor_id'] ) );
									echo esc_html( $processor );
									?>
								</td>
								<td><?php echo esc_html( ucfirst( $policy['payment_type'] ) ); ?></td>
								<td>
									<?php
									$policy_types = array(
										'org_absorbs' => __( 'Organization Absorbs', 'nonprofitsuite' ),
										'donor_pays'  => __( 'Donor Pays', 'nonprofitsuite' ),
										'hybrid'      => __( 'Hybrid Split', 'nonprofitsuite' ),
										'incentivize' => __( 'Incentivize (Org Absorbs)', 'nonprofitsuite' ),
									);
									echo esc_html( $policy_types[ $policy['policy_type'] ] ?? $policy['policy_type'] );
									?>
								</td>
								<td>
									<?php echo esc_html( $policy['fee_percentage'] ); ?>% + $<?php echo esc_html( number_format( $policy['fee_fixed_amount'], 2 ) ); ?>
								</td>
								<td>
									<?php echo esc_html( $policy['incentive_message'] ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Add New Fee Policy -->
		<div class="ns-add-fee-policy">
			<h2><?php esc_html_e( 'Add Fee Policy', 'nonprofitsuite' ); ?></h2>

			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Policy Types:', 'nonprofitsuite' ); ?></strong>
				</p>
				<ul>
					<li><strong><?php esc_html_e( 'Organization Absorbs:', 'nonprofitsuite' ); ?></strong> <?php esc_html_e( 'Your org pays all fees (donor pays advertised amount)', 'nonprofitsuite' ); ?></li>
					<li><strong><?php esc_html_e( 'Donor Pays:', 'nonprofitsuite' ); ?></strong> <?php esc_html_e( 'Donor covers fees on top of their donation', 'nonprofitsuite' ); ?></li>
					<li><strong><?php esc_html_e( 'Incentivize:', 'nonprofitsuite' ); ?></strong> <?php esc_html_e( 'Org absorbs with special messaging ("We cover ACH fees!")', 'nonprofitsuite' ); ?></li>
				</ul>
			</div>

			<form id="ns-fee-policy-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="processor_id"><?php esc_html_e( 'Payment Processor', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="processor_id" id="processor_id" class="regular-text" required>
								<option value=""><?php esc_html_e( 'Select processor...', 'nonprofitsuite' ); ?></option>
								<?php foreach ( $processors as $proc ) : ?>
									<option value="<?php echo esc_attr( $proc['id'] ); ?>">
										<?php echo esc_html( $proc['processor_name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="payment_type"><?php esc_html_e( 'Payment Type', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="payment_type" id="payment_type" class="regular-text" required>
								<option value="donation"><?php esc_html_e( 'Donation', 'nonprofitsuite' ); ?></option>
								<option value="membership"><?php esc_html_e( 'Membership', 'nonprofitsuite' ); ?></option>
								<option value="event"><?php esc_html_e( 'Event Registration', 'nonprofitsuite' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="policy_type"><?php esc_html_e( 'Policy Type', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select name="policy_type" id="policy_type" class="regular-text" required>
								<option value="org_absorbs"><?php esc_html_e( 'Organization Absorbs', 'nonprofitsuite' ); ?></option>
								<option value="donor_pays"><?php esc_html_e( 'Donor Pays', 'nonprofitsuite' ); ?></option>
								<option value="incentivize"><?php esc_html_e( 'Incentivize (with message)', 'nonprofitsuite' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fee_percentage"><?php esc_html_e( 'Fee Percentage', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="fee_percentage" id="fee_percentage" value="2.9" step="0.01" class="regular-text">
							<p class="description"><?php esc_html_e( 'Typical: Stripe 2.9%, PayPal 2.89%, Square 2.6%', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fee_fixed_amount"><?php esc_html_e( 'Fixed Fee Amount', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="number" name="fee_fixed_amount" id="fee_fixed_amount" value="0.30" step="0.01" class="regular-text">
							<p class="description"><?php esc_html_e( 'Typical: Stripe $0.30, PayPal $0.49', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>

					<tr id="incentive_message_row" style="display: none;">
						<th scope="row">
							<label for="incentive_message"><?php esc_html_e( 'Incentive Message', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="text" name="incentive_message" id="incentive_message" class="regular-text">
							<p class="description"><?php esc_html_e( 'Example: "We cover all ACH fees!" or "No fees when you pay with bank account"', 'nonprofitsuite' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Fee Policy', 'nonprofitsuite' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Show/hide incentive message field
	$('#policy_type').on('change', function() {
		if ($(this).val() === 'incentivize') {
			$('#incentive_message_row').show();
		} else {
			$('#incentive_message_row').hide();
		}
	});

	// Save fee policy
	$('#ns-fee-policy-form').on('submit', function(e) {
		e.preventDefault();

		const data = {
			action: 'ns_save_fee_policy',
			nonce: nsPaymentAdmin.nonce,
			processor_id: $('#processor_id').val(),
			payment_type: $('#payment_type').val(),
			policy_type: $('#policy_type').val(),
			fee_percentage: $('#fee_percentage').val(),
			fee_fixed_amount: $('#fee_fixed_amount').val(),
			incentive_message: $('#incentive_message').val()
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
});
</script>

<style>
.ns-fee-policies-container {
	margin-top: 20px;
}

.ns-fee-policies-list,
.ns-add-fee-policy {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
</style>
