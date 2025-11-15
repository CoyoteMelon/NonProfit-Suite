<?php
/**
 * Public Document Admin
 *
 * Admin interface for public document management.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Public_Document_Admin {

	/**
	 * Manager instance.
	 *
	 * @var NS_Public_Document_Manager
	 */
	private $manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-public-document-manager.php';
		$this->manager = NS_Public_Document_Manager::get_instance();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// AJAX handlers
		add_action( 'wp_ajax_ns_create_document_share', array( $this, 'ajax_create_share' ) );
		add_action( 'wp_ajax_ns_delete_document_share', array( $this, 'ajax_delete_share' ) );
		add_action( 'wp_ajax_ns_get_document_stats', array( $this, 'ajax_get_document_stats' ) );
		add_action( 'wp_ajax_ns_save_document_category', array( $this, 'ajax_save_category' ) );
		add_action( 'wp_ajax_ns_delete_document_category', array( $this, 'ajax_delete_category' ) );

		// Scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'Public Documents', 'nonprofitsuite' ),
			__( 'Public Documents', 'nonprofitsuite' ),
			'manage_options',
			'ns-public-documents',
			array( $this, 'render_public_documents_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'Document Categories', 'nonprofitsuite' ),
			__( 'Doc Categories', 'nonprofitsuite' ),
			'manage_options',
			'ns-document-categories',
			array( $this, 'render_categories_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'nonprofitsuite_page_ns-public-documents' !== $hook && 'nonprofitsuite_page_ns-document-categories' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'ns-public-documents', NS_PLUGIN_URL . 'admin/css/public-documents.css', array(), NS_VERSION );
		wp_enqueue_script( 'ns-public-documents', NS_PLUGIN_URL . 'admin/js/public-documents.js', array( 'jquery' ), NS_VERSION, true );

		wp_localize_script(
			'ns-public-documents',
			'nsPublicDocs',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'nonprofitsuite_admin' ),
			)
		);
	}

	/**
	 * Render public documents page.
	 */
	public function render_public_documents_page() {
		include NS_PLUGIN_DIR . 'admin/views/public-documents.php';
	}

	/**
	 * Render categories page.
	 */
	public function render_categories_page() {
		include NS_PLUGIN_DIR . 'admin/views/document-categories.php';
	}

	/**
	 * AJAX: Create document share.
	 */
	public function ajax_create_share() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$document_id      = absint( $_POST['document_id'] ?? 0 );
		$organization_id  = absint( $_POST['organization_id'] ?? 0 );
		$share_name       = sanitize_text_field( $_POST['share_name'] ?? '' );
		$share_type       = sanitize_text_field( $_POST['share_type'] ?? 'public' );
		$password         = $_POST['password'] ?? null;
		$expires_at       = sanitize_text_field( $_POST['expires_at'] ?? '' );
		$max_downloads    = absint( $_POST['max_downloads'] ?? 0 );
		$watermark_text   = sanitize_text_field( $_POST['watermark_text'] ?? '' );

		$permissions = array(
			'view'     => isset( $_POST['permissions']['view'] ),
			'download' => isset( $_POST['permissions']['download'] ),
			'print'    => isset( $_POST['permissions']['print'] ),
		);

		$share_id = $this->manager->create_share( array(
			'document_id'            => $document_id,
			'organization_id'        => $organization_id,
			'share_name'             => $share_name,
			'share_type'             => $share_type,
			'password'               => $password,
			'expires_at'             => $expires_at ?: null,
			'max_downloads'          => $max_downloads ?: null,
			'permissions'            => $permissions,
			'require_email'          => isset( $_POST['require_email'] ),
			'require_tos_acceptance' => isset( $_POST['require_tos'] ),
			'watermark_text'         => $watermark_text ?: null,
		) );

		if ( is_wp_error( $share_id ) ) {
			wp_send_json_error( array( 'message' => $share_id->get_error_message() ) );
		}

		$share = $this->manager->get_share_by_token(
			$this->get_share_token_by_id( $share_id )
		);

		wp_send_json_success( array(
			'share_id'  => $share_id,
			'share_url' => $this->manager->get_share_url( $share['share_token'] ),
			'message'   => __( 'Share created successfully', 'nonprofitsuite' ),
		) );
	}

	/**
	 * AJAX: Delete document share.
	 */
	public function ajax_delete_share() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$share_id = absint( $_POST['share_id'] ?? 0 );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_public_document_shares';

		$deleted = $wpdb->update(
			$table,
			array( 'is_active' => 0 ),
			array( 'id' => $share_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete share', 'nonprofitsuite' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Share deleted successfully', 'nonprofitsuite' ) ) );
	}

	/**
	 * AJAX: Get document statistics.
	 */
	public function ajax_get_document_stats() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$document_id = absint( $_POST['document_id'] ?? 0 );
		$period      = sanitize_text_field( $_POST['period'] ?? 'all' );

		$stats = $this->manager->get_document_statistics( $document_id, $period );

		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * AJAX: Save document category.
	 */
	public function ajax_save_category() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$category_id = absint( $_POST['category_id'] ?? 0 );
		$organization_id = absint( $_POST['organization_id'] ?? 0 );
		$category_name = sanitize_text_field( $_POST['category_name'] ?? '' );
		$category_slug = sanitize_text_field( $_POST['category_slug'] ?? '' );
		$category_description = sanitize_textarea_field( $_POST['category_description'] ?? '' );
		$parent_category_id = absint( $_POST['parent_category_id'] ?? 0 );
		$display_order = absint( $_POST['display_order'] ?? 0 );
		$is_public = isset( $_POST['is_public'] );
		$icon = sanitize_text_field( $_POST['icon'] ?? '' );
		$color = sanitize_hex_color( $_POST['color'] ?? '' );

		$saved_id = $this->manager->save_category( array(
			'id'                   => $category_id,
			'organization_id'      => $organization_id,
			'category_name'        => $category_name,
			'category_slug'        => $category_slug,
			'category_description' => $category_description,
			'parent_category_id'   => $parent_category_id ?: null,
			'display_order'        => $display_order,
			'is_public'            => $is_public,
			'icon'                 => $icon ?: null,
			'color'                => $color ?: null,
		) );

		if ( is_wp_error( $saved_id ) ) {
			wp_send_json_error( array( 'message' => $saved_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'category_id' => $saved_id,
			'message'     => __( 'Category saved successfully', 'nonprofitsuite' ),
		) );
	}

	/**
	 * AJAX: Delete document category.
	 */
	public function ajax_delete_category() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$category_id = absint( $_POST['category_id'] ?? 0 );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_document_categories';

		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $category_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete category', 'nonprofitsuite' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Category deleted successfully', 'nonprofitsuite' ) ) );
	}

	/**
	 * Get share token by share ID.
	 *
	 * @param int $share_id Share ID.
	 * @return string|null Share token or null if not found.
	 */
	private function get_share_token_by_id( $share_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_public_document_shares';

		return $wpdb->get_var(
			$wpdb->prepare( "SELECT share_token FROM {$table} WHERE id = %d", $share_id )
		);
	}
}

// Initialize
new NS_Public_Document_Admin();
