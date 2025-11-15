<?php
/**
 * Document Protection Manager
 *
 * Handles document protection, override mechanisms, and audit logging.
 * Supports automatic protection triggers and protection levels.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations/Storage
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Document Protection Manager Class
 */
class NonprofitSuite_Document_Protection {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Document_Protection
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Document_Protection
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks() {
		// Hook into status changes for automatic protection
		add_action( 'ns_storage_file_status_changed', array( $this, 'check_auto_protection' ), 10, 3 );

		// Hook into file operations to enforce protection
		add_filter( 'ns_storage_can_edit_file', array( $this, 'check_protection_on_edit' ), 10, 3 );
		add_filter( 'ns_storage_can_delete_file', array( $this, 'check_protection_on_delete' ), 10, 3 );
		add_filter( 'ns_storage_can_replace_file', array( $this, 'check_protection_on_replace' ), 10, 3 );
	}

	/**
	 * Protect a document
	 *
	 * @param int    $file_id File ID.
	 * @param string $protection_level Protection level (full, replace_only, edit_only).
	 * @param string $reason Why the document is being protected.
	 * @param string $override_capability Required capability to override (optional).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function protect_document( $file_id, $protection_level = 'full', $reason = '', $override_capability = 'manage_options' ) {
		global $wpdb;

		// Validate protection level
		$valid_levels = array( 'full', 'replace_only', 'edit_only' );
		if ( ! in_array( $protection_level, $valid_levels, true ) ) {
			return new WP_Error( 'invalid_protection_level', 'Invalid protection level specified.' );
		}

		// Check if file exists
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, is_protected, protection_level FROM {$table_files} WHERE id = %d",
			$file_id
		) );

		if ( ! $file ) {
			return new WP_Error( 'file_not_found', 'File not found.' );
		}

		// Update file with protection
		$result = $wpdb->update(
			$table_files,
			array(
				'is_protected'               => 1,
				'protected_at'               => current_time( 'mysql' ),
				'protected_by'               => get_current_user_id(),
				'protection_reason'          => sanitize_text_field( $reason ),
				'protection_level'           => $protection_level,
				'can_unprotect_capability'   => sanitize_text_field( $override_capability ),
			),
			array( 'id' => $file_id ),
			array( '%d', '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to protect document.' );
		}

		// Log the protection action
		$this->log_protection_action( $file_id, 'protected', $protection_level, $reason );

		do_action( 'ns_storage_document_protected', $file_id, $protection_level, $reason );

		return true;
	}

	/**
	 * Unprotect a document
	 *
	 * @param int    $file_id File ID.
	 * @param string $reason Why the document is being unprotected.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function unprotect_document( $file_id, $reason = '' ) {
		global $wpdb;

		// Get file
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, is_protected, protection_level, can_unprotect_capability FROM {$table_files} WHERE id = %d",
			$file_id
		) );

		if ( ! $file ) {
			return new WP_Error( 'file_not_found', 'File not found.' );
		}

		if ( ! $file->is_protected ) {
			return new WP_Error( 'not_protected', 'Document is not protected.' );
		}

		// Check capability
		if ( ! current_user_can( $file->can_unprotect_capability ) ) {
			return new WP_Error( 'insufficient_permissions', 'You do not have permission to unprotect this document.' );
		}

		// Update file
		$result = $wpdb->update(
			$table_files,
			array(
				'is_protected'               => 0,
				'protected_at'               => null,
				'protected_by'               => null,
				'protection_reason'          => null,
				'protection_level'           => 'none',
				'can_unprotect_capability'   => null,
			),
			array( 'id' => $file_id ),
			array( '%d', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to unprotect document.' );
		}

		// Log the action
		$this->log_protection_action( $file_id, 'unprotected', null, $reason );

		do_action( 'ns_storage_document_unprotected', $file_id, $reason );

		return true;
	}

	/**
	 * Override protection temporarily for a specific action
	 *
	 * @param int    $file_id File ID.
	 * @param string $action Action to perform (edit, delete, replace).
	 * @param string $authorization_reason Why override is needed.
	 * @return bool|WP_Error True on success (caller should perform action), WP_Error on failure.
	 */
	public function override_protection( $file_id, $action, $authorization_reason ) {
		global $wpdb;

		// Validate action
		$valid_actions = array( 'edit', 'delete', 'replace' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return new WP_Error( 'invalid_action', 'Invalid action specified.' );
		}

		// Get file
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, is_protected, protection_level, can_unprotect_capability FROM {$table_files} WHERE id = %d",
			$file_id
		) );

		if ( ! $file ) {
			return new WP_Error( 'file_not_found', 'File not found.' );
		}

		if ( ! $file->is_protected ) {
			return new WP_Error( 'not_protected', 'Document is not protected.' );
		}

		// Check capability
		if ( ! current_user_can( $file->can_unprotect_capability ) ) {
			return new WP_Error( 'insufficient_permissions', 'You do not have permission to override protection on this document.' );
		}

		// Check if action is allowed by protection level
		$allowed = false;
		switch ( $file->protection_level ) {
			case 'replace_only':
				$allowed = ( 'replace' === $action );
				break;
			case 'edit_only':
				$allowed = ( 'edit' === $action );
				break;
			case 'full':
				$allowed = false; // No actions allowed without override
				break;
		}

		if ( ! $allowed && empty( $authorization_reason ) ) {
			return new WP_Error( 'authorization_required', 'Authorization reason is required for this override.' );
		}

		// Log the override
		$this->log_protection_action(
			$file_id,
			'override_' . $action,
			$file->protection_level,
			'Override requested',
			$authorization_reason
		);

		do_action( 'ns_storage_protection_overridden', $file_id, $action, $authorization_reason );

		return true;
	}

	/**
	 * Check if document is protected
	 *
	 * @param int $file_id File ID.
	 * @return array|WP_Error Protection info or WP_Error.
	 */
	public function get_protection_status( $file_id ) {
		global $wpdb;

		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file = $wpdb->get_row( $wpdb->prepare(
			"SELECT is_protected, protected_at, protected_by, protection_reason,
			        protection_level, can_unprotect_capability
			 FROM {$table_files} WHERE id = %d",
			$file_id
		) );

		if ( ! $file ) {
			return new WP_Error( 'file_not_found', 'File not found.' );
		}

		return array(
			'is_protected'             => (bool) $file->is_protected,
			'protected_at'             => $file->protected_at,
			'protected_by'             => $file->protected_by,
			'protection_reason'        => $file->protection_reason,
			'protection_level'         => $file->protection_level,
			'can_unprotect_capability' => $file->can_unprotect_capability,
		);
	}

	/**
	 * Check for automatic protection based on rules
	 *
	 * @param int    $file_id File ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public function check_auto_protection( $file_id, $old_status, $new_status ) {
		global $wpdb;

		// Get active rules for status changes
		$table_rules = $wpdb->prefix . 'ns_storage_protection_rules';
		$rules = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_rules}
			 WHERE is_active = 1 AND trigger_type = %s AND trigger_value = %s",
			'status_change',
			$new_status
		) );

		foreach ( $rules as $rule ) {
			// Apply protection
			$this->protect_document(
				$file_id,
				$rule->protection_level,
				sprintf( 'Auto-protected: %s (Rule: %s)', $rule->description, $rule->rule_name ),
				$rule->required_override_capability
			);
		}
	}

	/**
	 * Check protection on edit attempt
	 *
	 * @param bool $can_edit Current permission.
	 * @param int  $file_id File ID.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public function check_protection_on_edit( $can_edit, $file_id, $user_id ) {
		if ( ! $can_edit ) {
			return false;
		}

		$status = $this->get_protection_status( $file_id );
		if ( is_wp_error( $status ) ) {
			return false;
		}

		if ( ! $status['is_protected'] ) {
			return true;
		}

		// Check if edit is allowed
		if ( in_array( $status['protection_level'], array( 'edit_only', 'replace_only' ), true ) ) {
			return false;
		}

		// Full protection - check capability
		return current_user_can( $status['can_unprotect_capability'] );
	}

	/**
	 * Check protection on delete attempt
	 *
	 * @param bool $can_delete Current permission.
	 * @param int  $file_id File ID.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public function check_protection_on_delete( $can_delete, $file_id, $user_id ) {
		if ( ! $can_delete ) {
			return false;
		}

		$status = $this->get_protection_status( $file_id );
		if ( is_wp_error( $status ) ) {
			return false;
		}

		if ( ! $status['is_protected'] ) {
			return true;
		}

		// Any protection level prevents deletion
		return current_user_can( $status['can_unprotect_capability'] );
	}

	/**
	 * Check protection on replace attempt
	 *
	 * @param bool $can_replace Current permission.
	 * @param int  $file_id File ID.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public function check_protection_on_replace( $can_replace, $file_id, $user_id ) {
		if ( ! $can_replace ) {
			return false;
		}

		$status = $this->get_protection_status( $file_id );
		if ( is_wp_error( $status ) ) {
			return false;
		}

		if ( ! $status['is_protected'] ) {
			return true;
		}

		// Replace only allowed for 'replace_only' level or with capability
		if ( 'replace_only' === $status['protection_level'] ) {
			return true;
		}

		return current_user_can( $status['can_unprotect_capability'] );
	}

	/**
	 * Log protection action to audit trail
	 *
	 * @param int    $file_id File ID.
	 * @param string $action Action performed.
	 * @param string $protection_level Protection level.
	 * @param string $trigger_reason Why action occurred.
	 * @param string $authorization_reason Override reason (optional).
	 */
	private function log_protection_action( $file_id, $action, $protection_level = null, $trigger_reason = '', $authorization_reason = '' ) {
		global $wpdb;

		$table_log = $wpdb->prefix . 'ns_storage_protection_log';

		$wpdb->insert(
			$table_log,
			array(
				'file_id'               => $file_id,
				'action'                => $action,
				'protection_level'      => $protection_level,
				'trigger_reason'        => sanitize_text_field( $trigger_reason ),
				'authorization_reason'  => sanitize_textarea_field( $authorization_reason ),
				'performed_by'          => get_current_user_id(),
				'performed_at'          => current_time( 'mysql' ),
				'ip_address'            => $this->get_client_ip(),
				'user_agent'            => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Create a protection rule
	 *
	 * @param array $args Rule arguments.
	 * @return int|WP_Error Rule ID on success, WP_Error on failure.
	 */
	public function create_protection_rule( $args ) {
		global $wpdb;

		$defaults = array(
			'rule_name'                      => '',
			'is_active'                      => 1,
			'trigger_type'                   => 'status_change',
			'trigger_value'                  => '',
			'protection_level'               => 'full',
			'required_override_capability'   => 'manage_options',
			'description'                    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate
		if ( empty( $args['rule_name'] ) || empty( $args['trigger_value'] ) ) {
			return new WP_Error( 'missing_required', 'Rule name and trigger value are required.' );
		}

		$table_rules = $wpdb->prefix . 'ns_storage_protection_rules';

		$result = $wpdb->insert(
			$table_rules,
			array(
				'rule_name'                      => sanitize_text_field( $args['rule_name'] ),
				'is_active'                      => $args['is_active'] ? 1 : 0,
				'trigger_type'                   => sanitize_text_field( $args['trigger_type'] ),
				'trigger_value'                  => sanitize_text_field( $args['trigger_value'] ),
				'protection_level'               => sanitize_text_field( $args['protection_level'] ),
				'required_override_capability'   => sanitize_text_field( $args['required_override_capability'] ),
				'description'                    => sanitize_textarea_field( $args['description'] ),
				'created_by'                     => get_current_user_id(),
				'created_at'                     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to create protection rule.' );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get protection log for a file
	 *
	 * @param int   $file_id File ID.
	 * @param array $args Query arguments.
	 * @return array Log entries.
	 */
	public function get_protection_log( $file_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$table_log = $wpdb->prefix . 'ns_storage_protection_log';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_log}
			 WHERE file_id = %d
			 ORDER BY performed_at DESC
			 LIMIT %d OFFSET %d",
			$file_id,
			$args['limit'],
			$args['offset']
		) );
	}

	/**
	 * Get all active protection rules
	 *
	 * @return array Rules.
	 */
	public function get_active_rules() {
		global $wpdb;

		$table_rules = $wpdb->prefix . 'ns_storage_protection_rules';

		return $wpdb->get_results(
			"SELECT * FROM {$table_rules} WHERE is_active = 1 ORDER BY rule_name"
		);
	}
}
