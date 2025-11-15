<?php
/**
 * Accounting Manager
 *
 * Manages chart of accounts, journal entries, and accounting exports.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Accounting_Manager Class
 *
 * Central accounting system manager.
 */
class NonprofitSuite_Accounting_Manager {

	/**
	 * Registered export adapters.
	 *
	 * @var array
	 */
	private static $adapters = array();

	/**
	 * Initialize accounting system.
	 */
	public static function init() {
		// Register default adapters
		self::register_adapter( 'quickbooks', 'NonprofitSuite_QuickBooks_IIF_Adapter' );
		self::register_adapter( 'xero', 'NonprofitSuite_Xero_CSV_Adapter' );
		self::register_adapter( 'wave', 'NonprofitSuite_Wave_CSV_Adapter' );

		// Hook into payment events to create journal entries
		add_action( 'nonprofitsuite_transaction_logged', array( __CLASS__, 'create_transaction_entries' ), 10, 2 );
	}

	/**
	 * Register an export adapter.
	 *
	 * @param string $key           Adapter key.
	 * @param string $adapter_class Adapter class name.
	 */
	public static function register_adapter( $key, $adapter_class ) {
		self::$adapters[ $key ] = $adapter_class;
	}

	/**
	 * Get export adapter.
	 *
	 * @param string $key Adapter key.
	 * @return NonprofitSuite_Accounting_Adapter|null Adapter instance or null.
	 */
	public static function get_adapter( $key ) {
		if ( ! isset( self::$adapters[ $key ] ) ) {
			return null;
		}

		$class = self::$adapters[ $key ];
		if ( ! class_exists( $class ) ) {
			return null;
		}

		return new $class();
	}

	/**
	 * Get all registered adapters.
	 *
	 * @return array Registered adapters.
	 */
	public static function get_adapters() {
		return self::$adapters;
	}

	/**
	 * Create or update account.
	 *
	 * @param array $account_data Account data.
	 * @return int|WP_Error Account ID or error.
	 */
	public static function save_account( $account_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_chart_of_accounts';

		$required = array( 'organization_id', 'account_number', 'account_name', 'account_type' );
		foreach ( $required as $field ) {
			if ( empty( $account_data[ $field ] ) ) {
				return new WP_Error( 'missing_field', "Required field missing: {$field}" );
			}
		}

		$data = array(
			'organization_id'   => $account_data['organization_id'],
			'account_number'    => $account_data['account_number'],
			'account_name'      => $account_data['account_name'],
			'account_type'      => $account_data['account_type'],
			'account_subtype'   => $account_data['account_subtype'] ?? null,
			'parent_account_id' => $account_data['parent_account_id'] ?? null,
			'description'       => $account_data['description'] ?? '',
			'is_active'         => $account_data['is_active'] ?? 1,
			'is_system'         => $account_data['is_system'] ?? 0,
		);

		if ( isset( $account_data['id'] ) && $account_data['id'] > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $account_data['id'] ) );
			return $account_data['id'];
		} else {
			$wpdb->insert( $table, $data );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Get account by ID.
	 *
	 * @param int $account_id Account ID.
	 * @return array|null Account data or null.
	 */
	public static function get_account( $account_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_chart_of_accounts';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $account_id ),
			ARRAY_A
		);
	}

	/**
	 * Get all accounts for an organization.
	 *
	 * @param int   $organization_id Organization ID.
	 * @param array $args            Query arguments.
	 * @return array Accounts.
	 */
	public static function get_accounts( $organization_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_chart_of_accounts';

		$defaults = array(
			'account_type' => '',
			'is_active'    => null,
			'order_by'     => 'account_number',
			'order'        => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY column against whitelist
		$allowed_order_by = array( 'account_number', 'account_name', 'account_type', 'balance', 'id' );
		if ( ! in_array( $args['order_by'], $allowed_order_by, true ) ) {
			$args['order_by'] = 'account_number';
		}

		// Validate ORDER direction
		$args['order'] = strtoupper( $args['order'] );
		if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ), true ) ) {
			$args['order'] = 'ASC';
		}

		$where = array( $wpdb->prepare( 'organization_id = %d', $organization_id ) );

		if ( ! empty( $args['account_type'] ) ) {
			$where[] = $wpdb->prepare( 'account_type = %s', $args['account_type'] );
		}

		if ( null !== $args['is_active'] ) {
			$where[] = $wpdb->prepare( 'is_active = %d', $args['is_active'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$args['order_by']} {$args['order']}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Create journal entry.
	 *
	 * @param array $entries Array of entry data (batch).
	 * @return string|WP_Error Batch ID or error.
	 */
	public static function create_journal_entry( $entries ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_journal_entries';

		$batch_id = 'BATCH-' . time() . '-' . wp_rand( 1000, 9999 );
		$entry_number = 'JE-' . date( 'Ymd' ) . '-' . wp_rand( 1000, 9999 );

		$total_debits = 0;
		$total_credits = 0;

		foreach ( $entries as $entry ) {
			$total_debits += $entry['debit_amount'];
			$total_credits += $entry['credit_amount'];

			$data = array(
				'organization_id' => $entry['organization_id'],
				'entry_number'    => $entry_number,
				'entry_date'      => $entry['entry_date'] ?? date( 'Y-m-d' ),
				'account_id'      => $entry['account_id'],
				'debit_amount'    => $entry['debit_amount'],
				'credit_amount'   => $entry['credit_amount'],
				'description'     => $entry['description'] ?? '',
				'reference_type'  => $entry['reference_type'] ?? 'manual',
				'reference_id'    => $entry['reference_id'] ?? null,
				'batch_id'        => $batch_id,
				'created_by'      => get_current_user_id(),
			);

			$wpdb->insert( $table, $data );

			// Update account balance
			self::update_account_balance( $entry['account_id'], $entry['debit_amount'], $entry['credit_amount'] );
		}

		// Validate double-entry
		if ( abs( $total_debits - $total_credits ) > 0.01 ) {
			return new WP_Error( 'unbalanced_entry', 'Journal entry debits and credits do not balance' );
		}

		return $batch_id;
	}

	/**
	 * Create journal entries from payment transaction.
	 *
	 * @param int   $transaction_id Transaction ID.
	 * @param array $transaction_data Transaction data.
	 */
	public static function create_transaction_entries( $transaction_id, $transaction_data ) {
		// Check if accounting integration is enabled
		$enabled = get_option( 'ns_accounting_auto_entries', 'yes' );
		if ( 'no' === $enabled ) {
			return;
		}

		// Get account IDs from settings
		$cash_account = get_option( 'ns_accounting_cash_account' );
		$revenue_account = get_option( 'ns_accounting_revenue_account' );
		$fee_account = get_option( 'ns_accounting_fee_account' );

		if ( ! $cash_account || ! $revenue_account ) {
			return;
		}

		$entries = array();

		// Debit: Cash Account
		$entries[] = array(
			'organization_id' => $transaction_data['organization_id'],
			'entry_date'      => date( 'Y-m-d' ),
			'account_id'      => $cash_account,
			'debit_amount'    => $transaction_data['net_amount'],
			'credit_amount'   => 0,
			'description'     => $transaction_data['description'] ?? 'Payment received',
			'reference_type'  => 'transaction',
			'reference_id'    => $transaction_id,
		);

		// Credit: Revenue Account
		$entries[] = array(
			'organization_id' => $transaction_data['organization_id'],
			'entry_date'      => date( 'Y-m-d' ),
			'account_id'      => $revenue_account,
			'debit_amount'    => 0,
			'credit_amount'   => $transaction_data['amount'],
			'description'     => $transaction_data['description'] ?? 'Payment received',
			'reference_type'  => 'transaction',
			'reference_id'    => $transaction_id,
		);

		// Debit: Fee Expense (if applicable)
		if ( $transaction_data['fee_amount'] > 0 && $fee_account ) {
			$entries[] = array(
				'organization_id' => $transaction_data['organization_id'],
				'entry_date'      => date( 'Y-m-d' ),
				'account_id'      => $fee_account,
				'debit_amount'    => $transaction_data['fee_amount'],
				'credit_amount'   => 0,
				'description'     => 'Payment processing fee',
				'reference_type'  => 'transaction',
				'reference_id'    => $transaction_id,
			);
		}

		self::create_journal_entry( $entries );
	}

	/**
	 * Update account balance.
	 *
	 * @param int   $account_id Account ID.
	 * @param float $debit      Debit amount.
	 * @param float $credit     Credit amount.
	 */
	private static function update_account_balance( $account_id, $debit, $credit ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_chart_of_accounts';

		$account = self::get_account( $account_id );
		if ( ! $account ) {
			return;
		}

		$change = 0;
		// Assets and Expenses increase with debits
		if ( in_array( $account['account_type'], array( 'asset', 'expense' ), true ) ) {
			$change = $debit - $credit;
		} else {
			// Liabilities, Equity, Revenue increase with credits
			$change = $credit - $debit;
		}

		$new_balance = $account['current_balance'] + $change;

		$wpdb->update(
			$table,
			array( 'current_balance' => $new_balance ),
			array( 'id' => $account_id ),
			array( '%f' ),
			array( '%d' )
		);
	}

	/**
	 * Export data using specified adapter.
	 *
	 * @param string $adapter_key Adapter key.
	 * @param string $export_type Export type (accounts, entries, transactions).
	 * @param array  $args        Export arguments.
	 * @return array|WP_Error Export result with content and filename.
	 */
	public static function export( $adapter_key, $export_type, $args = array() ) {
		$adapter = self::get_adapter( $adapter_key );
		if ( ! $adapter ) {
			return new WP_Error( 'invalid_adapter', 'Invalid export adapter' );
		}

		$org_id = $args['organization_id'] ?? 1;

		switch ( $export_type ) {
			case 'accounts':
				$data = self::get_accounts( $org_id );
				$content = $adapter->export_chart_of_accounts( $data );
				break;

			case 'entries':
				$data = self::get_journal_entries( $org_id, $args );
				$content = $adapter->export_journal_entries( $data, $args );
				break;

			case 'transactions':
				$data = NonprofitSuite_Transaction_Logger::get_organization_transactions( $org_id, $args );
				$content = $adapter->export_transactions( $data, $args );
				break;

			default:
				return new WP_Error( 'invalid_type', 'Invalid export type' );
		}

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$filename = sprintf(
			'%s-%s-%s.%s',
			sanitize_file_name( get_bloginfo( 'name' ) ),
			$export_type,
			date( 'Y-m-d' ),
			$adapter->get_file_extension()
		);

		return array(
			'content'   => $content,
			'filename'  => $filename,
			'mime_type' => $adapter->get_mime_type(),
		);
	}

	/**
	 * Get journal entries.
	 *
	 * @param int   $organization_id Organization ID.
	 * @param array $args            Query arguments.
	 * @return array Journal entries.
	 */
	public static function get_journal_entries( $organization_id, $args = array() ) {
		global $wpdb;
		$entries_table = $wpdb->prefix . 'ns_journal_entries';
		$accounts_table = $wpdb->prefix . 'ns_chart_of_accounts';

		$defaults = array(
			'date_from' => '',
			'date_to'   => '',
			'batch_id'  => '',
			'limit'     => 1000,
			'offset'    => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'e.organization_id = %d', $organization_id ) );

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'e.entry_date >= %s', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'e.entry_date <= %s', $args['date_to'] );
		}

		if ( ! empty( $args['batch_id'] ) ) {
			$where[] = $wpdb->prepare( 'e.batch_id = %s', $args['batch_id'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT e.*, a.account_number, a.account_name, a.account_type
				  FROM {$entries_table} e
				  LEFT JOIN {$accounts_table} a ON e.account_id = a.id
				  WHERE {$where_clause}
				  ORDER BY e.entry_date DESC, e.batch_id, e.id
				  LIMIT {$args['limit']} OFFSET {$args['offset']}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get setting value.
	 *
	 * @param int    $organization_id Organization ID.
	 * @param string $key             Setting key.
	 * @param mixed  $default         Default value.
	 * @return mixed Setting value.
	 */
	public static function get_setting( $organization_id, $key, $default = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_accounting_settings';

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table} WHERE organization_id = %d AND setting_key = %s",
				$organization_id,
				$key
			)
		);

		return $value !== null ? maybe_unserialize( $value ) : $default;
	}

	/**
	 * Save setting value.
	 *
	 * @param int    $organization_id Organization ID.
	 * @param string $key             Setting key.
	 * @param mixed  $value           Setting value.
	 */
	public static function save_setting( $organization_id, $key, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_accounting_settings';

		$serialized = maybe_serialize( $value );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE organization_id = %d AND setting_key = %s",
				$organization_id,
				$key
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array( 'setting_value' => $serialized ),
				array(
					'organization_id' => $organization_id,
					'setting_key'     => $key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'organization_id' => $organization_id,
					'setting_key'     => $key,
					'setting_value'   => $serialized,
				),
				array( '%d', '%s', '%s' )
			);
		}
	}
}
