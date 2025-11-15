/**
 * Background Check Consent Form JavaScript
 *
 * @package NonprofitSuite
 * @subpackage Public/JS
 * @since 1.18.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Validate all checkboxes are checked
		$('#background-check-consent-form').on('submit', function(e) {
			e.preventDefault();

			// Validate checkboxes
			const disclosureRead = $('#disclosure_read').is(':checked');
			const rightsRead = $('#rights_read').is(':checked');
			const authorizationGiven = $('#authorization_given').is(':checked');

			if (!disclosureRead || !rightsRead || !authorizationGiven) {
				alert('Please check all required checkboxes to provide your consent.');
				return false;
			}

			// Validate signature
			const signature = $('#signature').val().trim();
			if (signature.length < 3) {
				alert('Please provide your full name as electronic signature.');
				$('#signature').focus();
				return false;
			}

			// Confirm submission
			if (!confirm('By clicking OK, you confirm that you have read and understood all documents and authorize the background check. This action cannot be undone.')) {
				return false;
			}

			// Submit via AJAX
			const form = $(this);
			const submitBtn = form.find('button[type="submit"]');
			const status = $('#consent-status');

			submitBtn.prop('disabled', true).text('Submitting...');
			status.hide().removeClass('success error');

			$.ajax({
				url: nsBackgroundCheckConsent.ajaxUrl,
				method: 'POST',
				data: {
					action: 'ns_submit_background_check_consent',
					nonce: nsBackgroundCheckConsent.nonce,
					request_id: form.data('request-id'),
					signature: signature
				},
				success: function(response) {
					if (response.success) {
						status.addClass('success')
							.html('✓ ' + response.data.message + '<br>Your background check has been initiated. This page will reload in 3 seconds...')
							.show();

						setTimeout(function() {
							location.reload();
						}, 3000);
					} else {
						status.addClass('error')
							.text('✗ ' + (response.data.message || 'Failed to submit consent'))
							.show();
						submitBtn.prop('disabled', false).text('Submit Consent');
					}
				},
				error: function() {
					status.addClass('error')
						.text('✗ An error occurred. Please try again.')
						.show();
					submitBtn.prop('disabled', false).text('Submit Consent');
				}
			});

			return false;
		});

		// Scroll to first unchecked checkbox when clicking submit
		$('#background-check-consent-form button[type="submit"]').on('click', function(e) {
			const unchecked = $('#background-check-consent-form input[type="checkbox"]:not(:checked)').first();
			if (unchecked.length) {
				$('html, body').animate({
					scrollTop: unchecked.closest('.consent-section').offset().top - 100
				}, 500);
			}
		});
	});

})(jQuery);
