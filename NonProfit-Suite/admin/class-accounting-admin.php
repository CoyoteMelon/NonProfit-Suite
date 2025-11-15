<?php
/**
 * Accounting Admin Pages
 *
 * Provides admin interface for accounting system configuration and exports.
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
 * NonprofitSuite_Accounting_Admin Class
 *
 * Admin interface for accounting system.
 */
class NonprofitSuite_Accounting_Admin {

	/**
	 * Initialize admin pages.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'wp_ajax_ns_export_accounting', array( __CLASS__, 'ajax_export_accounting' ) );
		add_action( 'wp_ajax_ns_save_accounting_settings', array( __CLASS__, 'ajax_save_settings' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public static function add_admin_menu() {
		add_menu_page(
			__( 'Accounting', 'nonprofitsuite' ),
			__( 'Accounting', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-accounting',
			array( __CLASS__, 'render_accounting_page' ),
			'dashicons-chart-bar',
			35
		);

		add_submenu_page(
			'nonprofitsuite-accounting',
			__( 'Chart of Accounts', 'nonprofitsuite' ),
			__( 'Chart of Accounts', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-chart-of-accounts',
			array( __CLASS__, 'render_chart_page' )
		);

		add_submenu_page(
			'nonprofitsuite-accounting',
			__( 'Journal Entries', 'nonprofitsuite' ),
			__( 'Journal Entries', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-journal-entries',
			array( __CLASS__, 'render_journal_page' )
		);

		add_submenu_page(
			'nonprofitsuite-accounting',
			__( 'Export', 'nonprofitsuite' ),
			__( 'Export', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-accounting-export',
			array( __CLASS__, 'render_export_page' )
		);
	}

	/**
	 * Render accounting settings page.
	 */
	public static function render_accounting_page() {
		$org_id = 1; // TODO: Get from context
		$accounting_mode = get_option( 'ns_accounting_mode', 'builtin' ); // builtin, external, both
		$auto_entries = get_option( 'ns_accounting_auto_entries', 'yes' );

		include plugin_dir_path( __FILE__ ) . 'views/accounting-settings.php';
	}

	/**
	 * Render chart of accounts page.
	 */
	public static function render_chart_page() {
		$org_id = 1; // TODO: Get from context
		$accounts = NonprofitSuite_Accounting_Manager::get_accounts( $org_id );

		include plugin_dir_path( __FILE__ ) . 'views/chart-of-accounts.php';
	}

	/**
	 * Render journal entries page.
	 */
	public static function render_journal_page() {
		$org_id = 1; // TODO: Get from context

		$args = array();
		if ( isset( $_GET['date_from'] ) ) {
			$args['date_from'] = sanitize_text_field( $_GET['date_from'] );
		}
		if ( isset( $_GET['date_to'] ) ) {
			$args['date_to'] = sanitize_text_field( $_GET['date_to'] );
		}

		$entries = NonprofitSuite_Accounting_Manager::get_journal_entries( $org_id, $args );

		include plugin_dir_path( __FILE__ ) . 'views/journal-entries.php';
	}

	/**
	 * Render export page.
	 */
	public static function render_export_page() {
		$adapters = NonprofitSuite_Accounting_Manager::get_adapters();

		include plugin_dir_path( __FILE__ ) . 'views/accounting-export.php';
	}

	/**
	 * AJAX: Export accounting data.
	 */
	public static function ajax_export_accounting() {
		check_ajax_referer( 'ns_accounting', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$adapter_key = sanitize_text_field( $_POST['adapter'] );
		$export_type = sanitize_text_field( $_POST['export_type'] );

		$args = array(
			'organization_id' => 1, // TODO: Get from context
		);

		if ( isset( $_POST['date_from'] ) ) {
			$args['date_from'] = sanitize_text_field( $_POST['date_from'] );
		}
		if ( isset( $_POST['date_to'] ) ) {
			$args['date_to'] = sanitize_text_field( $_POST['date_to'] );
		}

		$result = NonprofitSuite_Accounting_Manager::export( $adapter_key, $export_type, $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Return file for download
		wp_send_json_success( array(
			'content'   => base64_encode( $result['content'] ),
			'filename'  => $result['filename'],
			'mime_type' => $result['mime_type'],
		) );
	}

	/**
	 * AJAX: Save accounting settings.
	 */
	public static function ajax_save_settings() {
		check_ajax_referer( 'ns_accounting', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		update_option( 'ns_accounting_mode', sanitize_text_field( $_POST['accounting_mode'] ) );
		update_option( 'ns_accounting_auto_entries', sanitize_text_field( $_POST['auto_entries'] ) );

		if ( isset( $_POST['cash_account'] ) ) {
			update_option( 'ns_accounting_cash_account', intval( $_POST['cash_account'] ) );
		}
		if ( isset( $_POST['revenue_account'] ) ) {
			update_option( 'ns_accounting_revenue_account', intval( $_POST['revenue_account'] ) );
		}
		if ( isset( $_POST['fee_account'] ) ) {
			update_option( 'ns_accounting_fee_account', intval( $_POST['fee_account'] ) );
		}

		wp_send_json_success( array( 'message' => 'Settings saved successfully' ) );
	}
}
