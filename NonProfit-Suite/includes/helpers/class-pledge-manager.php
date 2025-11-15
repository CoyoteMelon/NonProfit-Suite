<?php
/**
 * Pledge Manager
 *
 * Manages pledges (commitments to give) - creates accounting receivables.
 * Distinct from recurring donations which are subscriptions.
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
 * NonprofitSuite_Pledge_Manager Class
 *
 * Pledge tracking and management.
 */
class NonprofitSuite_Pledge_Manager {

	/**
	 * Create a new pledge.
	 *
	 * @param array $pledge_data Pledge data.
	 * @return int|WP_Error Pledge ID or error.
	 */
	public static function create_pledge( $pledge_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_pledges';

		// Calculate installment amount if not provided
		$installment_amount = isset( $pledge_data['installment_amount'] )
			? $pledge_data['installment_amount']
			: round( $pledge_data['total_amount'] / $pledge_data['installments_total'], 2 );

		$data = array(
			'donor_id'            => $pledge_data['donor_id'],
			'donor_name'          => isset( $pledge_data['donor_name'] ) ? $pledge_data['donor_name'] : null,
			'donor_email'         => isset( $pledge_data['donor_email'] ) ? $pledge_data['donor_email'] : null,
			'total_amount'        => $pledge_data['total_amount'],
			'amount_paid'         => 0.00,
			'amount_remaining'    => $pledge_data['total_amount'],
			'frequency'           => isset( $pledge_data['frequency'] ) ? $pledge_data['frequency'] : 'monthly',
			'installments_total'  => isset( $pledge_data['installments_total'] ) ? $pledge_data['installments_total'] : 1,
			'installments_paid'   => 0,
			'installment_amount'  => $installment_amount,
			'start_date'          => isset( $pledge_data['start_date'] ) ? $pledge_data['start_date'] : current_time( 'mysql', true ),
			'end_date'            => isset( $pledge_data['end_date'] ) ? $pledge_data['end_date'] : null,
			'next_due_date'       => isset( $pledge_data['next_due_date'] ) ? $pledge_data['next_due_date'] : self::calculate_next_due_date( $pledge_data ),
			'fund_restriction'    => isset( $pledge_data['fund_restriction'] ) ? $pledge_data['fund_restriction'] : null,
			'campaign_id'         => isset( $pledge_data['campaign_id'] ) ? $pledge_data['campaign_id'] : null,
			'status'              => 'active',
			'notes'               => isset( $pledge_data['notes'] ) ? $pledge_data['notes'] : null,
		);

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create pledge', 'nonprofitsuite' ) );
		}

		$pledge_id = $wpdb->insert_id;

		// Create accounting receivable entry
		self::create_receivable_entry( $pledge_id, $pledge_data['total_amount'] );

		return $pledge_id;
	}

	/**
	 * Record a payment toward a pledge.
	 *
	 * @param int   $pledge_id Pledge ID.
	 * @param float $amount    Payment amount.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function record_payment( $pledge_id, $amount ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_pledges';

		// Get pledge
		$pledge = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$pledge_id
			),
			ARRAY_A
		);

		if ( ! $pledge ) {
			return new WP_Error( 'pledge_not_found', __( 'Pledge not found', 'nonprofitsuite' ) );
		}

		// Update pledge
		$new_paid = $pledge['amount_paid'] + $amount;
		$new_remaining = $pledge['total_amount'] - $new_paid;
		$new_installments_paid = $pledge['installments_paid'] + 1;

		// Determine status
		$status = 'active';
		if ( $new_remaining <= 0 ) {
			$status = 'completed';
			$new_remaining = 0;
		}

		// Calculate next due date
		$next_due_date = $status === 'completed' ? null : self::calculate_next_due_date( $pledge, $new_installments_paid );

		$wpdb->update(
			$table,
			array(
				'amount_paid'       => $new_paid,
				'amount_remaining'  => $new_remaining,
				'installments_paid' => $new_installments_paid,
				'next_due_date'     => $next_due_date,
				'status'            => $status,
			),
			array( 'id' => $pledge_id )
		);

		// Update accounting receivable
		self::update_receivable( $pledge_id, $amount );

		return true;
	}

	/**
	 * Calculate next due date for pledge.
	 *
	 * @param array $pledge                Pledge data.
	 * @param int   $installments_paid     Optional. Current installments paid.
	 * @return string|null Next due date or null.
	 */
	private static function calculate_next_due_date( $pledge, $installments_paid = 0 ) {
		if ( ! isset( $pledge['start_date'] ) ) {
			return null;
		}

		$start = strtotime( $pledge['start_date'] );
		$frequency = isset( $pledge['frequency'] ) ? $pledge['frequency'] : 'monthly';

		switch ( $frequency ) {
			case 'weekly':
				$next = strtotime( '+' . ( $installments_paid + 1 ) . ' weeks', $start );
				break;

			case 'monthly':
				$next = strtotime( '+' . ( $installments_paid + 1 ) . ' months', $start );
				break;

			case 'quarterly':
				$next = strtotime( '+' . ( ( $installments_paid + 1 ) * 3 ) . ' months', $start );
				break;

			case 'annual':
				$next = strtotime( '+' . ( $installments_paid + 1 ) . ' years', $start );
				break;

			case 'one_time':
			default:
				$next = $start;
				break;
		}

		return gmdate( 'Y-m-d', $next );
	}

	/**
	 * Create accounting receivable entry for pledge.
	 *
	 * @param int   $pledge_id Pledge ID.
	 * @param float $amount    Total pledge amount.
	 */
	private static function create_receivable_entry( $pledge_id, $amount ) {
		// Would integrate with accounting module
		// Creates an accounts receivable entry
		do_action( 'nonprofitsuite_pledge_receivable_created', $pledge_id, $amount );
	}

	/**
	 * Update accounting receivable when payment received.
	 *
	 * @param int   $pledge_id Pledge ID.
	 * @param float $amount    Payment amount.
	 */
	private static function update_receivable( $pledge_id, $amount ) {
		// Would integrate with accounting module
		// Reduces the accounts receivable balance
		do_action( 'nonprofitsuite_pledge_payment_received', $pledge_id, $amount );
	}

	/**
	 * Get pledge by ID.
	 *
	 * @param int $pledge_id Pledge ID.
	 * @return array|null Pledge data or null.
	 */
	public static function get_pledge( $pledge_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_pledges WHERE id = %d",
				$pledge_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get donor's pledges.
	 *
	 * @param int    $donor_id Donor ID.
	 * @param string $status   Optional. Filter by status.
	 * @return array Array of pledges.
	 */
	public static function get_donor_pledges( $donor_id, $status = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_pledges';

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE donor_id = %d",
			$donor_id
		);

		if ( $status ) {
			$query .= $wpdb->prepare( " AND status = %s", $status );
		}

		$query .= " ORDER BY created_at DESC";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get pledges due for reminder.
	 *
	 * @param int $days_ahead Days ahead to look.
	 * @return array Array of pledges.
	 */
	public static function get_due_pledges( $days_ahead = 7 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_pledges';

		$date_threshold = gmdate( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status = 'active'
				AND next_due_date <= %s
				AND next_due_date >= CURDATE()
				ORDER BY next_due_date ASC",
				$date_threshold
			),
			ARRAY_A
		);
	}

	/**
	 * Get pledge summary statistics.
	 *
	 * @param array $args Optional. Query arguments.
	 * @return array Summary statistics.
	 */
	public static function get_pledge_summary( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_pledges';

		$where = array( '1=1' );

		if ( isset( $args['status'] ) ) {
			$where[] = $wpdb->prepare( "status = %s", $args['status'] );
		}

		if ( isset( $args['campaign_id'] ) ) {
			$where[] = $wpdb->prepare( "campaign_id = %d", $args['campaign_id'] );
		}

		$where_clause = implode( ' AND ', $where );

		$summary = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_pledges,
				SUM(total_amount) as total_pledged,
				SUM(amount_paid) as total_paid,
				SUM(amount_remaining) as total_remaining
			FROM {$table}
			WHERE {$where_clause}",
			ARRAY_A
		);

		return array(
			'total_pledges'   => (int) $summary['total_pledges'],
			'total_pledged'   => (float) $summary['total_pledged'],
			'total_paid'      => (float) $summary['total_paid'],
			'total_remaining' => (float) $summary['total_remaining'],
			'fulfillment_rate' => $summary['total_pledged'] > 0
				? round( ( $summary['total_paid'] / $summary['total_pledged'] ) * 100, 2 )
				: 0,
		);
	}

	/**
	 * Cancel a pledge.
	 *
	 * @param int    $pledge_id Pledge ID.
	 * @param string $reason    Optional. Cancellation reason.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function cancel_pledge( $pledge_id, $reason = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_pledges';

		$result = $wpdb->update(
			$table,
			array(
				'status' => 'cancelled',
				'notes'  => $reason,
			),
			array( 'id' => $pledge_id )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to cancel pledge', 'nonprofitsuite' ) );
		}

		// Update accounting receivable
		do_action( 'nonprofitsuite_pledge_cancelled', $pledge_id );

		return true;
	}
}
