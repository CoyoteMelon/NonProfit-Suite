<?php
/**
 * Service Delivery Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Service_Delivery {

	/**
	 * Create client
	 *
	 * @param array $data Client data
	 * @return int|WP_Error Client ID or error
	 */
	public static function create_client( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage service delivery' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_clients',
			array(
				'client_number' => isset( $data['client_number'] ) ? sanitize_text_field( $data['client_number'] ) : self::generate_client_number(),
				'first_name' => sanitize_text_field( $data['first_name'] ),
				'last_name' => sanitize_text_field( $data['last_name'] ),
				'date_of_birth' => isset( $data['date_of_birth'] ) ? sanitize_text_field( $data['date_of_birth'] ) : null,
				'gender' => isset( $data['gender'] ) ? sanitize_text_field( $data['gender'] ) : null,
				'email' => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : null,
				'phone' => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null,
				'address' => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : null,
				'city' => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null,
				'state' => isset( $data['state'] ) ? sanitize_text_field( $data['state'] ) : null,
				'zip_code' => isset( $data['zip_code'] ) ? sanitize_text_field( $data['zip_code'] ) : null,
				'intake_date' => isset( $data['intake_date'] ) ? sanitize_text_field( $data['intake_date'] ) : current_time( 'mysql' ),
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
				'risk_level' => isset( $data['risk_level'] ) ? sanitize_text_field( $data['risk_level'] ) : null,
				'primary_need' => isset( $data['primary_need'] ) ? sanitize_text_field( $data['primary_need'] ) : null,
				'referral_source' => isset( $data['referral_source'] ) ? sanitize_text_field( $data['referral_source'] ) : null,
				'case_manager_id' => isset( $data['case_manager_id'] ) ? absint( $data['case_manager_id'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
				'privacy_consent' => isset( $data['privacy_consent'] ) ? absint( $data['privacy_consent'] ) : 0,
				'data_sharing_consent' => isset( $data['data_sharing_consent'] ) ? absint( $data['data_sharing_consent'] ) : 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create client', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'clients' );
		return $wpdb->insert_id;
	}

	/**
	 * Generate unique client number
	 *
	 * @return string Client number
	 */
	private static function generate_client_number() {
		global $wpdb;

		do {
			$number = 'CL' . date( 'Y' ) . str_pad( wp_rand( 1, 9999 ), 4, '0', STR_PAD_LEFT );
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ns_clients WHERE client_number = %s",
				$number
			) );
		} while ( $exists );

		return $number;
	}

	/**
	 * Get clients
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of clients or error
	 */
	public static function get_clients( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'status' => null,
			'case_manager_id' => null,
			'risk_level' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( $args['case_manager_id'] ) {
			$where[] = 'case_manager_id = %d';
			$values[] = absint( $args['case_manager_id'] );
		}

		if ( $args['risk_level'] ) {
			$where[] = 'risk_level = %s';
			$values[] = sanitize_text_field( $args['risk_level'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for clients
		$cache_key = NonprofitSuite_Cache::list_key( 'clients', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, client_number, first_name, last_name, date_of_birth, gender, email, phone,
			               address, city, state, zip_code, intake_date, status, risk_level, primary_need,
			               referral_source, case_manager_id, notes, privacy_consent, data_sharing_consent, created_at
			        FROM {$wpdb->prefix}ns_clients
					WHERE $where_clause
					ORDER BY intake_date DESC
					" . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Get single client
	 *
	 * @param int $client_id Client ID
	 * @return object|WP_Error Client object or error
	 */
	public static function get_client( $client_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Use caching for single client
		$cache_key = NonprofitSuite_Cache::item_key( 'client', $client_id );
		$client = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $client_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, client_number, first_name, last_name, date_of_birth, gender, email, phone,
				        address, city, state, zip_code, intake_date, status, risk_level, primary_need,
				        referral_source, case_manager_id, notes, privacy_consent, data_sharing_consent, created_at
				 FROM {$wpdb->prefix}ns_clients WHERE id = %d",
				absint( $client_id )
			) );
		}, 300 );

		if ( ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found', 'nonprofitsuite' ) );
		}

		return $client;
	}

	/**
	 * Update client
	 *
	 * @param int   $client_id Client ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_client( $client_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage service delivery' );
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
			'first_name' => '%s',
			'last_name' => '%s',
			'date_of_birth' => '%s',
			'gender' => '%s',
			'email' => '%s',
			'phone' => '%s',
			'address' => '%s',
			'city' => '%s',
			'state' => '%s',
			'zip_code' => '%s',
			'status' => '%s',
			'risk_level' => '%s',
			'primary_need' => '%s',
			'referral_source' => '%s',
			'case_manager_id' => '%d',
			'notes' => '%s',
			'privacy_consent' => '%d',
			'data_sharing_consent' => '%d',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%d' ) {
					$update_data[ $key ] = absint( $value );
				} elseif ( $key === 'email' ) {
					$update_data[ $key ] = sanitize_email( $value );
				} elseif ( $key === 'notes' ) {
					$update_data[ $key ] = wp_kses_post( $value );
				} elseif ( $key === 'address' ) {
					$update_data[ $key ] = sanitize_textarea_field( $value );
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
			$wpdb->prefix . 'ns_clients',
			$update_data,
			array( 'id' => absint( $client_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'clients' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'client', $client_id ) );
		}

		return $result !== false;
	}

	/**
	 * Record service delivery
	 *
	 * @param array $data Service record data
	 * @return int|WP_Error Service record ID or error
	 */
	public static function record_service( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage service delivery' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_service_records',
			array(
				'client_id' => absint( $data['client_id'] ),
				'service_date' => sanitize_text_field( $data['service_date'] ),
				'service_type' => sanitize_text_field( $data['service_type'] ),
				'service_category' => isset( $data['service_category'] ) ? sanitize_text_field( $data['service_category'] ) : null,
				'duration_minutes' => isset( $data['duration_minutes'] ) ? absint( $data['duration_minutes'] ) : null,
				'provider_id' => isset( $data['provider_id'] ) ? absint( $data['provider_id'] ) : null,
				'location' => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : null,
				'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
				'outcome' => isset( $data['outcome'] ) ? sanitize_text_field( $data['outcome'] ) : null,
				'follow_up_needed' => isset( $data['follow_up_needed'] ) ? absint( $data['follow_up_needed'] ) : 0,
				'follow_up_date' => isset( $data['follow_up_date'] ) ? sanitize_text_field( $data['follow_up_date'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to record service', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'service_records' );
		return $wpdb->insert_id;
	}

	/**
	 * Get service records
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of service records or error
	 */
	public static function get_service_records( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'client_id' => null,
			'service_type' => null,
			'provider_id' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['client_id'] ) {
			$where[] = 'client_id = %d';
			$values[] = absint( $args['client_id'] );
		}

		if ( $args['service_type'] ) {
			$where[] = 'service_type = %s';
			$values[] = sanitize_text_field( $args['service_type'] );
		}

		if ( $args['provider_id'] ) {
			$where[] = 'provider_id = %d';
			$values[] = absint( $args['provider_id'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for service records
		$cache_key = NonprofitSuite_Cache::list_key( 'service_records', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, client_id, service_date, service_type, service_category, duration_minutes,
			               provider_id, location, description, outcome, follow_up_needed, follow_up_date,
			               notes, created_at
			        FROM {$wpdb->prefix}ns_service_records
					WHERE $where_clause
					ORDER BY service_date DESC
					" . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Create client goal
	 *
	 * @param array $data Goal data
	 * @return int|WP_Error Goal ID or error
	 */
	public static function create_goal( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage service delivery' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_client_goals',
			array(
				'client_id' => absint( $data['client_id'] ),
				'goal_category' => sanitize_text_field( $data['goal_category'] ),
				'goal_description' => wp_kses_post( $data['goal_description'] ),
				'target_date' => isset( $data['target_date'] ) ? sanitize_text_field( $data['target_date'] ) : null,
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
				'progress_percentage' => isset( $data['progress_percentage'] ) ? absint( $data['progress_percentage'] ) : 0,
				'measurement_method' => isset( $data['measurement_method'] ) ? wp_kses_post( $data['measurement_method'] ) : null,
				'barriers' => isset( $data['barriers'] ) ? wp_kses_post( $data['barriers'] ) : null,
				'action_steps' => isset( $data['action_steps'] ) ? wp_kses_post( $data['action_steps'] ) : null,
				'achieved_date' => isset( $data['achieved_date'] ) ? sanitize_text_field( $data['achieved_date'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create goal', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'client_goals' );
		return $wpdb->insert_id;
	}

	/**
	 * Get client goals
	 *
	 * @param int $client_id Client ID
	 * @return array Array of goals
	 */
	public static function get_goals( $client_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for client goals
		$cache_key = NonprofitSuite_Cache::list_key( 'client_goals', array( 'client_id' => $client_id ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $client_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, client_id, goal_category, goal_description, target_date, status,
				        progress_percentage, measurement_method, barriers, action_steps,
				        achieved_date, notes, created_at
				 FROM {$wpdb->prefix}ns_client_goals WHERE client_id = %d ORDER BY created_at DESC",
				absint( $client_id )
			) );
		}, 300 );
	}

	/**
	 * Update goal progress
	 *
	 * @param int   $goal_id Goal ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_goal( $goal_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage service delivery' );
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
			'goal_category' => '%s',
			'goal_description' => '%s',
			'target_date' => '%s',
			'status' => '%s',
			'progress_percentage' => '%d',
			'measurement_method' => '%s',
			'barriers' => '%s',
			'action_steps' => '%s',
			'achieved_date' => '%s',
			'notes' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%d' ) {
					$update_data[ $key ] = absint( $value );
				} elseif ( in_array( $key, array( 'goal_description', 'measurement_method', 'barriers', 'action_steps', 'notes' ) ) ) {
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
			$wpdb->prefix . 'ns_client_goals',
			$update_data,
			array( 'id' => absint( $goal_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'client_goals' );
		}

		return $result !== false;
	}

	/**
	 * Get dashboard data
	 *
	 * @return array Dashboard metrics
	 */
	public static function get_dashboard_data() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for service delivery dashboard
		$cache_key = NonprofitSuite_Cache::item_key( 'service_delivery_dashboard', 'summary' );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb ) {
			$active_clients = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_clients WHERE status = 'active'"
			);

			$services_this_month = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_service_records
				WHERE service_date >= %s",
				date( 'Y-m-01' )
			) );

			$high_risk_clients = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_clients WHERE status = 'active' AND risk_level = 'high'"
			);

			$recent_clients = $wpdb->get_results(
				"SELECT id, client_number, first_name, last_name, date_of_birth, gender, email, phone,
				        address, city, state, zip_code, intake_date, status, risk_level, primary_need,
				        referral_source, case_manager_id, notes, privacy_consent, data_sharing_consent, created_at
				 FROM {$wpdb->prefix}ns_clients ORDER BY intake_date DESC LIMIT 10"
			);

			return array(
				'active_clients' => absint( $active_clients ),
				'services_this_month' => absint( $services_this_month ),
				'high_risk_clients' => absint( $high_risk_clients ),
				'recent_clients' => $recent_clients,
			);
		}, 300 );
	}

	/**
	 * Get services needing follow-up
	 *
	 * @return array Array of service records
	 */
	public static function get_follow_ups_needed() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results(
			"SELECT sr.*, c.first_name, c.last_name
			FROM {$wpdb->prefix}ns_service_records sr
			LEFT JOIN {$wpdb->prefix}ns_clients c ON sr.client_id = c.id
			WHERE sr.follow_up_needed = 1
			AND (sr.follow_up_date IS NULL OR sr.follow_up_date <= CURDATE())
			ORDER BY sr.follow_up_date ASC, sr.service_date ASC
			LIMIT 50"
		);
	}
}
