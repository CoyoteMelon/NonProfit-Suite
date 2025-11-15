<?php
/**
 * Payment Transaction Logger
 *
 * Centralized logging for payment transactions.
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
 * NonprofitSuite_Transaction_Logger Class
 *
 * Handles payment transaction logging and retrieval.
 */
class NonprofitSuite_Transaction_Logger {

	/**
	 * Log a payment transaction.
	 *
	 * @param array $transaction_data Transaction data.
	 * @return int|WP_Error Transaction ID or error.
	 */
	public static function log_transaction( $transaction_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		// Validate required fields
		$required = array( 'organization_id', 'processor_id', 'amount', 'status' );
		foreach ( $required as $field ) {
			if ( ! isset( $transaction_data[ $field ] ) ) {
				return new WP_Error( 'missing_field', "Required field missing: {$field}" );
			}
		}

		// Prepare transaction data
		$data = array(
			'organization_id'          => $transaction_data['organization_id'],
			'donor_id'                 => isset( $transaction_data['donor_id'] ) ? $transaction_data['donor_id'] : null,
			'processor_id'             => $transaction_data['processor_id'],
			'processor_transaction_id' => isset( $transaction_data['processor_transaction_id'] ) ? $transaction_data['processor_transaction_id'] : '',
			'amount'                   => $transaction_data['amount'],
			'fee_amount'               => isset( $transaction_data['fee_amount'] ) ? $transaction_data['fee_amount'] : 0.00,
			'net_amount'               => isset( $transaction_data['net_amount'] ) ? $transaction_data['net_amount'] : $transaction_data['amount'],
			'currency'                 => isset( $transaction_data['currency'] ) ? $transaction_data['currency'] : 'USD',
			'status'                   => $transaction_data['status'],
			'payment_type'             => isset( $transaction_data['payment_type'] ) ? $transaction_data['payment_type'] : 'donation',
			'description'              => isset( $transaction_data['description'] ) ? $transaction_data['description'] : '',
			'fee_paid_by'              => isset( $transaction_data['fee_paid_by'] ) ? $transaction_data['fee_paid_by'] : 'org',
			'bank_account_id'          => isset( $transaction_data['bank_account_id'] ) ? $transaction_data['bank_account_id'] : null,
			'pledge_id'                => isset( $transaction_data['pledge_id'] ) ? $transaction_data['pledge_id'] : null,
			'recurring_donation_id'    => isset( $transaction_data['recurring_donation_id'] ) ? $transaction_data['recurring_donation_id'] : null,
			'fund_id'                  => isset( $transaction_data['fund_id'] ) ? $transaction_data['fund_id'] : null,
			'campaign_id'              => isset( $transaction_data['campaign_id'] ) ? $transaction_data['campaign_id'] : null,
			'event_id'                 => isset( $transaction_data['event_id'] ) ? $transaction_data['event_id'] : null,
			'created_at'               => isset( $transaction_data['created_at'] ) ? $transaction_data['created_at'] : current_time( 'mysql' ),
		);

		// Insert transaction
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to log transaction', array( 'error' => $wpdb->last_error ) );
		}

		$transaction_id = $wpdb->insert_id;

		// Trigger action for integrations
		do_action( 'nonprofitsuite_transaction_logged', $transaction_id, $data );

		return $transaction_id;
	}

	/**
	 * Update transaction status.
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param string $status         New status.
	 * @param array  $additional_data Additional data to update.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function update_transaction( $transaction_id, $status, $additional_data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$data = array_merge( $additional_data, array( 'status' => $status ) );

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $transaction_id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to update transaction' );
		}

		do_action( 'nonprofitsuite_transaction_updated', $transaction_id, $status, $data );

		return true;
	}

	/**
	 * Get transaction by ID.
	 *
	 * @param int $transaction_id Transaction ID.
	 * @return array|null Transaction data or null.
	 */
	public static function get_transaction( $transaction_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $transaction_id ),
			ARRAY_A
		);
	}

	/**
	 * Get transaction by processor transaction ID.
	 *
	 * @param string $processor_transaction_id Processor transaction ID.
	 * @return array|null Transaction data or null.
	 */
	public static function get_transaction_by_external_id( $processor_transaction_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE processor_transaction_id = %s", $processor_transaction_id ),
			ARRAY_A
		);
	}

	/**
	 * Get donor's transactions.
	 *
	 * @param int   $donor_id Donor ID.
	 * @param array $args     Query arguments.
	 * @return array Transactions.
	 */
	public static function get_donor_transactions( $donor_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$defaults = array(
			'status'       => '',
			'payment_type' => '',
			'order_by'     => 'created_at',
			'order'        => 'DESC',
			'limit'        => 50,
			'offset'       => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'donor_id = %d', $donor_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( ! empty( $args['payment_type'] ) ) {
			$where[] = $wpdb->prepare( 'payment_type = %s', $args['payment_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$args['order_by']} {$args['order']} LIMIT {$args['limit']} OFFSET {$args['offset']}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get organization's transactions.
	 *
	 * @param int   $organization_id Organization ID.
	 * @param array $args            Query arguments.
	 * @return array Transactions.
	 */
	public static function get_organization_transactions( $organization_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$defaults = array(
			'status'       => '',
			'payment_type' => '',
			'date_from'    => '',
			'date_to'      => '',
			'order_by'     => 'created_at',
			'order'        => 'DESC',
			'limit'        => 50,
			'offset'       => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'organization_id = %d', $organization_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( ! empty( $args['payment_type'] ) ) {
			$where[] = $wpdb->prepare( 'payment_type = %s', $args['payment_type'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$args['order_by']} {$args['order']} LIMIT {$args['limit']} OFFSET {$args['offset']}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get transaction summary.
	 *
	 * @param array $args Query arguments.
	 * @return array Summary statistics.
	 */
	public static function get_transaction_summary( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$where = array( '1=1' );

		if ( isset( $args['organization_id'] ) ) {
			$where[] = $wpdb->prepare( 'organization_id = %d', $args['organization_id'] );
		}

		if ( isset( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( isset( $args['payment_type'] ) ) {
			$where[] = $wpdb->prepare( 'payment_type = %s', $args['payment_type'] );
		}

		if ( isset( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] );
		}

		if ( isset( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT
			COUNT(*) as total_transactions,
			SUM(amount) as total_amount,
			SUM(fee_amount) as total_fees,
			SUM(net_amount) as total_net,
			AVG(amount) as average_amount,
			SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
			SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
			SUM(CASE WHEN status = 'disputed' THEN amount ELSE 0 END) as disputed_amount,
			SUM(CASE WHEN status = 'refunded' THEN refund_amount ELSE 0 END) as total_refunded
		FROM {$table} WHERE {$where_clause}";

		$summary = $wpdb->get_row( $query, ARRAY_A );

		return array(
			'total_transactions' => (int) $summary['total_transactions'],
			'total_amount'       => (float) $summary['total_amount'],
			'total_fees'         => (float) $summary['total_fees'],
			'total_net'          => (float) $summary['total_net'],
			'average_amount'     => (float) $summary['average_amount'],
			'completed_amount'   => (float) $summary['completed_amount'],
			'failed_count'       => (int) $summary['failed_count'],
			'disputed_amount'    => (float) $summary['disputed_amount'],
			'total_refunded'     => (float) $summary['total_refunded'],
		);
	}

	/**
	 * Get transactions by pledge.
	 *
	 * @param int $pledge_id Pledge ID.
	 * @return array Transactions.
	 */
	public static function get_pledge_transactions( $pledge_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE pledge_id = %d ORDER BY created_at DESC",
				$pledge_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get transactions by recurring donation.
	 *
	 * @param int $recurring_donation_id Recurring donation ID.
	 * @return array Transactions.
	 */
	public static function get_recurring_transactions( $recurring_donation_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE recurring_donation_id = %d ORDER BY created_at DESC",
				$recurring_donation_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get recent transactions.
	 *
	 * @param int $limit Number of transactions to retrieve.
	 * @return array Transactions.
	 */
	public static function get_recent_transactions( $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get transactions requiring attention (disputed, failed, etc.).
	 *
	 * @param int $organization_id Organization ID.
	 * @return array Transactions requiring attention.
	 */
	public static function get_attention_transactions( $organization_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE organization_id = %d
				AND status IN ('disputed', 'failed', 'pending_review')
				ORDER BY created_at DESC",
				$organization_id
			),
			ARRAY_A
		);
	}

	/**
	 * Mark transaction as swept.
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param string $sweep_batch_id Sweep batch ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function mark_swept( $transaction_id, $sweep_batch_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$result = $wpdb->update(
			$table,
			array(
				'sweep_batch_id' => $sweep_batch_id,
				'swept_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $transaction_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to mark transaction as swept' );
		}

		return true;
	}

	/**
	 * Get transactions ready for sweep.
	 *
	 * @param int $processor_id Processor ID.
	 * @return array Transactions ready to sweep.
	 */
	public static function get_unswepped_transactions( $processor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE processor_id = %d
				AND status = 'completed'
				AND (sweep_batch_id IS NULL OR sweep_batch_id = '')
				ORDER BY created_at ASC",
				$processor_id
			),
			ARRAY_A
		);
	}
}
