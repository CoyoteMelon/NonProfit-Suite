<?php
/**
 * AI Manager
 *
 * Central coordinator for AI operations across different providers.
 * Manages conversations, automation rules, and adapter coordination.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

class NS_AI_Manager {
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Hook into WordPress events for automation
		add_action( 'ns_form_submitted', array( $this, 'handle_form_submission_automation' ), 10, 3 );
		add_action( 'ns_task_created', array( $this, 'handle_task_creation_automation' ), 10, 2 );
	}

	/**
	 * Get AI adapter for a specific provider.
	 *
	 * @param string $provider Provider name (openai, anthropic, google).
	 * @param int    $organization_id Organization ID.
	 * @return NS_AI_Adapter|WP_Error Adapter instance or error.
	 */
	public function get_adapter( $provider, $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_ai_settings';
		$settings = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND provider = %s AND is_active = 1",
				$organization_id,
				$provider
			),
			ARRAY_A
		);

		if ( ! $settings ) {
			return new WP_Error( 'no_settings', 'No active settings found for this provider.' );
		}

		require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/interface-ai-adapter.php';

		switch ( $provider ) {
			case 'openai':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-openai-adapter.php';
				return new NS_OpenAI_Adapter(
					$settings['api_key'],
					$settings['model_name'] ?? 'gpt-4'
				);

			case 'anthropic':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-anthropic-adapter.php';
				return new NS_Anthropic_Adapter(
					$settings['api_key'],
					$settings['model_name'] ?? 'claude-3-sonnet-20240229'
				);

			case 'google':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-google-ai-adapter.php';
				return new NS_Google_AI_Adapter(
					$settings['api_key'],
					$settings['model_name'] ?? 'gemini-pro'
				);

			default:
				return new WP_Error( 'unsupported_provider', 'Unsupported AI provider.' );
		}
	}

	/**
	 * Create a new conversation.
	 *
	 * @param array $conversation_data Conversation data.
	 * @return int|WP_Error Conversation ID or error.
	 */
	public function create_conversation( $conversation_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_ai_conversations';
		$wpdb->insert(
			$table,
			array(
				'organization_id'     => $conversation_data['organization_id'],
				'user_id'             => $conversation_data['user_id'] ?? get_current_user_id(),
				'conversation_title'  => $conversation_data['title'] ?? null,
				'provider'            => $conversation_data['provider'],
				'model'               => $conversation_data['model'],
				'context_type'        => $conversation_data['context_type'] ?? null,
				'context_id'          => $conversation_data['context_id'] ?? null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Add message to conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $role Message role (user/assistant/system).
	 * @param string $content Message content.
	 * @param array  $metadata Additional metadata.
	 * @return int|WP_Error Message ID or error.
	 */
	public function add_message( $conversation_id, $role, $content, $metadata = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_ai_messages';
		$wpdb->insert(
			$table,
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
				'tokens'          => $metadata['tokens'] ?? null,
				'cost'            => $metadata['cost'] ?? null,
			),
			array( '%d', '%s', '%s', '%d', '%f' )
		);

		$message_id = $wpdb->insert_id;

		// Update conversation totals
		$this->update_conversation_totals( $conversation_id, $metadata );

		return $message_id;
	}

	/**
	 * Update conversation totals.
	 */
	private function update_conversation_totals( $conversation_id, $metadata ) {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'ns_ai_conversations';

		if ( isset( $metadata['tokens'] ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$conversations_table} 
					SET total_messages = total_messages + 1,
						total_tokens = total_tokens + %d,
						total_cost = total_cost + %f
					WHERE id = %d",
					$metadata['tokens'],
					$metadata['cost'] ?? 0,
					$conversation_id
				)
			);
		}

		// Update monthly spend
		if ( isset( $metadata['cost'] ) && $metadata['cost'] > 0 ) {
			$conversation = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT organization_id, provider FROM {$conversations_table} WHERE id = %d",
					$conversation_id
				),
				ARRAY_A
			);

			if ( $conversation ) {
				$settings_table = $wpdb->prefix . 'ns_ai_settings';
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$settings_table} 
						SET current_month_spend = current_month_spend + %f
						WHERE organization_id = %d AND provider = %s",
						$metadata['cost'],
						$conversation['organization_id'],
						$conversation['provider']
					)
				);
			}
		}
	}

	/**
	 * Chat with AI (send message and get response).
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $message User message.
	 * @return string|WP_Error AI response or error.
	 */
	public function chat( $conversation_id, $message ) {
		global $wpdb;

		// Get conversation details
		$conversations_table = $wpdb->prefix . 'ns_ai_conversations';
		$conversation        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$conversations_table} WHERE id = %d",
				$conversation_id
			),
			ARRAY_A
		);

		if ( ! $conversation ) {
			return new WP_Error( 'conversation_not_found', 'Conversation not found.' );
		}

		// Get conversation history
		$messages_table = $wpdb->prefix . 'ns_ai_messages';
		$history        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM {$messages_table} WHERE conversation_id = %d ORDER BY created_at ASC",
				$conversation_id
			),
			ARRAY_A
		);

		// Add current message to history
		$history[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Save user message
		$this->add_message( $conversation_id, 'user', $message );

		// Get adapter
		$adapter = $this->get_adapter( $conversation['provider'], $conversation['organization_id'] );

		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		// Get AI response
		$result = $adapter->chat_completion( $history );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Save assistant response
		$this->add_message(
			$conversation_id,
			'assistant',
			$result['content'],
			array(
				'tokens' => $result['tokens']['total'],
				'cost'   => $result['cost'],
			)
		);

		return $result['content'];
	}

	/**
	 * Execute automation rule.
	 *
	 * @param int   $rule_id Automation rule ID.
	 * @param array $trigger_data Data from the trigger event.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function execute_automation_rule( $rule_id, $trigger_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_ai_automation_rules';
		$rule  = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND is_active = 1", $rule_id ),
			ARRAY_A
		);

		if ( ! $rule ) {
			return new WP_Error( 'rule_not_found', 'Automation rule not found or inactive.' );
		}

		// Get adapter
		$adapter = $this->get_adapter( $rule['provider'], $rule['organization_id'] );

		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		// Execute AI action
		$ai_result = null;

		switch ( $rule['ai_action'] ) {
			case 'summarize':
				$ai_result = $adapter->summarize( $trigger_data['content'] ?? '' );
				break;

			case 'categorize':
				$categories = json_decode( $rule['ai_prompt'], true ) ?? array();
				$ai_result  = $adapter->categorize( $trigger_data['content'] ?? '', $categories );
				break;

			case 'extract':
				$fields    = json_decode( $rule['ai_prompt'], true ) ?? array();
				$ai_result = $adapter->extract_data( $trigger_data['content'] ?? '', $fields );
				break;

			case 'respond':
				$prompt    = str_replace( '{content}', $trigger_data['content'] ?? '', $rule['ai_prompt'] );
				$ai_result = $adapter->complete( $prompt );
				break;
		}

		if ( is_wp_error( $ai_result ) ) {
			return $ai_result;
		}

		// Execute action
		$action_config = json_decode( $rule['action_config'], true ) ?? array();

		switch ( $rule['action_type'] ) {
			case 'create_task':
				// Create task with AI result
				break;

			case 'send_email':
				// Send email with AI result
				break;

			case 'update_field':
				// Update field with AI result
				break;
		}

		// Update execution count
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} 
				SET execution_count = execution_count + 1, last_executed_at = NOW()
				WHERE id = %d",
				$rule_id
			)
		);

		return true;
	}

	/**
	 * Handle form submission automation.
	 */
	public function handle_form_submission_automation( $submission_id, $form_id, $submission_data ) {
		global $wpdb;

		// Find active automation rules for form submissions
		$table = $wpdb->prefix . 'ns_ai_automation_rules';
		$rules = $wpdb->get_results(
			"SELECT id FROM {$table} 
			WHERE trigger_type = 'form_submitted' AND is_active = 1",
			ARRAY_A
		);

		foreach ( $rules as $rule ) {
			$this->execute_automation_rule( $rule['id'], array(
				'form_id'       => $form_id,
				'submission_id' => $submission_id,
				'content'       => wp_json_encode( $submission_data ),
			));
		}
	}

	/**
	 * Handle task creation automation.
	 */
	public function handle_task_creation_automation( $task_id, $task_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_ai_automation_rules';
		$rules = $wpdb->get_results(
			"SELECT id FROM {$table} 
			WHERE trigger_type = 'task_created' AND is_active = 1",
			ARRAY_A
		);

		foreach ( $rules as $rule ) {
			$this->execute_automation_rule( $rule['id'], array(
				'task_id' => $task_id,
				'content' => $task_data['task_name'] . "\n" . ( $task_data['description'] ?? '' ),
			));
		}
	}
}

// Initialize the AI manager
NS_AI_Manager::get_instance();
