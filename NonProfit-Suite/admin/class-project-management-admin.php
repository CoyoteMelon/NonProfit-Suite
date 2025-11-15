<?php
/**
 * Project Management Admin
 *
 * Handles admin interface for project management.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

class NS_Project_Management_Admin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ns_test_pm_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ns_sync_projects', array( $this, 'ajax_sync_projects' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Projects',
			'Projects',
			'manage_options',
			'ns-projects',
			array( $this, 'render_projects_page' ),
			'dashicons-portfolio',
			31
		);

		add_submenu_page(
			'ns-projects',
			'All Projects',
			'All Projects',
			'manage_options',
			'ns-projects',
			array( $this, 'render_projects_page' )
		);

		add_submenu_page(
			'ns-projects',
			'Tasks',
			'Tasks',
			'manage_options',
			'ns-tasks',
			array( $this, 'render_tasks_page' )
		);

		add_submenu_page(
			'ns-projects',
			'PM Settings',
			'PM Settings',
			'manage_options',
			'ns-pm-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'ns-project' ) === false && strpos( $hook, 'ns-task' ) === false && strpos( $hook, 'ns-pm' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ns-pm-admin', plugins_url( 'css/pm-admin.css', __FILE__ ), array(), '1.0.0' );
		wp_enqueue_script( 'ns-pm-admin', plugins_url( 'js/pm-admin.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

		wp_localize_script(
			'ns-pm-admin',
			'nsPMAdmin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ns_pm_admin' ),
			)
		);
	}

	/**
	 * Render projects page.
	 */
	public function render_projects_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/projects-list.php';
	}

	/**
	 * Render tasks page.
	 */
	public function render_tasks_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/tasks-list.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		// Handle settings save
		if ( isset( $_POST['ns_pm_settings_nonce'] ) && wp_verify_nonce( $_POST['ns_pm_settings_nonce'], 'ns_pm_settings' ) ) {
			$this->save_settings( $_POST );
			echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
		}

		require_once NS_PLUGIN_DIR . 'admin/views/pm-settings.php';
	}

	/**
	 * Save PM settings.
	 */
	private function save_settings( $post_data ) {
		global $wpdb;

		$organization_id = 1; // TODO: Get from current user context
		$table           = $wpdb->prefix . 'ns_project_management_settings';

		$providers = array( 'asana', 'trello', 'monday' );

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
				case 'asana':
					$settings['oauth_token'] = $post_data[ $provider . '_access_token' ] ?? '';
					$settings['workspace_id'] = $post_data[ $provider . '_workspace_id' ] ?? '';
					break;

				case 'trello':
					$settings['api_key']    = $post_data[ $provider . '_api_key' ] ?? '';
					$settings['api_secret'] = $post_data[ $provider . '_api_token' ] ?? '';
					break;

				case 'monday':
					$settings['api_key'] = $post_data[ $provider . '_api_token' ] ?? '';
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
	 * AJAX: Test PM provider connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ns_pm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$provider        = $_POST['provider'] ?? '';
		$organization_id = 1; // TODO: Get from context

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-project-manager.php';
		$manager = NS_Project_Manager::get_instance();
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
	 * AJAX: Sync projects from external provider.
	 */
	public function ajax_sync_projects() {
		check_ajax_referer( 'ns_pm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$provider        = $_POST['provider'] ?? '';
		$organization_id = 1; // TODO: Get from context

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-project-manager.php';
		$manager      = NS_Project_Manager::get_instance();
		$synced_count = $manager->sync_projects( $organization_id, $provider );

		wp_send_json_success( sprintf( 'Synced %d projects.', $synced_count ) );
	}
}

new NS_Project_Management_Admin();
