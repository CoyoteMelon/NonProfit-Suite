<?php
/**
 * Sweep Manager
 *
 * Manages automated fund sweeps between payment processors and bank accounts.
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
 * NonprofitSuite_Sweep_Manager Class
 *
 * Automated fund sweep management.
 */
class NonprofitSuite_Sweep_Manager {

	/**
	 * Initialize sweep system.
	 */
	public static function init() {
		// Register cron job for sweep processing
		add_action( 'nonprofitsuite_process_sweeps', array( __CLASS__, 'process_due_sweeps' ) );

		// Schedule daily sweep check if not already scheduled
		if ( ! wp_next_scheduled( 'nonprofitsuite_process_sweeps' ) ) {
			wp_schedule_event( strtotime( '02:00:00' ), 'hourly', 'nonprofitsuite_process_sweeps' );
		}
	}

	/**
	 * Create a sweep schedule.
	 *
	 * @param array $sweep_data Sweep configuration.
	 * @return int|WP_Error Sweep ID or error.
	 */
	public static function create_sweep_schedule( $sweep_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_sweep_schedules';

		$data = array(
			'sweep_name'             => $sweep_data['sweep_name'],
			'source_type'            => $sweep_data['source_type'], // 'processor' or 'bank_account'
			'source_id'              => isset( $sweep_data['source_id'] ) ? $sweep_data['source_id'] : null,
			'destination_account_id' => $sweep_data['destination_account_id'],
			'sweep_frequency'        => isset( $sweep_data['sweep_frequency'] ) ? $sweep_data['sweep_frequency'] : 'daily',
			'schedule_time'          => isset( $sweep_data['schedule_time'] ) ? $sweep_data['schedule_time'] : '02:00:00',
			'minimum_amount'         => isset( $sweep_data['minimum_amount'] ) ? $sweep_data['minimum_amount'] : 0.00,
			'leave_buffer_amount'    => isset( $sweep_data['leave_buffer_amount'] ) ? $sweep_data['leave_buffer_amount'] : 0.00,
			'sweep_percentage'       => isset( $sweep_data['sweep_percentage'] ) ? $sweep_data['sweep_percentage'] : 100.00,
			'is_active'              => 1,
		);

		// Calculate next run time
		$data['next_run_at'] = self::calculate_next_run( $data['sweep_frequency'], $data['schedule_time'] );

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create sweep schedule', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Process all due sweeps.
	 *
	 * Called by cron job.
	 */
	public static function process_due_sweeps() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_sweep_schedules';

		// Get all due sweeps
		$due_sweeps = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE is_active = 1
				AND next_run_at <= %s",
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		$results = array(
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ( $due_sweeps as $sweep ) {
			$results['processed']++;

			$result = self::execute_sweep( $sweep );

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['errors'][] = array(
					'sweep_id' => $sweep['id'],
					'sweep_name' => $sweep['sweep_name'],
					'error' => $result->get_error_message(),
				);

				error_log( "Sweep failed [{$sweep['sweep_name']}]: " . $result->get_error_message() );
			} else {
				$results['succeeded']++;

				// Update last_run and next_run
				$wpdb->update(
					$table,
					array(
						'last_run_at' => current_time( 'mysql' ),
						'next_run_at' => self::calculate_next_run( $sweep['sweep_frequency'], $sweep['schedule_time'] ),
					),
					array( 'id' => $sweep['id'] )
				);
			}
		}

		// Log summary
		error_log( sprintf(
			'Sweep Manager: Processed %d sweeps - %d succeeded, %d failed',
			$results['processed'],
			$results['succeeded'],
			$results['failed']
		) );

		return $results;
	}

	/**
	 * Execute a single sweep.
	 *
	 * @param array $sweep Sweep configuration.
	 * @return array|WP_Error Sweep result or error.
	 */
	public static function execute_sweep( $sweep ) {
		// Get available balance from source
		$available_balance = self::get_source_balance( $sweep );

		if ( is_wp_error( $available_balance ) ) {
			return $available_balance;
		}

		// Check minimum amount
		if ( $available_balance < $sweep['minimum_amount'] ) {
			return new WP_Error( 'below_minimum', sprintf(
				__( 'Available balance ($%s) below minimum ($%s)', 'nonprofitsuite' ),
				number_format( $available_balance, 2 ),
				number_format( $sweep['minimum_amount'], 2 )
			) );
		}

		// Calculate sweep amount
		$sweep_amount = $available_balance - $sweep['leave_buffer_amount'];
		$sweep_amount = $sweep_amount * ( $sweep['sweep_percentage'] / 100 );

		if ( $sweep_amount <= 0 ) {
			return new WP_Error( 'no_funds', __( 'No funds available to sweep after buffer', 'nonprofitsuite' ) );
		}

		// Execute transfer
		$transfer_result = self::execute_transfer( $sweep, $sweep_amount );

		if ( is_wp_error( $transfer_result ) ) {
			return $transfer_result;
		}

		// Log sweep transaction
		self::log_sweep_transaction( $sweep, $sweep_amount, $transfer_result );

		return array(
			'sweep_id'    => $sweep['id'],
			'amount'      => $sweep_amount,
			'status'      => 'completed',
			'transfer_id' => $transfer_result['transfer_id'],
		);
	}

	/**
	 * Get available balance from source.
	 *
	 * @param array $sweep Sweep configuration.
	 * @return float|WP_Error Available balance or error.
	 */
	private static function get_source_balance( $sweep ) {
		if ( 'processor' === $sweep['source_type'] ) {
			// Get balance from payment processor
			$processor = self::get_processor( $sweep['source_id'] );

			if ( ! $processor ) {
				return new WP_Error( 'processor_not_found', __( 'Payment processor not found', 'nonprofitsuite' ) );
			}

			$adapter = NonprofitSuite_Payment_Manager::get_adapter( $processor['processor_type'], $sweep['source_id'] );

			if ( ! $adapter ) {
				return new WP_Error( 'adapter_not_found', __( 'Payment adapter not available', 'nonprofitsuite' ) );
			}

			return $adapter->get_available_balance();

		} elseif ( 'bank_account' === $sweep['source_type'] ) {
			// Get balance from bank account
			global $wpdb;
			$account = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ns_bank_accounts WHERE id = %d",
					$sweep['source_id']
				),
				ARRAY_A
			);

			if ( ! $account ) {
				return new WP_Error( 'account_not_found', __( 'Bank account not found', 'nonprofitsuite' ) );
			}

			return (float) $account['current_balance'];
		}

		return new WP_Error( 'invalid_source_type', __( 'Invalid source type', 'nonprofitsuite' ) );
	}

	/**
	 * Execute transfer between accounts.
	 *
	 * @param array $sweep  Sweep configuration.
	 * @param float $amount Amount to transfer.
	 * @return array|WP_Error Transfer result or error.
	 */
	private static function execute_transfer( $sweep, $amount ) {
		if ( 'processor' === $sweep['source_type'] ) {
			// Initiate payout from processor to bank account
			$processor = self::get_processor( $sweep['source_id'] );
			$adapter = NonprofitSuite_Payment_Manager::get_adapter( $processor['processor_type'], $sweep['source_id'] );

			// Get destination bank account details
			$destination = self::get_bank_account( $sweep['destination_account_id'] );

			if ( ! $destination ) {
				return new WP_Error( 'destination_not_found', __( 'Destination bank account not found', 'nonprofitsuite' ) );
			}

			// Initiate payout (bank_account_id is processor-specific external ID)
			$bank_account_id = isset( $destination['external_account_id'] ) ? $destination['external_account_id'] : '';

			$result = $adapter->initiate_payout( $amount, $bank_account_id );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Update destination balance
			self::update_bank_balance( $sweep['destination_account_id'], $amount, 'add' );

			return array(
				'transfer_id' => $result['payout_id'],
				'type' => 'payout',
				'metadata' => $result,
			);

		} elseif ( 'bank_account' === $sweep['source_type'] ) {
			// Bank-to-bank transfer (would integrate with banking API)
			// For now, just update balances

			// Deduct from source
			self::update_bank_balance( $sweep['source_id'], $amount, 'subtract' );

			// Add to destination
			self::update_bank_balance( $sweep['destination_account_id'], $amount, 'add' );

			return array(
				'transfer_id' => 'INTERNAL_' . wp_generate_uuid4(),
				'type' => 'internal_transfer',
				'metadata' => array(),
			);
		}

		return new WP_Error( 'invalid_transfer', __( 'Invalid transfer type', 'nonprofitsuite' ) );
	}

	/**
	 * Update bank account balance.
	 *
	 * @param int    $account_id Account ID.
	 * @param float  $amount     Amount.
	 * @param string $operation  Operation (add or subtract).
	 */
	private static function update_bank_balance( $account_id, $amount, $operation = 'add' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_bank_accounts';

		$operator = 'add' === $operation ? '+' : '-';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET current_balance = current_balance {$operator} %f WHERE id = %d",
				$amount,
				$account_id
			)
		);
	}

	/**
	 * Log sweep transaction.
	 *
	 * @param array $sweep           Sweep configuration.
	 * @param float $amount          Amount swept.
	 * @param array $transfer_result Transfer result.
	 */
	private static function log_sweep_transaction( $sweep, $amount, $transfer_result ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_transactions';

		$wpdb->insert(
			$table,
			array(
				'transaction_type'        => 'sweep',
				'processor_id'            => 'processor' === $sweep['source_type'] ? $sweep['source_id'] : null,
				'processor_transaction_id' => $transfer_result['transfer_id'],
				'bank_account_id'         => $sweep['destination_account_id'],
				'amount'                  => $amount,
				'net_amount'              => $amount,
				'currency'                => 'USD',
				'status'                  => 'completed',
				'sweep_batch_id'          => $sweep['id'],
				'processor_metadata'      => wp_json_encode( $transfer_result ),
				'transaction_date'        => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Calculate next run time for sweep.
	 *
	 * @param string $frequency    Sweep frequency.
	 * @param string $schedule_time Time of day (HH:MM:SS).
	 * @return string Next run datetime.
	 */
	private static function calculate_next_run( $frequency, $schedule_time ) {
		$now = current_time( 'timestamp' );
		$time_parts = explode( ':', $schedule_time );
		$hour = (int) $time_parts[0];
		$minute = isset( $time_parts[1] ) ? (int) $time_parts[1] : 0;

		switch ( $frequency ) {
			case 'nightly':
			case 'daily':
				$next = strtotime( 'tomorrow ' . $schedule_time );
				break;

			case 'weekly':
				$next = strtotime( 'next week ' . $schedule_time );
				break;

			default:
				$next = strtotime( 'tomorrow ' . $schedule_time );
				break;
		}

		return gmdate( 'Y-m-d H:i:s', $next );
	}

	/**
	 * Get processor details.
	 *
	 * @param int $processor_id Processor ID.
	 * @return array|null Processor data or null.
	 */
	private static function get_processor( $processor_id ) {
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
	 * Get bank account details.
	 *
	 * @param int $account_id Account ID.
	 * @return array|null Account data or null.
	 */
	private static function get_bank_account( $account_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_bank_accounts WHERE id = %d",
				$account_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get sweep schedule by ID.
	 *
	 * @param int $sweep_id Sweep ID.
	 * @return array|null Sweep data or null.
	 */
	public static function get_sweep_schedule( $sweep_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_sweep_schedules WHERE id = %d",
				$sweep_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get all active sweep schedules.
	 *
	 * @return array Array of sweep schedules.
	 */
	public static function get_active_sweeps() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ns_sweep_schedules WHERE is_active = 1 ORDER BY next_run_at ASC",
			ARRAY_A
		);
	}
}

// Initialize
NonprofitSuite_Sweep_Manager::init();
