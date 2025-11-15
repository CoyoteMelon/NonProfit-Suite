<?php
/**
 * Legal Access Management Module
 *
 * Manage attorney portal access, permissions, and firm relationships.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Legal_Access {

	/**
	 * Grant legal counsel access
	 *
	 * @param array $data Access data
	 * @return int|WP_Error Access ID or error
	 */
	public static function grant_access( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_legal_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Check if user already has access
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ns_legal_access WHERE user_id = %d AND status = 'active'",
			absint( $data['user_id'] )
		) );

		if ( $existing ) {
			return new WP_Error( 'duplicate', __( 'User already has active legal counsel access', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_legal_access',
			array(
				'user_id' => absint( $data['user_id'] ),
				'firm_name' => sanitize_text_field( $data['firm_name'] ),
				'attorney_name' => sanitize_text_field( $data['attorney_name'] ),
				'attorney_email' => sanitize_email( $data['attorney_email'] ),
				'attorney_phone' => isset( $data['attorney_phone'] ) ? sanitize_text_field( $data['attorney_phone'] ) : null,
				'bar_number' => isset( $data['bar_number'] ) ? sanitize_text_field( $data['bar_number'] ) : null,
				'specialization' => isset( $data['specialization'] ) ? sanitize_text_field( $data['specialization'] ) : null,
				'access_level' => isset( $data['access_level'] ) ? sanitize_text_field( $data['access_level'] ) : 'full',
				'granted_date' => current_time( 'mysql' ),
				'expiration_date' => isset( $data['expiration_date'] ) ? sanitize_text_field( $data['expiration_date'] ) : null,
				'status' => 'active',
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to grant legal counsel access', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'legal_access' );
		return $wpdb->insert_id;
	}

	/**
	 * Get legal access records
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

		// Use caching for legal access records
		$cache_key = NonprofitSuite_Cache::list_key( 'legal_access', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, user_id, firm_name, attorney_name, attorney_email, attorney_phone,
			               bar_number, specialization, access_level, granted_date, expiration_date,
			               status, notes, revoked_date, created_at
			        FROM {$wpdb->prefix}ns_legal_access
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
	 * Revoke legal access
	 *
	 * @param int $access_id Access ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function revoke_access( $access_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_legal_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_legal_access',
			array(
				'status' => 'revoked',
				'revoked_date' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $access_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'legal_access' );
		}

		return $result !== false;
	}

	/**
	 * Check if user has legal counsel access
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
			"SELECT id FROM {$wpdb->prefix}ns_legal_access
			WHERE user_id = %d AND status = 'active'
			AND (expiration_date IS NULL OR expiration_date > NOW())
			LIMIT 1",
			absint( $user_id )
		) );

		return ! empty( $access );
	}

	/**
	 * Get all attorneys
	 *
	 * @return array Array of attorney access records
	 */
	public static function get_attorneys() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results(
			"SELECT la.*, u.display_name, u.user_email
			FROM {$wpdb->prefix}ns_legal_access la
			JOIN {$wpdb->users} u ON la.user_id = u.ID
			WHERE la.status = 'active'
			ORDER BY la.firm_name, la.attorney_name"
		);
	}
}
