<?php
/**
 * CPA Access Management Module
 *
 * Manage CPA portal access, permissions, and firm relationships.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_CPA_Access {

	/**
	 * Grant CPA access
	 *
	 * @param array $data Access data
	 * @return int|WP_Error Access ID or error
	 */
	public static function grant_access( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_cpa_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Check if user already has access
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ns_cpa_access WHERE user_id = %d AND status = 'active'",
			absint( $data['user_id'] )
		) );

		if ( $existing ) {
			return new WP_Error( 'duplicate', __( 'User already has active CPA access', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_cpa_access',
			array(
				'user_id' => absint( $data['user_id'] ),
				'firm_name' => sanitize_text_field( $data['firm_name'] ),
				'contact_name' => sanitize_text_field( $data['contact_name'] ),
				'contact_email' => sanitize_email( $data['contact_email'] ),
				'contact_phone' => isset( $data['contact_phone'] ) ? sanitize_text_field( $data['contact_phone'] ) : null,
				'access_level' => isset( $data['access_level'] ) ? sanitize_text_field( $data['access_level'] ) : 'full',
				'granted_date' => current_time( 'mysql' ),
				'expiration_date' => isset( $data['expiration_date'] ) ? sanitize_text_field( $data['expiration_date'] ) : null,
				'status' => 'active',
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to grant CPA access', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'cpa_access' );
		return $wpdb->insert_id;
	}

	/**
	 * Get CPA access records
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of access records or error
	 */
	public static function get_access_records( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'status' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for CPA access records
		$cache_key = NonprofitSuite_Cache::list_key( 'cpa_access', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, user_id, firm_name, contact_name, contact_email, contact_phone,
			               access_level, granted_date, expiration_date, status, notes,
			               revoked_date, created_at
			        FROM {$wpdb->prefix}ns_cpa_access
			        WHERE $where_clause
			        ORDER BY granted_date DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Revoke CPA access
	 *
	 * @param int $access_id Access ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function revoke_access( $access_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_cpa_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_cpa_access',
			array(
				'status' => 'revoked',
				'revoked_date' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $access_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'cpa_access' );
		}

		return $result !== false;
	}

	/**
	 * Check if user has CPA access
	 *
	 * @param int $user_id User ID
	 * @return bool True if has active access
	 */
	public static function has_access( $user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return false;
		}

		global $wpdb;

		$access = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ns_cpa_access
			WHERE user_id = %d AND status = 'active'
			AND (expiration_date IS NULL OR expiration_date > NOW())
			LIMIT 1",
			absint( $user_id )
		) );

		return ! empty( $access );
	}

	/**
	 * Get dashboard data for CPA portal
	 *
	 * @param int $user_id CPA user ID
	 * @return array Dashboard data
	 */
	public static function get_dashboard_data( $user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for CPA dashboard
		$cache_key = NonprofitSuite_Cache::item_key( 'cpa_dashboard', $user_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $user_id ) {
			// Get CPA access record
			$access = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, user_id, firm_name, contact_name, contact_email, contact_phone,
				        access_level, granted_date, expiration_date, status, notes,
				        revoked_date, created_at
				 FROM {$wpdb->prefix}ns_cpa_access WHERE user_id = %d AND status = 'active' LIMIT 1",
				absint( $user_id )
			) );

			if ( ! $access ) {
				return array();
			}

			// Get shared files count
			$files_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_cpa_shared_files WHERE access_id = %d",
				$access->id
			) );

			// Get recent files
			$recent_files = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, access_id, file_name, file_type, file_path, file_size, category,
				        description, shared_by, shared_date, download_count, last_downloaded, created_at
				 FROM {$wpdb->prefix}ns_cpa_shared_files WHERE access_id = %d ORDER BY shared_date DESC LIMIT 10",
				$access->id
			) );

			return array(
				'access' => $access,
				'files_count' => absint( $files_count ),
				'recent_files' => $recent_files,
			);
		}, 300 );
	}

	/**
	 * Get all CPA users
	 *
	 * @return array Array of CPA access records
	 */
	public static function get_cpa_users() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results(
			"SELECT ca.*, u.display_name, u.user_email
			FROM {$wpdb->prefix}ns_cpa_access ca
			JOIN {$wpdb->users} u ON ca.user_id = u.ID
			WHERE ca.status = 'active'
			ORDER BY ca.firm_name, ca.contact_name"
		);
	}
}
