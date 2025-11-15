<?php
/**
 * SMS & Messaging Admin
 *
 * Handles the admin interface for SMS provider settings,
 * campaigns, and message logs.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

class NS_SMS_Admin {
	/**
	 * Initialize the admin interface.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ns_sms_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ns_sms_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ns_sms_send_test', array( $this, 'ajax_send_test' ) );
		add_action( 'wp_ajax_ns_sms_create_campaign', array( $this, 'ajax_create_campaign' ) );
		add_action( 'wp_ajax_ns_sms_send_campaign', array( $this, 'ajax_send_campaign' ) );
	}

	/**
	 * Add menu pages.
	 */
	public function add_menu_pages() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'SMS & Messaging', 'nonprofitsuite' ),
			__( 'SMS & Messaging', 'nonprofitsuite' ),
			'manage_options',
			'ns-sms',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'SMS Campaigns', 'nonprofitsuite' ),
			__( 'SMS Campaigns', 'nonprofitsuite' ),
			'manage_options',
			'ns-sms-campaigns',
			array( $this, 'render_campaigns_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'SMS Settings', 'nonprofitsuite' ),
			__( 'SMS Settings', 'nonprofitsuite' ),
			'manage_options',
			'ns-sms-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'ns-sms' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ns-sms-admin', NS_PLUGIN_URL . 'admin/css/sms-admin.css', array(), NS_VERSION );
		wp_enqueue_script( 'ns-sms-admin', NS_PLUGIN_URL . 'admin/js/sms-admin.js', array( 'jquery' ), NS_VERSION, true );

		wp_localize_script(
			'ns-sms-admin',
			'nsSMS',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ns_sms_nonce' ),
			)
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/sms-dashboard.php';
	}

	/**
	 * Render campaigns page.
	 */
	public function render_campaigns_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/sms-campaigns.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/sms-settings.php';
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ns_sms_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$provider        = sanitize_text_field( $_POST['provider'] );
		$organization_id = absint( $_POST['organization_id'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-sms-manager.php';
		$sms_manager = NS_SMS_Manager::get_instance();

		$adapter = $sms_manager->get_adapter( $provider, $organization_id );

		if ( is_wp_error( $adapter ) ) {
			wp_send_json_error( array( 'message' => $adapter->get_error_message() ) );
		}

		$result = $adapter->test_connection();

		if ( $result === true ) {
			wp_send_json_success( array( 'message' => 'Connection successful!' ) );
		} else {
			wp_send_json_error( array( 'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Connection failed.' ) );
		}
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'ns_sms_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		global $wpdb;

		$organization_id = absint( $_POST['organization_id'] );
		$provider        = sanitize_text_field( $_POST['provider'] );
		$account_sid     = sanitize_text_field( $_POST['account_sid'] );
		$api_key         = sanitize_text_field( $_POST['api_key'] );
		$api_secret      = sanitize_text_field( $_POST['api_secret'] );
		$phone_number    = sanitize_text_field( $_POST['phone_number'] );
		$is_active       = isset( $_POST['is_active'] ) ? 1 : 0;
		$monthly_limit   = absint( $_POST['monthly_limit'] );

		$table = $wpdb->prefix . 'ns_sms_settings';

		// Check if settings exist
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE organization_id = %d AND provider = %s",
				$organization_id,
				$provider
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'account_sid'   => $account_sid,
					'api_key'       => $api_key,
					'api_secret'    => $api_secret,
					'phone_number'  => $phone_number,
					'is_active'     => $is_active,
					'monthly_limit' => $monthly_limit,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'organization_id' => $organization_id,
					'provider'        => $provider,
					'account_sid'     => $account_sid,
					'api_key'         => $api_key,
					'api_secret'      => $api_secret,
					'phone_number'    => $phone_number,
					'is_active'       => $is_active,
					'monthly_limit'   => $monthly_limit,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		wp_send_json_success( array( 'message' => 'Settings saved successfully!' ) );
	}

	/**
	 * AJAX: Send test message.
	 */
	public function ajax_send_test() {
		check_ajax_referer( 'ns_sms_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$organization_id = absint( $_POST['organization_id'] );
		$provider        = sanitize_text_field( $_POST['provider'] );
		$to              = sanitize_text_field( $_POST['to'] );
		$message         = sanitize_textarea_field( $_POST['message'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-sms-manager.php';
		$sms_manager = NS_SMS_Manager::get_instance();

		$result = $sms_manager->send_message(
			array(
				'organization_id' => $organization_id,
				'to'              => $to,
				'message'         => $message,
				'provider'        => $provider,
				'message_type'    => 'transactional',
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Test message sent successfully!' ) );
	}

	/**
	 * AJAX: Create campaign.
	 */
	public function ajax_create_campaign() {
		check_ajax_referer( 'ns_sms_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$organization_id = absint( $_POST['organization_id'] );
		$campaign_name   = sanitize_text_field( $_POST['campaign_name'] );
		$message_body    = sanitize_textarea_field( $_POST['message_body'] );
		$provider        = sanitize_text_field( $_POST['provider'] );
		$target_segment  = sanitize_text_field( $_POST['target_segment'] );
		$scheduled_at    = sanitize_text_field( $_POST['scheduled_at'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-sms-manager.php';
		$sms_manager = NS_SMS_Manager::get_instance();

		$campaign_id = $sms_manager->create_campaign(
			array(
				'organization_id' => $organization_id,
				'campaign_name'   => $campaign_name,
				'message_body'    => $message_body,
				'provider'        => $provider,
				'target_segment'  => $target_segment,
				'scheduled_at'    => $scheduled_at ? $scheduled_at : null,
				'status'          => $scheduled_at ? 'scheduled' : 'draft',
			)
		);

		if ( is_wp_error( $campaign_id ) ) {
			wp_send_json_error( array( 'message' => $campaign_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => 'Campaign created successfully!',
				'campaign_id' => $campaign_id,
			)
		);
	}

	/**
	 * AJAX: Send campaign.
	 */
	public function ajax_send_campaign() {
		check_ajax_referer( 'ns_sms_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$campaign_id = absint( $_POST['campaign_id'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-sms-manager.php';
		$sms_manager = NS_SMS_Manager::get_instance();

		// Send campaign in background
		wp_schedule_single_event( time(), 'ns_send_sms_campaign', array( $campaign_id ) );

		wp_send_json_success( array( 'message' => 'Campaign is being sent...' ) );
	}
}

new NS_SMS_Admin();
