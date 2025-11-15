<?php
/**
 * Program Evaluation Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Program_Evaluation {

	/**
	 * Create evaluation
	 *
	 * @param array $data Evaluation data
	 * @return int|WP_Error Evaluation ID or error
	 */
	public static function create_evaluation( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage program evaluation' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_program_evaluations',
			array(
				'program_id' => isset( $data['program_id'] ) ? absint( $data['program_id'] ) : null,
				'evaluation_name' => sanitize_text_field( $data['evaluation_name'] ),
				'evaluation_type' => sanitize_text_field( $data['evaluation_type'] ),
				'framework' => isset( $data['framework'] ) ? sanitize_text_field( $data['framework'] ) : null,
				'start_date' => sanitize_text_field( $data['start_date'] ),
				'end_date' => isset( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'planning',
				'target_participants' => isset( $data['target_participants'] ) ? absint( $data['target_participants'] ) : null,
				'actual_participants' => isset( $data['actual_participants'] ) ? absint( $data['actual_participants'] ) : null,
				'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
				'methodology' => isset( $data['methodology'] ) ? wp_kses_post( $data['methodology'] ) : null,
				'findings' => isset( $data['findings'] ) ? wp_kses_post( $data['findings'] ) : null,
				'recommendations' => isset( $data['recommendations'] ) ? wp_kses_post( $data['recommendations'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create evaluation', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'program_evaluations' );
		return $wpdb->insert_id;
	}

	/**
	 * Get evaluations
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of evaluations or error
	 */
	public static function get_evaluations( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'program_id' => null,
			'status' => null,
			'evaluation_type' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['program_id'] ) {
			$where[] = 'program_id = %d';
			$values[] = absint( $args['program_id'] );
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( $args['evaluation_type'] ) {
			$where[] = 'evaluation_type = %s';
			$values[] = sanitize_text_field( $args['evaluation_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for program evaluations
		$cache_key = NonprofitSuite_Cache::list_key( 'program_evaluations', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, program_id, evaluation_name, evaluation_type, framework, start_date,
			               end_date, status, target_participants, actual_participants, description,
			               methodology, findings, recommendations, created_at
			        FROM {$wpdb->prefix}ns_program_evaluations
					WHERE $where_clause
					ORDER BY start_date DESC
					" . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Update evaluation
	 *
	 * @param int   $evaluation_id Evaluation ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_evaluation( $evaluation_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage program evaluation' );
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
			'program_id' => '%d',
			'evaluation_name' => '%s',
			'evaluation_type' => '%s',
			'framework' => '%s',
			'start_date' => '%s',
			'end_date' => '%s',
			'status' => '%s',
			'target_participants' => '%d',
			'actual_participants' => '%d',
			'description' => '%s',
			'methodology' => '%s',
			'findings' => '%s',
			'recommendations' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%d' ) {
					$update_data[ $key ] = absint( $value );
				} elseif ( in_array( $key, array( 'description', 'methodology', 'findings', 'recommendations' ) ) ) {
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
			$wpdb->prefix . 'ns_program_evaluations',
			$update_data,
			array( 'id' => absint( $evaluation_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'program_evaluations' );
		}

		return $result !== false;
	}

	/**
	 * Create evaluation metric
	 *
	 * @param array $data Metric data
	 * @return int|WP_Error Metric ID or error
	 */
	public static function create_metric( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage program evaluation' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_evaluation_metrics',
			array(
				'evaluation_id' => absint( $data['evaluation_id'] ),
				'metric_name' => sanitize_text_field( $data['metric_name'] ),
				'metric_type' => sanitize_text_field( $data['metric_type'] ),
				'measurement_unit' => isset( $data['measurement_unit'] ) ? sanitize_text_field( $data['measurement_unit'] ) : null,
				'target_value' => isset( $data['target_value'] ) ? floatval( $data['target_value'] ) : null,
				'baseline_value' => isset( $data['baseline_value'] ) ? floatval( $data['baseline_value'] ) : null,
				'current_value' => isset( $data['current_value'] ) ? floatval( $data['current_value'] ) : null,
				'data_source' => isset( $data['data_source'] ) ? sanitize_text_field( $data['data_source'] ) : null,
				'collection_frequency' => isset( $data['collection_frequency'] ) ? sanitize_text_field( $data['collection_frequency'] ) : null,
				'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create metric', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'evaluation_metrics' );
		return $wpdb->insert_id;
	}

	/**
	 * Get metrics for evaluation
	 *
	 * @param int $evaluation_id Evaluation ID
	 * @return array Array of metrics
	 */
	public static function get_metrics( $evaluation_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for evaluation metrics
		$cache_key = NonprofitSuite_Cache::list_key( 'evaluation_metrics', array( 'evaluation_id' => $evaluation_id ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $evaluation_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, evaluation_id, metric_name, metric_type, measurement_unit, target_value,
				        baseline_value, current_value, data_source, collection_frequency, description, created_at
				 FROM {$wpdb->prefix}ns_evaluation_metrics WHERE evaluation_id = %d ORDER BY created_at ASC",
				absint( $evaluation_id )
			) );
		}, 300 );
	}

	/**
	 * Update metric
	 *
	 * @param int   $metric_id Metric ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_metric( $metric_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage program evaluation' );
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
			'metric_name' => '%s',
			'metric_type' => '%s',
			'measurement_unit' => '%s',
			'target_value' => '%f',
			'baseline_value' => '%f',
			'current_value' => '%f',
			'data_source' => '%s',
			'collection_frequency' => '%s',
			'description' => '%s',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%f' ) {
					$update_data[ $key ] = floatval( $value );
				} elseif ( $key === 'description' ) {
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
			$wpdb->prefix . 'ns_evaluation_metrics',
			$update_data,
			array( 'id' => absint( $metric_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'evaluation_metrics' );
		}

		return $result !== false;
	}

	/**
	 * Record evaluation response
	 *
	 * @param array $data Response data
	 * @return int|WP_Error Response ID or error
	 */
	public static function record_response( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage program evaluation' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_evaluation_responses',
			array(
				'evaluation_id' => absint( $data['evaluation_id'] ),
				'respondent_id' => isset( $data['respondent_id'] ) ? absint( $data['respondent_id'] ) : null,
				'respondent_type' => isset( $data['respondent_type'] ) ? sanitize_text_field( $data['respondent_type'] ) : null,
				'response_date' => isset( $data['response_date'] ) ? sanitize_text_field( $data['response_date'] ) : current_time( 'mysql' ),
				'response_data' => isset( $data['response_data'] ) ? wp_json_encode( $data['response_data'] ) : null,
				'completion_status' => isset( $data['completion_status'] ) ? sanitize_text_field( $data['completion_status'] ) : 'complete',
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to record response', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'evaluation_responses' );
		return $wpdb->insert_id;
	}

	/**
	 * Get responses for evaluation
	 *
	 * @param int   $evaluation_id Evaluation ID
	 * @param array $args Additional query arguments
	 * @return array Array of responses
	 */
	public static function get_responses( $evaluation_id, $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Use caching for evaluation responses
		$cache_key = NonprofitSuite_Cache::list_key( 'evaluation_responses', array_merge( $args, array( 'evaluation_id' => $evaluation_id ) ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $evaluation_id, $args ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, evaluation_id, respondent_id, respondent_type, response_date, response_data,
				        completion_status, notes, created_at
				 FROM {$wpdb->prefix}ns_evaluation_responses
				WHERE evaluation_id = %d
				ORDER BY response_date DESC
				" . NonprofitSuite_Utilities::build_limit_clause( $args ),
				absint( $evaluation_id )
			) );
		}, 300 );
	}

	/**
	 * Record metric data point
	 *
	 * @param array $data Data point
	 * @return int|WP_Error Data ID or error
	 */
	public static function record_data( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage program evaluation' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_evaluation_data',
			array(
				'metric_id' => absint( $data['metric_id'] ),
				'collection_date' => isset( $data['collection_date'] ) ? sanitize_text_field( $data['collection_date'] ) : current_time( 'mysql' ),
				'value' => floatval( $data['value'] ),
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
				'collected_by' => isset( $data['collected_by'] ) ? absint( $data['collected_by'] ) : null,
			),
			array( '%d', '%s', '%f', '%s', '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to record data', 'nonprofitsuite' ) );
		}

		// Update current_value in metric
		$wpdb->update(
			$wpdb->prefix . 'ns_evaluation_metrics',
			array( 'current_value' => floatval( $data['value'] ) ),
			array( 'id' => absint( $data['metric_id'] ) ),
			array( '%f' ),
			array( '%d' )
		);

		NonprofitSuite_Cache::invalidate_module( 'evaluation_data' );
		NonprofitSuite_Cache::invalidate_module( 'evaluation_metrics' );

		return $wpdb->insert_id;
	}

	/**
	 * Get data points for metric
	 *
	 * @param int $metric_id Metric ID
	 * @return array Array of data points
	 */
	public static function get_metric_data( $metric_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for metric data points
		$cache_key = NonprofitSuite_Cache::list_key( 'evaluation_data', array( 'metric_id' => $metric_id ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $metric_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, metric_id, collection_date, value, notes, collected_by, created_at
				 FROM {$wpdb->prefix}ns_evaluation_data WHERE metric_id = %d ORDER BY collection_date ASC",
				absint( $metric_id )
			) );
		}, 300 );
	}

	/**
	 * Get evaluation dashboard data
	 *
	 * @return array Dashboard metrics
	 */
	public static function get_dashboard_data() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for evaluation dashboard
		$cache_key = NonprofitSuite_Cache::item_key( 'evaluation_dashboard', 'summary' );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb ) {
			$active_evaluations = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_program_evaluations WHERE status IN ('active', 'data_collection')"
			);

			$total_responses = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_evaluation_responses"
			);

			$recent_evaluations = $wpdb->get_results(
				"SELECT id, program_id, evaluation_name, evaluation_type, framework, start_date,
				        end_date, status, target_participants, actual_participants, description,
				        methodology, findings, recommendations, created_at
				 FROM {$wpdb->prefix}ns_program_evaluations ORDER BY start_date DESC LIMIT 5"
			);

			return array(
				'active_evaluations' => absint( $active_evaluations ),
				'total_responses' => absint( $total_responses ),
				'recent_evaluations' => $recent_evaluations,
			);
		}, 300 );
	}

	/**
	 * Calculate metric progress
	 *
	 * @param int $metric_id Metric ID
	 * @return float Progress percentage
	 */
	public static function calculate_metric_progress( $metric_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return 0;
		}

		global $wpdb;

		$metric = $wpdb->get_row( $wpdb->prepare(
			"SELECT baseline_value, current_value, target_value FROM {$wpdb->prefix}ns_evaluation_metrics WHERE id = %d",
			absint( $metric_id )
		) );

		if ( ! $metric || $metric->target_value === null || $metric->baseline_value === null ) {
			return 0;
		}

		$total_change_needed = $metric->target_value - $metric->baseline_value;
		if ( $total_change_needed == 0 ) {
			return 100;
		}

		$current_change = $metric->current_value - $metric->baseline_value;
		$progress = ( $current_change / $total_change_needed ) * 100;

		return max( 0, min( 100, $progress ) );
	}
}
