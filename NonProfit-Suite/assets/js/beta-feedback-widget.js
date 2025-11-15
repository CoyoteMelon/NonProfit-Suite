/**
 * Beta Feedback Widget
 *
 * Quick feedback modal for beta testers accessible from admin bar.
 *
 * @package    NonprofitSuite
 * @subpackage Beta/Assets
 * @since      1.0.0
 */

(function($) {
	'use strict';

	// Initialize feedback widget
	const BetaFeedbackWidget = {

		init: function() {
			this.createModal();
			this.bindEvents();
			this.setupScreenshot();
		},

		createModal: function() {
			const modalHTML = `
				<div id="ns-beta-feedback-modal" class="ns-modal" style="display: none;">
					<div class="ns-modal-overlay"></div>
					<div class="ns-modal-content">
						<div class="ns-modal-header">
							<h2>📢 Beta Feedback</h2>
							<button class="ns-modal-close" aria-label="Close">&times;</button>
						</div>

						<div class="ns-modal-body">
							<form id="ns-beta-feedback-form">
								<!-- Feedback Type -->
								<div class="ns-form-group">
									<label for="feedback_type">What type of feedback?</label>
									<select id="feedback_type" name="feedback_type" required>
										<option value="">Select type...</option>
										<option value="bug">🐛 Bug Report</option>
										<option value="feature_request">💡 Feature Request</option>
										<option value="improvement">✨ Improvement Suggestion</option>
										<option value="question">❓ Question</option>
										<option value="praise">👏 Praise / What's Working</option>
									</select>
								</div>

								<!-- Category -->
								<div class="ns-form-group">
									<label for="category">Which module/feature?</label>
									<select id="category" name="category">
										<option value="">Select module...</option>
										<option value="meetings">Meetings</option>
										<option value="documents">Documents</option>
										<option value="treasury">Treasury</option>
										<option value="donors">Donors</option>
										<option value="volunteers">Volunteers</option>
										<option value="compliance">Compliance</option>
										<option value="calendar">Calendar</option>
										<option value="email">Email</option>
										<option value="payments">Payments</option>
										<option value="membership">Membership</option>
										<option value="board">Board Portal</option>
										<option value="events">Events</option>
										<option value="grants">Grants</option>
										<option value="integrations">Integrations</option>
										<option value="other">Other</option>
									</select>
								</div>

								<!-- Subject -->
								<div class="ns-form-group">
									<label for="subject">Subject (optional)</label>
									<input type="text" id="subject" name="subject" placeholder="Brief summary...">
								</div>

								<!-- Message -->
								<div class="ns-form-group">
									<label for="message">Your Feedback *</label>
									<textarea id="message" name="message" rows="6" required placeholder="Please be as specific as possible. If reporting a bug, include steps to reproduce..."></textarea>
								</div>

								<!-- Screenshot -->
								<div class="ns-form-group">
									<label>
										<input type="checkbox" id="include_screenshot" name="include_screenshot">
										Include screenshot of current page
									</label>
									<div id="screenshot_preview" style="display: none; margin-top: 10px;">
										<img id="screenshot_img" style="max-width: 100%; border: 1px solid #ddd; border-radius: 4px;">
										<button type="button" id="retake_screenshot" class="ns-btn-secondary" style="margin-top: 10px;">Retake Screenshot</button>
									</div>
								</div>

								<!-- System Info -->
								<div class="ns-form-group" style="font-size: 12px; color: #666;">
									<label>
										<input type="checkbox" id="include_system_info" name="include_system_info" checked>
										Include browser and system information
									</label>
								</div>

								<!-- Submit -->
								<div class="ns-form-actions">
									<button type="button" class="ns-btn-secondary ns-modal-close-btn">Cancel</button>
									<button type="submit" class="ns-btn-primary">Submit Feedback</button>
								</div>

								<!-- Response Message -->
								<div id="feedback_response" style="display: none; margin-top: 15px;"></div>
							</form>
						</div>
					</div>
				</div>
			`;

			$('body').append(modalHTML);
		},

		bindEvents: function() {
			const self = this;

			// Open modal from admin bar
			$('#wp-admin-bar-beta-feedback a').on('click', function(e) {
				e.preventDefault();
				self.openModal();
			});

			// Close modal
			$('.ns-modal-close, .ns-modal-close-btn, .ns-modal-overlay').on('click', function() {
				self.closeModal();
			});

			// Form submission
			$('#ns-beta-feedback-form').on('submit', function(e) {
				e.preventDefault();
				self.submitFeedback();
			});

			// Screenshot handling
			$('#include_screenshot').on('change', function() {
				if ($(this).is(':checked')) {
					self.captureScreenshot();
				} else {
					$('#screenshot_preview').hide();
				}
			});

			$('#retake_screenshot').on('click', function() {
				self.captureScreenshot();
			});

			// ESC key to close
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('#ns-beta-feedback-modal').is(':visible')) {
					self.closeModal();
				}
			});
		},

		openModal: function() {
			$('#ns-beta-feedback-modal').fadeIn(200);
			$('#feedback_type').focus();
			$('body').addClass('ns-modal-open');
		},

		closeModal: function() {
			$('#ns-beta-feedback-modal').fadeOut(200);
			$('body').removeClass('ns-modal-open');
			this.resetForm();
		},

		resetForm: function() {
			$('#ns-beta-feedback-form')[0].reset();
			$('#screenshot_preview').hide();
			$('#feedback_response').hide();
		},

		setupScreenshot: function() {
			// Load html2canvas library if available
			if (typeof html2canvas !== 'undefined') {
				this.screenshotAvailable = true;
			} else {
				$('#include_screenshot').prop('disabled', true).closest('label').append(' <em>(requires html2canvas library)</em>');
			}
		},

		captureScreenshot: function() {
			const self = this;

			if (typeof html2canvas === 'undefined') {
				alert('Screenshot functionality not available');
				return;
			}

			// Hide modal temporarily
			$('#ns-beta-feedback-modal').hide();

			setTimeout(function() {
				html2canvas(document.body).then(function(canvas) {
					const screenshot = canvas.toDataURL('image/png');
					$('#screenshot_img').attr('src', screenshot);
					$('#screenshot_preview').show();
					$('#ns-beta-feedback-modal').show();

					// Store screenshot data
					$('#ns-beta-feedback-form').data('screenshot', screenshot);
				});
			}, 100);
		},

		submitFeedback: function() {
			const self = this;
			const $form = $('#ns-beta-feedback-form');
			const $submitBtn = $form.find('button[type="submit"]');
			const $response = $('#feedback_response');

			// Disable submit button
			$submitBtn.prop('disabled', true).text('Submitting...');

			// Prepare form data
			const formData = {
				action: 'ns_beta_submit_feedback',
				nonce: nsBetaWidget.nonce,
				feedback_type: $('#feedback_type').val(),
				category: $('#category').val(),
				subject: $('#subject').val(),
				message: $('#message').val(),
				user_agent: navigator.userAgent,
			};

			// Add screenshot if captured
			if ($('#include_screenshot').is(':checked') && $form.data('screenshot')) {
				formData.screenshot_url = $form.data('screenshot');
			}

			// Submit via AJAX
			$.ajax({
				url: nsBetaWidget.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						$response
							.removeClass('ns-error')
							.addClass('ns-success')
							.html('<strong>✓ Thank you!</strong> Your feedback has been submitted.')
							.show();

						// Reset form after delay
						setTimeout(function() {
							self.closeModal();
						}, 2000);
					} else {
						$response
							.removeClass('ns-success')
							.addClass('ns-error')
							.html('<strong>Error:</strong> ' + (response.data.message || 'Failed to submit feedback'))
							.show();
						$submitBtn.prop('disabled', false).text('Submit Feedback');
					}
				},
				error: function() {
					$response
						.removeClass('ns-success')
						.addClass('ns-error')
						.html('<strong>Error:</strong> Network error. Please try again.')
						.show();
					$submitBtn.prop('disabled', false).text('Submit Feedback');
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		// Only initialize if beta feedback button exists
		if ($('#wp-admin-bar-beta-feedback').length) {
			BetaFeedbackWidget.init();
		}
	});

})(jQuery);
