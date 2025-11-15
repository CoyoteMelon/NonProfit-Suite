/**
 * Volunteer Shifts UI JavaScript
 *
 * Handles interactive functionality for volunteer shift signup.
 *
 * @package NonprofitSuite
 * @since   1.5.0
 */

(function($) {
	'use strict';

	const VolunteerShifts = {
		/**
		 * Initialize the module.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Sign up for shift
			$(document).on('click', '.ns-signup-btn', this.handleSignup.bind(this));

			// Cancel shift signup
			$(document).on('click', '.ns-cancel-shift-btn', this.handleCancel.bind(this));

			// Apply filters
			$('#ns-apply-filters').on('click', this.handleFilterChange.bind(this));

			// Quick filter changes (optional - could auto-apply)
			// $('#ns-filter-event, #ns-filter-role, #ns-filter-availability').on('change', this.handleFilterChange.bind(this));
		},

		/**
		 * Handle shift signup.
		 *
		 * @param {Event} e Click event.
		 */
		handleSignup: function(e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const shiftId = $btn.data('shift-id');

			if (!nsVolunteerShifts.user_id) {
				this.showNotification(nsVolunteerShifts.strings.must_login, 'error');
				return;
			}

			// Disable button
			$btn.prop('disabled', true).text('Signing up...');

			$.ajax({
				url: nsVolunteerShifts.ajax_url,
				type: 'POST',
				data: {
					action: 'ns_signup_for_shift',
					nonce: nsVolunteerShifts.nonce,
					shift_id: shiftId
				},
				success: (response) => {
					if (response.success) {
						this.showNotification(nsVolunteerShifts.strings.signup_success, 'success');

						// Update the card UI
						const $card = $btn.closest('.ns-shift-card');
						$card.addClass('ns-shift-signed-up');

						// Replace button with status badge
						const $actions = $card.find('.ns-shift-actions');
						$actions.html(`
							<span class="ns-status-badge ns-signed-up">
								<span class="dashicons dashicons-yes-alt"></span>
								${nsVolunteerShifts.strings.already_signed || 'You are signed up'}
							</span>
						`);

						// Update positions count
						const $positions = $card.find('.ns-shift-positions');
						const positionsText = $positions.text();
						const match = positionsText.match(/(\d+) of (\d+)/);
						if (match) {
							const filled = parseInt(match[1]) + 1;
							const needed = parseInt(match[2]);
							$positions.html(`
								<span class="dashicons dashicons-admin-users"></span>
								${filled} of ${needed} positions filled
							`);

							// Mark as full if needed
							if (filled >= needed) {
								$card.addClass('ns-shift-full');
								$positions.addClass('ns-positions-full');
							}
						}

						// Reload after a delay to show updated "My Shifts" section
						setTimeout(() => {
							location.reload();
						}, 1500);
					} else {
						this.showNotification(response.data.message || nsVolunteerShifts.strings.error, 'error');
						$btn.prop('disabled', false).text('Sign Up');
					}
				},
				error: () => {
					this.showNotification(nsVolunteerShifts.strings.error, 'error');
					$btn.prop('disabled', false).text('Sign Up');
				}
			});
		},

		/**
		 * Handle shift cancellation.
		 *
		 * @param {Event} e Click event.
		 */
		handleCancel: function(e) {
			e.preventDefault();

			if (!confirm(nsVolunteerShifts.strings.confirm_cancel)) {
				return;
			}

			const $btn = $(e.currentTarget);
			const shiftId = $btn.data('shift-id');

			// Disable button
			$btn.prop('disabled', true).text('Cancelling...');

			$.ajax({
				url: nsVolunteerShifts.ajax_url,
				type: 'POST',
				data: {
					action: 'ns_cancel_shift_signup',
					nonce: nsVolunteerShifts.nonce,
					shift_id: shiftId
				},
				success: (response) => {
					if (response.success) {
						this.showNotification(nsVolunteerShifts.strings.cancel_success, 'success');

						// Remove the card with animation
						const $card = $btn.closest('.ns-shift-card');
						$card.fadeOut(400, function() {
							$(this).remove();

							// Check if section is now empty
							const $myShiftsSection = $('.ns-my-shifts-section');
							if ($myShiftsSection.find('.ns-shift-card').length === 0) {
								$myShiftsSection.find('.ns-my-shifts-grid').html(
									'<p class="ns-no-shifts">You have no upcoming shifts.</p>'
								);
							}
						});

						// Reload after a delay to update available shifts
						setTimeout(() => {
							location.reload();
						}, 1500);
					} else {
						this.showNotification(response.data.message || nsVolunteerShifts.strings.error, 'error');
						$btn.prop('disabled', false).text('Cancel Signup');
					}
				},
				error: () => {
					this.showNotification(nsVolunteerShifts.strings.error, 'error');
					$btn.prop('disabled', false).text('Cancel Signup');
				}
			});
		},

		/**
		 * Handle filter changes.
		 */
		handleFilterChange: function() {
			const eventId = $('#ns-filter-event').val();
			const role = $('#ns-filter-role').val();
			const availability = $('#ns-filter-availability').val();

			const $shiftsList = $('#ns-shifts-list');
			$shiftsList.addClass('loading');

			$.ajax({
				url: nsVolunteerShifts.ajax_url,
				type: 'POST',
				data: {
					action: 'ns_get_available_shifts',
					nonce: nsVolunteerShifts.nonce,
					event_id: eventId,
					role: role,
					only_available: availability
				},
				success: (response) => {
					if (response.success) {
						$shiftsList.html(response.data.html);
					} else {
						this.showNotification(nsVolunteerShifts.strings.error, 'error');
					}
				},
				error: () => {
					this.showNotification(nsVolunteerShifts.strings.error, 'error');
				},
				complete: () => {
					$shiftsList.removeClass('loading');
				}
			});
		},

		/**
		 * Show notification message.
		 *
		 * @param {string} message Message to display.
		 * @param {string} type    Notification type (success, error, info).
		 */
		showNotification: function(message, type) {
			type = type || 'info';

			const $notification = $('<div class="ns-notification"></div>')
				.addClass(type)
				.text(message)
				.appendTo('body');

			// Auto-dismiss after 4 seconds
			setTimeout(() => {
				$notification.fadeOut(300, function() {
					$(this).remove();
				});
			}, 4000);

			// Allow manual dismiss
			$notification.on('click', function() {
				$(this).fadeOut(300, function() {
					$(this).remove();
				});
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		VolunteerShifts.init();
	});

})(jQuery);
