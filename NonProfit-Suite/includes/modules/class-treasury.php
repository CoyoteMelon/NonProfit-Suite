<?php
/**
 * Treasury Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Treasury {

	/**
	 * Check if Pro license is active.
	 */
	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Treasury module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	/**
	 * Initialize default chart of accounts.
	 */
	public static function initialize_chart_of_accounts() {
		$check = self::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$default_accounts = array(
			// Assets
			array( 'number' => '1000', 'name' => 'Cash - Operating', 'type' => 'asset' ),
			array( 'number' => '1100', 'name' => 'Cash - Savings', 'type' => 'asset' ),
			array( 'number' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset' ),
			array( 'number' => '1500', 'name' => 'Property & Equipment', 'type' => 'asset' ),

			// Liabilities
			array( 'number' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability' ),
			array( 'number' => '2100', 'name' => 'Accrued Expenses', 'type' => 'liability' ),
			array( 'number' => '2500', 'name' => 'Long-term Debt', 'type' => 'liability' ),

			// Equity/Net Assets
			array( 'number' => '3000', 'name' => 'Unrestricted Net Assets', 'type' => 'equity' ),
			array( 'number' => '3100', 'name' => 'Temporarily Restricted Net Assets', 'type' => 'equity' ),
			array( 'number' => '3200', 'name' => 'Permanently Restricted Net Assets', 'type' => 'equity' ),

			// Revenue
			array( 'number' => '4000', 'name' => 'Donations - Individual', 'type' => 'revenue' ),
			array( 'number' => '4100', 'name' => 'Donations - Corporate', 'type' => 'revenue' ),
			array( 'number' => '4200', 'name' => 'Grant Revenue', 'type' => 'revenue' ),
			array( 'number' => '4300', 'name' => 'Program Service Revenue', 'type' => 'revenue' ),
			array( 'number' => '4400', 'name' => 'Special Events Revenue', 'type' => 'revenue' ),
			array( 'number' => '4500', 'name' => 'Investment Income', 'type' => 'revenue' ),
			array( 'number' => '4900', 'name' => 'Other Revenue', 'type' => 'revenue' ),

			// Expenses - Program
			array( 'number' => '5000', 'name' => 'Program Salaries', 'type' => 'expense' ),
			array( 'number' => '5100', 'name' => 'Program Supplies', 'type' => 'expense' ),
			array( 'number' => '5200', 'name' => 'Program Services', 'type' => 'expense' ),

			// Expenses - Administration
			array( 'number' => '6000', 'name' => 'Administrative Salaries', 'type' => 'expense' ),
			array( 'number' => '6100', 'name' => 'Office Rent', 'type' => 'expense' ),
			array( 'number' => '6200', 'name' => 'Office Supplies', 'type' => 'expense' ),
			array( 'number' => '6300', 'name' => 'Professional Fees', 'type' => 'expense' ),
			array( 'number' => '6400', 'name' => 'Insurance', 'type' => 'expense' ),
			array( 'number' => '6500', 'name' => 'Utilities', 'type' => 'expense' ),

			// Expenses - Fundraising
			array( 'number' => '7000', 'name' => 'Fundraising Salaries', 'type' => 'expense' ),
			array( 'number' => '7100', 'name' => 'Fundraising Events', 'type' => 'expense' ),
			array( 'number' => '7200', 'name' => 'Donor Recognition', 'type' => 'expense' ),
		);

		foreach ( $default_accounts as $account ) {
			self::create_account( array(
				'account_number' => $account['number'],
				'account_name' => $account['name'],
				'account_type' => $account['type'],
			) );
		}

		return true;
	}

	/**
	 * Create account.
	 */
	public static function create_account( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_accounts';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Validate required fields
		if ( empty( $data['account_number'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Account number is required.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['account_name'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Account name is required.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['account_type'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Account type is required.', 'nonprofitsuite' ) );
		}

		// Validate account_type
		$valid_types = array( 'asset', 'liability', 'equity', 'revenue', 'expense' );
		if ( ! in_array( $data['account_type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_account_type', __( 'Invalid account type. Must be asset, liability, equity, revenue, or expense.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'account_number' => sanitize_text_field( $data['account_number'] ),
				'account_name' => sanitize_text_field( $data['account_name'] ),
				'account_type' => sanitize_text_field( $data['account_type'] ),
				'parent_account_id' => isset( $data['parent_account_id'] ) ? absint( $data['parent_account_id'] ) : null,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to create account - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to create account.', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get all accounts.
	 */
	public static function get_accounts( $type = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_accounts';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Use cache for accounts list
		$cache_key = NonprofitSuite_Cache::list_key( 'treasury_accounts', array( 'type' => $type ) );

		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $type ) {
			$where = "WHERE is_active = 1";
			if ( $type ) {
				$where .= $wpdb->prepare( " AND account_type = %s", $type );
			}

			// SELECT specific columns instead of *
			return $wpdb->get_results( "SELECT id, account_number, account_name, account_type, balance, description, parent_account_id FROM {$table} {$where} ORDER BY account_number ASC" );
		}, 300 ); // Cache for 5 minutes
	}

	/**
	 * Record transaction.
	 */
	public static function record_transaction( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_transactions';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Validate required fields
		if ( empty( $data['transaction_date'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Transaction date is required.', 'nonprofitsuite' ) );
		}

		// Validate date format
		if ( ! strtotime( $data['transaction_date'] ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid transaction date format.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['transaction_type'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Transaction type is required.', 'nonprofitsuite' ) );
		}

		// Validate transaction_type
		$valid_types = array( 'debit', 'credit' );
		if ( ! in_array( $data['transaction_type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_transaction_type', __( 'Invalid transaction type. Must be debit or credit.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['account_id'] ) || ! is_numeric( $data['account_id'] ) || $data['account_id'] <= 0 ) {
			return new WP_Error( 'invalid_account_id', __( 'Valid account ID is required.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['amount'] ) || ! is_numeric( $data['amount'] ) || $data['amount'] <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Transaction amount must be greater than zero.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['description'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Transaction description is required.', 'nonprofitsuite' ) );
		}

		// Start transaction for data consistency
		$wpdb->query( 'START TRANSACTION' );

		$result = $wpdb->insert(
			$table,
			array(
				'transaction_date' => sanitize_text_field( $data['transaction_date'] ),
				'transaction_type' => sanitize_text_field( $data['transaction_type'] ),
				'account_id' => absint( $data['account_id'] ),
				'amount' => floatval( $data['amount'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'reference_number' => isset( $data['reference_number'] ) ? sanitize_text_field( $data['reference_number'] ) : null,
				'payee' => isset( $data['payee'] ) ? sanitize_text_field( $data['payee'] ) : null,
				'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
				'fund_id' => isset( $data['fund_id'] ) ? absint( $data['fund_id'] ) : null,
				'created_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'NonprofitSuite: Failed to record transaction - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to record transaction.', 'nonprofitsuite' ) );
		}

		$transaction_id = $wpdb->insert_id;

		// Update account balance
		$update_result = self::update_account_balance( $data['account_id'] );
		if ( is_wp_error( $update_result ) ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'NonprofitSuite: Failed to update account balance - ' . $update_result->get_error_message() );
			return new WP_Error( 'db_error', __( 'Failed to update account balance.', 'nonprofitsuite' ) );
		}

		// Commit transaction
		$wpdb->query( 'COMMIT' );

		// Invalidate treasury cache when new transaction is recorded
		NonprofitSuite_Cache::invalidate_module( 'treasury' );

		return $transaction_id;
	}

	/**
	 * Get transactions.
	 */
	public static function get_transactions( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_transactions';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Build WHERE clause
		$where = "WHERE 1=1";
		$where_params = array();

		if ( ! empty( $args['account_id'] ) && $args['account_id'] > 0 ) {
			$where .= " AND account_id = %d";
			$where_params[] = $args['account_id'];
		}
		if ( ! empty( $args['start_date'] ) ) {
			$where .= " AND transaction_date >= %s";
			$where_params[] = $args['start_date'];
		}
		if ( ! empty( $args['end_date'] ) ) {
			$where .= " AND transaction_date <= %s";
			$where_params[] = $args['end_date'];
		}

		// Prepare WHERE clause if there are parameters
		if ( ! empty( $where_params ) ) {
			$where = $wpdb->prepare( $where, $where_params );
		}

		// Build query with specific columns and pagination
		$sql = "SELECT id, transaction_date, transaction_type, account_id, amount, description,
		               reference_number, payee, category, fund_id, reconciled, created_by, created_at
		        FROM {$table}
		        {$where}
		        ORDER BY transaction_date DESC, id DESC
		        " . NonprofitSuite_Utilities::build_limit_clause( $args );

		return $wpdb->get_results( $sql );
	}

	/**
	 * Update account balance.
	 */
	private static function update_account_balance( $account_id ) {
		global $wpdb;
		$accounts_table = $wpdb->prefix . 'ns_accounts';
		$trans_table = $wpdb->prefix . 'ns_transactions';

		$balance = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(CASE
				WHEN transaction_type = 'debit' THEN amount
				WHEN transaction_type = 'credit' THEN -amount
				ELSE 0
			END) FROM {$trans_table} WHERE account_id = %d",
			$account_id
		) );

		if ( null === $balance && $wpdb->last_error ) {
			error_log( 'NonprofitSuite: Failed to calculate account balance - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to calculate account balance.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$accounts_table,
			array( 'balance' => $balance ),
			array( 'id' => $account_id ),
			array( '%f' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to update account balance - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to update account balance.', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Generate Balance Sheet.
	 */
	public static function generate_balance_sheet( $as_of_date = null ) {
		$check = self::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( ! $as_of_date ) {
			$as_of_date = current_time( 'Y-m-d' );
		}

		// Cache balance sheet for 1 hour (it's expensive to generate)
		$cache_key = 'ns_treasury_balance_sheet_' . $as_of_date;

		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $as_of_date ) {
			$assets = self::get_accounts_by_type_with_balance( 'asset', $as_of_date );
			$liabilities = self::get_accounts_by_type_with_balance( 'liability', $as_of_date );
			$equity = self::get_accounts_by_type_with_balance( 'equity', $as_of_date );

			return array(
				'as_of_date' => $as_of_date,
				'assets' => $assets,
				'liabilities' => $liabilities,
				'equity' => $equity,
				'total_assets' => array_sum( wp_list_pluck( $assets, 'balance' ) ),
				'total_liabilities' => array_sum( wp_list_pluck( $liabilities, 'balance' ) ),
				'total_equity' => array_sum( wp_list_pluck( $equity, 'balance' ) ),
			);
		}, 3600, 'transient' ); // Cache for 1 hour using transients (survives server restart)
	}

	/**
	 * Generate Income Statement (Statement of Activities).
	 */
	public static function generate_income_statement( $start_date, $end_date ) {
		$check = self::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Cache income statement for 1 hour
		$cache_key = sprintf( 'ns_treasury_income_stmt_%s_%s', $start_date, $end_date );

		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $start_date, $end_date ) {
			$revenue = self::get_accounts_by_type_with_balance( 'revenue', $end_date, $start_date );
			$expenses = self::get_accounts_by_type_with_balance( 'expense', $end_date, $start_date );

			$total_revenue = array_sum( wp_list_pluck( $revenue, 'balance' ) );
			$total_expenses = array_sum( wp_list_pluck( $expenses, 'balance' ) );

			return array(
				'start_date' => $start_date,
				'end_date' => $end_date,
				'revenue' => $revenue,
				'expenses' => $expenses,
				'total_revenue' => $total_revenue,
				'total_expenses' => $total_expenses,
				'net_income' => $total_revenue - $total_expenses,
			);
		}, 3600, 'transient' ); // Cache for 1 hour
	}

	/**
	 * Get accounts by type with balance.
	 */
	private static function get_accounts_by_type_with_balance( $type, $end_date, $start_date = null ) {
		global $wpdb;
		$accounts_table = $wpdb->prefix . 'ns_accounts';
		$trans_table = $wpdb->prefix . 'ns_transactions';

		$date_where = $wpdb->prepare( "AND t.transaction_date <= %s", $end_date );
		if ( $start_date ) {
			$date_where .= $wpdb->prepare( " AND t.transaction_date >= %s", $start_date );
		}

		$sql = $wpdb->prepare(
			"SELECT a.id, a.account_number, a.account_name, a.account_type,
			COALESCE(SUM(CASE
				WHEN t.transaction_type = 'debit' THEN t.amount
				WHEN t.transaction_type = 'credit' THEN -t.amount
				ELSE 0
			END), 0) as balance
			FROM {$accounts_table} a
			LEFT JOIN {$trans_table} t ON a.id = t.account_id {$date_where}
			WHERE a.account_type = %s AND a.is_active = 1
			GROUP BY a.id
			ORDER BY a.account_number",
			$type
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get account types.
	 */
	public static function get_account_types() {
		return array(
			'asset' => __( 'Asset', 'nonprofitsuite' ),
			'liability' => __( 'Liability', 'nonprofitsuite' ),
			'equity' => __( 'Equity/Net Assets', 'nonprofitsuite' ),
			'revenue' => __( 'Revenue', 'nonprofitsuite' ),
			'expense' => __( 'Expense', 'nonprofitsuite' ),
		);
	}

	/**
	 * Get transaction types.
	 */
	public static function get_transaction_types() {
		return array(
			'debit' => __( 'Debit', 'nonprofitsuite' ),
			'credit' => __( 'Credit', 'nonprofitsuite' ),
		);
	}
}
