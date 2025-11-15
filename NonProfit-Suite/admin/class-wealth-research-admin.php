<?php
/**
 * Wealth Research Admin
 *
 * Handles admin interface for wealth research features.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 * @since 1.18.0
 */

class NS_Wealth_Research_Admin {

	/**
	 * Wealth research manager
	 *
	 * @var NS_Wealth_Research_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-wealth-research-manager.php';
		$this->manager = new \NonprofitSuite\Helpers\NS_Wealth_Research_Manager();

		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ns_wealth_research_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ns_wealth_research_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ns_wealth_research_do_research', array( $this, 'ajax_do_research' ) );
		add_action( 'wp_ajax_ns_wealth_research_batch_screen', array( $this, 'ajax_batch_screen' ) );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_menu_pages() {
		// Check if feature is enabled via feature flag
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-capability-manager.php';
		if ( ! \NonprofitSuite\Helpers\NS_Capability_Manager::is_wealth_research_enabled() ) {
			return;
		}

		add_submenu_page(
			'nonprofitsuite',
			__( 'Wealth Research', 'nonprofitsuite' ),
			__( 'Wealth Research', 'nonprofitsuite' ),
			'view_wealth_research',
			'ns-wealth-research',
			array( $this, 'render_research_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'Wealth Research Settings', 'nonprofitsuite' ),
			__( 'WR Settings', 'nonprofitsuite' ),
			'configure_wealth_research',
			'ns-wealth-research-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'nonprofitsuite_page_ns-wealth-research', 'nonprofitsuite_page_ns-wealth-research-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'ns-wealth-research-admin',
			plugin_dir_url( __FILE__ ) . 'css/wealth-research-admin.css',
			array(),
			'1.18.0'
		);

		wp_enqueue_script(
			'ns-wealth-research-admin',
			plugin_dir_url( __FILE__ ) . 'js/wealth-research-admin.js',
			array( 'jquery' ),
			'1.18.0',
			true
		);

		wp_localize_script(
			'ns-wealth-research-admin',
			'nsWealthResearch',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ns_wealth_research' ),
			)
		);
	}

	/**
	 * Render research page
	 */
	public function render_research_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/wealth-research.php';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		require_once plugin_dir_path( __FILE__ ) . 'views/wealth-research-settings.php';
	}

	/**
	 * AJAX: Test provider connection
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ns_wealth_research', 'nonce' );

		if ( ! current_user_can( 'configure_wealth_research' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$provider    = sanitize_text_field( $_POST['provider'] ?? '' );
		$api_key     = sanitize_text_field( $_POST['api_key'] ?? '' );
		$api_secret  = sanitize_text_field( $_POST['api_secret'] ?? '' );

		// Create temporary adapter to test
		$config = array(
			'api_key'         => $api_key,
			'api_secret'      => $api_secret,
			'organization_id' => 1, // Test org
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
		check_ajax_referer( 'ns_wealth_research', 'nonce' );

		if ( ! current_user_can( 'configure_wealth_research' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_wealth_research_settings';

		$provider        = sanitize_text_field( $_POST['provider'] ?? '' );
		$api_key         = sanitize_text_field( $_POST['api_key'] ?? '' );
		$api_secret      = sanitize_text_field( $_POST['api_secret'] ?? '' );
		$api_endpoint    = esc_url_raw( $_POST['api_endpoint'] ?? '' );
		$monthly_limit   = intval( $_POST['monthly_limit'] ?? 0 );
		$organization_id = intval( $_POST['organization_id'] ?? 1 );

		$data = array(
			'organization_id' => $organization_id,
			'provider'        => $provider,
			'api_key'         => $api_key,
			'api_secret'      => $api_secret,
			'api_endpoint'    => $api_endpoint,
			'monthly_limit'   => $monthly_limit,
			'is_active'       => 1,
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
	 * AJAX: Perform research on contact
	 */
	public function ajax_do_research() {
		check_ajax_referer( 'ns_wealth_research', 'nonce' );

		if ( ! current_user_can( 'manage_wealth_research' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$contact_id = intval( $_POST['contact_id'] ?? 0 );
		$depth      = sanitize_text_field( $_POST['depth'] ?? 'basic' );

		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => 'Invalid contact ID' ) );
		}

		$result = $this->manager->research_contact( $contact_id, $depth );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ?? 'Research failed' ) );
		}
	}

	/**
	 * AJAX: Batch screen contacts
	 */
	public function ajax_batch_screen() {
		check_ajax_referer( 'ns_wealth_research', 'nonce' );

		if ( ! current_user_can( 'manage_wealth_research' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$contact_ids = array_map( 'intval', $_POST['contact_ids'] ?? array() );

		if ( empty( $contact_ids ) ) {
			wp_send_json_error( array( 'message' => 'No contacts selected' ) );
		}

		$result = $this->manager->batch_screen_contacts( $contact_ids );

		wp_send_json_success( $result );
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
			case 'wealthengine':
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/adapters/class-wealthengine-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_WealthEngine_Adapter( $config );

			case 'donorsearch':
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/adapters/class-donorsearch-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_DonorSearch_Adapter( $config );

			case 'blackbaud':
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/adapters/class-blackbaud-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_Blackbaud_Adapter( $config );

			default:
				return null;
		}
	}
}

// Initialize
new NS_Wealth_Research_Admin();
