<?php
/**
 * QuickBooks IIF Export Adapter
 *
 * Exports accounting data in QuickBooks IIF format.
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
 * NonprofitSuite_QuickBooks_IIF_Adapter Class
 *
 * QuickBooks IIF export implementation.
 */
class NonprofitSuite_QuickBooks_IIF_Adapter implements NonprofitSuite_Accounting_Adapter {

	/**
	 * Export chart of accounts.
	 *
	 * @param array $accounts Array of account data.
	 * @return string IIF formatted accounts.
	 */
	public function export_chart_of_accounts( $accounts ) {
		$output = "!ACCNT\tNAME\tACCNTTYPE\tDESC\tACCNUM\n";

		foreach ( $accounts as $account ) {
			$type = $this->map_account_type( $account['account_type'] );
			$output .= sprintf(
				"ACCNT\t%s\t%s\t%s\t%s\n",
				$this->escape_field( $account['account_name'] ),
				$type,
				$this->escape_field( $account['description'] ),
				$this->escape_field( $account['account_number'] )
			);
		}

		return $output;
	}

	/**
	 * Export journal entries.
	 *
	 * @param array $entries Array of journal entry data.
	 * @param array $args    Optional export arguments.
	 * @return string IIF formatted journal entries.
	 */
	public function export_journal_entries( $entries, $args = array() ) {
		$output = "!TRNS\tTRNSID\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tMEMO\n";
		$output .= "!SPL\tSPLID\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tMEMO\n";
		$output .= "!ENDTRNS\n";

		// Group entries by batch_id
		$batches = array();
		foreach ( $entries as $entry ) {
			$batch_id = $entry['batch_id'] ?? 'BATCH-' . $entry['id'];
			if ( ! isset( $batches[ $batch_id ] ) ) {
				$batches[ $batch_id ] = array();
			}
			$batches[ $batch_id ][] = $entry;
		}

		foreach ( $batches as $batch_id => $batch_entries ) {
			if ( empty( $batch_entries ) ) {
				continue;
			}

			$first_entry = $batch_entries[0];
			$date = date( 'm/d/Y', strtotime( $first_entry['entry_date'] ) );

			// First line: TRNS (header)
			$output .= sprintf(
				"TRNS\t%s\tGENERAL JOURNAL\t%s\t\t\t%s\n",
				$this->escape_field( $batch_id ),
				$date,
				$this->escape_field( $first_entry['description'] )
			);

			// Subsequent lines: SPL (splits)
			foreach ( $batch_entries as $entry ) {
				$amount = 0;
				if ( $entry['debit_amount'] > 0 ) {
					$amount = $entry['debit_amount'];
				} elseif ( $entry['credit_amount'] > 0 ) {
					$amount = -$entry['credit_amount'];
				}

				$output .= sprintf(
					"SPL\t%s\tGENERAL JOURNAL\t%s\t%s\t%s\t%s\n",
					$this->escape_field( $batch_id . '-' . $entry['id'] ),
					$date,
					$this->escape_field( $entry['account_name'] ),
					number_format( $amount, 2, '.', '' ),
					$this->escape_field( $entry['description'] )
				);
			}

			$output .= "ENDTRNS\n";
		}

		return $output;
	}

	/**
	 * Export transactions.
	 *
	 * @param array $transactions Array of transaction data.
	 * @param array $args         Optional export arguments.
	 * @return string IIF formatted transactions.
	 */
	public function export_transactions( $transactions, $args = array() ) {
		// Convert transactions to journal entries format
		$entries = array();

		foreach ( $transactions as $txn ) {
			$batch_id = 'TXN-' . $txn['id'];
			$date = $txn['created_at'];

			// Debit: Cash/Bank Account
			$entries[] = array(
				'id'           => $txn['id'] . '-1',
				'batch_id'     => $batch_id,
				'entry_date'   => $date,
				'account_name' => 'Cash - Operating',
				'debit_amount' => $txn['net_amount'],
				'credit_amount' => 0,
				'description'  => $txn['description'],
			);

			// Credit: Revenue Account
			$entries[] = array(
				'id'           => $txn['id'] . '-2',
				'batch_id'     => $batch_id,
				'entry_date'   => $date,
				'account_name' => 'Donation Revenue',
				'debit_amount' => 0,
				'credit_amount' => $txn['amount'],
				'description'  => $txn['description'],
			);

			// If there are fees, debit expense account
			if ( $txn['fee_amount'] > 0 ) {
				$entries[] = array(
					'id'           => $txn['id'] . '-3',
					'batch_id'     => $batch_id,
					'entry_date'   => $date,
					'account_name' => 'Payment Processing Fees',
					'debit_amount' => $txn['fee_amount'],
					'credit_amount' => 0,
					'description'  => 'Processing fee for ' . $txn['description'],
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
		return 'QuickBooks IIF';
	}

	/**
	 * Get export file extension.
	 *
	 * @return string File extension.
	 */
	public function get_file_extension() {
		return 'iif';
	}

	/**
	 * Get export MIME type.
	 *
	 * @return string MIME type.
	 */
	public function get_mime_type() {
		return 'application/octet-stream';
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
	 * Map account type to QuickBooks format.
	 *
	 * @param string $type NS account type.
	 * @return string QuickBooks account type.
	 */
	private function map_account_type( $type ) {
		$mapping = array(
			'asset'     => 'BANK',
			'liability' => 'OCLIAB',
			'equity'    => 'EQUITY',
			'revenue'   => 'INC',
			'expense'   => 'EXP',
		);

		return $mapping[ $type ] ?? 'OASSET';
	}

	/**
	 * Escape field for IIF format.
	 *
	 * @param string $field Field value.
	 * @return string Escaped field.
	 */
	private function escape_field( $field ) {
		// Remove tabs and newlines
		$field = str_replace( array( "\t", "\n", "\r" ), ' ', $field );
		// Trim whitespace
		return trim( $field );
	}
}
