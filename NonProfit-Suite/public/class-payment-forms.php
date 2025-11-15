<?php
/**
 * Payment Forms - Frontend Shortcodes
 *
 * Provides public-facing donation and payment forms.
 *
 * @package    NonprofitSuite
 * @subpackage Public
 * @since      1.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Payment_Forms Class
 *
 * Frontend payment form shortcodes and handlers.
 */
class NonprofitSuite_Payment_Forms {

	/**
	 * Initialize shortcodes and handlers.
	 */
	public static function init() {
		add_shortcode( 'ns_donation_form', array( __CLASS__, 'donation_form_shortcode' ) );
		add_shortcode( 'ns_recurring_donation', array( __CLASS__, 'recurring_donation_shortcode' ) );
		add_shortcode( 'ns_pledge_form', array( __CLASS__, 'pledge_form_shortcode' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_public_assets' ) );
		add_action( 'wp_ajax_ns_process_donation', array( __CLASS__, 'ajax_process_donation' ) );
		add_action( 'wp_ajax_nopriv_ns_process_donation', array( __CLASS__, 'ajax_process_donation' ) );
		add_action( 'wp_ajax_ns_create_recurring', array( __CLASS__, 'ajax_create_recurring' ) );
		add_action( 'wp_ajax_nopriv_ns_create_recurring', array( __CLASS__, 'ajax_create_recurring' ) );
	}

	/**
	 * Enqueue public assets.
	 */
	public static function enqueue_public_assets() {
		if ( has_shortcode( get_post()->post_content, 'ns_donation_form' ) ||
		     has_shortcode( get_post()->post_content, 'ns_recurring_donation' ) ||
		     has_shortcode( get_post()->post_content, 'ns_pledge_form' ) ) {

			wp_enqueue_style( 'nonprofitsuite-payment-forms', plugins_url( 'css/payment-forms.css', __FILE__ ), array(), '1.7.0' );
			wp_enqueue_script( 'nonprofitsuite-payment-forms', plugins_url( 'js/payment-forms.js', __FILE__ ), array( 'jquery' ), '1.7.0', true );

			wp_localize_script( 'nonprofitsuite-payment-forms', 'nsPaymentForms', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ns_payment_forms' ),
			) );

			// Load Stripe.js if Stripe is enabled
			$stripe_enabled = self::is_processor_enabled( 'stripe' );
			if ( $stripe_enabled ) {
				wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
			}
		}
	}

	/**
	 * Donation form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function donation_form_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'title'          => __( 'Make a Donation', 'nonprofitsuite' ),
			'description'    => '',
			'amounts'        => '25,50,100,250',
			'default_amount' => '50',
			'show_fees'      => 'yes',
			'fund_id'        => '',
			'campaign_id'    => '',
		), $atts );

		$org_id = 1; // TODO: Get from context

		// Get active processors with fee comparison
		$processors = self::get_active_processors( $org_id );

		if ( empty( $processors ) ) {
			return '<p>' . esc_html__( 'Payment processors are currently unavailable. Please try again later.', 'nonprofitsuite' ) . '</p>';
		}

		$amounts = array_map( 'trim', explode( ',', $atts['amounts'] ) );

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'templates/donation-form.php';
		return ob_get_clean();
	}

	/**
	 * Recurring donation shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function recurring_donation_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'title'          => __( 'Become a Monthly Supporter', 'nonprofitsuite' ),
			'description'    => '',
			'amounts'        => '10,25,50,100',
			'default_amount' => '25',
			'frequencies'    => 'monthly,quarterly,annual',
			'default_freq'   => 'monthly',
			'fund_id'        => '',
			'campaign_id'    => '',
		), $atts );

		$org_id = 1; // TODO: Get from context

		$processors = self::get_active_processors( $org_id, true ); // Only recurring-capable

		if ( empty( $processors ) ) {
			return '<p>' . esc_html__( 'Recurring donations are currently unavailable. Please try again later.', 'nonprofitsuite' ) . '</p>';
		}

		$amounts = array_map( 'trim', explode( ',', $atts['amounts'] ) );
		$frequencies = array_map( 'trim', explode( ',', $atts['frequencies'] ) );

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'templates/recurring-form.php';
		return ob_get_clean();
	}

	/**
	 * Pledge form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function pledge_form_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'title'       => __( 'Make a Pledge', 'nonprofitsuite' ),
			'description' => '',
			'min_amount'  => '100',
			'fund_id'     => '',
			'campaign_id' => '',
		), $atts );

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'templates/pledge-form.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: Process donation.
	 */
	public static function ajax_process_donation() {
		check_ajax_referer( 'ns_payment_forms', 'nonce' );

		$processor_id = intval( $_POST['processor_id'] );
		$amount = floatval( $_POST['amount'] );
		$payment_method_id = sanitize_text_field( $_POST['payment_method_id'] );
		$donor_email = sanitize_email( $_POST['donor_email'] );
		$donor_name = sanitize_text_field( $_POST['donor_name'] );
		$fund_id = isset( $_POST['fund_id'] ) ? intval( $_POST['fund_id'] ) : null;
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : null;

		// Create or get donor
		$donor_id = self::get_or_create_donor( $donor_email, $donor_name );

		// Prepare payment data
		$payment_data = array(
			'amount'            => $amount,
			'payment_method_id' => $payment_method_id,
			'description'       => 'Donation from ' . $donor_name,
			'payment_type'      => 'donation',
			'donor_id'          => $donor_id,
			'currency'          => 'USD',
		);

		// Process payment
		$result = NonprofitSuite_Payment_Manager::process_payment( $processor_id, $payment_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Log transaction
		NonprofitSuite_Transaction_Logger::log_transaction( array(
			'organization_id'  => 1, // TODO: Get from context
			'donor_id'         => $donor_id,
			'processor_id'     => $processor_id,
			'processor_transaction_id' => $result['transaction_id'],
			'amount'           => $amount,
			'fee_amount'       => $result['fee_amount'],
			'net_amount'       => $result['net_amount'],
			'status'           => $result['status'],
			'payment_type'     => 'donation',
			'fund_id'          => $fund_id,
			'campaign_id'      => $campaign_id,
		) );

		// Send thank you email
		self::send_donation_receipt( $donor_email, $donor_name, $amount );

		wp_send_json_success( array(
			'message'        => __( 'Thank you for your donation!', 'nonprofitsuite' ),
			'transaction_id' => $result['transaction_id'],
		) );
	}

	/**
	 * AJAX: Create recurring donation.
	 */
	public static function ajax_create_recurring() {
		check_ajax_referer( 'ns_payment_forms', 'nonce' );

		$processor_id = intval( $_POST['processor_id'] );
		$amount = floatval( $_POST['amount'] );
		$frequency = sanitize_text_field( $_POST['frequency'] );
		$payment_method_id = sanitize_text_field( $_POST['payment_method_id'] );
		$donor_email = sanitize_email( $_POST['donor_email'] );
		$donor_name = sanitize_text_field( $_POST['donor_name'] );
		$fund_id = isset( $_POST['fund_id'] ) ? intval( $_POST['fund_id'] ) : null;
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : null;

		// Create or get donor
		$donor_id = self::get_or_create_donor( $donor_email, $donor_name );

		// Get processor adapter
		global $wpdb;
		$processor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ns_payment_processors WHERE id = %d", $processor_id ), ARRAY_A );

		$adapter = NonprofitSuite_Payment_Manager::get_adapter( $processor['processor_type'], $processor_id );

		// Create subscription with processor
		$subscription_data = array(
			'amount'       => $amount,
			'frequency'    => $frequency,
			'email'        => $donor_email,
			'custom_id'    => $donor_id,
			'product_name' => ucfirst( $frequency ) . ' Donation',
			'description'  => ucfirst( $frequency ) . ' donation from ' . $donor_name,
		);

		$result = $adapter->create_subscription( $subscription_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Create recurring donation record
		$recurring_id = NonprofitSuite_Recurring_Donation_Manager::create_subscription( array(
			'organization_id' => 1, // TODO: Get from context
			'donor_id'        => $donor_id,
			'processor_id'    => $processor_id,
			'subscription_id' => $result['subscription_id'],
			'amount'          => $amount,
			'frequency'       => $frequency,
			'fund_id'         => $fund_id,
			'campaign_id'     => $campaign_id,
			'status'          => $result['status'],
		) );

		// Send confirmation email
		self::send_recurring_confirmation( $donor_email, $donor_name, $amount, $frequency );

		wp_send_json_success( array(
			'message'         => __( 'Thank you for setting up a recurring donation!', 'nonprofitsuite' ),
			'subscription_id' => $result['subscription_id'],
		) );
	}

	/**
	 * Get active payment processors with fee information.
	 *
	 * @param int  $org_id          Organization ID.
	 * @param bool $recurring_only  Only recurring-capable processors.
	 * @return array Active processors with fee info.
	 */
	private static function get_active_processors( $org_id, $recurring_only = false ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';

		$query = "SELECT * FROM {$table} WHERE organization_id = %d AND is_active = 1 ORDER BY display_order ASC, is_preferred DESC";
		$processors = $wpdb->get_results( $wpdb->prepare( $query, $org_id ), ARRAY_A );

		// Add fee information
		foreach ( $processors as &$processor ) {
			$processor['fee_info'] = NonprofitSuite_Fee_Calculator::get_fee_policy( $processor['id'], 'donation' );
		}

		return $processors;
	}

	/**
	 * Check if a processor type is enabled.
	 *
	 * @param string $processor_type Processor type.
	 * @return bool True if enabled.
	 */
	private static function is_processor_enabled( $processor_type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_payment_processors';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE processor_type = %s AND is_active = 1",
			$processor_type
		) );

		return $count > 0;
	}

	/**
	 * Get or create donor.
	 *
	 * @param string $email Donor email.
	 * @param string $name  Donor name.
	 * @return int Donor ID.
	 */
	private static function get_or_create_donor( $email, $name ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_contacts';

		// Try to find existing donor
		$donor_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE email = %s",
			$email
		) );

		if ( ! $donor_id ) {
			// Create new donor
			$name_parts = explode( ' ', $name, 2 );
			$wpdb->insert( $table, array(
				'organization_id' => 1, // TODO: Get from context
				'first_name'      => $name_parts[0],
				'last_name'       => isset( $name_parts[1] ) ? $name_parts[1] : '',
				'email'           => $email,
				'contact_type'    => 'donor',
				'created_at'      => current_time( 'mysql' ),
			) );
			$donor_id = $wpdb->insert_id;
		}

		return $donor_id;
	}

	/**
	 * Send donation receipt email.
	 *
	 * @param string $email Donor email.
	 * @param string $name  Donor name.
	 * @param float  $amount Donation amount.
	 */
	private static function send_donation_receipt( $email, $name, $amount ) {
		$subject = __( 'Thank you for your donation', 'nonprofitsuite' );
		$message = sprintf(
			__( 'Dear %s,\n\nThank you for your generous donation of $%s.\n\nYour support helps us continue our mission.\n\nSincerely,\n%s', 'nonprofitsuite' ),
			$name,
			number_format( $amount, 2 ),
			get_bloginfo( 'name' )
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Send recurring donation confirmation.
	 *
	 * @param string $email     Donor email.
	 * @param string $name      Donor name.
	 * @param float  $amount    Donation amount.
	 * @param string $frequency Donation frequency.
	 */
	private static function send_recurring_confirmation( $email, $name, $amount, $frequency ) {
		$subject = __( 'Thank you for setting up a recurring donation', 'nonprofitsuite' );
		$message = sprintf(
			__( 'Dear %s,\n\nThank you for setting up a %s donation of $%s.\n\nYour ongoing support means the world to us.\n\nSincerely,\n%s', 'nonprofitsuite' ),
			$name,
			$frequency,
			number_format( $amount, 2 ),
			get_bloginfo( 'name' )
		);

		wp_mail( $email, $subject, $message );
	}
}
