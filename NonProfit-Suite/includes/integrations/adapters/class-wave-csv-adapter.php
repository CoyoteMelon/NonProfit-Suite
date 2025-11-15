<?php
/**
 * Wave CSV Export Adapter
 *
 * Exports accounting data in Wave CSV format.
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
 * NonprofitSuite_Wave_CSV_Adapter Class
 *
 * Wave CSV export implementation.
 */
class NonprofitSuite_Wave_CSV_Adapter implements NonprofitSuite_Accounting_Adapter {

	/**
	 * Export chart of accounts.
	 *
	 * @param array $accounts Array of account data.
	 * @return string CSV formatted accounts.
	 */
	public function export_chart_of_accounts( $accounts ) {
		$output = "Account Name,Account Type,Account Number,Description\n";

		foreach ( $accounts as $account ) {
			$type = $this->map_account_type( $account['account_type'] );
			$output .= sprintf(
				"%s,%s,%s,%s\n",
				$this->escape_csv( $account['account_name'] ),
				$type,
				$this->escape_csv( $account['account_number'] ),
				$this->escape_csv( $account['description'] )
			);
		}

		return $output;
	}

	/**
	 * Export journal entries.
	 *
	 * @param array $entries Array of journal entry data.
	 * @param array $args    Optional export arguments.
	 * @return string CSV formatted journal entries.
	 */
	public function export_journal_entries( $entries, $args = array() ) {
		$output = "Transaction Date,Account Name,Debit Amount,Credit Amount,Description,Reference\n";

		foreach ( $entries as $entry ) {
			$date = date( 'Y-m-d', strtotime( $entry['entry_date'] ) );
			$output .= sprintf(
				"%s,%s,%s,%s,%s,%s\n",
				$date,
				$this->escape_csv( $entry['account_name'] ),
				$entry['debit_amount'] > 0 ? number_format( $entry['debit_amount'], 2, '.', '' ) : '',
				$entry['credit_amount'] > 0 ? number_format( $entry['credit_amount'], 2, '.', '' ) : '',
				$this->escape_csv( $entry['description'] ),
				$this->escape_csv( $entry['batch_id'] ?? $entry['entry_number'] )
			);
		}

		return $output;
	}

	/**
	 * Export transactions.
	 *
	 * @param array $transactions Array of transaction data.
	 * @param array $args         Optional export arguments.
	 * @return string CSV formatted transactions.
	 */
	public function export_transactions( $transactions, $args = array() ) {
		$entries = array();

		foreach ( $transactions as $txn ) {
			$batch_id = 'TXN-' . $txn['id'];
			$date = $txn['created_at'];

			// Cash received
			$entries[] = array(
				'entry_number'  => $txn['id'] . '-1',
				'batch_id'      => $batch_id,
				'entry_date'    => $date,
				'account_name'  => 'Cash - Operating',
				'debit_amount'  => $txn['net_amount'],
				'credit_amount' => 0,
				'description'   => $txn['description'],
			);

			// Revenue
			$entries[] = array(
				'entry_number'  => $txn['id'] . '-2',
				'batch_id'      => $batch_id,
				'entry_date'    => $date,
				'account_name'  => 'Donation Income',
				'debit_amount'  => 0,
				'credit_amount' => $txn['amount'],
				'description'   => $txn['description'],
			);

			// Fees
			if ( $txn['fee_amount'] > 0 ) {
				$entries[] = array(
					'entry_number'  => $txn['id'] . '-3',
					'batch_id'      => $batch_id,
					'entry_date'    => $date,
					'account_name'  => 'Payment Processing Fees',
					'debit_amount'  => $txn['fee_amount'],
					'credit_amount' => 0,
					'description'   => 'Processing fee for ' . $txn['description'],
				);
			}
		}

		return $this->export_journal_entries( $entries, $args );
	}

	/**
	 * Get export format name.
	 *
	 * @return string Format name.
	 */
	public function get_format_name() {
		return 'Wave CSV';
	}

	/**
	 * Get export file extension.
	 *
	 * @return string File extension.
	 */
	public function get_file_extension() {
		return 'csv';
	}

	/**
	 * Get export MIME type.
	 *
	 * @return string MIME type.
	 */
	public function get_mime_type() {
		return 'text/csv';
	}

	/**
	 * Get supported export types.
	 *
	 * @return array Supported export types.
	 */
	public function get_supported_types() {
		return array( 'accounts', 'entries', 'transactions' );
	}

	/**
	 * Map account type to Wave format.
	 *
	 * @param string $type NS account type.
	 * @return string Wave account type.
	 */
	private function map_account_type( $type ) {
		$mapping = array(
			'asset'     => 'Asset',
			'liability' => 'Liability',
			'equity'    => 'Equity',
			'revenue'   => 'Income',
			'expense'   => 'Expense',
		);

		return $mapping[ $type ] ?? 'Asset';
	}

	/**
	 * Escape field for CSV.
	 *
	 * @param string $field Field value.
	 * @return string Escaped field.
	 */
	private function escape_csv( $field ) {
		if ( strpos( $field, ',' ) !== false || strpos( $field, '"' ) !== false || strpos( $field, "\n" ) !== false ) {
			return '"' . str_replace( '"', '""', $field ) . '"';
		}
		return $field;
	}
}
