<?php
/**
 * Fee Calculator
 *
 * Calculates payment processing fees and manages fee policies/incentives.
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
 * NonprofitSuite_Fee_Calculator Class
 *
 * Fee calculation and incentive management.
 */
class NonprofitSuite_Fee_Calculator {

	/**
	 * Calculate fee for a payment.
	 *
	 * @param int    $processor_id Processor ID.
	 * @param float  $amount       Payment amount.
	 * @param string $payment_type Payment type (donation, membership, event).
	 * @return array {
	 *     Fee calculation result.
	 *
	 *     @type float  $amount            Original amount.
	 *     @type float  $fee_amount        Processing fee.
	 *     @type float  $net_amount        Amount after fees.
	 *     @type float  $total_amount      Total if donor pays fees.
	 *     @type string $fee_paid_by       'org' or 'donor'.
	 *     @type string $incentive_message Optional incentive message.
	 * }
	 */
	public static function calculate_fee( $processor_id, $amount, $payment_type = 'donation' ) {
		$policy = self::get_fee_policy( $processor_id, $payment_type );

		if ( ! $policy ) {
			// Default to org absorbs with 2.9% + $0.30
			return array(
				'amount'            => $amount,
				'fee_amount'        => round( ( $amount * 0.029 ) + 0.30, 2 ),
				'net_amount'        => round( $amount - ( ( $amount * 0.029 ) + 0.30 ), 2 ),
				'total_amount'      => $amount,
				'fee_paid_by'       => 'org',
				'incentive_message' => null,
			);
		}

		// Calculate base fee
		$fee_amount = ( $amount * ( $policy['fee_percentage'] / 100 ) ) + $policy['fee_fixed_amount'];
		$fee_amount = round( $fee_amount, 2 );

		$result = array(
			'amount'       => $amount,
			'fee_amount'   => $fee_amount,
			'policy_type'  => $policy['policy_type'],
			'incentive_message' => $policy['incentive_message'],
		);

		switch ( $policy['policy_type'] ) {
			case 'org_absorbs':
				// Organization pays all fees
				$result['net_amount']   = round( $amount - $fee_amount, 2 );
				$result['total_amount'] = $amount;
				$result['fee_paid_by']  = 'org';
				break;

			case 'donor_pays':
				// Donor pays fees on top
				$result['net_amount']   = $amount;
				$result['total_amount'] = round( $amount + $fee_amount, 2 );
				$result['fee_paid_by']  = 'donor';
				break;

			case 'incentivize':
				// Org absorbs fees as incentive
				$result['net_amount']   = round( $amount - $fee_amount, 2 );
				$result['total_amount'] = $amount;
				$result['fee_paid_by']  = 'org';
				break;

			case 'hybrid':
				// Split fees (could be customized)
				$fee_split = $fee_amount / 2;
				$result['net_amount']   = round( $amount - $fee_split, 2 );
				$result['total_amount'] = round( $amount + $fee_split, 2 );
				$result['fee_paid_by']  = 'shared';
				break;

			default:
				$result['net_amount']   = round( $amount - $fee_amount, 2 );
				$result['total_amount'] = $amount;
				$result['fee_paid_by']  = 'org';
				break;
		}

		return $result;
	}

	/**
	 * Get fee policy for processor and payment type.
	 *
	 * @param int    $processor_id Processor ID.
	 * @param string $payment_type Payment type.
	 * @return array|null Policy data or null.
	 */
	public static function get_fee_policy( $processor_id, $payment_type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_fee_policies';

		// Try specific payment type first
		$policy = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE processor_id = %d
				AND payment_type = %s
				AND is_active = 1
				LIMIT 1",
				$processor_id,
				$payment_type
			),
			ARRAY_A
		);

		if ( $policy ) {
			return $policy;
		}

		// Fallback to 'all' payment types
		$policy = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE processor_id = %d
				AND payment_type = 'all'
				AND is_active = 1
				LIMIT 1",
				$processor_id
			),
			ARRAY_A
		);

		return $policy;
	}

	/**
	 * Create a fee policy.
	 *
	 * @param array $policy_data Policy data.
	 * @return int|WP_Error Policy ID or error.
	 */
	public static function create_policy( $policy_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_fee_policies';

		$data = array(
			'processor_id'       => $policy_data['processor_id'],
			'payment_type'       => isset( $policy_data['payment_type'] ) ? $policy_data['payment_type'] : 'all',
			'policy_type'        => $policy_data['policy_type'],
			'fee_percentage'     => isset( $policy_data['fee_percentage'] ) ? $policy_data['fee_percentage'] : 2.90,
			'fee_fixed_amount'   => isset( $policy_data['fee_fixed_amount'] ) ? $policy_data['fee_fixed_amount'] : 0.30,
			'incentive_type'     => isset( $policy_data['incentive_type'] ) ? $policy_data['incentive_type'] : null,
			'incentive_message'  => isset( $policy_data['incentive_message'] ) ? $policy_data['incentive_message'] : null,
			'discount_amount'    => isset( $policy_data['discount_amount'] ) ? $policy_data['discount_amount'] : null,
			'is_active'          => 1,
		);

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create fee policy', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get processors with incentives for payment type.
	 *
	 * @param string $payment_type Payment type.
	 * @return array Array of processors with incentive info.
	 */
	public static function get_incentivized_processors( $payment_type = 'donation' ) {
		global $wpdb;

		$query = "SELECT p.*, pol.policy_type, pol.incentive_message, pol.incentive_type
			FROM {$wpdb->prefix}ns_payment_processors p
			INNER JOIN {$wpdb->prefix}ns_payment_fee_policies pol ON p.id = pol.processor_id
			WHERE p.is_active = 1
			AND pol.is_active = 1
			AND pol.policy_type = 'incentivize'
			AND (pol.payment_type = %s OR pol.payment_type = 'all')
			ORDER BY p.display_order ASC";

		return $wpdb->get_results(
			$wpdb->prepare( $query, $payment_type ),
			ARRAY_A
		);
	}

	/**
	 * Get fee comparison for donor UI.
	 *
	 * Shows donors the difference between payment methods.
	 *
	 * @param float  $amount       Donation amount.
	 * @param string $payment_type Payment type.
	 * @return array Array of processors with fee info.
	 */
	public static function get_fee_comparison( $amount, $payment_type = 'donation' ) {
		$processors = NonprofitSuite_Payment_Manager::get_active_processors( $payment_type );
		$comparison = array();

		foreach ( $processors as $processor ) {
			$fee_info = self::calculate_fee( $processor['id'], $amount, $payment_type );

			$comparison[] = array(
				'processor_id'   => $processor['id'],
				'processor_name' => $processor['processor_name'],
				'processor_type' => $processor['processor_type'],
				'amount'         => $amount,
				'fee_amount'     => $fee_info['fee_amount'],
				'net_to_org'     => $fee_info['net_amount'],
				'total_charged'  => $fee_info['total_amount'],
				'fee_paid_by'    => $fee_info['fee_paid_by'],
				'incentive_message' => $fee_info['incentive_message'],
				'is_preferred'   => (bool) $processor['is_preferred'],
			);
		}

		// Sort by net_to_org descending (best for org first)
		usort( $comparison, function( $a, $b ) {
			return $b['net_to_org'] <=> $a['net_to_org'];
		} );

		return $comparison;
	}
}
