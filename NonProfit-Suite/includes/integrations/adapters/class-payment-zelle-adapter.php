<?php
/**
 * Zelle Payment Adapter
 *
 * Manual reconciliation helper for Zelle payments.
 * Zelle does not provide a public API, so this adapter helps track
 * Zelle payments manually recorded by admins.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Payment_Zelle_Adapter Class
 *
 * Manual tracking for Zelle payments (no API available).
 */
class NonprofitSuite_Payment_Zelle_Adapter implements NonprofitSuite_Payment_Adapter_Interface {

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'payment', 'zelle' );
	}

	/**
	 * Create a payment
	 *
	 * Records a Zelle payment manually
	 *
	 * @param array $payment_data Payment data
	 * @return array|WP_Error
	 */
	public function create_payment( $payment_data ) {
		global $wpdb;

		$payment_data = wp_parse_args( $payment_data, array(
			'amount'         => 0,
			'currency'       => 'USD',
			'description'    => '',
			'donor_email'    => '',
			'donor_name'     => '',
			'donor_phone'    => '',
			'reference_id'   => '', // Bank reference or confirmation number
			'received_date'  => current_time( 'mysql' ),
			'metadata'       => array(),
		) );

		// Generate a local payment ID
		$payment_id = 'zelle_' . wp_generate_uuid4();

		// Store in local payments table
		$table_name = $wpdb->prefix . 'ns_zelle_payments';

		$result = $wpdb->insert(
			$table_name,
			array(
				'payment_id'    => $payment_id,
				'amount'        => $payment_data['amount'],
				'currency'      => $payment_data['currency'],
				'donor_email'   => $payment_data['donor_email'],
				'donor_name'    => $payment_data['donor_name'],
				'donor_phone'   => $payment_data['donor_phone'],
				'description'   => $payment_data['description'],
				'reference_id'  => $payment_data['reference_id'],
				'received_date' => $payment_data['received_date'],
				'status'        => 'succeeded',
				'metadata'      => wp_json_encode( $payment_data['metadata'] ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Failed to record Zelle payment', 'nonprofitsuite' ) );
		}

		do_action( 'ns_zelle_payment_recorded', $payment_id, $payment_data );

		return array(
			'payment_id' => $payment_id,
			'status'     => 'succeeded',
			'amount'     => $payment_data['amount'],
			'currency'   => $payment_data['currency'],
		);
	}

	/**
	 * Capture a payment
	 *
	 * Zelle payments are instant and cannot be captured
	 *
	 * @param string $payment_id Payment ID
	 * @return array|WP_Error
	 */
	public function capture_payment( $payment_id ) {
		return $this->get_payment( $payment_id );
	}

	/**
	 * Refund a payment
	 *
	 * Zelle does not support refunds via API.
	 * This marks the payment as refunded locally and requires manual bank transfer.
	 *
	 * @param string $payment_id Payment ID
	 * @param array  $args       Refund arguments
	 * @return array|WP_Error
	 */
	public function refund_payment( $payment_id, $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ns_zelle_payments';

		// Get original payment
		$payment = $this->get_payment( $payment_id );

		if ( is_wp_error( $payment ) ) {
			return $payment;
		}

		$refund_amount = isset( $args['amount'] ) ? (int) $args['amount'] : $payment['amount'];

		// Update payment status to refunded
		$wpdb->update(
			$table_name,
			array(
				'status'         => 'refunded',
				'refund_amount'  => $refund_amount,
				'refund_reason'  => $args['reason'] ?? '',
				'refund_date'    => current_time( 'mysql' ),
			),
			array( 'payment_id' => $payment_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%s' )
		);

		do_action( 'ns_zelle_payment_refunded', $payment_id, $refund_amount );

		return array(
			'refund_id' => 'zelle_refund_' . wp_generate_uuid4(),
			'status'    => 'manual_refund_required',
			'amount'    => $refund_amount,
			'note'      => __( 'Please process refund manually via Zelle or bank transfer', 'nonprofitsuite' ),
		);
	}

	/**
	 * Get payment
	 *
	 * @param string $payment_id Payment ID
	 * @return array|WP_Error
	 */
	public function get_payment( $payment_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ns_zelle_payments';

		$payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE payment_id = %s",
				$payment_id
			),
			ARRAY_A
		);

		if ( ! $payment ) {
			return new WP_Error( 'not_found', __( 'Payment not found', 'nonprofitsuite' ) );
		}

		return array(
			'payment_id' => $payment['payment_id'],
			'status'     => $payment['status'],
			'amount'     => (int) $payment['amount'],
			'currency'   => $payment['currency'],
			'created'    => strtotime( $payment['created_at'] ),
			'donor_name' => $payment['donor_name'],
			'donor_email' => $payment['donor_email'],
			'reference_id' => $payment['reference_id'],
		);
	}

	/**
	 * List payments
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public function list_payments( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'limit'          => 50,
			'offset'         => 0,
			'status'         => '',
			'created_after'  => '',
			'created_before' => '',
		) );

		$table_name = $wpdb->prefix . 'ns_zelle_payments';
		$where = array( '1=1' );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( ! empty( $args['created_after'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['created_after'] );
		}

		if ( ! empty( $args['created_before'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['created_before'] );
		}

		$where_clause = implode( ' AND ', $where );

		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $payments as $payment ) {
			$result[] = array(
				'payment_id' => $payment['payment_id'],
				'status'     => $payment['status'],
				'amount'     => (int) $payment['amount'],
				'currency'   => $payment['currency'],
				'created'    => strtotime( $payment['created_at'] ),
			);
		}

		return $result;
	}

	/**
	 * Create subscription
	 *
	 * Zelle does not support subscriptions
	 *
	 * @param array $subscription_data Subscription data
	 * @return array|WP_Error
	 */
	public function create_subscription( $subscription_data ) {
		return new WP_Error( 'not_supported', __( 'Zelle does not support subscriptions', 'nonprofitsuite' ) );
	}

	/**
	 * Cancel subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @param array  $args            Cancel arguments
	 * @return bool|WP_Error
	 */
	public function cancel_subscription( $subscription_id, $args = array() ) {
		return new WP_Error( 'not_supported', __( 'Zelle does not support subscriptions', 'nonprofitsuite' ) );
	}

	/**
	 * Update subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @param array  $update_data     Update data
	 * @return array|WP_Error
	 */
	public function update_subscription( $subscription_id, $update_data ) {
		return new WP_Error( 'not_supported', __( 'Zelle does not support subscriptions', 'nonprofitsuite' ) );
	}

	/**
	 * Get subscription
	 *
	 * @param string $subscription_id Subscription ID
	 * @return array|WP_Error
	 */
	public function get_subscription( $subscription_id ) {
		return new WP_Error( 'not_supported', __( 'Zelle does not support subscriptions', 'nonprofitsuite' ) );
	}

	/**
	 * Create customer
	 *
	 * @param array $customer_data Customer data
	 * @return array|WP_Error
	 */
	public function create_customer( $customer_data ) {
		// Zelle doesn't have customer objects, just return dummy ID
		return array(
			'customer_id' => 'zelle_customer_' . md5( $customer_data['email'] ),
		);
	}

	/**
	 * Get customer
	 *
	 * @param string $customer_id Customer ID
	 * @return array|WP_Error
	 */
	public function get_customer( $customer_id ) {
		return new WP_Error( 'not_supported', __( 'Zelle does not have customer objects', 'nonprofitsuite' ) );
	}

	/**
	 * Handle webhook
	 *
	 * Zelle does not have webhooks
	 *
	 * @param array $payload Webhook payload
	 * @return bool|WP_Error
	 */
	public function handle_webhook( $payload ) {
		return new WP_Error( 'not_supported', __( 'Zelle does not support webhooks', 'nonprofitsuite' ) );
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload   Raw payload
	 * @param string $signature Signature header
	 * @return bool
	 */
	public function verify_webhook_signature( $payload, $signature ) {
		return false;
	}

	/**
	 * Get checkout URL
	 *
	 * @param array $args Checkout arguments
	 * @return string|WP_Error
	 */
	public function get_checkout_url( $args = array() ) {
		return new WP_Error( 'not_supported', __( 'Zelle does not provide checkout URLs', 'nonprofitsuite' ) );
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		global $wpdb;

		// Check if table exists
		$table_name = $wpdb->prefix . 'ns_zelle_payments';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

		if ( ! $table_exists ) {
			return new WP_Error( 'table_missing', __( 'Zelle payments table not created. Please run database migrations.', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Zelle';
	}

	/**
	 * Create Zelle payments table
	 *
	 * Should be called during plugin activation or migration
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ns_zelle_payments';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			payment_id varchar(100) NOT NULL,
			amount bigint(20) NOT NULL COMMENT 'Amount in cents',
			currency varchar(3) DEFAULT 'USD',
			donor_email varchar(255),
			donor_name varchar(255),
			donor_phone varchar(50),
			description text,
			reference_id varchar(255) COMMENT 'Bank reference or confirmation number',
			received_date datetime,
			status varchar(20) DEFAULT 'succeeded',
			refund_amount bigint(20),
			refund_reason text,
			refund_date datetime,
			metadata longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY payment_id (payment_id),
			KEY status (status),
			KEY donor_email (donor_email),
			KEY received_date (received_date)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}
