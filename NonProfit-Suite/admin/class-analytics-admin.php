<?php
/**
 * Analytics & Reporting Admin
 *
 * Handles the admin interface for analytics provider settings,
 * dashboards, and reports.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

class NS_Analytics_Admin {
	/**
	 * Initialize the admin interface.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ns_analytics_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ns_analytics_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Add menu pages.
	 */
	public function add_menu_pages() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'Analytics Dashboard', 'nonprofitsuite' ),
			__( 'Analytics', 'nonprofitsuite' ),
			'manage_options',
			'ns-analytics',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'Analytics Settings', 'nonprofitsuite' ),
			__( 'Analytics Settings', 'nonprofitsuite' ),
			'manage_options',
			'ns-analytics-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'ns-analytics' ) === false ) {
			return;
		}

		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true );
		wp_localize_script(
			'chart-js',
			'nsAnalytics',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ns_analytics_nonce' ),
			)
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/analytics-dashboard.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/analytics-settings.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'ns_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		global $wpdb;

		$organization_id = absint( $_POST['organization_id'] );
		$provider        = sanitize_text_field( $_POST['provider'] );
		$tracking_id     = sanitize_text_field( $_POST['tracking_id'] );
		$api_key         = sanitize_text_field( $_POST['api_key'] );
		$api_secret      = sanitize_text_field( $_POST['api_secret'] );
		$property_id     = sanitize_text_field( $_POST['property_id'] );
		$is_active       = isset( $_POST['is_active'] ) ? 1 : 0;

		$table = $wpdb->prefix . 'ns_analytics_settings';

		$wpdb->replace(
			$table,
			array(
				'organization_id' => $organization_id,
				'provider'        => $provider,
				'tracking_id'     => $tracking_id,
				'api_key'         => $api_key,
				'api_secret'      => $api_secret,
				'property_id'     => $property_id,
				'is_active'       => $is_active,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		wp_send_json_success( array( 'message' => 'Settings saved successfully!' ) );
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ns_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$provider        = sanitize_text_field( $_POST['provider'] );
		$organization_id = absint( $_POST['organization_id'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-analytics-manager.php';
		$manager = NS_Analytics_Manager::get_instance();

		$adapter = $manager->get_adapter( $provider, $organization_id );

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
}

new NS_Analytics_Admin();
