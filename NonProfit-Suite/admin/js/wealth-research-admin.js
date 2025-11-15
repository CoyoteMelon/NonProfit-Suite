/**
 * Wealth Research Admin JavaScript
 *
 * @package NonprofitSuite
 * @subpackage Admin/JS
 * @since 1.18.0
 */

(function($) {
	'use strict';

	/**
	 * Wealth research admin functionality
	 * Main JavaScript is inline in view files for better organization
	 * This file is for shared utilities and helpers
	 */

	// Global helpers
	window.nsWealthResearchHelpers = {
		/**
		 * Format currency
		 */
		formatCurrency: function(amount) {
			return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
		},

		/**
		 * Get capacity badge HTML
		 */
		getCapacityBadge: function(rating) {
			const classes = {
				'A+': 'capacity-aplus',
				'A': 'capacity-a',
				'B': 'capacity-b',
				'C': 'capacity-c',
				'D': 'capacity-d'
			};

			const className = classes[rating] || 'capacity-d';
			return '<span class="capacity-badge ' + className + '">' + rating + '</span>';
		}
	};

})(jQuery);
