<?php
/**
 * State Compliance Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_State_Compliance {

	/**
	 * Add state operation
	 *
	 * @param array $data State operation data
	 * @return int|WP_Error Operation ID or error
	 */
	public static function add_state_operation( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage compliance' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Check if state already exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ns_state_operations WHERE state_code = %s",
			sanitize_text_field( $data['state_code'] )
		) );

		if ( $existing ) {
			return new WP_Error( 'duplicate', __( 'Organization already operates in this state', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_state_operations',
			array(
				'state_code' => sanitize_text_field( $data['state_code'] ),
				'state_name' => sanitize_text_field( $data['state_name'] ),
				'operation_type' => sanitize_text_field( $data['operation_type'] ),
				'registration_date' => isset( $data['registration_date'] ) ? sanitize_text_field( $data['registration_date'] ) : null,
				'registration_number' => isset( $data['registration_number'] ) ? sanitize_text_field( $data['registration_number'] ) : null,
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
				'registered_agent' => isset( $data['registered_agent'] ) ? sanitize_text_field( $data['registered_agent'] ) : null,
				'registered_agent_address' => isset( $data['registered_agent_address'] ) ? sanitize_textarea_field( $data['registered_agent_address'] ) : null,
				'annual_report_due' => isset( $data['annual_report_due'] ) ? sanitize_text_field( $data['annual_report_due'] ) : null,
				'charitable_registration_number' => isset( $data['charitable_registration_number'] ) ? sanitize_text_field( $data['charitable_registration_number'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to add state operation', 'nonprofitsuite' ) );
		}

		$operation_id = $wpdb->insert_id;

		// Auto-generate compliance requirements from state JSON
		self::generate_requirements_from_json( $operation_id, $data['state_code'] );

		NonprofitSuite_Cache::invalidate_module( 'state_operations' );
		return $operation_id;
	}

	/**
	 * Get state operations
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of state operations or error
	 */
	public static function get_state_operations( $args = array() ) {
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

		// Use caching for state operations
		$cache_key = NonprofitSuite_Cache::list_key( 'state_operations', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, state_code, state_name, operation_type, registration_date, registration_number,
			               status, registered_agent, registered_agent_address, annual_report_due,
			               charitable_registration_number, notes, created_at
			        FROM {$wpdb->prefix}ns_state_operations
			        WHERE $where_clause
			        ORDER BY state_name ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Update state operation
	 *
	 * @param int   $operation_id Operation ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_state_operation( $operation_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage compliance' );
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
			'operation_type' => '%s',
			'registration_date' => '%s',
			'registration_number' => '%s',
			'status' => '%s',
			'registered_agent' => '%s',
			'registered_agent_address' => '%s',
			'annual_report_due' => '%s',
			'charitable_registration_number' => '%s',
			'notes' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $key === 'registered_agent_address' ) {
					$update_data[ $key ] = sanitize_textarea_field( $value );
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
			$wpdb->prefix . 'ns_state_operations',
			$update_data,
			array( 'id' => absint( $operation_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'state_operations' );
		}

		return $result !== false;
	}

	/**
	 * Generate compliance requirements from state JSON
	 *
	 * @param int    $operation_id Operation ID
	 * @param string $state_code State code
	 * @return bool True on success
	 */
	private static function generate_requirements_from_json( $operation_id, $state_code ) {
		// 1. Validate state code format (must be exactly 2 letters)
		if ( ! preg_match( '/^[A-Z]{2}$/i', $state_code ) ) {
			error_log( 'NonprofitSuite: Invalid state code format: ' . $state_code );
			return false;
		}

		// 2. Sanitize and normalize state code
		$state_code = strtoupper( sanitize_text_field( $state_code ) );
		$state_code_lower = strtolower( $state_code );

		// 3. Build path safely
		$json_file = NONPROFITSUITE_PLUGIN_DIR . 'data/states/' . $state_code_lower . '.json';

		// 4. Use realpath to resolve path and verify it's within plugin directory
		$real_path = realpath( $json_file );
		$plugin_dir = realpath( NONPROFITSUITE_PLUGIN_DIR );

		// Ensure the resolved path exists and starts with the plugin directory
		if ( ! $real_path || strpos( $real_path, $plugin_dir ) !== 0 ) {
			error_log( 'NonprofitSuite: Invalid file path detected for state: ' . $state_code );
			return false;
		}

		// 5. Verify file exists (redundant but safe)
		if ( ! file_exists( $real_path ) ) {
			return false;
		}

		$json_data = file_get_contents( $real_path );
		$state_data = json_decode( $json_data, true );

		if ( ! $state_data || ! isset( $state_data['requirements'] ) ) {
			return false;
		}

		global $wpdb;

		foreach ( $state_data['requirements'] as $requirement ) {
			$wpdb->insert(
				$wpdb->prefix . 'ns_state_requirements',
				array(
					'state_operation_id' => $operation_id,
					'requirement_type' => sanitize_text_field( $requirement['type'] ),
					'requirement_name' => sanitize_text_field( $requirement['name'] ),
					'description' => wp_kses_post( $requirement['description'] ),
					'frequency' => isset( $requirement['frequency'] ) ? sanitize_text_field( $requirement['frequency'] ) : null,
					'due_date' => isset( $requirement['due_date'] ) ? sanitize_text_field( $requirement['due_date'] ) : null,
					'filing_method' => isset( $requirement['filing_method'] ) ? sanitize_text_field( $requirement['filing_method'] ) : null,
					'fee_amount' => isset( $requirement['fee'] ) ? floatval( $requirement['fee'] ) : null,
					'agency' => isset( $requirement['agency'] ) ? sanitize_text_field( $requirement['agency'] ) : null,
					'website_url' => isset( $requirement['url'] ) ? esc_url_raw( $requirement['url'] ) : null,
					'status' => 'pending',
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Get requirements for state operation
	 *
	 * @param int $operation_id Operation ID
	 * @return array Array of requirements
	 */
	public static function get_requirements( $operation_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for state requirements
		$cache_key = NonprofitSuite_Cache::list_key( 'state_requirements', array( 'operation_id' => $operation_id ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $operation_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, state_operation_id, requirement_type, requirement_name, description, frequency,
				        due_date, filing_method, fee_amount, agency, website_url, status, completed_date,
				        next_due_date, confirmation_number, notes, created_at
				 FROM {$wpdb->prefix}ns_state_requirements WHERE state_operation_id = %d ORDER BY due_date ASC",
				absint( $operation_id )
			) );
		}, 300 );
	}

	/**
	 * Update requirement status
	 *
	 * @param int   $requirement_id Requirement ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_requirement( $requirement_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage compliance' );
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
			'status' => '%s',
			'completed_date' => '%s',
			'next_due_date' => '%s',
			'confirmation_number' => '%s',
			'notes' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $key === 'notes' ) {
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
			$wpdb->prefix . 'ns_state_requirements',
			$update_data,
			array( 'id' => absint( $requirement_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'state_requirements' );
		}

		return $result !== false;
	}

	/**
	 * Get compliance dashboard data
	 *
	 * @return array Dashboard data
	 */
	public static function get_dashboard_data() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$states_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_state_operations WHERE status = 'active'"
		);

		$pending_requirements = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_state_requirements WHERE status IN ('pending', 'in_progress')"
		);

		$overdue_requirements = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_state_requirements
			WHERE status IN ('pending', 'in_progress') AND due_date < %s",
			current_time( 'mysql' )
		) );

		$upcoming_deadlines = $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, s.state_name
			FROM {$wpdb->prefix}ns_state_requirements r
			LEFT JOIN {$wpdb->prefix}ns_state_operations s ON r.state_operation_id = s.id
			WHERE r.status IN ('pending', 'in_progress')
			AND r.due_date BETWEEN %s AND %s
			ORDER BY r.due_date ASC
			LIMIT 10",
			current_time( 'mysql' ),
			date( 'Y-m-d', strtotime( '+30 days' ) )
		) );

		return array(
			'states_count' => absint( $states_count ),
			'pending_requirements' => absint( $pending_requirements ),
			'overdue_requirements' => absint( $overdue_requirements ),
			'upcoming_deadlines' => $upcoming_deadlines,
		);
	}

	/**
	 * Get all pending requirements
	 *
	 * @return array Array of pending requirements with state info
	 */
	public static function get_pending_requirements() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results(
			"SELECT r.*, s.state_name, s.state_code
			FROM {$wpdb->prefix}ns_state_requirements r
			LEFT JOIN {$wpdb->prefix}ns_state_operations s ON r.state_operation_id = s.id
			WHERE r.status IN ('pending', 'in_progress')
			ORDER BY r.due_date ASC"
		);
	}

	/**
	 * Get overdue requirements
	 *
	 * @return array Array of overdue requirements
	 */
	public static function get_overdue_requirements() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, s.state_name, s.state_code
			FROM {$wpdb->prefix}ns_state_requirements r
			LEFT JOIN {$wpdb->prefix}ns_state_operations s ON r.state_operation_id = s.id
			WHERE r.status IN ('pending', 'in_progress')
			AND r.due_date < %s
			ORDER BY r.due_date ASC",
			current_time( 'mysql' )
		) );
	}

	/**
	 * Get requirements by type
	 *
	 * @param string $type Requirement type
	 * @return array Array of requirements
	 */
	public static function get_requirements_by_type( $type ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, s.state_name, s.state_code
			FROM {$wpdb->prefix}ns_state_requirements r
			LEFT JOIN {$wpdb->prefix}ns_state_operations s ON r.state_operation_id = s.id
			WHERE r.requirement_type = %s
			ORDER BY r.due_date ASC",
			sanitize_text_field( $type )
		) );
	}

	/**
	 * Load state data from JSON
	 *
	 * @param string $state_code State code
	 * @return array|bool State data or false
	 */
	public static function load_state_data( $state_code ) {
		$json_file = NONPROFITSUITE_PLUGIN_DIR . 'data/states/' . strtolower( $state_code ) . '.json';

		if ( ! file_exists( $json_file ) ) {
			return false;
		}

		$json_data = file_get_contents( $json_file );
		return json_decode( $json_data, true );
	}

	/**
	 * Get available states with JSON files
	 *
	 * @return array Array of available state codes
	 */
	public static function get_available_states() {
		$states_dir = NONPROFITSUITE_PLUGIN_DIR . 'data/states/';

		if ( ! is_dir( $states_dir ) ) {
			return array();
		}

		$files = glob( $states_dir . '*.json' );
		$states = array();

		foreach ( $files as $file ) {
			$state_code = strtoupper( basename( $file, '.json' ) );
			$states[] = $state_code;
		}

		return $states;
	}

	/**
	 * Calculate compliance rate
	 *
	 * @return float Compliance rate percentage
	 */
	public static function calculate_compliance_rate() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return 0;
		}

		global $wpdb;

		$total_requirements = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_state_requirements"
		);

		if ( ! $total_requirements ) {
			return 100;
		}

		$completed_requirements = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_state_requirements WHERE status = 'completed'"
		);

		return ( $completed_requirements / $total_requirements ) * 100;
	}
}
