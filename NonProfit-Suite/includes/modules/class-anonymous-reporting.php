<?php
/**
 * Anonymous Reporting Module (Whistleblower System)
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Anonymous_Reporting {

	/**
	 * Generate unique report number
	 *
	 * @return string Report number
	 */
	private static function generate_report_number() {
		global $wpdb;

		do {
			$number = 'AR' . date( 'Y' ) . wp_rand( 10000, 99999 );
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ns_anonymous_reports WHERE report_number = %s",
				$number
			) );
		} while ( $exists );

		return $number;
	}

	/**
	 * Submit anonymous report
	 *
	 * @param array $data Report data
	 * @return array|WP_Error Report info or error
	 */
	public static function submit_report( $data ) {
		global $wpdb;

		$report_number = self::generate_report_number();

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_anonymous_reports',
			array(
				'report_number' => $report_number,
				'category' => sanitize_text_field( $data['category'] ),
				'description' => wp_kses_post( $data['description'] ),
				'incident_date' => isset( $data['incident_date'] ) ? sanitize_text_field( $data['incident_date'] ) : null,
				'location' => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : null,
				'witnesses' => isset( $data['witnesses'] ) ? wp_kses_post( $data['witnesses'] ) : null,
				'evidence_description' => isset( $data['evidence_description'] ) ? wp_kses_post( $data['evidence_description'] ) : null,
				'status' => 'submitted',
				'priority' => isset( $data['priority'] ) ? sanitize_text_field( $data['priority'] ) : 'medium',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to submit report', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'anonymous_reports' );
		return array(
			'report_number' => $report_number,
			'message' => __( 'Report submitted successfully. Save this number to check status: ', 'nonprofitsuite' ) . $report_number,
		);
	}

	/**
	 * Check report status (public, no auth required)
	 *
	 * @param string $report_number Report number
	 * @return array|WP_Error Status info or error
	 */
	public static function check_status( $report_number ) {
		global $wpdb;

		$report = $wpdb->get_row( $wpdb->prepare(
			"SELECT report_number, status, created_at FROM {$wpdb->prefix}ns_anonymous_reports WHERE report_number = %s",
			sanitize_text_field( $report_number )
		) );

		if ( ! $report ) {
			return new WP_Error( 'not_found', __( 'Report not found', 'nonprofitsuite' ) );
		}

		$status_labels = array(
			'submitted' => __( 'Submitted - Under Review', 'nonprofitsuite' ),
			'investigating' => __( 'Under Investigation', 'nonprofitsuite' ),
			'resolved' => __( 'Resolved', 'nonprofitsuite' ),
			'closed' => __( 'Closed', 'nonprofitsuite' ),
		);

		return array(
			'report_number' => $report->report_number,
			'status' => $status_labels[ $report->status ] ?? ucfirst( $report->status ),
			'submitted_date' => date( 'F j, Y', strtotime( $report->created_at ) ),
		);
	}

	/**
	 * Get all reports (admin only)
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of reports or error
	 */
	public static function get_reports( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'status' => null,
			'category' => null,
			'priority' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( $args['category'] ) {
			$where[] = 'category = %s';
			$values[] = sanitize_text_field( $args['category'] );
		}

		if ( $args['priority'] ) {
			$where[] = 'priority = %s';
			$values[] = sanitize_text_field( $args['priority'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for anonymous reports
		$cache_key = NonprofitSuite_Cache::list_key( 'anonymous_reports', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, report_number, category, description, incident_date, location, witnesses,
			               evidence_description, status, priority, assigned_to, investigation_notes,
			               resolution, resolved_date, followup_required, created_at
			        FROM {$wpdb->prefix}ns_anonymous_reports
			        WHERE $where_clause
			        ORDER BY created_at DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Get single report (admin only)
	 *
	 * @param int $report_id Report ID
	 * @return object|WP_Error Report object or error
	 */
	public static function get_report( $report_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Use caching for single report
		$cache_key = NonprofitSuite_Cache::item_key( 'anonymous_report', $report_id );
		$report = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $report_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, report_number, category, description, incident_date, location, witnesses,
				        evidence_description, status, priority, assigned_to, investigation_notes,
				        resolution, resolved_date, followup_required, created_at
				 FROM {$wpdb->prefix}ns_anonymous_reports WHERE id = %d",
				absint( $report_id )
			) );
		}, 300 );

		if ( ! $report ) {
			return new WP_Error( 'not_found', __( 'Report not found', 'nonprofitsuite' ) );
		}

		return $report;
	}

	/**
	 * Update report (admin only)
	 *
	 * @param int   $report_id Report ID
	 * @param array $data Update data
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_report( $report_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage anonymous reports' );
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
			'priority' => '%s',
			'assigned_to' => '%d',
			'investigation_notes' => '%s',
			'resolution' => '%s',
			'resolved_date' => '%s',
			'followup_required' => '%d',
		);

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				if ( $allowed_fields[ $key ] === '%d' ) {
					$update_data[ $key ] = absint( $value );
				} elseif ( in_array( $key, array( 'investigation_notes', 'resolution' ) ) ) {
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
			$wpdb->prefix . 'ns_anonymous_reports',
			$update_data,
			array( 'id' => absint( $report_id ) ),
			$format,
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'anonymous_reports' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'anonymous_report', $report_id ) );
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

		// Use caching for dashboard data
		$cache_key = NonprofitSuite_Cache::item_key( 'anonymous_reports_dashboard', 'summary' );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb ) {
			$new_reports = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_anonymous_reports WHERE status = 'submitted'"
			);

			$investigating = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_anonymous_reports WHERE status = 'investigating'"
			);

			$high_priority = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_anonymous_reports WHERE priority = 'high' AND status NOT IN ('resolved', 'closed')"
			);

			$recent_reports = $wpdb->get_results(
				"SELECT id, report_number, category, description, incident_date, location, witnesses,
				        evidence_description, status, priority, assigned_to, investigation_notes,
				        resolution, resolved_date, followup_required, created_at
				 FROM {$wpdb->prefix}ns_anonymous_reports ORDER BY created_at DESC LIMIT 10"
			);

			return array(
				'new_reports' => absint( $new_reports ),
				'investigating' => absint( $investigating ),
				'high_priority' => absint( $high_priority ),
				'recent_reports' => $recent_reports,
			);
		}, 300 );
	}

	/**
	 * Get reports by category
	 *
	 * @return array Category counts
	 */
	public static function get_category_stats() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_results(
			"SELECT category, COUNT(*) as count
			FROM {$wpdb->prefix}ns_anonymous_reports
			GROUP BY category
			ORDER BY count DESC"
		);
	}
}
