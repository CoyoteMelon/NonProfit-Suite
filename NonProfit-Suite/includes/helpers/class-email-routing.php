<?php
/**
 * Email Routing Engine
 *
 * Handles intelligent email routing based on role-based addresses, group emails,
 * functional categories, and AI assistant email processing.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.6.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Email_Routing Class
 *
 * Manages email address routing and automation.
 */
class NonprofitSuite_Email_Routing {

	/**
	 * Register an organizational email address.
	 *
	 * @param array $address_data {
	 *     Email address configuration.
	 *
	 *     @type string $email_address      Full email (e.g., president@org.org).
	 *     @type string $address_type       Type: role, group, functional, ai.
	 *     @type int    $role_id            Optional. Position/role ID for role-based emails.
	 *     @type string $group_type         Optional. Group type: board, committee, staff.
	 *     @type string $functional_category Optional. Category: irs, casos, legal.
	 *     @type array  $forward_to_users   Optional. Array of user IDs to forward to.
	 *     @type string $archive_category   Optional. Archive category for incoming mail.
	 *     @type bool   $trigger_automations Optional. Enable automation triggers.
	 *     @type array  $automation_rules   Optional. Automation rule configurations.
	 * }
	 *
	 * @return int|WP_Error Email address ID or WP_Error.
	 */
	public static function register_email_address( $address_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_addresses';

		// Parse local part from email
		$email_parts = explode( '@', $address_data['email_address'] );
		$local_part = $email_parts[0];

		$data = array(
			'email_address'        => sanitize_email( $address_data['email_address'] ),
			'local_part'           => sanitize_text_field( $local_part ),
			'address_type'         => isset( $address_data['address_type'] ) ? $address_data['address_type'] : 'role',
			'role_id'              => isset( $address_data['role_id'] ) ? $address_data['role_id'] : null,
			'group_type'           => isset( $address_data['group_type'] ) ? $address_data['group_type'] : null,
			'functional_category'  => isset( $address_data['functional_category'] ) ? $address_data['functional_category'] : null,
			'forward_to_users'     => isset( $address_data['forward_to_users'] ) ? wp_json_encode( $address_data['forward_to_users'] ) : null,
			'archive_category'     => isset( $address_data['archive_category'] ) ? $address_data['archive_category'] : null,
			'trigger_automations'  => isset( $address_data['trigger_automations'] ) ? $address_data['trigger_automations'] : 0,
			'automation_rules'     => isset( $address_data['automation_rules'] ) ? wp_json_encode( $address_data['automation_rules'] ) : null,
			'is_active'            => 1,
		);

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to register email address', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get recipients for an organizational email address.
	 *
	 * Resolves role-based and group-based addresses to actual user email addresses.
	 *
	 * @param string $org_email Organizational email address.
	 * @return array|WP_Error Array of recipient email addresses or WP_Error.
	 */
	public static function get_recipients( $org_email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_addresses';

		$address_config = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email_address = %s AND is_active = 1",
				$org_email
			),
			ARRAY_A
		);

		if ( ! $address_config ) {
			return new WP_Error( 'address_not_found', __( 'Email address not configured', 'nonprofitsuite' ) );
		}

		$recipients = array();

		switch ( $address_config['address_type'] ) {
			case 'role':
				$recipients = self::resolve_role_recipients( $address_config );
				break;

			case 'group':
				$recipients = self::resolve_group_recipients( $address_config );
				break;

			case 'functional':
			case 'ai':
				$recipients = self::resolve_manual_recipients( $address_config );
				break;
		}

		return array_unique( array_filter( $recipients ) );
	}

	/**
	 * Resolve role-based email recipients.
	 *
	 * Gets current user(s) holding a position (e.g., president@).
	 *
	 * @param array $address_config Email address configuration.
	 * @return array Array of email addresses.
	 */
	private static function resolve_role_recipients( $address_config ) {
		global $wpdb;

		if ( ! $address_config['role_id'] ) {
			return array();
		}

		// Get current office holder(s) from positions table
		$positions_table = $wpdb->prefix . 'ns_positions';

		$current_holders = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$positions_table}
				WHERE position_id = %d
				AND status = 'active'
				AND (start_date IS NULL OR start_date <= CURDATE())
				AND (end_date IS NULL OR end_date >= CURDATE())",
				$address_config['role_id']
			)
		);

		$emails = array();
		foreach ( $current_holders as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$emails[] = $user->user_email;
			}
		}

		return $emails;
	}

	/**
	 * Resolve group-based email recipients.
	 *
	 * Gets all users in a group (e.g., board@, committee@).
	 *
	 * @param array $address_config Email address configuration.
	 * @return array Array of email addresses.
	 */
	private static function resolve_group_recipients( $address_config ) {
		global $wpdb;

		$group_type = $address_config['group_type'];
		$emails = array();

		switch ( $group_type ) {
			case 'board':
				// Get all current board members
				$positions_table = $wpdb->prefix . 'ns_positions';
				$board_members = $wpdb->get_col(
					"SELECT DISTINCT user_id FROM {$positions_table}
					WHERE position_type = 'board'
					AND status = 'active'
					AND (start_date IS NULL OR start_date <= CURDATE())
					AND (end_date IS NULL OR end_date >= CURDATE())"
				);

				foreach ( $board_members as $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user ) {
						$emails[] = $user->user_email;
					}
				}
				break;

			case 'committee':
				// Could extract committee name from local_part or use forward_to_users
				$emails = self::resolve_manual_recipients( $address_config );
				break;

			case 'staff':
				// Get all users with staff role
				$staff_users = get_users( array( 'role' => 'staff' ) );
				foreach ( $staff_users as $user ) {
					$emails[] = $user->user_email;
				}
				break;

			case 'volunteers':
				// Get all users with volunteer role
				$volunteer_users = get_users( array( 'role' => 'volunteer' ) );
				foreach ( $volunteer_users as $user ) {
					$emails[] = $user->user_email;
				}
				break;

			default:
				$emails = self::resolve_manual_recipients( $address_config );
				break;
		}

		return $emails;
	}

	/**
	 * Resolve manually configured recipients.
	 *
	 * Uses the forward_to_users JSON array.
	 *
	 * @param array $address_config Email address configuration.
	 * @return array Array of email addresses.
	 */
	private static function resolve_manual_recipients( $address_config ) {
		if ( empty( $address_config['forward_to_users'] ) ) {
			return array();
		}

		$user_ids = json_decode( $address_config['forward_to_users'], true );
		if ( ! is_array( $user_ids ) ) {
			return array();
		}

		$emails = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$emails[] = $user->user_email;
			}
		}

		return $emails;
	}

	/**
	 * Process incoming email.
	 *
	 * Handles routing, archiving, and automation triggers.
	 *
	 * @param array $email_data Incoming email data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function process_incoming_email( $email_data ) {
		global $wpdb;

		// Determine which org email address this was sent to
		$to_address = is_array( $email_data['to'] ) ? $email_data['to'][0] : $email_data['to'];

		$address_table = $wpdb->prefix . 'ns_email_addresses';
		$address_config = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$address_table} WHERE email_address = %s AND is_active = 1",
				$to_address
			),
			ARRAY_A
		);

		if ( ! $address_config ) {
			// Not an org email, ignore
			return true;
		}

		// Log incoming email
		$log_id = self::log_email( array_merge( $email_data, array(
			'direction'        => 'inbound',
			'email_address_id' => $address_config['id'],
			'archive_category' => $address_config['archive_category'],
			'status'           => 'received',
			'received_at'      => current_time( 'mysql' ),
		) ) );

		// Handle special address types
		if ( 'ai' === $address_config['address_type'] ) {
			return self::process_ai_email( $email_data, $address_config, $log_id );
		}

		// Forward to recipients
		$recipients = self::get_recipients( $to_address );
		if ( ! is_wp_error( $recipients ) && ! empty( $recipients ) ) {
			self::forward_email( $email_data, $recipients );
		}

		// Trigger automations if enabled
		if ( $address_config['trigger_automations'] && ! empty( $address_config['automation_rules'] ) ) {
			self::trigger_automations( $email_data, $address_config );
		}

		// Send auto-reply if configured
		if ( $address_config['auto_reply_enabled'] && $address_config['auto_reply_template_id'] ) {
			self::send_auto_reply( $email_data, $address_config );
		}

		return true;
	}

	/**
	 * Process AI assistant email.
	 *
	 * Handles requests sent to ai@name.org.
	 *
	 * @param array $email_data      Email data.
	 * @param array $address_config  Address configuration.
	 * @param int   $log_id          Email log ID.
	 * @return bool|WP_Error Result.
	 */
	private static function process_ai_email( $email_data, $address_config, $log_id ) {
		// Extract request from email body
		$request_text = ! empty( $email_data['body_text'] ) ? $email_data['body_text'] : strip_tags( $email_data['body_html'] );

		// Parse sender
		$from_email = $email_data['from_address'];
		$user = get_user_by( 'email', $from_email );

		if ( ! $user ) {
			// Unknown sender, send error reply
			self::send_ai_error_reply( $email_data, 'Unknown sender. Please use your registered email address.' );
			return new WP_Error( 'unknown_sender', 'Email from unregistered user' );
		}

		// Parse the request and create appropriate actions
		$actions = self::parse_ai_request( $request_text, $user->ID );

		// Execute actions and compile response
		$response_text = "Request processed:\n\n";

		foreach ( $actions as $action ) {
			$result = self::execute_ai_action( $action, $user->ID );

			if ( is_wp_error( $result ) ) {
				$response_text .= "❌ {$action['description']}: {$result->get_error_message()}\n";
			} else {
				$response_text .= "✅ {$action['description']}\n";
			}
		}

		// Send response email
		$adapter = NonprofitSuite_Email_Manager::get_default_adapter();
		$adapter->send( array(
			'to'        => $from_email,
			'subject'   => 'Re: ' . $email_data['subject'],
			'body_text' => $response_text,
			'from_email' => $address_config['email_address'],
			'from_name'  => get_bloginfo( 'name' ) . ' AI Assistant',
		) );

		return true;
	}

	/**
	 * Parse AI email request into actionable tasks.
	 *
	 * @param string $request_text Email body text.
	 * @param int    $user_id      Requesting user ID.
	 * @return array Array of action configurations.
	 */
	private static function parse_ai_request( $request_text, $user_id ) {
		$actions = array();

		// Simple keyword-based parsing (can be enhanced with actual AI later)
		$text_lower = strtolower( $request_text );

		// Create task
		if ( preg_match( '/create (?:a )?task[:\s]+(.+)/i', $request_text, $matches ) ) {
			$actions[] = array(
				'type'        => 'create_task',
				'description' => 'Create task: ' . trim( $matches[1] ),
				'params'      => array( 'title' => trim( $matches[1] ) ),
			);
		}

		// Schedule meeting
		if ( preg_match( '/schedule (?:a )?meeting[:\s]+(.+)/i', $request_text, $matches ) ) {
			$actions[] = array(
				'type'        => 'schedule_meeting',
				'description' => 'Schedule meeting: ' . trim( $matches[1] ),
				'params'      => array( 'title' => trim( $matches[1] ) ),
			);
		}

		// Add reminder
		if ( preg_match( '/remind me (?:to )?(.+)/i', $request_text, $matches ) ) {
			$actions[] = array(
				'type'        => 'add_reminder',
				'description' => 'Add reminder: ' . trim( $matches[1] ),
				'params'      => array( 'note' => trim( $matches[1] ) ),
			);
		}

		return $actions;
	}

	/**
	 * Execute an AI-parsed action.
	 *
	 * @param array $action  Action configuration.
	 * @param int   $user_id User ID.
	 * @return mixed|WP_Error Result or WP_Error.
	 */
	private static function execute_ai_action( $action, $user_id ) {
		switch ( $action['type'] ) {
			case 'create_task':
				global $wpdb;
				$table = $wpdb->prefix . 'ns_tasks';
				$result = $wpdb->insert(
					$table,
					array(
						'title'       => $action['params']['title'],
						'assigned_to' => $user_id,
						'created_by'  => $user_id,
						'status'      => 'pending',
					)
				);
				return $result ? $wpdb->insert_id : new WP_Error( 'task_failed', 'Could not create task' );

			case 'schedule_meeting':
				// Would integrate with calendar system
				return new WP_Error( 'not_implemented', 'Meeting scheduling coming soon' );

			case 'add_reminder':
				// Would create a reminder
				return new WP_Error( 'not_implemented', 'Reminders coming soon' );

			default:
				return new WP_Error( 'unknown_action', 'Unknown action type' );
		}
	}

	/**
	 * Send AI error reply.
	 *
	 * @param array  $original_email Original email data.
	 * @param string $error_message  Error message.
	 */
	private static function send_ai_error_reply( $original_email, $error_message ) {
		$adapter = NonprofitSuite_Email_Manager::get_default_adapter();
		$adapter->send( array(
			'to'        => $original_email['from_address'],
			'subject'   => 'Re: ' . $original_email['subject'],
			'body_text' => "Error processing your request:\n\n{$error_message}",
			'from_email' => $original_email['to'],
			'from_name'  => get_bloginfo( 'name' ) . ' AI Assistant',
		) );
	}

	/**
	 * Forward email to recipients.
	 *
	 * @param array $email_data  Original email data.
	 * @param array $recipients  Array of recipient emails.
	 */
	private static function forward_email( $email_data, $recipients ) {
		$adapter = NonprofitSuite_Email_Manager::get_default_adapter();

		foreach ( $recipients as $recipient ) {
			$adapter->send( array(
				'to'        => $recipient,
				'from_email' => $email_data['from_address'],
				'subject'   => $email_data['subject'],
				'body_html' => $email_data['body_html'],
				'body_text' => $email_data['body_text'],
			) );
		}
	}

	/**
	 * Trigger automation rules based on incoming email.
	 *
	 * @param array $email_data      Email data.
	 * @param array $address_config  Address configuration.
	 */
	private static function trigger_automations( $email_data, $address_config ) {
		$rules = json_decode( $address_config['automation_rules'], true );

		if ( ! is_array( $rules ) ) {
			return;
		}

		foreach ( $rules as $rule ) {
			// Check if rule conditions are met
			if ( ! self::check_rule_conditions( $rule, $email_data ) ) {
				continue;
			}

			// Execute rule actions
			self::execute_rule_actions( $rule, $email_data );
		}
	}

	/**
	 * Check if automation rule conditions are met.
	 *
	 * @param array $rule       Rule configuration.
	 * @param array $email_data Email data.
	 * @return bool True if conditions met.
	 */
	private static function check_rule_conditions( $rule, $email_data ) {
		if ( empty( $rule['conditions'] ) ) {
			return true;
		}

		foreach ( $rule['conditions'] as $condition ) {
			switch ( $condition['type'] ) {
				case 'subject_contains':
					if ( stripos( $email_data['subject'], $condition['value'] ) === false ) {
						return false;
					}
					break;

				case 'body_contains':
					$body = $email_data['body_text'] ?: strip_tags( $email_data['body_html'] );
					if ( stripos( $body, $condition['value'] ) === false ) {
						return false;
					}
					break;

				case 'from_domain':
					$from_domain = substr( strrchr( $email_data['from_address'], '@' ), 1 );
					if ( $from_domain !== $condition['value'] ) {
						return false;
					}
					break;
			}
		}

		return true;
	}

	/**
	 * Execute automation rule actions.
	 *
	 * @param array $rule       Rule configuration.
	 * @param array $email_data Email data.
	 */
	private static function execute_rule_actions( $rule, $email_data ) {
		if ( empty( $rule['actions'] ) ) {
			return;
		}

		foreach ( $rule['actions'] as $action ) {
			switch ( $action['type'] ) {
				case 'create_task':
					global $wpdb;
					$table = $wpdb->prefix . 'ns_tasks';
					$wpdb->insert(
						$table,
						array(
							'title'       => $action['title'] ?: $email_data['subject'],
							'description' => $email_data['body_text'],
							'status'      => 'pending',
						)
					);
					break;

				case 'file_document':
					// Would save email as document
					break;

				case 'notify_user':
					// Would send notification to specific user
					break;
			}
		}
	}

	/**
	 * Send auto-reply.
	 *
	 * @param array $email_data      Original email data.
	 * @param array $address_config  Address configuration.
	 */
	private static function send_auto_reply( $email_data, $address_config ) {
		// Would load and render template, then send
	}

	/**
	 * Log email to database.
	 *
	 * @param array $email_data Email data.
	 * @return int|false Log ID or false on failure.
	 */
	public static function log_email( $email_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_log';

		$data = array(
			'direction'           => isset( $email_data['direction'] ) ? $email_data['direction'] : 'outbound',
			'from_address'        => isset( $email_data['from_address'] ) ? $email_data['from_address'] : null,
			'to_addresses'        => isset( $email_data['to'] ) ? wp_json_encode( (array) $email_data['to'] ) : null,
			'cc_addresses'        => isset( $email_data['cc'] ) ? wp_json_encode( $email_data['cc'] ) : null,
			'bcc_addresses'       => isset( $email_data['bcc'] ) ? wp_json_encode( $email_data['bcc'] ) : null,
			'subject'             => isset( $email_data['subject'] ) ? $email_data['subject'] : null,
			'body_text'           => isset( $email_data['body_text'] ) ? $email_data['body_text'] : null,
			'body_html'           => isset( $email_data['body_html'] ) ? $email_data['body_html'] : null,
			'email_address_id'    => isset( $email_data['email_address_id'] ) ? $email_data['email_address_id'] : null,
			'archive_category'    => isset( $email_data['archive_category'] ) ? $email_data['archive_category'] : null,
			'related_entity_type' => isset( $email_data['related_entity_type'] ) ? $email_data['related_entity_type'] : null,
			'related_entity_id'   => isset( $email_data['related_entity_id'] ) ? $email_data['related_entity_id'] : null,
			'message_id'          => isset( $email_data['message_id'] ) ? $email_data['message_id'] : null,
			'status'              => isset( $email_data['status'] ) ? $email_data['status'] : 'pending',
			'sent_at'             => isset( $email_data['sent_at'] ) ? $email_data['sent_at'] : null,
			'received_at'         => isset( $email_data['received_at'] ) ? $email_data['received_at'] : null,
		);

		$result = $wpdb->insert( $table, $data );

		return $result ? $wpdb->insert_id : false;
	}
}
