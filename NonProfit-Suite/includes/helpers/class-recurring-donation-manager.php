<?php
/**
 * Recurring Donation Manager
 *
 * Manages subscription-based recurring donations.
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
 * NonprofitSuite_Recurring_Donation_Manager Class
 *
 * Handles recurring donation subscriptions.
 */
class NonprofitSuite_Recurring_Donation_Manager {

	/**
	 * Create recurring donation subscription.
	 *
	 * @param array $subscription_data Subscription data.
	 * @return int|WP_Error Recurring donation ID or error.
	 */
	public static function create_subscription( $subscription_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		// Validate required fields
		$required = array( 'organization_id', 'processor_id', 'amount', 'frequency' );
		foreach ( $required as $field ) {
			if ( empty( $subscription_data[ $field ] ) ) {
				return new WP_Error( 'missing_field', "Required field missing: {$field}" );
			}
		}

		// Prepare data
		$data = array(
			'organization_id' => $subscription_data['organization_id'],
			'donor_id'        => isset( $subscription_data['donor_id'] ) ? $subscription_data['donor_id'] : null,
			'processor_id'    => $subscription_data['processor_id'],
			'subscription_id' => isset( $subscription_data['subscription_id'] ) ? $subscription_data['subscription_id'] : '',
			'amount'          => $subscription_data['amount'],
			'currency'        => isset( $subscription_data['currency'] ) ? $subscription_data['currency'] : 'USD',
			'frequency'       => $subscription_data['frequency'], // monthly, annual, etc.
			'status'          => isset( $subscription_data['status'] ) ? $subscription_data['status'] : 'active',
			'fund_id'         => isset( $subscription_data['fund_id'] ) ? $subscription_data['fund_id'] : null,
			'campaign_id'     => isset( $subscription_data['campaign_id'] ) ? $subscription_data['campaign_id'] : null,
			'total_charged'   => 0.00,
			'charge_count'    => 0,
			'created_at'      => current_time( 'mysql' ),
		);

		// Calculate next charge date
		$data['next_charge_date'] = self::calculate_next_charge_date( $data['frequency'] );

		// Insert subscription
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to create recurring donation', array( 'error' => $wpdb->last_error ) );
		}

		$recurring_id = $wpdb->insert_id;

		// Trigger action for integrations
		do_action( 'nonprofitsuite_recurring_donation_created', $recurring_id, $data );

		return $recurring_id;
	}

	/**
	 * Update recurring donation.
	 *
	 * @param int   $recurring_id Recurring donation ID.
	 * @param array $update_data  Data to update.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function update_subscription( $recurring_id, $update_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$allowed_fields = array(
			'amount', 'currency', 'frequency', 'status',
			'next_charge_date', 'fund_id', 'campaign_id',
			'total_charged', 'charge_count', 'last_charge_date',
			'failure_reason',
		);

		$data = array();
		foreach ( $update_data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				$data[ $key ] = $value;
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No valid data to update' );
		}

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $recurring_id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to update recurring donation' );
		}

		do_action( 'nonprofitsuite_recurring_donation_updated', $recurring_id, $data );

		return true;
	}

	/**
	 * Cancel recurring donation.
	 *
	 * @param int    $recurring_id       Recurring donation ID.
	 * @param string $cancellation_reason Reason for cancellation.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function cancel_subscription( $recurring_id, $cancellation_reason = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		// Get subscription
		$subscription = self::get_subscription( $recurring_id );
		if ( ! $subscription ) {
			return new WP_Error( 'not_found', 'Recurring donation not found' );
		}

		// Cancel with processor if subscription_id exists
		if ( ! empty( $subscription['subscription_id'] ) ) {
			$processor = self::get_processor( $subscription['processor_id'] );
			if ( $processor ) {
				$adapter = NonprofitSuite_Payment_Manager::get_adapter( $processor['processor_type'], $subscription['processor_id'] );
				$result = $adapter->cancel_subscription( $subscription['subscription_id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		// Update database
		$wpdb->update(
			$table,
			array(
				'status'              => 'cancelled',
				'ended_at'            => current_time( 'mysql' ),
				'cancellation_reason' => $cancellation_reason,
			),
			array( 'id' => $recurring_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'nonprofitsuite_recurring_donation_cancelled', $recurring_id, $cancellation_reason );

		return true;
	}

	/**
	 * Pause recurring donation.
	 *
	 * @param int $recurring_id Recurring donation ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function pause_subscription( $recurring_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$result = $wpdb->update(
			$table,
			array(
				'status'    => 'paused',
				'paused_at' => current_time( 'mysql' ),
			),
			array( 'id' => $recurring_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to pause recurring donation' );
		}

		do_action( 'nonprofitsuite_recurring_donation_paused', $recurring_id );

		return true;
	}

	/**
	 * Resume recurring donation.
	 *
	 * @param int $recurring_id Recurring donation ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function resume_subscription( $recurring_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		// Calculate new next charge date
		$subscription = self::get_subscription( $recurring_id );
		$next_charge_date = self::calculate_next_charge_date( $subscription['frequency'] );

		$result = $wpdb->update(
			$table,
			array(
				'status'           => 'active',
				'paused_at'        => null,
				'next_charge_date' => $next_charge_date,
			),
			array( 'id' => $recurring_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to resume recurring donation' );
		}

		do_action( 'nonprofitsuite_recurring_donation_resumed', $recurring_id );

		return true;
	}

	/**
	 * Record successful charge for recurring donation.
	 *
	 * @param int   $recurring_id Recurring donation ID.
	 * @param float $amount       Amount charged.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function record_charge( $recurring_id, $amount ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$subscription = self::get_subscription( $recurring_id );
		if ( ! $subscription ) {
			return new WP_Error( 'not_found', 'Recurring donation not found' );
		}

		// Calculate next charge date
		$next_charge_date = self::calculate_next_charge_date(
			$subscription['frequency'],
			$subscription['next_charge_date']
		);

		$wpdb->update(
			$table,
			array(
				'total_charged'     => $subscription['total_charged'] + $amount,
				'charge_count'      => $subscription['charge_count'] + 1,
				'last_charge_date'  => current_time( 'mysql' ),
				'next_charge_date'  => $next_charge_date,
				'failure_reason'    => null, // Clear any previous failure
			),
			array( 'id' => $recurring_id ),
			array( '%f', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'nonprofitsuite_recurring_charge_recorded', $recurring_id, $amount );

		return true;
	}

	/**
	 * Get recurring donation by ID.
	 *
	 * @param int $recurring_id Recurring donation ID.
	 * @return array|null Recurring donation data or null.
	 */
	public static function get_subscription( $recurring_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $recurring_id ),
			ARRAY_A
		);
	}

	/**
	 * Get recurring donation by subscription ID.
	 *
	 * @param string $subscription_id External subscription ID.
	 * @return array|null Recurring donation data or null.
	 */
	public static function get_subscription_by_external_id( $subscription_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE subscription_id = %s", $subscription_id ),
			ARRAY_A
		);
	}

	/**
	 * Get donor's recurring donations.
	 *
	 * @param int    $donor_id Donor ID.
	 * @param string $status   Optional status filter.
	 * @return array Recurring donations.
	 */
	public static function get_donor_subscriptions( $donor_id, $status = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$where = $wpdb->prepare( 'donor_id = %d', $donor_id );
		if ( ! empty( $status ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get organization's recurring donations.
	 *
	 * @param int   $organization_id Organization ID.
	 * @param array $args            Query arguments.
	 * @return array Recurring donations.
	 */
	public static function get_organization_subscriptions( $organization_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$defaults = array(
			'status'   => '',
			'order_by' => 'created_at',
			'order'    => 'DESC',
			'limit'    => 50,
			'offset'   => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'organization_id = %d', $organization_id );
		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$args['order_by']} {$args['order']} LIMIT {$args['limit']} OFFSET {$args['offset']}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get recurring donation summary.
	 *
	 * @param array $args Query arguments.
	 * @return array Summary statistics.
	 */
	public static function get_subscription_summary( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_recurring_donations';

		$where = array( '1=1' );
		if ( isset( $args['organization_id'] ) ) {
			$where[] = $wpdb->prepare( 'organization_id = %d', $args['organization_id'] );
		}
		if ( isset( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT
			COUNT(*) as total_subscriptions,
			SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
			SUM(total_charged) as lifetime_value,
			AVG(amount) as average_amount,
			SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as monthly_recurring_revenue
		FROM {$table} WHERE {$where_clause}";

		$summary = $wpdb->get_row( $query, ARRAY_A );

		return array(
			'total_subscriptions'       => (int) $summary['total_subscriptions'],
			'active_subscriptions'      => (int) $summary['active_subscriptions'],
			'lifetime_value'            => (float) $summary['lifetime_value'],
			'average_amount'            => (float) $summary['average_amount'],
			'monthly_recurring_revenue' => (float) $summary['monthly_recurring_revenue'],
		);
	}

	/**
	 * Calculate next charge date.
	 *
	 * @param string $frequency Frequency (monthly, annual, weekly, quarterly).
	 * @param string $from_date Optional date to calculate from (default: now).
	 * @return string Next charge date (Y-m-d format).
	 */
	private static function calculate_next_charge_date( $frequency, $from_date = null ) {
		$from = $from_date ? strtotime( $from_date ) : time();

		switch ( $frequency ) {
			case 'weekly':
				$next = strtotime( '+1 week', $from );
				break;
			case 'monthly':
				$next = strtotime( '+1 month', $from );
				break;
			case 'quarterly':
				$next = strtotime( '+3 months', $from );
				break;
			case 'annual':
				$next = strtotime( '+1 year', $from );
				break;
			default:
				$next = strtotime( '+1 month', $from );
		}

		return gmdate( 'Y-m-d', $next );
	}

	/**
	 * Get processor configuration.
	 *
	 * @param int $processor_id Processor ID.
	 * @return array|null Processor configuration.
	 */
	private static function get_processor( $processor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $processor_id ),
			ARRAY_A
		);
	}
}
