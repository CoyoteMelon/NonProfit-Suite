<?php
/**
 * AI & Automation Admin
 *
 * Handles the admin interface for AI provider settings,
 * conversations, and automation rules.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

class NS_AI_Admin {
	/**
	 * Initialize the admin interface.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ns_ai_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ns_ai_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ns_ai_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_ns_ai_create_conversation', array( $this, 'ajax_create_conversation' ) );
		add_action( 'wp_ajax_ns_ai_delete_conversation', array( $this, 'ajax_delete_conversation' ) );
		add_action( 'wp_ajax_ns_ai_save_automation_rule', array( $this, 'ajax_save_automation_rule' ) );
		add_action( 'wp_ajax_ns_ai_delete_automation_rule', array( $this, 'ajax_delete_automation_rule' ) );
		add_action( 'wp_ajax_ns_ai_toggle_automation_rule', array( $this, 'ajax_toggle_automation_rule' ) );
	}

	/**
	 * Add menu pages.
	 */
	public function add_menu_pages() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'AI & Automation', 'nonprofitsuite' ),
			__( 'AI & Automation', 'nonprofitsuite' ),
			'manage_options',
			'ns-ai',
			array( $this, 'render_conversations_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'Automation Rules', 'nonprofitsuite' ),
			__( 'Automation Rules', 'nonprofitsuite' ),
			'manage_options',
			'ns-ai-automation',
			array( $this, 'render_automation_rules_page' )
		);

		add_submenu_page(
			'nonprofitsuite',
			__( 'AI Settings', 'nonprofitsuite' ),
			__( 'AI Settings', 'nonprofitsuite' ),
			'manage_options',
			'ns-ai-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'ns-ai' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ns-ai-admin', NS_PLUGIN_URL . 'admin/css/ai-admin.css', array(), NS_VERSION );
		wp_enqueue_script( 'ns-ai-admin', NS_PLUGIN_URL . 'admin/js/ai-admin.js', array( 'jquery' ), NS_VERSION, true );

		wp_localize_script(
			'ns-ai-admin',
			'nsAI',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ns_ai_nonce' ),
			)
		);
	}

	/**
	 * Render conversations page.
	 */
	public function render_conversations_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/ai-conversations.php';
	}

	/**
	 * Render automation rules page.
	 */
	public function render_automation_rules_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/ai-automation-rules.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		require_once NS_PLUGIN_DIR . 'admin/views/ai-settings.php';
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$provider        = sanitize_text_field( $_POST['provider'] );
		$api_key         = sanitize_text_field( $_POST['api_key'] );
		$model           = sanitize_text_field( $_POST['model'] );
		$organization_id = absint( $_POST['organization_id'] );

		require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/interface-ai-adapter.php';

		switch ( $provider ) {
			case 'openai':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-openai-adapter.php';
				$adapter = new NS_OpenAI_Adapter( $api_key, $model );
				break;

			case 'anthropic':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-anthropic-adapter.php';
				$adapter = new NS_Anthropic_Adapter( $api_key, $model );
				break;

			case 'google':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-google-ai-adapter.php';
				$adapter = new NS_Google_AI_Adapter( $api_key, $model );
				break;

			default:
				wp_send_json_error( array( 'message' => 'Invalid provider.' ) );
				return;
		}

		$result = $adapter->test_connection();

		if ( $result === true ) {
			wp_send_json_success( array( 'message' => 'Connection successful!' ) );
		} else {
			wp_send_json_error( array( 'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Connection failed.' ) );
		}
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		global $wpdb;

		$organization_id = absint( $_POST['organization_id'] );
		$provider        = sanitize_text_field( $_POST['provider'] );
		$api_key         = sanitize_text_field( $_POST['api_key'] );
		$model_name      = sanitize_text_field( $_POST['model_name'] );
		$is_active       = isset( $_POST['is_active'] ) ? 1 : 0;
		$monthly_budget  = floatval( $_POST['monthly_budget'] );

		$table = $wpdb->prefix . 'ns_ai_settings';

		// Check if settings exist
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE organization_id = %d AND provider = %s",
				$organization_id,
				$provider
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'api_key'        => $api_key,
					'model_name'     => $model_name,
					'is_active'      => $is_active,
					'monthly_budget' => $monthly_budget,
					'updated_at'     => current_time( 'mysql' ),
				),
				array(
					'id' => $existing->id,
				),
				array( '%s', '%s', '%d', '%f', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'organization_id' => $organization_id,
					'provider'        => $provider,
					'api_key'         => $api_key,
					'model_name'      => $model_name,
					'is_active'       => $is_active,
					'monthly_budget'  => $monthly_budget,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%f' )
			);
		}

		wp_send_json_success( array( 'message' => 'Settings saved successfully!' ) );
	}

	/**
	 * AJAX: Send message.
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$conversation_id = absint( $_POST['conversation_id'] );
		$message         = sanitize_textarea_field( $_POST['message'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-ai-manager.php';
		$ai_manager = NS_AI_Manager::get_instance();

		$response = $ai_manager->chat( $conversation_id, $message );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		wp_send_json_success( array( 'response' => $response ) );
	}

	/**
	 * AJAX: Create conversation.
	 */
	public function ajax_create_conversation() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$organization_id = absint( $_POST['organization_id'] );
		$provider        = sanitize_text_field( $_POST['provider'] );
		$model           = sanitize_text_field( $_POST['model'] );
		$title           = sanitize_text_field( $_POST['title'] );

		require_once NS_PLUGIN_DIR . 'includes/helpers/class-ai-manager.php';
		$ai_manager = NS_AI_Manager::get_instance();

		$conversation_id = $ai_manager->create_conversation(
			array(
				'organization_id' => $organization_id,
				'provider'        => $provider,
				'model'           => $model,
				'title'           => $title,
			)
		);

		if ( is_wp_error( $conversation_id ) ) {
			wp_send_json_error( array( 'message' => $conversation_id->get_error_message() ) );
		}

		wp_send_json_success( array( 'conversation_id' => $conversation_id ) );
	}

	/**
	 * AJAX: Delete conversation.
	 */
	public function ajax_delete_conversation() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		global $wpdb;

		$conversation_id = absint( $_POST['conversation_id'] );

		// Delete messages
		$wpdb->delete(
			$wpdb->prefix . 'ns_ai_messages',
			array( 'conversation_id' => $conversation_id ),
			array( '%d' )
		);

		// Delete conversation
		$wpdb->delete(
			$wpdb->prefix . 'ns_ai_conversations',
			array( 'id' => $conversation_id ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => 'Conversation deleted successfully!' ) );
	}

	/**
	 * AJAX: Save automation rule.
	 */
	public function ajax_save_automation_rule() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		global $wpdb;

		$rule_id         = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$organization_id = absint( $_POST['organization_id'] );
		$rule_name       = sanitize_text_field( $_POST['rule_name'] );
		$trigger_type    = sanitize_text_field( $_POST['trigger_type'] );
		$trigger_config  = wp_json_encode( $_POST['trigger_config'] ?? array() );
		$provider        = sanitize_text_field( $_POST['provider'] );
		$ai_action       = sanitize_text_field( $_POST['ai_action'] );
		$ai_prompt       = sanitize_textarea_field( $_POST['ai_prompt'] );
		$action_type     = sanitize_text_field( $_POST['action_type'] );
		$action_config   = wp_json_encode( $_POST['action_config'] ?? array() );
		$is_active       = isset( $_POST['is_active'] ) ? 1 : 0;

		$table = $wpdb->prefix . 'ns_ai_automation_rules';

		$data = array(
			'organization_id' => $organization_id,
			'rule_name'       => $rule_name,
			'trigger_type'    => $trigger_type,
			'trigger_config'  => $trigger_config,
			'provider'        => $provider,
			'ai_action'       => $ai_action,
			'ai_prompt'       => $ai_prompt,
			'action_type'     => $action_type,
			'action_config'   => $action_config,
			'is_active'       => $is_active,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( $rule_id > 0 ) {
			$data['updated_at'] = current_time( 'mysql' );
			$format[]           = '%s';

			$wpdb->update( $table, $data, array( 'id' => $rule_id ), $format, array( '%d' ) );
		} else {
			$wpdb->insert( $table, $data, $format );
			$rule_id = $wpdb->insert_id;
		}

		wp_send_json_success(
			array(
				'message' => 'Automation rule saved successfully!',
				'rule_id' => $rule_id,
			)
		);
	}

	/**
	 * AJAX: Delete automation rule.
	 */
	public function ajax_delete_automation_rule() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		global $wpdb;

		$rule_id = absint( $_POST['rule_id'] );

		$wpdb->delete(
			$wpdb->prefix . 'ns_ai_automation_rules',
			array( 'id' => $rule_id ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => 'Automation rule deleted successfully!' ) );
	}

	/**
	 * AJAX: Toggle automation rule.
	 */
	public function ajax_toggle_automation_rule() {
		check_ajax_referer( 'ns_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		global $wpdb;

		$rule_id   = absint( $_POST['rule_id'] );
		$is_active = isset( $_POST['is_active'] ) ? 1 : 0;

		$wpdb->update(
			$wpdb->prefix . 'ns_ai_automation_rules',
			array( 'is_active' => $is_active ),
			array( 'id' => $rule_id ),
			array( '%d' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => 'Automation rule updated successfully!' ) );
	}
}

new NS_AI_Admin();
