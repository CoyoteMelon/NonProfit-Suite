<?php
/**
 * Marketing Admin Controller
 *
 * Handles admin pages and AJAX operations for marketing.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Marketing_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'wp_ajax_ns_save_marketing_settings', array( __CLASS__, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ns_create_campaign', array( __CLASS__, 'ajax_create_campaign' ) );
		add_action( 'wp_ajax_ns_send_campaign', array( __CLASS__, 'ajax_send_campaign' ) );
		add_action( 'wp_ajax_ns_create_segment', array( __CLASS__, 'ajax_create_segment' ) );
	}

	public static function add_menu_pages() {
		add_submenu_page(
			'nonprofitsuite',
			'Marketing',
			'Marketing',
			'manage_options',
			'ns-marketing',
			array( __CLASS__, 'render_campaigns_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			'Marketing Settings',
			null,
			'manage_options',
			'ns-marketing-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_campaigns_page() {
		$org_id = isset( $_GET['organization_id'] ) ? intval( $_GET['organization_id'] ) : 0;

		if ( ! $org_id ) {
			echo '<div class="notice notice-error"><p>Please select an organization.</p></div>';
			return;
		}

		global $wpdb;
		$campaigns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_marketing_campaigns WHERE organization_id = %d ORDER BY created_at DESC",
				$org_id
			),
			ARRAY_A
		);

		include plugin_dir_path( __FILE__ ) . 'views/marketing-campaigns.php';
	}

	public static function render_settings_page() {
		$org_id = isset( $_GET['organization_id'] ) ? intval( $_GET['organization_id'] ) : 0;

		if ( ! $org_id ) {
			echo '<div class="notice notice-error"><p>Please select an organization.</p></div>';
			return;
		}

		global $wpdb;
		$settings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_marketing_settings WHERE organization_id = %d",
				$org_id
			),
			ARRAY_A
		);

		include plugin_dir_path( __FILE__ ) . 'views/marketing-settings.php';
	}

	public static function ajax_save_settings() {
		check_ajax_referer( 'ns_marketing_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( $_POST['platform'] ) : '';
		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
		$settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();

		if ( ! $platform || ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing parameters' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_marketing_settings';

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE organization_id = %d AND platform = %s",
				$org_id,
				$platform
			)
		);

		$data = array(
			'organization_id' => $org_id,
			'platform'        => $platform,
			'platform_mode'   => isset( $settings['platform_mode'] ) ? sanitize_text_field( $settings['platform_mode'] ) : 'builtin',
			'api_key'         => isset( $settings['api_key'] ) ? sanitize_text_field( $settings['api_key'] ) : '',
			'server_prefix'   => isset( $settings['server_prefix'] ) ? sanitize_text_field( $settings['server_prefix'] ) : '',
			'from_email'      => isset( $settings['from_email'] ) ? sanitize_email( $settings['from_email'] ) : '',
			'from_name'       => isset( $settings['from_name'] ) ? sanitize_text_field( $settings['from_name'] ) : '',
			'reply_to_email'  => isset( $settings['reply_to_email'] ) ? sanitize_email( $settings['reply_to_email'] ) : '',
			'is_active'       => isset( $settings['is_active'] ) ? intval( $settings['is_active'] ) : 1,
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing ) );
		} else {
			$wpdb->insert( $table, $data );
		}

		wp_send_json_success( array( 'message' => 'Settings saved successfully' ) );
	}

	public static function ajax_create_campaign() {
		check_ajax_referer( 'ns_marketing_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
		$campaign_data = isset( $_POST['campaign'] ) ? $_POST['campaign'] : array();

		if ( ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing organization ID' ) );
		}

		$campaign_id = NonprofitSuite_Marketing_Manager::create_campaign( $org_id, $campaign_data );

		wp_send_json_success( array(
			'message'     => 'Campaign created successfully',
			'campaign_id' => $campaign_id,
		) );
	}

	public static function ajax_send_campaign() {
		check_ajax_referer( 'ns_marketing_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;

		if ( ! $campaign_id ) {
			wp_send_json_error( array( 'message' => 'Missing campaign ID' ) );
		}

		$result = NonprofitSuite_Marketing_Manager::send_campaign( $campaign_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Campaign sent successfully' ) );
	}

	public static function ajax_create_segment() {
		check_ajax_referer( 'ns_marketing_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
		$segment_data = isset( $_POST['segment'] ) ? $_POST['segment'] : array();

		if ( ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing organization ID' ) );
		}

		$segment_id = NonprofitSuite_Marketing_Manager::create_segment( $org_id, $segment_data );

		wp_send_json_success( array(
			'message'    => 'Segment created successfully',
			'segment_id' => $segment_id,
		) );
	}
}
