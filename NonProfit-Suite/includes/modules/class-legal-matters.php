<?php
/**
 * Legal Matters Management Module
 *
 * Track legal cases, issues, priorities, and costs.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Legal_Matters {

	/**
	 * Create legal matter
	 *
	 * @param array $data Matter data
	 * @return int|WP_Error Matter ID or error
	 */
	public static function create_matter( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_legal_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_legal_matters',
			array(
				'access_id' => absint( $data['access_id'] ),
				'matter_number' => isset( $data['matter_number'] ) ? sanitize_text_field( $data['matter_number'] ) : null,
				'matter_name' => sanitize_text_field( $data['matter_name'] ),
				'matter_type' => sanitize_text_field( $data['matter_type'] ),
				'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
				'priority' => isset( $data['priority'] ) ? sanitize_text_field( $data['priority'] ) : 'medium',
				'opened_date' => isset( $data['opened_date'] ) ? sanitize_text_field( $data['opened_date'] ) : current_time( 'mysql' ),
				'closed_date' => isset( $data['closed_date'] ) ? sanitize_text_field( $data['closed_date'] ) : null,
				'estimated_cost' => isset( $data['estimated_cost'] ) ? floatval( $data['estimated_cost'] ) : null,
				'actual_cost' => isset( $data['actual_cost'] ) ? floatval( $data['actual_cost'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create legal matter', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'legal_matters' );
		return $wpdb->insert_id;
	}

	/**
	 * Get legal matters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of matters or error
	 */
	public static function get_matters( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'access_id' => null,
			'status' => null,
			'matter_type' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['access_id'] ) {
			$where[] = 'access_id = %d';
			$values[] = absint( $args['access_id'] );
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( $args['matter_type'] ) {
			$where[] = 'matter_type = %s';
			$values[] = sanitize_text_field( $args['matter_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for legal matters
		$cache_key = NonprofitSuite_Cache::list_key( 'legal_matters', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, access_id, matter_number, matter_name, matter_type, description,
			               status, priority, opened_date, closed_date, estimated_cost, actual_cost,
			               notes, created_at
			        FROM {$wpdb->prefix}ns_legal_matters
					WHERE $where_clause
					ORDER BY opened_date DESC
					" . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Update legal matter
	 *
	 * @param int   $matter_id Matter ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_matter( $matter_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_legal_access();
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
			'matter_number' => '%s',
			'matter_name' => '%s',
			'matter_type' => '%s',
			'description' => '%s',
			'status' => '%s',
			'priority' => '%s',
			'opened_date' => '%s',
			'closed_date' => '%s',
			'estimated_cost' => '%f',
			'actual_cost' => '%f',
			'notes' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%f' ) {
					$update_data[ $key ] = floatval( $value );
				} elseif ( in_array( $key, array( 'description', 'notes' ) ) ) {
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
			$wpdb->prefix . 'ns_legal_matters',
			$update_data,
			array( 'id' => absint( $matter_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'legal_matters' );
		}

		return $result !== false;
	}

	/**
	 * Get dashboard data for legal counsel portal
	 *
	 * @param int $user_id Attorney user ID
	 * @return array Dashboard data
	 */
	public static function get_dashboard_data( $user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for legal counsel dashboard
		$cache_key = NonprofitSuite_Cache::item_key( 'legal_dashboard', $user_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $user_id ) {
			// Get legal access record
			$access = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, user_id, firm_name, attorney_name, attorney_email, attorney_phone,
				        bar_number, specialization, access_level, granted_date, expiration_date,
				        status, notes, revoked_date, created_at
				 FROM {$wpdb->prefix}ns_legal_access WHERE user_id = %d AND status = 'active' LIMIT 1",
				absint( $user_id )
			) );

			if ( ! $access ) {
				return array();
			}

			// Get matter counts by status
			$active_matters = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_legal_matters WHERE access_id = %d AND status = 'active'",
				$access->id
			) );

			$closed_matters = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_legal_matters WHERE access_id = %d AND status = 'closed'",
				$access->id
			) );

			// Get recent matters
			$recent_matters = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, access_id, matter_number, matter_name, matter_type, description,
				        status, priority, opened_date, closed_date, estimated_cost, actual_cost,
				        notes, created_at
				 FROM {$wpdb->prefix}ns_legal_matters WHERE access_id = %d ORDER BY opened_date DESC LIMIT 10",
				$access->id
			) );

			// Get total costs
			$total_costs = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(actual_cost) FROM {$wpdb->prefix}ns_legal_matters WHERE access_id = %d",
				$access->id
			) );

			return array(
				'access' => $access,
				'active_matters' => absint( $active_matters ),
				'closed_matters' => absint( $closed_matters ),
				'recent_matters' => $recent_matters,
				'total_costs' => floatval( $total_costs ),
			);
		}, 300 );
	}

	/**
	 * Get matters by type
	 *
	 * @param int    $access_id Access ID
	 * @param string $matter_type Matter type
	 * @return array Array of matters
	 */
	public static function get_matters_by_type( $access_id, $matter_type ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for matters by type
		$cache_key = NonprofitSuite_Cache::list_key( 'legal_matters_by_type', array( 'access_id' => $access_id, 'matter_type' => $matter_type ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $access_id, $matter_type ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, access_id, matter_number, matter_name, matter_type, description,
				        status, priority, opened_date, closed_date, estimated_cost, actual_cost,
				        notes, created_at
				 FROM {$wpdb->prefix}ns_legal_matters
				WHERE access_id = %d AND matter_type = %s
				ORDER BY opened_date DESC",
				absint( $access_id ),
				sanitize_text_field( $matter_type )
			) );
		}, 300 );
	}

	/**
	 * Get high priority matters
	 *
	 * @param int $access_id Access ID
	 * @return array Array of high priority matters
	 */
	public static function get_high_priority_matters( $access_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for high priority matters
		$cache_key = NonprofitSuite_Cache::list_key( 'legal_matters_high_priority', array( 'access_id' => $access_id ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $access_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, access_id, matter_number, matter_name, matter_type, description,
				        status, priority, opened_date, closed_date, estimated_cost, actual_cost,
				        notes, created_at
				 FROM {$wpdb->prefix}ns_legal_matters
				WHERE access_id = %d AND priority = 'high' AND status = 'active'
				ORDER BY opened_date ASC",
				absint( $access_id )
			) );
		}, 300 );
	}
}
