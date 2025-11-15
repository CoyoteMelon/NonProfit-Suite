<?php
/**
 * Donation Form Template
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ns-donation-form-container">
	<div class="ns-donation-form">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<h2 class="ns-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<?php if ( ! empty( $atts['description'] ) ) : ?>
			<p class="ns-form-description"><?php echo esc_html( $atts['description'] ); ?></p>
		<?php endif; ?>

		<form id="ns-donation-form" method="post">
			<input type="hidden" name="fund_id" value="<?php echo esc_attr( $atts['fund_id'] ); ?>">
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $atts['campaign_id'] ); ?>">

			<!-- Amount Selection -->
			<div class="ns-form-section">
				<label class="ns-form-label"><?php esc_html_e( 'Donation Amount', 'nonprofitsuite' ); ?></label>
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
								<?php if ( 'yes' === $atts['show_fees'] && ! empty( $processor['fee_info'] ) ) : ?>
									<span class="ns-processor-fee">
										<?php
										$fee_info = $processor['fee_info'];
										if ( 'incentivize' === $fee_info['policy_type'] ) {
											echo '<span class="ns-incentive">' . esc_html( $fee_info['incentive_message'] ) . '</span>';
										} elseif ( 'org_absorbs' === $fee_info['policy_type'] ) {
											esc_html_e( 'No fees', 'nonprofitsuite' );
										} elseif ( 'donor_pays' === $fee_info['policy_type'] ) {
											echo esc_html( $fee_info['fee_percentage'] ) . '% + $' . esc_html( number_format( $fee_info['fee_fixed_amount'], 2 ) );
										}
										?>
									</span>
								<?php endif; ?>
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

			<!-- Payment Method (Stripe Elements will be inserted here) -->
			<div class="ns-form-section">
				<label class="ns-form-label"><?php esc_html_e( 'Payment Details', 'nonprofitsuite' ); ?></label>
				<div id="ns-card-element" class="ns-card-element"></div>
				<div id="ns-card-errors" class="ns-error-message"></div>
			</div>

			<!-- Submit Button -->
			<div class="ns-form-section">
				<button type="submit" class="ns-submit-btn" id="ns-submit-donation">
					<?php esc_html_e( 'Complete Donation', 'nonprofitsuite' ); ?>
				</button>
				<p class="ns-secure-text">
					<span class="dashicons dashicons-lock"></span>
					<?php esc_html_e( 'Secure payment processing', 'nonprofitsuite' ); ?>
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
	let stripe = null;
	let cardElement = null;

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

	// Processor selection - initialize Stripe if selected
	$('input[name="processor_id"]').on('change', function() {
		const processorName = $(this).closest('.ns-processor-option').find('.ns-processor-name').text().toLowerCase();

		if (processorName.includes('stripe')) {
			initializeStripe();
		}
	});

	function initializeStripe() {
		if (typeof Stripe === 'undefined') {
			console.error('Stripe.js not loaded');
			return;
		}

		// Get Stripe publishable key (should be passed via localized script)
		const publishableKey = 'pk_test_...'; // TODO: Load from PHP

		stripe = Stripe(publishableKey);
		const elements = stripe.elements();
		cardElement = elements.create('card', {
			style: {
				base: {
					fontSize: '16px',
					color: '#32325d',
					'::placeholder': {
						color: '#aab7c4',
					},
				},
			},
		});

		cardElement.mount('#ns-card-element');

		cardElement.on('change', function(event) {
			const displayError = $('#ns-card-errors');
			if (event.error) {
				displayError.text(event.error.message);
			} else {
				displayError.text('');
			}
		});
	}

	// Form submission
	$('#ns-donation-form').on('submit', function(e) {
		e.preventDefault();

		if (selectedAmount <= 0) {
			showMessage('Please select a donation amount', 'error');
			return;
		}

		const $submitBtn = $('#ns-submit-donation');
		$submitBtn.prop('disabled', true).text('Processing...');

		// Create payment method with Stripe
		if (stripe && cardElement) {
			stripe.createPaymentMethod({
				type: 'card',
				card: cardElement,
				billing_details: {
					name: $('#donor_name').val(),
					email: $('#donor_email').val(),
				},
			}).then(function(result) {
				if (result.error) {
					showMessage(result.error.message, 'error');
					$submitBtn.prop('disabled', false).text('Complete Donation');
				} else {
					processDonation(result.paymentMethod.id);
				}
			});
		} else {
			// For non-Stripe processors
			processDonation('');
		}
	});

	function processDonation(paymentMethodId) {
		const formData = {
			action: 'ns_process_donation',
			nonce: nsPaymentForms.nonce,
			processor_id: $('input[name="processor_id"]:checked').val(),
			amount: selectedAmount,
			payment_method_id: paymentMethodId,
			donor_name: $('#donor_name').val(),
			donor_email: $('#donor_email').val(),
			fund_id: $('input[name="fund_id"]').val(),
			campaign_id: $('input[name="campaign_id"]').val(),
		};

		$.post(nsPaymentForms.ajaxurl, formData, function(response) {
			if (response.success) {
				showMessage(response.data.message, 'success');
				$('#ns-donation-form')[0].reset();
				$('.ns-amount-btn').removeClass('active');
			} else {
				showMessage(response.data.message, 'error');
			}

			$('#ns-submit-donation').prop('disabled', false).text('Complete Donation');
		});
	}

	function showMessage(message, type) {
		const $messages = $('#ns-form-messages');
		$messages
			.removeClass('ns-success ns-error')
			.addClass('ns-' + type)
			.text(message)
			.show();

		if (type === 'success') {
			setTimeout(function() {
				$messages.fadeOut();
			}, 5000);
		}
	}

	// Auto-select first amount
	$('.ns-amount-btn:first').click();

	// Auto-select first processor if only one
	if ($('input[name="processor_id"]').length === 1) {
		$('input[name="processor_id"]').prop('checked', true).trigger('change');
	}
});
</script>
