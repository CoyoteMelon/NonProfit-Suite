<?php
/**
 * Payment Manager
 *
 * Central manager for payment adapters and processing.
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
 * NonprofitSuite_Payment_Manager Class
 *
 * Manages payment adapters and provides unified interface.
 */
class NonprofitSuite_Payment_Manager {

	/**
	 * Registered adapters.
	 *
	 * @var array
	 */
	private static $adapters = array();

	/**
	 * Initialize payment system.
	 */
	public static function init() {
		// Register payment adapters
		self::register_adapter( 'stripe', 'NonprofitSuite_Stripe_Payment_Adapter' );
	}

	/**
	 * Register a payment adapter.
	 *
	 * @param string $processor_key Processor key (e.g., 'stripe', 'paypal').
	 * @param string $class_name    Adapter class name.
	 */
	public static function register_adapter( $processor_key, $class_name ) {
		self::$adapters[ $processor_key ] = $class_name;
	}

	/**
	 * Get payment adapter instance.
	 *
	 * @param string $processor_key Processor key.
	 * @param int    $processor_id  Optional. Specific processor ID for credentials.
	 * @return NonprofitSuite_Payment_Adapter|null Adapter instance or null.
	 */
	public static function get_adapter( $processor_key, $processor_id = null ) {
		if ( ! isset( self::$adapters[ $processor_key ] ) ) {
			return null;
		}

		$class = self::$adapters[ $processor_key ];

		if ( ! class_exists( $class ) ) {
			return null;
		}

		// Load config from processor if ID provided
		$config = array();
		if ( $processor_id ) {
			$config = self::get_processor_config( $processor_id );
		}

		return new $class( $config );
	}

	/**
	 * Get processor configuration.
	 *
	 * @param int $processor_id Processor ID.
	 * @return array Configuration array.
	 */
	private static function get_processor_config( $processor_id ) {
		global $wpdb;
		$processor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_payment_processors WHERE id = %d",
				$processor_id
			),
			ARRAY_A
		);

		if ( ! $processor ) {
			return array();
		}

		// Decrypt credentials
		$credentials = json_decode( $processor['credentials'], true );

		return is_array( $credentials ) ? $credentials : array();
	}

	/**
	 * Process a payment through appropriate adapter.
	 *
	 * @param int   $processor_id  Processor ID.
	 * @param array $payment_data  Payment data.
	 * @return array|WP_Error Payment result or error.
	 */
	public static function process_payment( $processor_id, $payment_data ) {
		$processor = self::get_processor( $processor_id );

		if ( ! $processor ) {
			return new WP_Error( 'processor_not_found', __( 'Payment processor not found', 'nonprofitsuite' ) );
		}

		$adapter = self::get_adapter( $processor['processor_type'], $processor_id );

		if ( ! $adapter ) {
			return new WP_Error( 'adapter_not_found', __( 'Payment adapter not available', 'nonprofitsuite' ) );
		}

		// Calculate fees using fee policy
		$fee_info = NonprofitSuite_Fee_Calculator::calculate_fee( $processor_id, $payment_data['amount'], $payment_data['payment_type'] );

		// Adjust amount if donor is paying fees
		if ( $fee_info['fee_paid_by'] === 'donor' ) {
			$payment_data['amount'] = $fee_info['total_amount'];
		}

		// Process payment
		$result = $adapter->process_payment( $payment_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log transaction
		self::log_transaction( $processor_id, $payment_data, $result, $fee_info );

		return $result;
	}

	/**
	 * Log payment transaction.
	 *
	 * @param int   $processor_id Processor ID.
	 * @param array $payment_data Payment data.
	 * @param array $result       Payment result.
	 * @param array $fee_info     Fee information.
	 */
	private static function log_transaction( $processor_id, $payment_data, $result, $fee_info ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$wpdb->insert(
			$table,
			array(
				'transaction_type'        => isset( $payment_data['transaction_type'] ) ? $payment_data['transaction_type'] : 'donation',
				'processor_id'            => $processor_id,
				'processor_transaction_id' => $result['transaction_id'],
				'donor_id'                => isset( $payment_data['donor_id'] ) ? $payment_data['donor_id'] : null,
				'donor_name'              => isset( $payment_data['donor_name'] ) ? $payment_data['donor_name'] : null,
				'donor_email'             => isset( $payment_data['email'] ) ? $payment_data['email'] : null,
				'amount'                  => $result['amount'],
				'fee_amount'              => $result['fee_amount'],
				'net_amount'              => $result['net_amount'],
				'currency'                => isset( $payment_data['currency'] ) ? $payment_data['currency'] : 'USD',
				'fee_paid_by'             => $fee_info['fee_paid_by'],
				'status'                  => $result['status'],
				'pledge_id'               => isset( $payment_data['pledge_id'] ) ? $payment_data['pledge_id'] : null,
				'recurring_donation_id'   => isset( $payment_data['recurring_donation_id'] ) ? $payment_data['recurring_donation_id'] : null,
				'fund_restriction'        => isset( $payment_data['fund_restriction'] ) ? $payment_data['fund_restriction'] : null,
				'campaign_id'             => isset( $payment_data['campaign_id'] ) ? $payment_data['campaign_id'] : null,
				'processor_metadata'      => wp_json_encode( $result['metadata'] ),
				'transaction_date'        => current_time( 'mysql' ),
			)
		);

		// Update pledge if applicable
		if ( isset( $payment_data['pledge_id'] ) ) {
			NonprofitSuite_Pledge_Manager::record_payment( $payment_data['pledge_id'], $result['amount'] );
		}
	}

	/**
	 * Get processor details.
	 *
	 * @param int $processor_id Processor ID.
	 * @return array|null Processor data or null.
	 */
	public static function get_processor( $processor_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_payment_processors WHERE id = %d",
				$processor_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get all active processors.
	 *
	 * @param string $payment_type Optional. Filter by payment type.
	 * @return array Array of processors.
	 */
	public static function get_active_processors( $payment_type = null ) {
		global $wpdb;

		$query = "SELECT * FROM {$wpdb->prefix}ns_payment_processors WHERE is_active = 1";

		if ( $payment_type ) {
			// Would join with fee policies table to filter by payment type
		}

		$query .= " ORDER BY display_order ASC, is_preferred DESC";

		return $wpdb->get_results( $query, ARRAY_A );
	}
}

// Initialize
NonprofitSuite_Payment_Manager::init();
