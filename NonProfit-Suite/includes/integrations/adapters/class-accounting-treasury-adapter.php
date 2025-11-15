<?php
/**
 * Treasury Accounting Adapter
 *
 * Adapter for NonprofitSuite's built-in Treasury module.
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
 * NonprofitSuite_Accounting_Treasury_Adapter Class
 *
 * Implements accounting integration using the built-in Treasury module.
 * This adapter serves as a pass-through since Treasury is the native system.
 */
class NonprofitSuite_Accounting_Treasury_Adapter implements NonprofitSuite_Accounting_Adapter_Interface {

	/**
	 * Sync a transaction (creates in Treasury module)
	 *
	 * @param array $transaction Transaction data
	 * @return array|WP_Error Transaction data
	 */
	public function sync_transaction( $transaction ) {
		// Treasury module is the source of truth - no sync needed
		// This method is for when external systems sync TO Treasury
		return new WP_Error( 'not_applicable', __( 'Built-in Treasury is the source of truth', 'nonprofitsuite' ) );
	}

	/**
	 * Sync multiple transactions in bulk
	 *
	 * @param array $transactions Array of transaction data
	 * @return array|WP_Error Result
	 */
	public function sync_transactions_bulk( $transactions ) {
		return new WP_Error( 'not_applicable', __( 'Built-in Treasury is the source of truth', 'nonprofitsuite' ) );
	}

	/**
	 * Get chart of accounts
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of accounts
	 */
	public function get_chart_of_accounts( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_treasury_accounts';

		$args = wp_parse_args( $args, array(
			'type'        => null,
			'active_only' => true,
		) );

		$where = array( '1=1' );
		$prepare_args = array();

		if ( $args['active_only'] ) {
			$where[] = 'active = 1';
		}

		if ( $args['type'] ) {
			$where[] = 'account_type = %s';
			$prepare_args[] = $args['type'];
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT id, name, account_type as type, account_code as code, balance FROM {$table} WHERE {$where_clause} ORDER BY account_code";

		if ( ! empty( $prepare_args ) ) {
			$query = $wpdb->prepare( $query, $prepare_args );
		}

		$accounts = $wpdb->get_results( $query, ARRAY_A );

		return $accounts ? $accounts : array();
	}

	/**
	 * Create account in chart of accounts
	 *
	 * @param array $account_data Account data
	 * @return array|WP_Error Account data
	 */
	public function create_account( $account_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_treasury_accounts';

		$account = array(
			'name'         => $account_data['name'],
			'account_type' => $account_data['type'],
			'account_code' => isset( $account_data['code'] ) ? $account_data['code'] : '',
			'description'  => isset( $account_data['description'] ) ? $account_data['description'] : '',
			'parent_id'    => isset( $account_data['parent_id'] ) ? $account_data['parent_id'] : null,
			'balance'      => 0,
			'active'       => 1,
			'created_at'   => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $account );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		return array(
			'account_id' => $wpdb->insert_id,
		);
	}

	/**
	 * Sync contact (not applicable for built-in)
	 *
	 * @param array $contact_data Contact data
	 * @return array|WP_Error Contact data
	 */
	public function sync_contact( $contact_data ) {
		return new WP_Error( 'not_applicable', __( 'Contacts managed in People module', 'nonprofitsuite' ) );
	}

	/**
	 * Get contacts
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of contacts
	 */
	public function get_contacts( $args = array() ) {
		// Delegate to People module
		return NonprofitSuite_Person::get_all( $args );
	}

	/**
	 * Get account balance
	 *
	 * @param string $account_id Account identifier
	 * @param array  $args       Query arguments
	 * @return array|WP_Error Balance data
	 */
	public function get_account_balance( $account_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_treasury_accounts';

		$account = $wpdb->get_row( $wpdb->prepare(
			"SELECT balance FROM {$table} WHERE id = %d",
			$account_id
		), ARRAY_A );

		if ( ! $account ) {
			return new WP_Error( 'account_not_found', __( 'Account not found', 'nonprofitsuite' ) );
		}

		return array(
			'balance'  => $account['balance'],
			'currency' => 'USD', // TODO: Make configurable
		);
	}

	/**
	 * Generate financial report
	 *
	 * @param string $report_type Report type
	 * @param array  $args        Report arguments
	 * @return array|WP_Error Report data
	 */
	public function generate_report( $report_type, $args = array() ) {
		// TODO: Implement report generation using Treasury module
		return new WP_Error( 'not_implemented', __( 'Report generation coming soon', 'nonprofitsuite' ) );
	}

	/**
	 * Export data for CPA
	 *
	 * @param array $args Export arguments
	 * @return string|WP_Error File path
	 */
	public function export_for_cpa( $args = array() ) {
		// TODO: Implement CSV/IIF export
		return new WP_Error( 'not_implemented', __( 'Export functionality coming soon', 'nonprofitsuite' ) );
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_treasury_accounts';

		// Check if table exists
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

		if ( ! $exists ) {
			return new WP_Error( 'table_missing', __( 'Treasury tables do not exist', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return __( 'Built-in Treasury Module', 'nonprofitsuite' );
	}
}
