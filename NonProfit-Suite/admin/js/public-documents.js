/**
 * Public Documents Admin JavaScript
 *
 * @package NonprofitSuite
 * @subpackage Admin/JS
 */

(function($) {
	'use strict';

	/**
	 * Public documents admin functionality
	 * Main JavaScript is inline in view files for better organization
	 * This file is for shared utilities and helpers
	 */

	// Global helpers
	window.nsPublicDocsHelpers = {
		/**
		 * Format file size
		 */
		formatSize: function(bytes) {
			if (bytes === 0) return '0 Bytes';
			const k = 1024;
			const sizes = ['Bytes', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
		},

		/**
		 * Copy text to clipboard
		 */
		copyToClipboard: function(text) {
			if (navigator.clipboard) {
				navigator.clipboard.writeText(text);
			} else {
				// Fallback for older browsers
				const textarea = document.createElement('textarea');
				textarea.value = text;
				document.body.appendChild(textarea);
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);
			}
		}
	};

})(jQuery);
