/**
 * Setup Wizard JavaScript
 *
 * @package NonprofitSuite
 * @subpackage Admin/JS
 */

(function($) {
	'use strict';

	/**
	 * Setup wizard and migration tools functionality
	 * Main JavaScript is inline in view files for better organization
	 * This file is for shared utilities and helpers
	 */

	// Global helpers
	window.nsSetupHelpers = {
		/**
		 * Show a notification message
		 */
		showNotice: function(message, type) {
			const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.wrap h1').after(notice);

			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Validate email address
		 */
		isValidEmail: function(email) {
			const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return re.test(email);
		},

		/**
		 * Format number with commas
		 */
		formatNumber: function(num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		}
	};

})(jQuery);
