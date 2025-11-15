<?php
/**
 * Forms Admin
 *
 * Handles admin interface for forms and surveys.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

class NS_Forms_Admin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ns_test_form_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ns_sync_form_submissions', array( $this, 'ajax_sync_submissions' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Forms & Surveys',
			'Forms & Surveys',
			'manage_options',
			'ns-forms',
			array( $this, 'render_forms_page' ),
			'dashicons-feedback',
			30
		);

		add_submenu_page(
			'ns-forms',
			'All Forms',
			'All Forms',
			'manage_options',
			'ns-forms',
			array( $this, 'render_forms_page' )
		);

		add_submenu_page(
			'ns-forms',
			'Submissions',
			'Submissions',
			'manage_options',
			'ns-form-submissions',
			array( $this, 'render_submissions_page' )
		);

		add_submenu_page(
			'ns-forms',
			'Settings',
			'Settings',
			'manage_options',
			'ns-forms-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'ns-form' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ns-forms-admin', plugins_url( 'css/forms-admin.css', __FILE__ ), array(), '1.0.0' );
		wp_enqueue_script( 'ns-forms-admin', plugins_url( 'js/forms-admin.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

		wp_localize_script(
			'ns-forms-admin',
			'nsFormsAdmin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ns_forms_admin' ),
			)
		);
	}

	/**
	 * Render forms page.
	 */
	public function render_forms_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/forms-list.php';
	}

	/**
	 * Render submissions page.
	 */
	public function render_submissions_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/form-submissions.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		// Handle settings save
		if ( isset( $_POST['ns_forms_settings_nonce'] ) && wp_verify_nonce( $_POST['ns_forms_settings_nonce'], 'ns_forms_settings' ) ) {
			$this->save_settings( $_POST );
			echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
		}

		require_once NS_PLUGIN_DIR . 'admin/views/forms-settings.php';
	}

	/**
	 * Save forms settings.
	 */
	private function save_settings( $post_data ) {
		global $wpdb;

		$organization_id = 1; // TODO: Get from current user context
		$table           = $wpdb->prefix . 'ns_forms_settings';

		$providers = array( 'google_forms', 'typeform', 'jotform' );

		foreach ( $providers as $provider ) {
			if ( ! isset( $post_data[ $provider . '_enabled' ] ) ) {
				continue;
			}

			$settings = array(
				'organization_id' => $organization_id,
				'provider'        => $provider,
				'is_active'       => 1,
			);

			// Provider-specific settings
			switch ( $provider ) {
				case 'google_forms':
					$settings['oauth_token']         = $post_data[ $provider . '_access_token' ] ?? '';
					$settings['oauth_refresh_token'] = $post_data[ $provider . '_refresh_token' ] ?? '';
					break;

				case 'typeform':
					$settings['api_key']        = $post_data[ $provider . '_api_key' ] ?? '';
					$settings['webhook_secret'] = $post_data[ $provider . '_webhook_secret' ] ?? '';
					break;

				case 'jotform':
					$settings['api_key'] = $post_data[ $provider . '_api_key' ] ?? '';
					break;
			}

			// Check if settings exist
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE organization_id = %d AND provider = %s",
					$organization_id,
					$provider
				)
			);

			if ( $exists ) {
				$wpdb->update(
					$table,
					$settings,
					array(
						'organization_id' => $organization_id,
						'provider'        => $provider,
					)
				);
			} else {
				$wpdb->insert( $table, $settings );
			}
		}
	}

	/**
	 * AJAX: Test form provider connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ns_forms_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$provider        = $_POST['provider'] ?? '';
		$organization_id = 1; // TODO: Get from context

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-forms-manager.php';
		$manager = NS_Forms_Manager::get_instance();
		$adapter = $manager->get_adapter( $provider, $organization_id );

		if ( is_wp_error( $adapter ) ) {
			wp_send_json_error( $adapter->get_error_message() );
		}

		$result = $adapter->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		if ( $result ) {
			wp_send_json_success( 'Connection successful!' );
		} else {
			wp_send_json_error( 'Connection failed.' );
		}
	}

	/**
	 * AJAX: Sync form submissions from external provider.
	 */
	public function ajax_sync_submissions() {
		check_ajax_referer( 'ns_forms_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$form_id = intval( $_POST['form_id'] ?? 0 );

		if ( ! $form_id ) {
			wp_send_json_error( 'Invalid form ID.' );
		}

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-forms-manager.php';
		$manager      = NS_Forms_Manager::get_instance();
		$synced_count = $manager->sync_external_submissions( $form_id );

		wp_send_json_success( sprintf( 'Synced %d new submissions.', $synced_count ) );
	}
}

new NS_Forms_Admin();
