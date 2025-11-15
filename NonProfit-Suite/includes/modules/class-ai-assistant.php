<?php
/**
 * AI Assistant Integration Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_AI_Assistant {

	/**
	 * Start new conversation
	 *
	 * @param int    $user_id User ID
	 * @param string $model AI model (claude, gpt4, gemini)
	 * @return int|WP_Error Conversation ID or error
	 */
	public static function start_conversation( $user_id, $model = 'claude' ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_ai_conversations',
			array(
				'user_id' => absint( $user_id ),
				'model' => sanitize_text_field( $model ),
				'total_messages' => 0,
				'total_tokens' => 0,
				'cost' => 0,
			),
			array( '%d', '%s', '%d', '%d', '%f' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to start conversation', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Add message to conversation
	 *
	 * @param int    $conversation_id Conversation ID
	 * @param string $role user|assistant
	 * @param string $message Message content
	 * @param int    $tokens Token count
	 * @return int|WP_Error Message ID or error
	 */
	public static function add_message( $conversation_id, $role, $message, $tokens = 0 ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_ai_messages',
			array(
				'conversation_id' => absint( $conversation_id ),
				'role' => sanitize_text_field( $role ),
				'message' => wp_kses_post( $message ),
				'tokens' => absint( $tokens ),
			),
			array( '%d', '%s', '%s', '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to add message', 'nonprofitsuite' ) );
		}

		// Update conversation totals
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ns_ai_conversations
			SET total_messages = total_messages + 1,
			    total_tokens = total_tokens + %d
			WHERE id = %d",
			absint( $tokens ),
			absint( $conversation_id )
		) );

		NonprofitSuite_Cache::invalidate_module( 'ai_messages' );
		return $wpdb->insert_id;
	}

	/**
	 * Get conversation messages
	 *
	 * @param int $conversation_id Conversation ID
	 * @return array|WP_Error Array of messages or error
	 */
	public static function get_messages( $conversation_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Use caching for AI messages
		$cache_key = NonprofitSuite_Cache::list_key( 'ai_messages', array( 'conversation_id' => $conversation_id ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $conversation_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, conversation_id, role, message, tokens, created_at
				 FROM {$wpdb->prefix}ns_ai_messages WHERE conversation_id = %d ORDER BY created_at ASC",
				absint( $conversation_id )
			) );
		}, 300 );
	}

	/**
	 * Get API key (securely)
	 *
	 * Priority:
	 * 1. Check wp-config.php constant (most secure)
	 * 2. Check encrypted database option (fallback)
	 *
	 * @return string|false API key or false if not configured
	 */
	private static function get_api_key() {
		// Option 1: Check wp-config.php constant (RECOMMENDED)
		if ( defined( 'NONPROFITSUITE_AI_API_KEY' ) && ! empty( NONPROFITSUITE_AI_API_KEY ) ) {
			return NONPROFITSUITE_AI_API_KEY;
		}

		// Option 2: Get encrypted value from database
		$encrypted = get_option( 'nonprofitsuite_ai_api_key_encrypted' );
		if ( ! empty( $encrypted ) ) {
			return self::decrypt_api_key( $encrypted );
		}

		// Legacy: Check for unencrypted key (insecure)
		$plain_key = get_option( 'nonprofitsuite_ai_api_key' );
		if ( ! empty( $plain_key ) ) {
			// Migrate to encrypted storage
			self::save_api_key( $plain_key );
			delete_option( 'nonprofitsuite_ai_api_key' );
			return $plain_key;
		}

		return false;
	}

	/**
	 * Encrypt API key for database storage
	 *
	 * @param string $api_key Plain text API key
	 * @return string Encrypted API key
	 */
	private static function encrypt_api_key( $api_key ) {
		$key = wp_salt( 'auth' );
		$iv = substr( wp_salt( 'secure_auth' ), 0, 16 );

		$encrypted = openssl_encrypt(
			$api_key,
			'AES-256-CBC',
			$key,
			0,
			$iv
		);

		return base64_encode( $encrypted );
	}

	/**
	 * Decrypt API key from database
	 *
	 * @param string $encrypted_key Encrypted API key
	 * @return string|false Decrypted API key or false on failure
	 */
	private static function decrypt_api_key( $encrypted_key ) {
		$key = wp_salt( 'auth' );
		$iv = substr( wp_salt( 'secure_auth' ), 0, 16 );

		$decrypted = openssl_decrypt(
			base64_decode( $encrypted_key ),
			'AES-256-CBC',
			$key,
			0,
			$iv
		);

		return $decrypted ? $decrypted : false;
	}

	/**
	 * Save API key securely
	 *
	 * @param string $api_key Plain text API key
	 * @return bool True on success
	 */
	public static function save_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		$encrypted = self::encrypt_api_key( $api_key );
		return update_option( 'nonprofitsuite_ai_api_key_encrypted', $encrypted );
	}

	/**
	 * Check if API configured
	 *
	 * @return bool True if API key exists
	 */
	public static function is_api_configured() {
		$api_key = self::get_api_key();
		return ! empty( $api_key );
	}

	/**
	 * Get usage stats
	 *
	 * @param int $user_id User ID
	 * @return array Usage statistics
	 */
	public static function get_usage_stats( $user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as conversation_count,
				SUM(total_messages) as total_messages,
				SUM(total_tokens) as total_tokens,
				SUM(cost) as total_cost
			FROM {$wpdb->prefix}ns_ai_conversations
			WHERE user_id = %d",
			absint( $user_id )
		), ARRAY_A );

		return $stats;
	}

	/**
	 * CONTINUE4 Phase 5: AI Assistant Enhancements
	 * Enhanced query with bug detection and feature request routing
	 *
	 * @param string $user_message User's message
	 * @param array  $conversation_history Previous messages
	 * @return string AI response
	 */
	public static function query( $user_message, $conversation_history = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return __( 'PRO license required for AI Assistant', 'nonprofitsuite' );
		}

		// Check for bug report
		if ( self::is_bug_report( $user_message ) ) {
			$response = __( 'It sounds like you\'ve encountered an issue. ', 'nonprofitsuite' );
			$response .= __( 'Please report to **support@silverhost.net** with details:', 'nonprofitsuite' ) . "\n\n";
			$response .= "- " . __( 'What you were trying to do', 'nonprofitsuite' ) . "\n";
			$response .= "- " . __( 'What happened instead', 'nonprofitsuite' ) . "\n";
			$response .= "- " . __( 'Any error messages', 'nonprofitsuite' ) . "\n";
			$response .= "- " . __( 'Steps to reproduce', 'nonprofitsuite' ) . "\n\n";
			$response .= __( 'Usually get response within 24-48 hours.', 'nonprofitsuite' ) . "\n\n";
			$response .= __( 'Let me see if I can help with a workaround...', 'nonprofitsuite' ) . "\n\n";
			$response .= self::call_ai_api( $user_message, $conversation_history );
			return $response;
		}

		// Check for feature request
		if ( self::is_feature_request( $user_message ) ) {
			// Check if feature exists
			$existing = self::check_existing_features( $user_message );

			if ( ! empty( $existing ) ) {
				$response = __( 'Good news - check out: ', 'nonprofitsuite' ) . implode( ', ', $existing ) . "\n\n";
			} else {
				$response = __( 'That feature isn\'t available yet. Submit request to **support@silverhost.net** - they prioritize based on user demand.', 'nonprofitsuite' ) . "\n\n";
			}

			$response .= self::call_ai_api( $user_message, $conversation_history );
			return $response;
		}

		// Normal processing
		return self::call_ai_api( $user_message, $conversation_history );
	}

	/**
	 * Get enhanced system prompt with organization context
	 *
	 * @return string System prompt
	 */
	private static function get_system_prompt() {
		$org_context = self::get_organization_context();

		$prompt = "You are the NonprofitSuite AI Assistant.\n\n";
		$prompt .= "ORGANIZATION CONTEXT:\n";
		$prompt .= $org_context . "\n\n";
		$prompt .= "CRITICAL RULES FOR USER SUPPORT:\n\n";
		$prompt .= "1. BUG DETECTION:\n";
		$prompt .= "If user reports ERROR, BUG, CRASH, or something NOT WORKING, respond:\n";
		$prompt .= "'This sounds like a bug. Please report to support@silverhost.net with:\n";
		$prompt .= "- What you were trying to do\n";
		$prompt .= "- What happened instead\n";
		$prompt .= "- Any error messages\n";
		$prompt .= "- Steps to reproduce\n";
		$prompt .= "Usually get response within 24-48 hours. [Offer workaround if possible]'\n\n";
		$prompt .= "2. MISSING FEATURES:\n";
		$prompt .= "If user asks for feature that DOESN'T EXIST, respond:\n";
		$prompt .= "'Great idea! NonprofitSuite doesn't currently have this. Submit feature request to support@silverhost.net - they prioritize based on user demand. [Offer alternative if available]'\n\n";
		$prompt .= "3. STATE-SPECIFIC REQUIREMENTS:\n";
		$prompt .= "If user asks about state-specific requirement not tracked, respond:\n";
		$prompt .= "'This is state-specific requirement not currently auto-tracked. I recommend:\n";
		$prompt .= "1. Submit feature request to support@silverhost.net\n";
		$prompt .= "2. Track manually in Compliance Module for now\n";
		$prompt .= "[Provide official resource links]'\n\n";
		$prompt .= "4. ALWAYS BE HELPFUL:\n";
		$prompt .= "- Provide actionable advice\n";
		$prompt .= "- Reference actual modules/features\n";
		$prompt .= "- Never make up features\n";
		$prompt .= "- Be accurate and cite sources\n";

		return $prompt;
	}

	/**
	 * Get organization context for AI assistant
	 *
	 * @return string Organization context
	 */
	private static function get_organization_context() {
		$context = array();

		$settings = get_option( 'nonprofitsuite_settings', array() );
		$context[] = 'Org: ' . ( isset( $settings['organization_name'] ) ? $settings['organization_name'] : 'Not Set' );
		$context[] = 'Fiscal Year End: ' . ( isset( $settings['fiscal_year_end'] ) ? $settings['fiscal_year_end'] : 'Dec 31' );

		// State operations
		if ( class_exists( 'NonprofitSuite_State_Compliance' ) ) {
			$states = NonprofitSuite_State_Compliance::get_state_operations();
			if ( ! empty( $states ) ) {
				$state_list = array_column( $states, 'state_code' );
				$context[] = 'States: ' . implode( ', ', $state_list );
			}
		}

		// Activity counts
		global $wpdb;
		$meetings = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_meetings" );
		$context[] = "Meetings: {$meetings}";

		$is_pro = NonprofitSuite_License::is_pro_active();
		$context[] = 'License: ' . ( $is_pro ? 'Pro' : 'Free' );

		return implode( "\n", $context );
	}

	/**
	 * Detect if message is a bug report
	 *
	 * @param string $query User message
	 * @return bool True if bug report detected
	 */
	private static function is_bug_report( $query ) {
		$bug_keywords = array(
			'error',
			'bug',
			'broken',
			'not working',
			'crash',
			'problem',
			'issue',
			'wrong',
			'failed',
		);

		$query_lower = strtolower( $query );
		foreach ( $bug_keywords as $keyword ) {
			if ( strpos( $query_lower, $keyword ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect if message is a feature request
	 *
	 * @param string $query User message
	 * @return bool True if feature request detected
	 */
	private static function is_feature_request( $query ) {
		$feature_keywords = array(
			'can you add',
			'feature request',
			'i wish',
			'does this support',
			'is there a way',
		);

		$query_lower = strtolower( $query );
		foreach ( $feature_keywords as $keyword ) {
			if ( strpos( $query_lower, $keyword ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if feature exists in NonprofitSuite
	 *
	 * @param string $query User message
	 * @return array Found modules
	 */
	private static function check_existing_features( $query ) {
		$query_lower = strtolower( $query );
		$found        = array();

		$feature_map = array(
			'raffle'              => 'State Compliance Module (CA)',
			'crypto'              => 'Alternative Assets Module',
			'bitcoin'             => 'Alternative Assets Module',
			'cryptocurrency'      => 'Alternative Assets Module',
			'multi-state'         => 'State Compliance Module',
			'chapters'            => 'Chapter & Affiliate Module',
			'audit'               => 'Audit Module',
			'cpa'                 => 'CPA Dashboard',
			'attorney'            => 'Legal Counsel Dashboard',
			'meeting'             => 'Board Meetings Module',
			'minutes'             => 'Board Meetings Module',
			'bylaws'              => 'Bylaws Module',
			'policy'              => 'Policies Module',
			'conflict of interest' => 'Conflict of Interest Module',
			'donor'               => 'Donor Management Module',
			'volunteer'           => 'Volunteer Management Module',
			'grant'               => 'Grant Management Module',
			'program'             => 'Programs & Operations Module',
			'treasury'            => 'Treasury Module',
			'form 990'            => 'Form 990 Module',
			'990'                 => 'Form 990 Module',
			'filing'              => 'State Compliance Module',
			'registration'        => 'State Compliance Module',
			'license'             => 'Professional Licensing System',
			'bar admission'       => 'Professional Licensing System',
			'in-kind'             => 'In-Kind Donations Module',
			'financial report'    => 'Financial Reports Module',
			'insurance'           => 'Insurance Tracking Module',
			'whistleblower'       => 'Whistleblower Policy Module',
		);

		foreach ( $feature_map as $keyword => $module ) {
			if ( strpos( $query_lower, $keyword ) !== false ) {
				$found[] = $module;
			}
		}

		return array_unique( $found );
	}

	/**
	 * Call AI API (stub for actual API integration)
	 *
	 * @param string $user_message User's message
	 * @param array  $conversation_history Previous messages
	 * @return string AI response
	 */
	private static function call_ai_api( $user_message, $conversation_history = array() ) {
		// This is a stub method that would integrate with actual AI API
		// (Claude, GPT-4, Gemini, etc.) in production

		$system_prompt = self::get_system_prompt();

		// In production, this would make actual API call with:
		// - $system_prompt as system message
		// - $conversation_history as previous messages
		// - $user_message as current user message

		// For now, return helpful message
		return __( '[AI Assistant API integration pending. This would process your query with full context and provide intelligent responses.]', 'nonprofitsuite' );
	}
}
