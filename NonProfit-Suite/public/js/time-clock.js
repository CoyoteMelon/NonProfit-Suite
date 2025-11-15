/**
 * Time Clock UI JavaScript
 *
 * Handles interactive functionality for time clock and timesheet.
 *
 * @package NonprofitSuite
 * @since   1.5.0
 */

(function($) {
	'use strict';

	const TimeClock = {
		/**
		 * Clock interval ID.
		 */
		clockInterval: null,

		/**
		 * Elapsed time interval ID.
		 */
		elapsedInterval: null,

		/**
		 * Initialize the module.
		 */
		init: function() {
			this.updateClock();
			this.startClockUpdate();
			this.startElapsedTimeUpdate();
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Clock in form
			$('#ns-clock-in-form').on('submit', this.handleClockIn.bind(this));

			// Clock out form
			$('#ns-clock-out-form').on('submit', this.handleClockOut.bind(this));

			// Submit time entries
			$('#ns-submit-selected').on('click', this.handleSubmitEntries.bind(this));

			// Select all checkbox
			$('#ns-select-all-entries').on('change', this.handleSelectAll.bind(this));

			// Period selector
			$('#ns-period-select').on('change', this.handlePeriodChange.bind(this));
		},

		/**
		 * Update the clock display.
		 */
		updateClock: function() {
			const now = new Date();
			const timeString = now.toLocaleTimeString('en-US', {
				hour: 'numeric',
				minute: '2-digit',
				second: '2-digit',
				hour12: true
			});

			$('.ns-current-time').text(timeString);
		},

		/**
		 * Start clock update interval.
		 */
		startClockUpdate: function() {
			this.clockInterval = setInterval(() => {
				this.updateClock();
			}, 1000);
		},

		/**
		 * Update elapsed time display.
		 */
		updateElapsedTime: function() {
			const $elapsed = $('.ns-elapsed-time');
			if ($elapsed.length === 0) {
				return;
			}

			const startTimestamp = parseInt($elapsed.data('start'), 10);
			const now = Math.floor(Date.now() / 1000);
			const elapsed = now - startTimestamp;

			const hours = Math.floor(elapsed / 3600);
			const minutes = Math.floor((elapsed % 3600) / 60);
			const seconds = elapsed % 60;

			const timeString = [hours, minutes, seconds]
				.map(val => String(val).padStart(2, '0'))
				.join(':');

			$elapsed.text(timeString);
		},

		/**
		 * Start elapsed time update interval.
		 */
		startElapsedTimeUpdate: function() {
			if ($('.ns-elapsed-time').length > 0) {
				this.updateElapsedTime();
				this.elapsedInterval = setInterval(() => {
					this.updateElapsedTime();
				}, 1000);
			}
		},

		/**
		 * Handle clock in.
		 *
		 * @param {Event} e Submit event.
		 */
		handleClockIn: function(e) {
			e.preventDefault();

			const $form = $(e.currentTarget);
			const $btn = $form.find('button[type="submit"]');
			const formData = $form.serialize();

			$btn.prop('disabled', true).text('Clocking in...');

			$.ajax({
				url: nsTimeClock.ajax_url,
				type: 'POST',
				data: formData + '&action=ns_clock_in&nonce=' + nsTimeClock.nonce,
				success: (response) => {
					if (response.success) {
						this.showNotification(nsTimeClock.strings.clock_in_success, 'success');
						setTimeout(() => {
							location.reload();
						}, 1000);
					} else {
						this.showNotification(response.data.message || nsTimeClock.strings.error, 'error');
						$btn.prop('disabled', false).text('Clock In');
					}
				},
				error: () => {
					this.showNotification(nsTimeClock.strings.error, 'error');
					$btn.prop('disabled', false).text('Clock In');
				}
			});
		},

		/**
		 * Handle clock out.
		 *
		 * @param {Event} e Submit event.
		 */
		handleClockOut: function(e) {
			e.preventDefault();

			const $form = $(e.currentTarget);
			const $btn = $form.find('button[type="submit"]');
			const formData = $form.serialize();

			$btn.prop('disabled', true).text('Clocking out...');

			$.ajax({
				url: nsTimeClock.ajax_url,
				type: 'POST',
				data: formData + '&action=ns_clock_out&nonce=' + nsTimeClock.nonce,
				success: (response) => {
					if (response.success) {
						this.showNotification(nsTimeClock.strings.clock_out_success, 'success');
						setTimeout(() => {
							location.reload();
						}, 1000);
					} else {
						this.showNotification(response.data.message || nsTimeClock.strings.error, 'error');
						$btn.prop('disabled', false).text('Clock Out');
					}
				},
				error: () => {
					this.showNotification(nsTimeClock.strings.error, 'error');
					$btn.prop('disabled', false).text('Clock Out');
				}
			});
		},

		/**
		 * Handle submit time entries.
		 */
		handleSubmitEntries: function() {
			const $checked = $('.ns-entry-checkbox:checked');

			if ($checked.length === 0) {
				this.showNotification('Please select at least one entry.', 'error');
				return;
			}

			if (!confirm(nsTimeClock.strings.confirm_submit)) {
				return;
			}

			const entryIds = $checked.map(function() {
				return $(this).val();
			}).get();

			$.ajax({
				url: nsTimeClock.ajax_url,
				type: 'POST',
				data: {
					action: 'ns_submit_time_entries',
					nonce: nsTimeClock.nonce,
					entry_ids: entryIds
				},
				success: (response) => {
					if (response.success) {
						this.showNotification(nsTimeClock.strings.submit_success, 'success');
						setTimeout(() => {
							location.reload();
						}, 1500);
					} else {
						this.showNotification(response.data.message || nsTimeClock.strings.error, 'error');
					}
				},
				error: () => {
					this.showNotification(nsTimeClock.strings.error, 'error');
				}
			});
		},

		/**
		 * Handle select all checkbox.
		 *
		 * @param {Event} e Change event.
		 */
		handleSelectAll: function(e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.ns-entry-checkbox').prop('checked', isChecked);
		},

		/**
		 * Handle period change.
		 *
		 * @param {Event} e Change event.
		 */
		handlePeriodChange: function(e) {
			const period = $(e.currentTarget).val();
			const url = new URL(window.location.href);
			url.searchParams.set('period', period);
			window.location.href = url.toString();
		},

		/**
		 * Show notification message.
		 *
		 * @param {string} message Message to display.
		 * @param {string} type    Notification type (success, error).
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
		},

		/**
		 * Stop all intervals on page unload.
		 */
		cleanup: function() {
			if (this.clockInterval) {
				clearInterval(this.clockInterval);
			}
			if (this.elapsedInterval) {
				clearInterval(this.elapsedInterval);
			}
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		TimeClock.init();
	});

	// Cleanup on page unload
	$(window).on('beforeunload', function() {
		TimeClock.cleanup();
	});

})(jQuery);
