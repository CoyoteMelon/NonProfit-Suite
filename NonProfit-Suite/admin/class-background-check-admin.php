<?php
/**
 * Background Check Admin
 *
 * Handles admin interface for FCRA-compliant background check features.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 * @since 1.18.0
 */

class NS_Background_Check_Admin {

	/**
	 * Background check manager
	 *
	 * @var NS_Background_Check_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-background-check-manager.php';
		$this->manager = new \NonprofitSuite\Helpers\NS_Background_Check_Manager();

		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_ns_background_check_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ns_background_check_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ns_background_check_request', array( $this, 'ajax_request_check' ) );
		add_action( 'wp_ajax_ns_background_check_send_invitation', array( $this, 'ajax_send_invitation' ) );
		add_action( 'wp_ajax_ns_background_check_approve', array( $this, 'ajax_approve' ) );
		add_action( 'wp_ajax_ns_background_check_adverse_action', array( $this, 'ajax_adverse_action' ) );
		add_action( 'wp_ajax_ns_background_check_get_status', array( $this, 'ajax_get_status' ) );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_menu_pages() {
		// Check if feature is enabled via feature flag
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-capability-manager.php';
		if ( ! \NonprofitSuite\Helpers\NS_Capability_Manager::is_background_checks_enabled() ) {
			return;
		}

		add_submenu_page(
			'nonprofitsuite',
			__( 'Background Checks', 'nonprofitsuite' ),
			__( 'Background Checks', 'nonprofitsuite' ),
			'view_background_checks',
			'ns-background-checks',
			array( $this, 'render_checks_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'Background Check Settings', 'nonprofitsuite' ),
			__( 'BC Settings', 'nonprofitsuite' ),
			'configure_background_checks',
			'ns-background-check-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'nonprofitsuite_page_ns-background-checks', 'nonprofitsuite_page_ns-background-check-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'ns-background-check-admin',
			plugin_dir_url( __FILE__ ) . 'css/background-check-admin.css',
			array(),
			'1.18.0'
		);

		wp_enqueue_script(
			'ns-background-check-admin',
			plugin_dir_url( __FILE__ ) . 'js/background-check-admin.js',
			array( 'jquery' ),
			'1.18.0',
			true
		);

		wp_localize_script(
			'ns-background-check-admin',
			'nsBackgroundCheck',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ns_background_check' ),
			)
		);
	}

	/**
	 * Render checks page
	 */
	public function render_checks_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/background-checks.php';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/background-check-settings.php';
	}

	/**
	 * AJAX: Test provider connection
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'configure_background_checks' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$provider   = sanitize_text_field( $_POST['provider'] ?? '' );
		$api_key    = sanitize_text_field( $_POST['api_key'] ?? '' );
		$api_secret = sanitize_text_field( $_POST['api_secret'] ?? '' );

		// Create temporary adapter to test
		$config = array(
			'api_key'         => $api_key,
			'api_secret'      => $api_secret,
			'organization_id' => 1,
		);

		$adapter = $this->get_test_adapter( $provider, $config );

		if ( ! $adapter ) {
			wp_send_json_error( array( 'message' => 'Invalid provider' ) );
		}

		$validation = $adapter->validate_configuration();

		if ( $validation['valid'] ) {
			wp_send_json_success( array( 'message' => 'Connection successful!' ) );
		} else {
			wp_send_json_error( array( 'message' => $validation['error_message'] ) );
		}
	}

	/**
	 * AJAX: Save provider settings
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'configure_background_checks' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_background_check_settings';

		$provider                 = sanitize_text_field( $_POST['provider'] ?? '' );
		$api_key                  = sanitize_text_field( $_POST['api_key'] ?? '' );
		$api_secret               = sanitize_text_field( $_POST['api_secret'] ?? '' );
		$webhook_secret           = sanitize_text_field( $_POST['webhook_secret'] ?? '' );
		$default_volunteer_pkg    = sanitize_text_field( $_POST['default_volunteer_package'] ?? 'basic' );
		$default_staff_pkg        = sanitize_text_field( $_POST['default_staff_package'] ?? 'standard' );
		$default_board_pkg        = sanitize_text_field( $_POST['default_board_package'] ?? 'premium' );
		$organization_id          = intval( $_POST['organization_id'] ?? 1 );

		$data = array(
			'organization_id'          => $organization_id,
			'provider'                 => $provider,
			'api_key'                  => $api_key,
			'api_secret'               => $api_secret,
			'webhook_secret'           => $webhook_secret,
			'default_volunteer_package' => $default_volunteer_pkg,
			'default_staff_package'    => $default_staff_pkg,
			'default_board_package'    => $default_board_pkg,
			'is_active'                => 1,
		);

		// Check if settings exist
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE organization_id = %d AND provider = %s",
				$organization_id,
				$provider
			)
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing ) );
		} else {
			$wpdb->insert( $table, $data );
		}

		wp_send_json_success( array( 'message' => 'Settings saved successfully!' ) );
	}

	/**
	 * AJAX: Request background check
	 */
	public function ajax_request_check() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'manage_background_checks' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$contact_id = intval( $_POST['contact_id'] ?? 0 );
		$check_type = sanitize_text_field( $_POST['check_type'] ?? 'volunteer' );
		$package    = sanitize_text_field( $_POST['package'] ?? 'basic' );

		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => 'Invalid contact ID' ) );
		}

		$result = $this->manager->request_check( $contact_id, $check_type, $package );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ?? 'Request failed' ) );
		}
	}

	/**
	 * AJAX: Send consent invitation
	 */
	public function ajax_send_invitation() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'manage_background_checks' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$request_id = intval( $_POST['request_id'] ?? 0 );

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request ID' ) );
		}

		$result = $this->manager->send_consent_invitation( $request_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ?? 'Failed to send invitation' ) );
		}
	}

	/**
	 * AJAX: Approve candidate
	 */
	public function ajax_approve() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'manage_background_checks' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$request_id = intval( $_POST['request_id'] ?? 0 );
		$notes      = sanitize_textarea_field( $_POST['notes'] ?? '' );

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request ID' ) );
		}

		$result = $this->manager->approve_candidate( $request_id, $notes );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Candidate approved successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to approve candidate' ) );
		}
	}

	/**
	 * AJAX: Initiate adverse action
	 */
	public function ajax_adverse_action() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'manage_background_checks' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$request_id  = intval( $_POST['request_id'] ?? 0 );
		$reason      = sanitize_textarea_field( $_POST['reason'] ?? '' );
		$pre_adverse = ! empty( $_POST['pre_adverse'] );

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request ID' ) );
		}

		$result = $this->manager->initiate_adverse_action(
			$request_id,
			array(
				'reason'      => $reason,
				'pre_adverse' => $pre_adverse,
			)
		);

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ?? 'Failed to initiate adverse action' ) );
		}
	}

	/**
	 * AJAX: Get check status
	 */
	public function ajax_get_status() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'view_background_checks' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$request_id = intval( $_POST['request_id'] ?? 0 );

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request ID' ) );
		}

		$result = $this->manager->get_check_status( $request_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ?? 'Failed to get status' ) );
		}
	}

	/**
	 * Get test adapter instance
	 *
	 * @param string $provider Provider name
	 * @param array  $config   Configuration
	 * @return object|null Adapter instance
	 */
	private function get_test_adapter( $provider, $config ) {
		switch ( $provider ) {
			case 'checkr':
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/adapters/class-checkr-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_Checkr_Adapter( $config );

			default:
				return null;
		}
	}
}

// Initialize
new NS_Background_Check_Admin();
