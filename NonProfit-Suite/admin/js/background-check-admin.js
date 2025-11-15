/**
 * Background Check Admin JavaScript
 *
 * @package NonprofitSuite
 * @subpackage Admin/JS
 * @since 1.18.0
 */

(function($) {
	'use strict';

	/**
	 * Background check admin functionality
	 * Main JavaScript is inline in view files for better organization
	 * This file is for shared utilities and helpers
	 */

	// Global helpers
	window.nsBackgroundCheckHelpers = {
		/**
		 * Format currency
		 */
		formatCurrency: function(amount) {
			return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
		},

		/**
		 * Get status badge HTML
		 */
		getStatusBadge: function(status) {
			const classes = {
				'pending': 'status-pending',
				'sent': 'status-sent',
				'in_progress': 'status-in_progress',
				'completed': 'status-completed',
				'cancelled': 'status-cancelled'
			};

			const className = classes[status] || 'status-pending';
			const label = status.replace('_', ' ');
			return '<span class="status-badge ' + className + '">' + label + '</span>';
		},

		/**
		 * Get result badge HTML
		 */
		getResultBadge: function(result) {
			const classes = {
				'clear': 'result-clear',
				'consider': 'result-consider',
				'suspended': 'result-suspended'
			};

			const className = classes[result] || 'result-consider';
			return '<span class="result-badge ' + className + '">' + result + '</span>';
		}
	};

})(jQuery);
