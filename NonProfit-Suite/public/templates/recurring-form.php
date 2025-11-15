<?php
/**
 * Recurring Donation Form Template
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ns-donation-form-container">
	<div class="ns-donation-form ns-recurring-form">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<h2 class="ns-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<?php if ( ! empty( $atts['description'] ) ) : ?>
			<p class="ns-form-description"><?php echo esc_html( $atts['description'] ); ?></p>
		<?php endif; ?>

		<form id="ns-recurring-form" method="post">
			<input type="hidden" name="fund_id" value="<?php echo esc_attr( $atts['fund_id'] ); ?>">
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $atts['campaign_id'] ); ?>">

			<!-- Frequency Selection -->
			<div class="ns-form-section">
				<label class="ns-form-label"><?php esc_html_e( 'Donation Frequency', 'nonprofitsuite' ); ?></label>
				<div class="ns-frequency-buttons">
					<?php foreach ( $frequencies as $freq ) : ?>
						<button type="button" class="ns-frequency-btn" data-frequency="<?php echo esc_attr( $freq ); ?>">
							<?php echo esc_html( ucfirst( $freq ) ); ?>
						</button>
					<?php endforeach; ?>
				</div>
				<input type="hidden" id="donation_frequency" name="frequency" required>
			</div>

			<!-- Amount Selection -->
			<div class="ns-form-section">
				<label class="ns-form-label"><?php esc_html_e( 'Monthly Amount', 'nonprofitsuite' ); ?></label>
				<div class="ns-amount-buttons">
					<?php foreach ( $amounts as $amt ) : ?>
						<button type="button" class="ns-amount-btn" data-amount="<?php echo esc_attr( $amt ); ?>">
							$<?php echo esc_html( $amt ); ?>
						</button>
					<?php endforeach; ?>
					<button type="button" class="ns-amount-btn ns-amount-custom"><?php esc_html_e( 'Custom', 'nonprofitsuite' ); ?></button>
				</div>

				<div class="ns-custom-amount" style="display: none;">
					<label for="custom_amount"><?php esc_html_e( 'Custom Amount', 'nonprofitsuite' ); ?></label>
					<div class="ns-input-prefix">
						<span class="ns-prefix">$</span>
						<input type="number" id="custom_amount" name="custom_amount" min="1" step="0.01" class="ns-input">
					</div>
				</div>

				<input type="hidden" id="donation_amount" name="amount" required>
			</div>

			<!-- Payment Processor Selection -->
			<div class="ns-form-section">
				<label class="ns-form-label"><?php esc_html_e( 'Payment Method', 'nonprofitsuite' ); ?></label>
				<div class="ns-processor-selection">
					<?php foreach ( $processors as $processor ) : ?>
						<label class="ns-processor-option">
							<input type="radio" name="processor_id" value="<?php echo esc_attr( $processor['id'] ); ?>" required>
							<span class="ns-processor-info">
								<span class="ns-processor-name"><?php echo esc_html( $processor['processor_name'] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Donor Information -->
			<div class="ns-form-section">
				<div class="ns-form-row">
					<div class="ns-form-field">
						<label for="donor_name" class="ns-form-label"><?php esc_html_e( 'Full Name', 'nonprofitsuite' ); ?></label>
						<input type="text" id="donor_name" name="donor_name" class="ns-input" required>
					</div>

					<div class="ns-form-field">
						<label for="donor_email" class="ns-form-label"><?php esc_html_e( 'Email Address', 'nonprofitsuite' ); ?></label>
						<input type="email" id="donor_email" name="donor_email" class="ns-input" required>
					</div>
				</div>
			</div>

			<!-- Payment Method -->
			<div class="ns-form-section">
				<label class="ns-form-label"><?php esc_html_e( 'Payment Details', 'nonprofitsuite' ); ?></label>
				<div id="ns-card-element" class="ns-card-element"></div>
				<div id="ns-card-errors" class="ns-error-message"></div>
			</div>

			<!-- Submit Button -->
			<div class="ns-form-section">
				<button type="submit" class="ns-submit-btn" id="ns-submit-recurring">
					<?php esc_html_e( 'Start Recurring Donation', 'nonprofitsuite' ); ?>
				</button>
				<p class="ns-secure-text">
					<span class="dashicons dashicons-lock"></span>
					<?php esc_html_e( 'Cancel anytime', 'nonprofitsuite' ); ?>
				</p>
			</div>

			<!-- Success/Error Messages -->
			<div id="ns-form-messages" class="ns-form-messages" style="display: none;"></div>
		</form>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	let selectedAmount = 0;
	let selectedFrequency = '';

	// Frequency selection
	$('.ns-frequency-btn').on('click', function() {
		$('.ns-frequency-btn').removeClass('active');
		$(this).addClass('active');
		selectedFrequency = $(this).data('frequency');
		$('#donation_frequency').val(selectedFrequency);
	});

	// Amount selection
	$('.ns-amount-btn').on('click', function() {
		$('.ns-amount-btn').removeClass('active');
		$(this).addClass('active');

		if ($(this).hasClass('ns-amount-custom')) {
			$('.ns-custom-amount').show();
			$('#custom_amount').focus();
		} else {
			$('.ns-custom-amount').hide();
			selectedAmount = parseFloat($(this).data('amount'));
			$('#donation_amount').val(selectedAmount);
		}
	});

	// Custom amount
	$('#custom_amount').on('input', function() {
		selectedAmount = parseFloat($(this).val()) || 0;
		$('#donation_amount').val(selectedAmount);
	});

	// Form submission
	$('#ns-recurring-form').on('submit', function(e) {
		e.preventDefault();

		if (selectedAmount <= 0 || !selectedFrequency) {
			showMessage('Please select both amount and frequency', 'error');
			return;
		}

		const $submitBtn = $('#ns-submit-recurring');
		$submitBtn.prop('disabled', true).text('Processing...');

		const formData = {
			action: 'ns_create_recurring',
			nonce: nsPaymentForms.nonce,
			processor_id: $('input[name="processor_id"]:checked').val(),
			amount: selectedAmount,
			frequency: selectedFrequency,
			payment_method_id: '', // TODO: Integrate with Stripe
			donor_name: $('#donor_name').val(),
			donor_email: $('#donor_email').val(),
			fund_id: $('input[name="fund_id"]').val(),
			campaign_id: $('input[name="campaign_id"]').val(),
		};

		$.post(nsPaymentForms.ajaxurl, formData, function(response) {
			if (response.success) {
				showMessage(response.data.message, 'success');
				$('#ns-recurring-form')[0].reset();
				$('.ns-amount-btn, .ns-frequency-btn').removeClass('active');
			} else {
				showMessage(response.data.message, 'error');
			}

			$submitBtn.prop('disabled', false).text('Start Recurring Donation');
		});
	});

	function showMessage(message, type) {
		const $messages = $('#ns-form-messages');
		$messages
			.removeClass('ns-success ns-error')
			.addClass('ns-' + type)
			.text(message)
			.show();
	}

	// Auto-select default frequency
	$('.ns-frequency-btn[data-frequency="<?php echo esc_js( $atts['default_freq'] ); ?>"]').click();

	// Auto-select default amount
	$('.ns-amount-btn[data-amount="<?php echo esc_js( $atts['default_amount'] ); ?>"]').click();
});
</script>
