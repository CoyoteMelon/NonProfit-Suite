<?php
/**
 * Payment System Admin Pages
 *
 * Provides admin interface for payment processor configuration.
 *
 * @package    NonprofitSuite
 * @subpackage Admin
 * @since      1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Payment_Admin Class
 *
 * Admin interface for payment system.
 */
class NonprofitSuite_Payment_Admin {

	/**
	 * Initialize admin pages.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_ns_save_processor', array( __CLASS__, 'ajax_save_processor' ) );
		add_action( 'wp_ajax_ns_test_processor', array( __CLASS__, 'ajax_test_processor' ) );
		add_action( 'wp_ajax_ns_delete_processor', array( __CLASS__, 'ajax_delete_processor' ) );
		add_action( 'wp_ajax_ns_save_bank_account', array( __CLASS__, 'ajax_save_bank_account' ) );
		add_action( 'wp_ajax_ns_save_sweep_schedule', array( __CLASS__, 'ajax_save_sweep_schedule' ) );
		add_action( 'wp_ajax_ns_test_sweep', array( __CLASS__, 'ajax_test_sweep' ) );
		add_action( 'wp_ajax_ns_save_fee_policy', array( __CLASS__, 'ajax_save_fee_policy' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public static function add_admin_menu() {
		add_menu_page(
			__( 'Payment Processing', 'nonprofitsuite' ),
			__( 'Payments', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-payments',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-money-alt',
			30
		);

		add_submenu_page(
			'nonprofitsuite-payments',
			__( 'Payment Dashboard', 'nonprofitsuite' ),
			__( 'Dashboard', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-payments',
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			'nonprofitsuite-payments',
			__( 'Payment Processors', 'nonprofitsuite' ),
			__( 'Processors', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-processors',
			array( __CLASS__, 'render_processors_page' )
		);

		add_submenu_page(
			'nonprofitsuite-payments',
			__( 'Bank Accounts', 'nonprofitsuite' ),
			__( 'Bank Accounts', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-bank-accounts',
			array( __CLASS__, 'render_bank_accounts_page' )
		);

		add_submenu_page(
			'nonprofitsuite-payments',
			__( 'Sweep Schedules', 'nonprofitsuite' ),
			__( 'Sweep Schedules', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-sweep-schedules',
			array( __CLASS__, 'render_sweep_schedules_page' )
		);

		add_submenu_page(
			'nonprofitsuite-payments',
			__( 'Fee Policies', 'nonprofitsuite' ),
			__( 'Fee Policies', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-fee-policies',
			array( __CLASS__, 'render_fee_policies_page' )
		);

		add_submenu_page(
			'nonprofitsuite-payments',
			__( 'Transactions', 'nonprofitsuite' ),
			__( 'Transactions', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-transactions',
			array( __CLASS__, 'render_transactions_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'nonprofitsuite' ) === false ) {
			return;
		}

		wp_enqueue_style( 'nonprofitsuite-payment-admin', plugins_url( 'css/payment-admin.css', __FILE__ ), array(), '1.7.0' );
		wp_enqueue_script( 'nonprofitsuite-payment-admin', plugins_url( 'js/payment-admin.js', __FILE__ ), array( 'jquery' ), '1.7.0', true );

		wp_localize_script( 'nonprofitsuite-payment-admin', 'nsPaymentAdmin', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ns_payment_admin' ),
		) );
	}

	/**
	 * Render dashboard page.
	 */
	public static function render_dashboard_page() {
		global $wpdb;

		// Get summary statistics
		$org_id = 1; // TODO: Get from context

		$transaction_summary = NonprofitSuite_Transaction_Logger::get_transaction_summary( array(
			'organization_id' => $org_id,
			'status'          => 'completed',
		) );

		$recurring_summary = NonprofitSuite_Recurring_Donation_Manager::get_subscription_summary( array(
			'organization_id' => $org_id,
		) );

		$pledge_summary = NonprofitSuite_Pledge_Manager::get_pledge_summary( array(
			'organization_id' => $org_id,
		) );

		$attention_transactions = NonprofitSuite_Transaction_Logger::get_attention_transactions( $org_id );

		include plugin_dir_path( __FILE__ ) . 'views/payment-dashboard.php';
	}

	/**
	 * Render processors configuration page.
	 */
	public static function render_processors_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';

		$processors = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY display_order ASC", ARRAY_A );

		$available_processors = array(
			'stripe' => array(
				'name'         => 'Stripe',
				'description'  => 'Accept credit cards, debit cards, and digital wallets',
				'capabilities' => array( 'one_time', 'recurring', 'refunds', 'payouts' ),
				'fields'       => array(
					array( 'key' => 'api_key', 'label' => 'Secret Key', 'type' => 'password' ),
					array( 'key' => 'publishable_key', 'label' => 'Publishable Key', 'type' => 'text' ),
					array( 'key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password' ),
				),
			),
			'paypal'  => array(
				'name'         => 'PayPal',
				'description'  => 'PayPal account payments and subscriptions',
				'capabilities' => array( 'one_time', 'recurring', 'refunds', 'payouts' ),
				'fields'       => array(
					array( 'key' => 'client_id', 'label' => 'Client ID', 'type' => 'text' ),
					array( 'key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password' ),
					array( 'key' => 'payout_email', 'label' => 'Payout Email', 'type' => 'email' ),
					array( 'key' => 'sandbox', 'label' => 'Sandbox Mode', 'type' => 'checkbox' ),
				),
			),
			'square'  => array(
				'name'         => 'Square',
				'description'  => 'Online and in-person payments',
				'capabilities' => array( 'one_time', 'recurring', 'refunds', 'in_person' ),
				'fields'       => array(
					array( 'key' => 'access_token', 'label' => 'Access Token', 'type' => 'password' ),
					array( 'key' => 'location_id', 'label' => 'Location ID', 'type' => 'text' ),
				),
			),
			'venmo'   => array(
				'name'         => 'Venmo',
				'description'  => 'Peer-to-peer mobile payments',
				'capabilities' => array( 'one_time' ),
				'fields'       => array(
					array( 'key' => 'username', 'label' => 'Venmo Username', 'type' => 'text' ),
				),
			),
			'zelle'   => array(
				'name'         => 'Zelle',
				'description'  => 'Direct bank transfers',
				'capabilities' => array( 'one_time' ),
				'fields'       => array(
					array( 'key' => 'email', 'label' => 'Zelle Email', 'type' => 'email' ),
					array( 'key' => 'phone', 'label' => 'Zelle Phone', 'type' => 'tel' ),
				),
			),
			'ach'     => array(
				'name'         => 'ACH/eCheck',
				'description'  => 'Direct bank account debits',
				'capabilities' => array( 'one_time', 'recurring' ),
				'fields'       => array(
					array( 'key' => 'provider', 'label' => 'ACH Provider', 'type' => 'select', 'options' => array( 'stripe', 'plaid' ) ),
				),
			),
		);

		include plugin_dir_path( __FILE__ ) . 'views/processors-page.php';
	}

	/**
	 * Render bank accounts page.
	 */
	public static function render_bank_accounts_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_bank_accounts';

		$bank_accounts = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY account_type ASC", ARRAY_A );

		include plugin_dir_path( __FILE__ ) . 'views/bank-accounts-page.php';
	}

	/**
	 * Render sweep schedules page.
	 */
	public static function render_sweep_schedules_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_sweep_schedules';

		$sweep_schedules = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

		// Get processors for source dropdown
		$processors_table = $wpdb->prefix . 'ns_payment_processors';
		$processors = $wpdb->get_results( "SELECT id, processor_name FROM {$processors_table}", ARRAY_A );

		// Get bank accounts for destination dropdown
		$bank_table = $wpdb->prefix . 'ns_bank_accounts';
		$bank_accounts = $wpdb->get_results( "SELECT id, account_name, account_type FROM {$bank_table}", ARRAY_A );

		include plugin_dir_path( __FILE__ ) . 'views/sweep-schedules-page.php';
	}

	/**
	 * Render fee policies page.
	 */
	public static function render_fee_policies_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_fee_policies';

		$fee_policies = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY processor_id ASC", ARRAY_A );

		// Get processors
		$processors_table = $wpdb->prefix . 'ns_payment_processors';
		$processors = $wpdb->get_results( "SELECT id, processor_name FROM {$processors_table}", ARRAY_A );

		include plugin_dir_path( __FILE__ ) . 'views/fee-policies-page.php';
	}

	/**
	 * Render transactions page.
	 */
	public static function render_transactions_page() {
		$org_id = 1; // TODO: Get from context

		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page = 50;

		$args = array(
			'organization_id' => $org_id,
			'limit'           => $per_page,
			'offset'          => ( $page - 1 ) * $per_page,
		);

		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
			$args['status'] = sanitize_text_field( $_GET['status'] );
		}

		if ( isset( $_GET['payment_type'] ) && ! empty( $_GET['payment_type'] ) ) {
			$args['payment_type'] = sanitize_text_field( $_GET['payment_type'] );
		}

		$transactions = NonprofitSuite_Transaction_Logger::get_organization_transactions( $org_id, $args );

		include plugin_dir_path( __FILE__ ) . 'views/transactions-page.php';
	}

	/**
	 * AJAX: Save processor configuration.
	 */
	public static function ajax_save_processor() {
		check_ajax_referer( 'ns_payment_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$processor_id = isset( $_POST['processor_id'] ) ? intval( $_POST['processor_id'] ) : 0;
		$processor_type = sanitize_text_field( $_POST['processor_type'] );
		$processor_name = sanitize_text_field( $_POST['processor_name'] );
		$credentials = json_decode( stripslashes( $_POST['credentials'] ), true );
		$is_active = isset( $_POST['is_active'] ) ? 1 : 0;
		$is_preferred = isset( $_POST['is_preferred'] ) ? 1 : 0;
		$display_order = intval( $_POST['display_order'] );
		$min_amount = floatval( $_POST['min_amount'] );
		$max_amount = floatval( $_POST['max_amount'] );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';

		$data = array(
			'organization_id' => 1, // TODO: Get from context
			'processor_type'  => $processor_type,
			'processor_name'  => $processor_name,
			'credentials'     => wp_json_encode( $credentials ),
			'is_active'       => $is_active,
			'is_preferred'    => $is_preferred,
			'display_order'   => $display_order,
			'min_amount'      => $min_amount,
			'max_amount'      => $max_amount,
		);

		if ( $processor_id > 0 ) {
			// Update existing
			$wpdb->update( $table, $data, array( 'id' => $processor_id ) );
			$message = 'Processor updated successfully';
		} else {
			// Insert new
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$processor_id = $wpdb->insert_id;
			$message = 'Processor created successfully';
		}

		// Get webhook URL
		$webhook_url = NonprofitSuite_Webhook_Router::get_webhook_url( $processor_type );

		wp_send_json_success( array(
			'message'      => $message,
			'processor_id' => $processor_id,
			'webhook_url'  => $webhook_url,
		) );
	}

	/**
	 * AJAX: Test processor connection.
	 */
	public static function ajax_test_processor() {
		check_ajax_referer( 'ns_payment_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$processor_id = intval( $_POST['processor_id'] );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';
		$processor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $processor_id ), ARRAY_A );

		if ( ! $processor ) {
			wp_send_json_error( array( 'message' => 'Processor not found' ) );
		}

		$adapter = NonprofitSuite_Payment_Manager::get_adapter( $processor['processor_type'], $processor_id );
		$result = $adapter->validate_config();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Connection successful!' ) );
	}

	/**
	 * AJAX: Delete processor.
	 */
	public static function ajax_delete_processor() {
		check_ajax_referer( 'ns_payment_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$processor_id = intval( $_POST['processor_id'] );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';
		$wpdb->delete( $table, array( 'id' => $processor_id ) );

		wp_send_json_success( array( 'message' => 'Processor deleted successfully' ) );
	}

	/**
	 * AJAX: Save bank account.
	 */
	public static function ajax_save_bank_account() {
		check_ajax_referer( 'ns_payment_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$account_id = isset( $_POST['account_id'] ) ? intval( $_POST['account_id'] ) : 0;
		$account_name = sanitize_text_field( $_POST['account_name'] );
		$account_type = sanitize_text_field( $_POST['account_type'] );
		$account_details = json_decode( stripslashes( $_POST['account_details'] ), true );
		$current_balance = floatval( $_POST['current_balance'] );
		$minimum_buffer = floatval( $_POST['minimum_buffer'] );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_bank_accounts';

		$data = array(
			'organization_id' => 1, // TODO: Get from context
			'account_name'    => $account_name,
			'account_type'    => $account_type,
			'account_details' => wp_json_encode( $account_details ),
			'current_balance' => $current_balance,
			'minimum_buffer'  => $minimum_buffer,
		);

		if ( $account_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $account_id ) );
			$message = 'Bank account updated successfully';
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$account_id = $wpdb->insert_id;
			$message = 'Bank account created successfully';
		}

		wp_send_json_success( array(
			'message'    => $message,
			'account_id' => $account_id,
		) );
	}

	/**
	 * AJAX: Save sweep schedule.
	 */
	public static function ajax_save_sweep_schedule() {
		check_ajax_referer( 'ns_payment_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$schedule_data = array(
			'organization_id'       => 1, // TODO: Get from context
			'source_type'           => sanitize_text_field( $_POST['source_type'] ),
			'source_id'             => intval( $_POST['source_id'] ),
			'destination_account_id' => intval( $_POST['destination_account_id'] ),
			'sweep_frequency'       => sanitize_text_field( $_POST['sweep_frequency'] ),
			'schedule_time'         => sanitize_text_field( $_POST['schedule_time'] ),
			'minimum_amount'        => floatval( $_POST['minimum_amount'] ),
			'leave_buffer_amount'   => floatval( $_POST['leave_buffer_amount'] ),
			'sweep_percentage'      => floatval( $_POST['sweep_percentage'] ),
			'is_active'             => isset( $_POST['is_active'] ) ? 1 : 0,
		);

		$schedule_id = NonprofitSuite_Sweep_Manager::create_sweep_schedule( $schedule_data );

		if ( is_wp_error( $schedule_id ) ) {
			wp_send_json_error( array( 'message' => $schedule_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'     => 'Sweep schedule created successfully',
			'schedule_id' => $schedule_id,
		) );
	}

	/**
	 * AJAX: Test sweep execution.
	 */
	public static function ajax_test_sweep() {
		check_ajax_referer( 'ns_payment_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$schedule_id = intval( $_POST['schedule_id'] );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_sweep_schedules';
		$sweep = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $schedule_id ), ARRAY_A );

		if ( ! $sweep ) {
			wp_send_json_error( array( 'message' => 'Sweep schedule not found' ) );
		}

		$result = NonprofitSuite_Sweep_Manager::execute_sweep( $sweep );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => 'Sweep executed successfully',
			'result'  => $result,
		) );
	}

	/**
	 * AJAX: Save fee policy.
	 */
	public static function ajax_save_fee_policy() {
		check_ajax_referer( 'ns_payment_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$result = NonprofitSuite_Fee_Calculator::create_policy( array(
			'processor_id'       => intval( $_POST['processor_id'] ),
			'payment_type'       => sanitize_text_field( $_POST['payment_type'] ),
			'policy_type'        => sanitize_text_field( $_POST['policy_type'] ),
			'fee_percentage'     => floatval( $_POST['fee_percentage'] ),
			'fee_fixed_amount'   => floatval( $_POST['fee_fixed_amount'] ),
			'incentive_message'  => sanitize_text_field( $_POST['incentive_message'] ),
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Fee policy saved successfully' ) );
	}
}
