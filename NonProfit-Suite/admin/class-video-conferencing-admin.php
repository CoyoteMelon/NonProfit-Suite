<?php
/**
 * Video Conferencing Admin Controller
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Video_Conferencing_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'wp_ajax_ns_create_video_meeting', array( __CLASS__, 'ajax_create_meeting' ) );
		add_action( 'wp_ajax_ns_delete_video_meeting', array( __CLASS__, 'ajax_delete_meeting' ) );
	}

	public static function add_menu_pages() {
		add_submenu_page(
			'nonprofitsuite',
			'Video Conferencing',
			'Video Meetings',
			'manage_options',
			'ns-video-conferencing',
			array( __CLASS__, 'render_meetings_page' )
		);
	}

	public static function render_meetings_page() {
		$org_id = isset( $_GET['organization_id'] ) ? intval( $_GET['organization_id'] ) : 0;

		if ( ! $org_id ) {
			echo '<div class="notice notice-error"><p>Please select an organization.</p></div>';
			return;
		}

		$meetings = NonprofitSuite_Video_Conferencing_Manager::list_meetings( $org_id );

		include plugin_dir_path( __FILE__ ) . 'views/video-conferencing-meetings.php';
	}

	public static function ajax_create_meeting() {
		check_ajax_referer( 'ns_video_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$org_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : 'zoom';
		$meeting_data = isset( $_POST['meeting'] ) ? $_POST['meeting'] : array();

		if ( ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Missing organization ID' ) );
		}

		$meeting_id = NonprofitSuite_Video_Conferencing_Manager::create_meeting( $org_id, $provider, $meeting_data );

		if ( is_wp_error( $meeting_id ) ) {
			wp_send_json_error( array( 'message' => $meeting_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => 'Meeting created successfully',
			'meeting_id' => $meeting_id,
		) );
	}

	public static function ajax_delete_meeting() {
		check_ajax_referer( 'ns_video_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$meeting_id = isset( $_POST['meeting_id'] ) ? intval( $_POST['meeting_id'] ) : 0;

		if ( ! $meeting_id ) {
			wp_send_json_error( array( 'message' => 'Missing meeting ID' ) );
		}

		$result = NonprofitSuite_Video_Conferencing_Manager::delete_meeting( $meeting_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Meeting cancelled successfully' ) );
	}
}
