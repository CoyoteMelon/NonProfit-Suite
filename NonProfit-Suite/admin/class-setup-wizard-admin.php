<?php
/**
 * Setup Wizard Admin
 *
 * Handles the setup wizard and migration admin interface.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

class NS_Setup_Wizard_Admin {
	/**
	 * Initialize the admin interface.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ns_setup_process_step', array( $this, 'ajax_process_step' ) );
		add_action( 'wp_ajax_ns_setup_skip_step', array( $this, 'ajax_skip_step' ) );
		add_action( 'wp_ajax_ns_migration_create_job', array( $this, 'ajax_create_migration_job' ) );
		add_action( 'wp_ajax_ns_migration_process_job', array( $this, 'ajax_process_migration_job' ) );
		add_action( 'wp_ajax_ns_migration_upload_csv', array( $this, 'ajax_upload_csv' ) );
	}

	/**
	 * Add menu pages.
	 */
	public function add_menu_pages() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'Setup Wizard', 'nonprofitsuite' ),
			__( 'Setup Wizard', 'nonprofitsuite' ),
			'manage_options',
			'ns-setup-wizard',
			array( $this, 'render_setup_wizard_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'Migration Tools', 'nonprofitsuite' ),
			__( 'Migration Tools', 'nonprofitsuite' ),
			'manage_options',
			'ns-migration-tools',
			array( $this, 'render_migration_tools_page' )
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'ns-setup' ) === false && strpos( $hook, 'ns-migration' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ns-setup-wizard', NS_PLUGIN_URL . 'admin/css/setup-wizard.css', array(), NS_VERSION );
		wp_enqueue_script( 'ns-setup-wizard', NS_PLUGIN_URL . 'admin/js/setup-wizard.js', array( 'jquery' ), NS_VERSION, true );

		wp_localize_script(
			'ns-setup-wizard',
			'nsSetup',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ns_setup_nonce' ),
			)
		);
	}

	/**
	 * Render setup wizard page.
	 */
	public function render_setup_wizard_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/setup-wizard.php';
	}

	/**
	 * Render migration tools page.
	 */
	public function render_migration_tools_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/migration-tools.php';
	}

	/**
	 * AJAX: Process wizard step.
	 */
	public function ajax_process_step() {
		check_ajax_referer( 'ns_setup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$organization_id = absint( $_POST['organization_id'] );
		$step_name       = sanitize_text_field( $_POST['step_name'] );
		$step_data       = $_POST['step_data'] ?? array();

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-setup-wizard-manager.php';
		$manager = NS_Setup_Wizard_Manager::get_instance();

		$result = $manager->process_step( $organization_id, $step_name, $step_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$next_step = $manager->get_current_step( $organization_id );

		wp_send_json_success( array(
			'message'   => 'Step completed successfully!',
			'next_step' => $next_step,
		) );
	}

	/**
	 * AJAX: Skip wizard step.
	 */
	public function ajax_skip_step() {
		check_ajax_referer( 'ns_setup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$organization_id = absint( $_POST['organization_id'] );
		$step_name       = sanitize_text_field( $_POST['step_name'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-setup-wizard-manager.php';
		$manager = NS_Setup_Wizard_Manager::get_instance();

		$manager->skip_step( $organization_id, $step_name );

		$next_step = $manager->get_current_step( $organization_id );

		wp_send_json_success( array(
			'message'   => 'Step skipped.',
			'next_step' => $next_step,
		) );
	}

	/**
	 * AJAX: Upload CSV file.
	 */
	public function ajax_upload_csv() {
		check_ajax_referer( 'ns_setup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		if ( ! isset( $_FILES['csv_file'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
		}

		$file = $_FILES['csv_file'];

		// Validate file type
		$file_type = wp_check_filetype( $file['name'] );
		if ( $file_type['ext'] !== 'csv' ) {
			wp_send_json_error( array( 'message' => 'Please upload a CSV file.' ) );
		}

		// Move uploaded file
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/nonprofitsuite/imports/';

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Sanitize filename and prevent path traversal
		$filename    = basename( sanitize_file_name( $file['name'] ) );
		$target_file = $target_dir . time() . '_' . $filename;

		// Verify target file is within intended directory
		$real_target_dir = realpath( $target_dir );
		$real_target_file = $real_target_dir . '/' . time() . '_' . $filename;

		if ( strpos( $real_target_file, $real_target_dir ) !== 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid file path.' ) );
		}

		if ( ! move_uploaded_file( $file['tmp_name'], $target_file ) ) {
			wp_send_json_error( array( 'message' => 'Failed to save file.' ) );
		}

		// Read CSV headers
		$handle  = fopen( $target_file, 'r' );
		$headers = fgetcsv( $handle );
		fclose( $handle );

		wp_send_json_success( array(
			'file_path' => $target_file,
			'headers'   => $headers,
		) );
	}

	/**
	 * AJAX: Create migration job.
	 */
	public function ajax_create_migration_job() {
		check_ajax_referer( 'ns_setup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-migration-manager.php';
		$manager = NS_Migration_Manager::get_instance();

		$job_data = array(
			'organization_id' => absint( $_POST['organization_id'] ),
			'job_name'        => sanitize_text_field( $_POST['job_name'] ),
			'migration_type'  => sanitize_text_field( $_POST['migration_type'] ),
			'source_system'   => sanitize_text_field( $_POST['source_system'] ),
			'source_file'     => sanitize_text_field( $_POST['source_file'] ),
			'mapping_config'  => $_POST['mapping_config'] ?? array(),
		);

		$job_id = $manager->create_job( $job_data );

		if ( is_wp_error( $job_id ) ) {
			wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'job_id'  => $job_id,
			'message' => 'Migration job created successfully!',
		) );
	}

	/**
	 * AJAX: Process migration job.
	 */
	public function ajax_process_migration_job() {
		check_ajax_referer( 'ns_setup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$job_id = absint( $_POST['job_id'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-migration-manager.php';
		$manager = NS_Migration_Manager::get_instance();

		$result = $manager->process_job( $job_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$status = $manager->get_job_status( $job_id );

		wp_send_json_success( array(
			'message' => 'Migration completed!',
			'status'  => $status,
		) );
	}
}

new NS_Setup_Wizard_Admin();
