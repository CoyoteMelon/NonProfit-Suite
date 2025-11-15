<?php
/**
 * Accounting Export Adapter Interface
 *
 * Defines the contract for accounting system export adapters.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Accounting_Adapter Interface
 *
 * Interface for accounting export adapters.
 */
interface NonprofitSuite_Accounting_Adapter {

	/**
	 * Export chart of accounts.
	 *
	 * @param array $accounts Array of account data.
	 * @return string|WP_Error Export content or error.
	 */
	public function export_chart_of_accounts( $accounts );

	/**
	 * Export journal entries.
	 *
	 * @param array $entries Array of journal entry data.
	 * @param array $args    Optional export arguments.
	 * @return string|WP_Error Export content or error.
	 */
	public function export_journal_entries( $entries, $args = array() );

	/**
	 * Export transactions (donations, payments).
	 *
	 * @param array $transactions Array of transaction data.
	 * @param array $args         Optional export arguments.
	 * @return string|WP_Error Export content or error.
	 */
	public function export_transactions( $transactions, $args = array() );

	/**
	 * Get export format name.
	 *
	 * @return string Format name (e.g., 'QuickBooks IIF', 'Xero CSV').
	 */
	public function get_format_name();

	/**
	 * Get export file extension.
	 *
	 * @return string File extension (e.g., 'iif', 'csv').
	 */
	public function get_file_extension();

	/**
	 * Get export MIME type.
	 *
	 * @return string MIME type.
	 */
	public function get_mime_type();

	/**
	 * Get supported export types.
	 *
	 * @return array Supported export types (accounts, entries, transactions).
	 */
	public function get_supported_types();
}
