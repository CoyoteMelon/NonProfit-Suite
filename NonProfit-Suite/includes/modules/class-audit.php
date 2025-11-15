<?php
/**
 * Audit Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Audit {

	/**
	 * Create new audit
	 *
	 * @param array $data Audit data
	 * @return int|WP_Error Audit ID or error
	 */
	public static function create_audit( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage audits' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'audit_year' => date( 'Y' ),
			'audit_type' => 'financial',
			'status' => 'planning',
			'engagement_letter_signed' => 0,
			'management_letter_received' => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_audits',
			array(
				'audit_year' => absint( $data['audit_year'] ),
				'audit_type' => sanitize_text_field( $data['audit_type'] ),
				'auditor_firm' => isset( $data['auditor_firm'] ) ? sanitize_text_field( $data['auditor_firm'] ) : null,
				'auditor_contact_id' => isset( $data['auditor_contact_id'] ) ? absint( $data['auditor_contact_id'] ) : null,
				'audit_start_date' => isset( $data['audit_start_date'] ) ? sanitize_text_field( $data['audit_start_date'] ) : null,
				'audit_end_date' => isset( $data['audit_end_date'] ) ? sanitize_text_field( $data['audit_end_date'] ) : null,
				'fieldwork_start' => isset( $data['fieldwork_start'] ) ? sanitize_text_field( $data['fieldwork_start'] ) : null,
				'fieldwork_end' => isset( $data['fieldwork_end'] ) ? sanitize_text_field( $data['fieldwork_end'] ) : null,
				'report_date' => isset( $data['report_date'] ) ? sanitize_text_field( $data['report_date'] ) : null,
				'status' => sanitize_text_field( $data['status'] ),
				'opinion' => isset( $data['opinion'] ) ? sanitize_text_field( $data['opinion'] ) : null,
				'fee' => isset( $data['fee'] ) ? floatval( $data['fee'] ) : null,
				'engagement_letter_signed' => absint( $data['engagement_letter_signed'] ),
				'management_letter_received' => absint( $data['management_letter_received'] ),
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create audit', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'audits' );
		return $wpdb->insert_id;
	}

	/**
	 * Get audits
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of audits or error
	 */
	public static function get_audits( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'audit_year' => null,
			'status' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$where_values = array();

		if ( $args['audit_year'] ) {
			$where[] = 'audit_year = %d';
			$where_values[] = absint( $args['audit_year'] );
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$where_values[] = sanitize_text_field( $args['status'] );
		}

		// Use caching for audit lists
		$cache_key = NonprofitSuite_Cache::list_key( 'audits', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where, $where_values, $args ) {
			$sql = "SELECT id, audit_year, audit_type, auditor_firm, auditor_contact_id,
			               audit_start_date, audit_end_date, fieldwork_start, fieldwork_end,
			               report_date, status, opinion, fee, engagement_letter_signed,
			               management_letter_received, notes, created_at
			        FROM {$wpdb->prefix}ns_audits
			        WHERE " . implode( ' AND ', $where ) . "
			        ORDER BY audit_year DESC, created_at DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $where_values ) ) {
				$sql = $wpdb->prepare( $sql, $where_values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Get single audit
	 *
	 * @param int $audit_id Audit ID
	 * @return object|WP_Error Audit object or error
	 */
	public static function get_audit( $audit_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Use caching for individual audits
		$cache_key = NonprofitSuite_Cache::item_key( 'audit', $audit_id );
		$audit = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $audit_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, audit_year, audit_type, auditor_firm, auditor_contact_id,
				        audit_start_date, audit_end_date, fieldwork_start, fieldwork_end,
				        report_date, status, opinion, fee, engagement_letter_signed,
				        management_letter_received, notes, created_at
				 FROM {$wpdb->prefix}ns_audits
				 WHERE id = %d",
				absint( $audit_id )
			) );
		}, 300 );

		if ( ! $audit ) {
			return new WP_Error( 'not_found', __( 'Audit not found', 'nonprofitsuite' ) );
		}

		return $audit;
	}

	/**
	 * Update audit
	 *
	 * @param int   $audit_id Audit ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_audit( $audit_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage audits' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$update_data = array();
		$format = array();

		$allowed_fields = array(
			'audit_year' => '%d',
			'audit_type' => '%s',
			'auditor_firm' => '%s',
			'auditor_contact_id' => '%d',
			'audit_start_date' => '%s',
			'audit_end_date' => '%s',
			'fieldwork_start' => '%s',
			'fieldwork_end' => '%s',
			'report_date' => '%s',
			'status' => '%s',
			'opinion' => '%s',
			'fee' => '%f',
			'engagement_letter_signed' => '%d',
			'management_letter_received' => '%d',
			'notes' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( in_array( $allowed_fields[ $key ], array( '%d' ) ) ) {
					$update_data[ $key ] = absint( $value );
				} elseif ( $allowed_fields[ $key ] === '%f' ) {
					$update_data[ $key ] = floatval( $value );
				} elseif ( $key === 'notes' ) {
					$update_data[ $key ] = wp_kses_post( $value );
				} else {
					$update_data[ $key ] = sanitize_text_field( $value );
				}
				$format[] = $allowed_fields[ $key ];
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No valid data to update', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_audits',
			$update_data,
			array( 'id' => absint( $audit_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'audits' );
		}

		return $result !== false;
	}

	/**
	 * Create audit request
	 *
	 * @param array $data Request data
	 * @return int|WP_Error Request ID or error
	 */
	public static function create_request( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage audits' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_audit_requests',
			array(
				'audit_id' => absint( $data['audit_id'] ),
				'request_number' => isset( $data['request_number'] ) ? sanitize_text_field( $data['request_number'] ) : null,
				'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
				'description' => wp_kses_post( $data['description'] ),
				'requested_date' => sanitize_text_field( $data['requested_date'] ),
				'due_date' => isset( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : null,
				'assigned_to' => isset( $data['assigned_to'] ) ? absint( $data['assigned_to'] ) : null,
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'pending',
				'response' => isset( $data['response'] ) ? wp_kses_post( $data['response'] ) : null,
				'completed_date' => isset( $data['completed_date'] ) ? sanitize_text_field( $data['completed_date'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create request', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'audit_requests' );
		return $wpdb->insert_id;
	}

	/**
	 * Get audit requests
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of requests or error
	 */
	public static function get_requests( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'audit_id' => null,
			'status' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$where_values = array();

		if ( $args['audit_id'] ) {
			$where[] = 'audit_id = %d';
			$where_values[] = absint( $args['audit_id'] );
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$where_values[] = sanitize_text_field( $args['status'] );
		}

		// Use caching for audit request lists
		$cache_key = NonprofitSuite_Cache::list_key( 'audit_requests', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where, $where_values, $args ) {
			$sql = "SELECT id, audit_id, request_number, category, description, requested_date,
			               due_date, assigned_to, status, response, completed_date, notes, created_at
			        FROM {$wpdb->prefix}ns_audit_requests
			        WHERE " . implode( ' AND ', $where ) . "
			        ORDER BY due_date ASC, created_at DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $where_values ) ) {
				$sql = $wpdb->prepare( $sql, $where_values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Update request
	 *
	 * @param int   $request_id Request ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_request( $request_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage audits' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$update_data = array();
		$format = array();

		$allowed_fields = array(
			'request_number' => '%s',
			'category' => '%s',
			'description' => '%s',
			'requested_date' => '%s',
			'due_date' => '%s',
			'assigned_to' => '%d',
			'status' => '%s',
			'response' => '%s',
			'completed_date' => '%s',
			'notes' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%d' ) {
					$update_data[ $key ] = absint( $value );
				} elseif ( in_array( $key, array( 'description', 'response', 'notes' ) ) ) {
					$update_data[ $key ] = wp_kses_post( $value );
				} else {
					$update_data[ $key ] = sanitize_text_field( $value );
				}
				$format[] = $allowed_fields[ $key ];
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No valid data to update', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_audit_requests',
			$update_data,
			array( 'id' => absint( $request_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'audit_requests' );
		}

		return $result !== false;
	}

	/**
	 * Create audit finding
	 *
	 * @param array $data Finding data
	 * @return int|WP_Error Finding ID or error
	 */
	public static function create_finding( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage audits' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_audit_findings',
			array(
				'audit_id' => absint( $data['audit_id'] ),
				'finding_number' => isset( $data['finding_number'] ) ? sanitize_text_field( $data['finding_number'] ) : null,
				'finding_type' => sanitize_text_field( $data['finding_type'] ),
				'severity' => sanitize_text_field( $data['severity'] ),
				'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
				'description' => wp_kses_post( $data['description'] ),
				'recommendation' => isset( $data['recommendation'] ) ? wp_kses_post( $data['recommendation'] ) : null,
				'management_response' => isset( $data['management_response'] ) ? wp_kses_post( $data['management_response'] ) : null,
				'corrective_action_plan' => isset( $data['corrective_action_plan'] ) ? wp_kses_post( $data['corrective_action_plan'] ) : null,
				'responsible_person' => isset( $data['responsible_person'] ) ? absint( $data['responsible_person'] ) : null,
				'target_completion_date' => isset( $data['target_completion_date'] ) ? sanitize_text_field( $data['target_completion_date'] ) : null,
				'actual_completion_date' => isset( $data['actual_completion_date'] ) ? sanitize_text_field( $data['actual_completion_date'] ) : null,
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'open',
				'follow_up_notes' => isset( $data['follow_up_notes'] ) ? wp_kses_post( $data['follow_up_notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create finding', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'audit_findings' );
		return $wpdb->insert_id;
	}

	/**
	 * Get audit findings
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of findings or error
	 */
	public static function get_findings( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'audit_id' => null,
			'status' => null,
			'severity' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$where_values = array();

		if ( $args['audit_id'] ) {
			$where[] = 'audit_id = %d';
			$where_values[] = absint( $args['audit_id'] );
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$where_values[] = sanitize_text_field( $args['status'] );
		}

		if ( $args['severity'] ) {
			$where[] = 'severity = %s';
			$where_values[] = sanitize_text_field( $args['severity'] );
		}

		// Use caching for audit findings lists
		$cache_key = NonprofitSuite_Cache::list_key( 'audit_findings', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where, $where_values, $args ) {
			$sql = "SELECT id, audit_id, finding_number, finding_type, severity, category,
			               description, recommendation, management_response, corrective_action_plan,
			               responsible_person, target_completion_date, actual_completion_date,
			               status, follow_up_notes, created_at
			        FROM {$wpdb->prefix}ns_audit_findings
			        WHERE " . implode( ' AND ', $where ) . "
			        ORDER BY severity DESC, created_at DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $where_values ) ) {
				$sql = $wpdb->prepare( $sql, $where_values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Update finding
	 *
	 * @param int   $finding_id Finding ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_finding( $finding_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage audits' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$update_data = array();
		$format = array();

		$allowed_fields = array(
			'finding_number' => '%s',
			'finding_type' => '%s',
			'severity' => '%s',
			'category' => '%s',
			'description' => '%s',
			'recommendation' => '%s',
			'management_response' => '%s',
			'corrective_action_plan' => '%s',
			'responsible_person' => '%d',
			'target_completion_date' => '%s',
			'actual_completion_date' => '%s',
			'status' => '%s',
			'follow_up_notes' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%d' ) {
					$update_data[ $key ] = absint( $value );
				} elseif ( in_array( $key, array( 'description', 'recommendation', 'management_response', 'corrective_action_plan', 'follow_up_notes' ) ) ) {
					$update_data[ $key ] = wp_kses_post( $value );
				} else {
					$update_data[ $key ] = sanitize_text_field( $value );
				}
				$format[] = $allowed_fields[ $key ];
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No valid data to update', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_audit_findings',
			$update_data,
			array( 'id' => absint( $finding_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'audit_findings' );
		}

		return $result !== false;
	}

	/**
	 * Get audit dashboard data
	 *
	 * @return array Dashboard metrics
	 */
	public static function get_dashboard_data() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$current_year = date( 'Y' );

		// Use caching for dashboard data
		$cache_key = NonprofitSuite_Cache::list_key( 'audit_dashboard', array( 'year' => $current_year ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $current_year ) {
			$current_audit = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, audit_year, audit_type, auditor_firm, status, opinion, created_at
				 FROM {$wpdb->prefix}ns_audits
				 WHERE audit_year = %d
				 ORDER BY created_at DESC
				 LIMIT 1",
				$current_year
			) );

			$pending_requests = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_audit_requests
				WHERE status IN ('pending', 'in_progress') AND audit_id IN (SELECT id FROM {$wpdb->prefix}ns_audits WHERE audit_year = %d)",
				$current_year
			) );

			$open_findings = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_audit_findings
				WHERE status = 'open' AND audit_id IN (SELECT id FROM {$wpdb->prefix}ns_audits WHERE audit_year = %d)",
				$current_year
			) );

			return array(
				'current_audit' => $current_audit,
				'pending_requests' => absint( $pending_requests ),
				'open_findings' => absint( $open_findings ),
			);
		}, 300 );
	}
}
