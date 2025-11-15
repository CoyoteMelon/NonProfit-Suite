<?php
/**
 * Advocacy Issues Module
 *
 * Track legislative issues, bills, positions, and decision dates
 * while maintaining compliance with nonprofit lobbying rules.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Advocacy_Issues {

	/**
	 * Create an advocacy issue
	 *
	 * @param array $data Issue data
	 * @return int|WP_Error Issue ID or error
	 */
	public static function create_issue( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage advocacy issues' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'title' => '',
			'description' => null,
			'issue_type' => '',
			'status' => 'monitoring',
			'priority' => 'medium',
			'position' => null,
			'legislative_body' => null,
			'bill_number' => null,
			'sponsor' => null,
			'current_stage' => null,
			'target_decision_date' => null,
			'decision_date' => null,
			'outcome' => null,
			'impact_level' => null,
			'talking_points' => null,
			'resources' => null,
			'assigned_to' => null,
			'notes' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['title'] ) || empty( $data['issue_type'] ) ) {
			return new WP_Error( 'missing_required', __( 'Title and issue type are required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_advocacy_issues',
			array(
				'title' => sanitize_text_field( $data['title'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'issue_type' => sanitize_text_field( $data['issue_type'] ),
				'status' => sanitize_text_field( $data['status'] ),
				'priority' => sanitize_text_field( $data['priority'] ),
				'position' => sanitize_text_field( $data['position'] ),
				'legislative_body' => sanitize_text_field( $data['legislative_body'] ),
				'bill_number' => sanitize_text_field( $data['bill_number'] ),
				'sponsor' => sanitize_text_field( $data['sponsor'] ),
				'current_stage' => sanitize_text_field( $data['current_stage'] ),
				'target_decision_date' => $data['target_decision_date'],
				'decision_date' => $data['decision_date'],
				'outcome' => sanitize_text_field( $data['outcome'] ),
				'impact_level' => sanitize_text_field( $data['impact_level'] ),
				'talking_points' => sanitize_textarea_field( $data['talking_points'] ),
				'resources' => sanitize_textarea_field( $data['resources'] ),
				'assigned_to' => absint( $data['assigned_to'] ),
				'notes' => sanitize_textarea_field( $data['notes'] ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create issue.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'advocacy_issues' );
		return $wpdb->insert_id;
	}

	/**
	 * Get issues with filters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_issues( $args = array() ) {
		global $wpdb;

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, array(
			'issue_type' => null,
			'status'     => null,
			'priority'   => null,
			'position'   => null,
		) ) );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['issue_type'] ) ) {
			$where[] = 'issue_type = %s';
			$params[] = $args['issue_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['priority'] ) ) {
			$where[] = 'priority = %s';
			$params[] = $args['priority'];
		}

		if ( ! empty( $args['position'] ) ) {
			$where[] = 'position = %s';
			$params[] = $args['position'];
		}

		$where_clause = implode( ' AND ', $where );

		// Build query with specific columns
		$sql = "SELECT id, title, description, issue_type, status, priority, position,
		               legislative_body, bill_number, sponsor, current_stage,
		               target_decision_date, decision_date, outcome, impact_level,
		               assigned_to, created_at
		        FROM {$wpdb->prefix}ns_advocacy_issues
		        WHERE $where_clause
		        ORDER BY " . $args['orderby'] . "
		        " . NonprofitSuite_Utilities::build_limit_clause( $args );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$results = $wpdb->get_results( $sql );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch issues.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Update issue
	 *
	 * @param int $id Issue ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_issue( $id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage advocacy issues' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$allowed_fields = array(
			'title', 'description', 'status', 'priority', 'position',
			'current_stage', 'target_decision_date', 'decision_date',
			'outcome', 'talking_points', 'notes',
		);

		$update_data = array();
		$update_format = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				if ( in_array( $key, array( 'target_decision_date', 'decision_date' ), true ) ) {
					$update_data[ $key ] = $value;
					$update_format[] = '%s';
				} elseif ( in_array( $key, array( 'description', 'talking_points', 'notes' ), true ) ) {
					$update_data[ $key ] = sanitize_textarea_field( $value );
					$update_format[] = '%s';
				} else {
					$update_data[ $key ] = sanitize_text_field( $value );
					$update_format[] = '%s';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No valid fields to update.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_advocacy_issues',
			$update_data,
			array( 'id' => absint( $id ) ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update issue.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'advocacy_issues' );
		return true;
	}

	/**
	 * Update issue status
	 *
	 * @param int $id Issue ID
	 * @param string $new_status New status
	 * @return bool|WP_Error
	 */
	public static function update_status( $id, $new_status ) {
		return self::update_issue( $id, array( 'status' => $new_status ) );
	}

	/**
	 * Get active issues
	 *
	 * @return array|WP_Error
	 */
	public static function get_active_issues() {
		return self::get_issues( array(
			'status' => 'active_campaign',
			'orderby' => 'priority',
			'order' => 'DESC',
		) );
	}

	/**
	 * Get issues with upcoming decision dates
	 *
	 * @param int $days_ahead Number of days to look ahead
	 * @return array|WP_Error
	 */
	public static function get_issues_by_decision_date( $days_ahead = 30 ) {
		global $wpdb;

		$date_limit = date( 'Y-m-d', strtotime( "+$days_ahead days" ) );

		// Use caching for upcoming issues
		$cache_key = NonprofitSuite_Cache::list_key( 'advocacy_issues_upcoming', array( 'days_ahead' => $days_ahead ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $date_limit ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, title, description, issue_type, status, priority, position,
				        legislative_body, bill_number, sponsor, current_stage,
				        target_decision_date, decision_date, outcome, impact_level,
				        assigned_to, created_at
				 FROM {$wpdb->prefix}ns_advocacy_issues
				 WHERE target_decision_date >= %s
				 AND target_decision_date <= %s
				 AND status IN ('monitoring', 'active_campaign')
				 ORDER BY target_decision_date ASC",
				current_time( 'Y-m-d' ),
				$date_limit
			) );
		}, 300 );
	}

	/**
	 * Get issue timeline
	 *
	 * @param int $id Issue ID
	 * @return array
	 */
	public static function get_issue_timeline( $id ) {
		global $wpdb;

		$timeline = array();

		// Add campaigns
		$campaigns = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, campaign_name, start_date, end_date, status
			FROM {$wpdb->prefix}ns_advocacy_campaigns
			WHERE issue_id = %d
			ORDER BY start_date DESC",
			$id
		) );

		foreach ( $campaigns as $campaign ) {
			$timeline[] = array(
				'date' => $campaign->start_date,
				'type' => 'campaign_start',
				'title' => sprintf( __( 'Started: %s', 'nonprofitsuite' ), $campaign->campaign_name ),
			);

			if ( $campaign->end_date ) {
				$timeline[] = array(
					'date' => $campaign->end_date,
					'type' => 'campaign_end',
					'title' => sprintf( __( 'Ended: %s', 'nonprofitsuite' ), $campaign->campaign_name ),
				);
			}
		}

		// Sort by date
		usort( $timeline, function( $a, $b ) {
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		} );

		return $timeline;
	}
}
