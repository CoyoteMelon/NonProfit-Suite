<?php
/**
 * Xero CSV Export Adapter
 *
 * Exports accounting data in Xero CSV format.
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
 * NonprofitSuite_Xero_CSV_Adapter Class
 *
 * Xero CSV export implementation.
 */
class NonprofitSuite_Xero_CSV_Adapter implements NonprofitSuite_Accounting_Adapter {

	/**
	 * Export chart of accounts.
	 *
	 * @param array $accounts Array of account data.
	 * @return string CSV formatted accounts.
	 */
	public function export_chart_of_accounts( $accounts ) {
		$output = "Account Code,Account Name,Type,Description,Tax Type\n";

		foreach ( $accounts as $account ) {
			$type = $this->map_account_type( $account['account_type'] );
			$output .= sprintf(
				"%s,%s,%s,%s,%s\n",
				$this->escape_csv( $account['account_number'] ),
				$this->escape_csv( $account['account_name'] ),
				$type,
				$this->escape_csv( $account['description'] ),
				'Tax Exempt (0%)'
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
		$output = "*ContactName,EmailAddress,POAddressLine1,POAddressLine2,POAddressLine3,POAddressLine4,POCity,PORegion,POPostalCode,POCountry,*InvoiceNumber,Reference,*InvoiceDate,*DueDate,Total,TaxTotal,*InvoiceAmountPaid,*InvoiceAmountDue,InventoryItemCode,*Description,*Quantity,*UnitAmount,Discount,*AccountCode,*TaxType,TaxAmount,TrackingName1,TrackingOption1,TrackingName2,TrackingOption2,Currency,BrandingTheme\n";

		// Group entries by batch
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
			$date = date( 'd/m/Y', strtotime( $first_entry['entry_date'] ) );

			foreach ( $batch_entries as $entry ) {
				$amount = $entry['debit_amount'] > 0 ? $entry['debit_amount'] : -$entry['credit_amount'];

				$output .= sprintf(
					"Journal Entry,,,,,,,,,,%s,%s,%s,%s,%s,0,%s,0,,%s,1,%s,0,%s,Tax Exempt (0%),0,,,,USD,\n",
					$this->escape_csv( $batch_id ),
					$this->escape_csv( $batch_id ),
					$date,
					$date,
					number_format( abs( $amount ), 2, '.', '' ),
					number_format( abs( $amount ), 2, '.', '' ),
					$this->escape_csv( $entry['description'] ),
					number_format( $amount, 2, '.', '' ),
					$this->escape_csv( $entry['account_number'] ?? '' )
				);
			}
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

			// Cash account debit
			$entries[] = array(
				'id'             => $txn['id'] . '-1',
				'batch_id'       => $batch_id,
				'entry_date'     => $date,
				'account_number' => '1000',
				'account_name'   => 'Operating Account',
				'debit_amount'   => $txn['net_amount'],
				'credit_amount'  => 0,
				'description'    => $txn['description'],
			);

			// Revenue account credit
			$entries[] = array(
				'id'             => $txn['id'] . '-2',
				'batch_id'       => $batch_id,
				'entry_date'     => $date,
				'account_number' => '4000',
				'account_name'   => 'Donation Revenue',
				'debit_amount'   => 0,
				'credit_amount'  => $txn['amount'],
				'description'    => $txn['description'],
			);

			// Fee expense
			if ( $txn['fee_amount'] > 0 ) {
				$entries[] = array(
					'id'             => $txn['id'] . '-3',
					'batch_id'       => $batch_id,
					'entry_date'     => $date,
					'account_number' => '6100',
					'account_name'   => 'Payment Processing Fees',
					'debit_amount'   => $txn['fee_amount'],
					'credit_amount'  => 0,
					'description'    => 'Processing fee',
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
		return 'Xero CSV';
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
	 * Map account type to Xero format.
	 *
	 * @param string $type NS account type.
	 * @return string Xero account type.
	 */
	private function map_account_type( $type ) {
		$mapping = array(
			'asset'     => 'BANK',
			'liability' => 'LIABILITY',
			'equity'    => 'EQUITY',
			'revenue'   => 'REVENUE',
			'expense'   => 'EXPENSE',
		);

		return $mapping[ $type ] ?? 'CURRENT';
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
