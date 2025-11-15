<?php
/**
 * CRM Admin Controller
 *
 * Handles admin pages and AJAX operations for CRM integrations.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_CRM_Admin {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'wp_ajax_ns_test_crm_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ns_save_crm_settings', array( __CLASS__, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ns_sync_contact_to_crm', array( __CLASS__, 'ajax_sync_contact' ) );
		add_action( 'wp_ajax_ns_run_manual_sync', array( __CLASS__, 'ajax_run_manual_sync' ) );
		add_action( 'wp_ajax_ns_save_field_mappings', array( __CLASS__, 'ajax_save_field_mappings' ) );
		add_action( 'wp_ajax_ns_get_crm_fields', array( __CLASS__, 'ajax_get_crm_fields' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public static function add_menu_pages() {
		add_submenu_page(
			'nonprofitsuite',
			'CRM Integrations',
			'CRM',
			'manage_options',
			'ns-crm-settings',
			array( __CLASS__, 'render_settings_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			'CRM Field Mappings',
			null, // Hidden from menu
			'manage_options',
			'ns-crm-field-mappings',
			array( __CLASS__, 'render_field_mappings_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			'CRM Sync Log',
			null, // Hidden from menu
			'manage_options',
			'ns-crm-sync-log',
			array( __CLASS__, 'render_sync_log_page' )
		);
	}

	/**
	 * Render CRM settings page.
	 */
	public static function render_settings_page() {
		$org_id = isset( $_GET['organization_id'] ) ? intval( $_GET['organization_id'] ) : 0;

		if ( ! $org_id ) {
			echo '<div class="notice notice-error"><p>Please select an organization.</p></div>';
			return;
		}

		// Get current settings
		global $wpdb;
		$settings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_crm_settings WHERE organization_id = %d",
				$org_id
			),
			ARRAY_A
		);

		$active_providers = array();
		foreach ( $settings as $setting ) {
			if ( $setting['is_active'] ) {
				$active_providers[ $setting['crm_provider'] ] = $setting;
			}
		}

		include plugin_dir_path( __FILE__ ) . 'views/crm-settings.php';
	}

	/**
	 * Render field mappings page.
	 */
	public static function render_field_mappings_page() {
		$org_id = isset( $_GET['organization_id'] ) ? intval( $_GET['organization_id'] ) : 0;
		$provider = isset( $_GET['provider'] ) ? sanitize_text_field( $_GET['provider'] ) : '';

		if ( ! $org_id || ! $provider ) {
			echo '<div class="notice notice-error"><p>Invalid parameters.</p></div>';
			return;
		}

		// Get current mappings
		global $wpdb;
		$mappings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_crm_field_mappings WHERE organization_id = %d AND crm_provider = %s",
				$org_id,
				$provider
			),
			ARRAY_A
		);

		include plugin_dir_path( __FILE__ ) . 'views/crm-field-mappings.php';
	}

	/**
	 * Render sync log page.
	 */
	public static function render_sync_log_page() {
		$org_id = isset( $_GET['organization_id'] ) ? intval( $_GET['organization_id'] ) : 0;

		if ( ! $org_id ) {
			echo '<div class="notice notice-error"><p>Please select an organization.</p></div>';
			return;
		}

		// Get sync log entries
		global $wpdb;
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_crm_sync_log WHERE organization_id = %d ORDER BY synced_at DESC LIMIT 100",
				$org_id
			),
			ARRAY_A
		);

		include plugin_dir_path( __FILE__ ) . 'views/crm-sync-log.php';
	}

	/**
	 * AJAX: Test CRM connection.
	 */
	public static function ajax_test_connection() {
		check_ajax_referer( 'ns_crm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;

		if ( ! $provider || ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing parameters' ) );
		}

		$adapter = NonprofitSuite_CRM_Manager::get_adapter( $provider, $org_id );

		if ( ! $adapter ) {
			wp_send_json_error( array( 'message' => 'Adapter not found or not configured' ) );
		}

		$credentials = NonprofitSuite_CRM_Manager::get_crm_credentials( $provider, $org_id );
		$result = $adapter->test_connection( $credentials );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Connection successful!' ) );
	}

	/**
	 * AJAX: Save CRM settings.
	 */
	public static function ajax_save_settings() {
		check_ajax_referer( 'ns_crm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
		$settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();

		if ( ! $provider || ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing parameters' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_settings';

		// Check if settings exist
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE organization_id = %d AND crm_provider = %s",
				$org_id,
				$provider
			)
		);

		$data = array(
			'organization_id' => $org_id,
			'crm_provider'    => $provider,
			'crm_mode'        => isset( $settings['crm_mode'] ) ? sanitize_text_field( $settings['crm_mode'] ) : 'builtin',
			'api_url'         => isset( $settings['api_url'] ) ? esc_url_raw( $settings['api_url'] ) : '',
			'api_key'         => isset( $settings['api_key'] ) ? sanitize_text_field( $settings['api_key'] ) : '',
			'api_secret'      => isset( $settings['api_secret'] ) ? sanitize_text_field( $settings['api_secret'] ) : '',
			'sync_direction'  => isset( $settings['sync_direction'] ) ? sanitize_text_field( $settings['sync_direction'] ) : 'push',
			'sync_frequency'  => isset( $settings['sync_frequency'] ) ? sanitize_text_field( $settings['sync_frequency'] ) : 'hourly',
			'is_active'       => isset( $settings['is_active'] ) ? intval( $settings['is_active'] ) : 1,
			'settings'        => isset( $settings['extra'] ) ? wp_json_encode( $settings['extra'] ) : '',
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing ) );
		} else {
			$wpdb->insert( $table, $data );
		}

		wp_send_json_success( array( 'message' => 'Settings saved successfully' ) );
	}

	/**
	 * AJAX: Sync a contact to CRM.
	 */
	public static function ajax_sync_contact() {
		check_ajax_referer( 'ns_crm_sync', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$contact_id = isset( $_POST['contact_id'] ) ? intval( $_POST['contact_id'] ) : 0;
		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;

		if ( ! $contact_id || ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing parameters' ) );
		}

		// Get active providers
		$providers = NonprofitSuite_CRM_Manager::get_active_providers( $org_id );

		if ( empty( $providers ) ) {
			wp_send_json_error( array( 'message' => 'No active CRM providers configured' ) );
		}

		$results = array();

		foreach ( $providers as $provider ) {
			$result = NonprofitSuite_CRM_Manager::push_contact( $provider, $org_id, $contact_id );

			if ( is_wp_error( $result ) ) {
				$results[ $provider ] = array(
					'success' => false,
					'message' => $result->get_error_message(),
				);
			} else {
				$results[ $provider ] = array(
					'success' => true,
					'crm_id'  => $result['crm_id'],
				);
			}
		}

		wp_send_json_success( array(
			'message' => 'Sync completed',
			'results' => $results,
		) );
	}

	/**
	 * AJAX: Run manual sync.
	 */
	public static function ajax_run_manual_sync() {
		check_ajax_referer( 'ns_crm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;

		if ( ! $provider || ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing parameters' ) );
		}

		$result = NonprofitSuite_CRM_Manager::sync_organization( $provider, $org_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Sync completed successfully' ) );
	}

	/**
	 * AJAX: Save field mappings.
	 */
	public static function ajax_save_field_mappings() {
		check_ajax_referer( 'ns_crm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
		$mappings = isset( $_POST['mappings'] ) ? $_POST['mappings'] : array();

		if ( ! $provider || ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing parameters' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_field_mappings';

		// Delete existing mappings
		$wpdb->delete(
			$table,
			array(
				'organization_id' => $org_id,
				'crm_provider'    => $provider,
			)
		);

		// Insert new mappings
		foreach ( $mappings as $mapping ) {
			$wpdb->insert(
				$table,
				array(
					'organization_id'     => $org_id,
					'crm_provider'        => $provider,
					'entity_type'         => sanitize_text_field( $mapping['entity_type'] ),
					'ns_field_name'       => sanitize_text_field( $mapping['ns_field_name'] ),
					'ns_field_type'       => sanitize_text_field( $mapping['ns_field_type'] ),
					'crm_field_name'      => sanitize_text_field( $mapping['crm_field_name'] ),
					'crm_field_type'      => sanitize_text_field( $mapping['crm_field_type'] ),
					'sync_direction'      => sanitize_text_field( $mapping['sync_direction'] ),
					'conflict_resolution' => sanitize_text_field( $mapping['conflict_resolution'] ),
					'is_required'         => intval( $mapping['is_required'] ),
					'is_active'           => intval( $mapping['is_active'] ),
				)
			);
		}

		wp_send_json_success( array( 'message' => 'Field mappings saved successfully' ) );
	}

	/**
	 * AJAX: Get CRM fields.
	 */
	public static function ajax_get_crm_fields() {
		check_ajax_referer( 'ns_crm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
		$entity_type = isset( $_POST['entity_type'] ) ? sanitize_text_field( $_POST['entity_type'] ) : 'contact';

		if ( ! $provider || ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing parameters' ) );
		}

		$adapter = NonprofitSuite_CRM_Manager::get_adapter( $provider, $org_id );

		if ( ! $adapter ) {
			wp_send_json_error( array( 'message' => 'Adapter not found' ) );
		}

		$fields = $adapter->get_entity_fields( $entity_type );

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'message' => $fields->get_error_message() ) );
		}

		wp_send_json_success( array( 'fields' => $fields ) );
	}
}
