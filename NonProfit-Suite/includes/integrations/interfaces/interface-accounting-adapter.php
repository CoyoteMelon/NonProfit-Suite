<?php
/**
 * Accounting Adapter Interface
 *
 * Defines the contract for accounting providers (QuickBooks, Xero, Wave, etc.)
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accounting Adapter Interface
 *
 * All accounting adapters must implement this interface.
 */
interface NonprofitSuite_Accounting_Adapter_Interface {

	/**
	 * Sync a transaction to accounting system
	 *
	 * @param array $transaction Transaction data
	 *                           - type: income, expense, transfer (required)
	 *                           - date: Transaction date (required)
	 *                           - amount: Amount (required)
	 *                           - account_id: Account ID from chart of accounts (required)
	 *                           - description: Transaction description (optional)
	 *                           - contact_id: Contact/vendor ID (optional)
	 *                           - reference: Reference number (optional)
	 *                           - attachments: Array of file IDs (optional)
	 * @return array|WP_Error Transaction data with keys: transaction_id
	 */
	public function sync_transaction( $transaction );

	/**
	 * Sync multiple transactions in bulk
	 *
	 * @param array $transactions Array of transaction data arrays
	 * @return array|WP_Error Result with keys: synced_count, failed_count, errors
	 */
	public function sync_transactions_bulk( $transactions );

	/**
	 * Get chart of accounts
	 *
	 * @param array $args Query arguments
	 *                    - type: Filter by account type (optional)
	 *                    - active_only: Only active accounts (optional, default true)
	 * @return array|WP_Error Array of accounts or WP_Error on failure
	 *                        Each account: id, name, type, code, balance
	 */
	public function get_chart_of_accounts( $args = array() );

	/**
	 * Create account in chart of accounts
	 *
	 * @param array $account_data Account data
	 *                            - name: Account name (required)
	 *                            - type: Account type (required)
	 *                            - code: Account code (optional)
	 *                            - description: Description (optional)
	 *                            - parent_id: Parent account ID (optional)
	 * @return array|WP_Error Account data with keys: account_id
	 */
	public function create_account( $account_data );

	/**
	 * Sync contact (donor/vendor) to accounting system
	 *
	 * @param array $contact_data Contact data
	 *                            - name: Contact name (required)
	 *                            - type: customer, vendor, both (optional)
	 *                            - email: Email address (optional)
	 *                            - phone: Phone number (optional)
	 *                            - address: Address array (optional)
	 *                            - tax_id: Tax ID/EIN (optional)
	 * @return array|WP_Error Contact data with keys: contact_id
	 */
	public function sync_contact( $contact_data );

	/**
	 * Get contacts from accounting system
	 *
	 * @param array $args Query arguments
	 *                    - type: Filter by customer/vendor (optional)
	 *                    - limit: Maximum number (optional)
	 *                    - offset: Pagination offset (optional)
	 * @return array|WP_Error Array of contacts or WP_Error on failure
	 */
	public function get_contacts( $args = array() );

	/**
	 * Get account balance
	 *
	 * @param string $account_id Account identifier
	 * @param array  $args       Query arguments
	 *                           - as_of_date: Balance as of date (optional)
	 * @return array|WP_Error Balance data or WP_Error on failure
	 *                        - balance: Current balance
	 *                        - currency: Currency code
	 */
	public function get_account_balance( $account_id, $args = array() );

	/**
	 * Generate financial report
	 *
	 * @param string $report_type Report type: balance_sheet, income_statement, cash_flow
	 * @param array  $args        Report arguments
	 *                            - start_date: Start date (optional)
	 *                            - end_date: End date (optional)
	 *                            - format: json, pdf, csv (optional, default json)
	 * @return array|WP_Error Report data or WP_Error on failure
	 */
	public function generate_report( $report_type, $args = array() );

	/**
	 * Export data for CPA
	 *
	 * @param array $args Export arguments
	 *                    - start_date: Start date (optional)
	 *                    - end_date: End date (optional)
	 *                    - format: qbo, iif, csv (optional)
	 * @return string|WP_Error File path or WP_Error on failure
	 */
	public function export_for_cpa( $args = array() );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "QuickBooks Online", "Xero")
	 */
	public function get_provider_name();
}
