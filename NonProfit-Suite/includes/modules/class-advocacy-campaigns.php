<?php
/**
 * Advocacy Campaigns Module
 *
 * Manage advocacy campaigns, track progress toward goals,
 * and generate campaign reports.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Advocacy_Campaigns {

	/**
	 * Create a campaign
	 *
	 * @param int $issue_id Issue ID
	 * @param array $data Campaign data
	 * @return int|WP_Error Campaign ID or error
	 */
	public static function create_campaign( $issue_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage advocacy campaigns' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'campaign_name' => '',
			'campaign_type' => '',
			'description' => null,
			'start_date' => current_time( 'Y-m-d' ),
			'end_date' => null,
			'status' => 'planning',
			'target_audience' => null,
			'call_to_action' => null,
			'action_count' => 0,
			'goal_count' => null,
			'email_template' => null,
			'letter_template' => null,
			'talking_points' => null,
			'resources' => null,
			'hashtags' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['campaign_name'] ) || empty( $data['campaign_type'] ) ) {
			return new WP_Error( 'missing_required', __( 'Campaign name and type are required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_advocacy_campaigns',
			array(
				'issue_id' => absint( $issue_id ),
				'campaign_name' => sanitize_text_field( $data['campaign_name'] ),
				'campaign_type' => sanitize_text_field( $data['campaign_type'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'start_date' => $data['start_date'],
				'end_date' => $data['end_date'],
				'status' => sanitize_text_field( $data['status'] ),
				'target_audience' => sanitize_textarea_field( $data['target_audience'] ),
				'call_to_action' => sanitize_textarea_field( $data['call_to_action'] ),
				'action_count' => absint( $data['action_count'] ),
				'goal_count' => absint( $data['goal_count'] ),
				'email_template' => sanitize_textarea_field( $data['email_template'] ),
				'letter_template' => sanitize_textarea_field( $data['letter_template'] ),
				'talking_points' => sanitize_textarea_field( $data['talking_points'] ),
				'resources' => sanitize_textarea_field( $data['resources'] ),
				'hashtags' => sanitize_text_field( $data['hashtags'] ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create campaign.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'advocacy_campaigns' );
		return $wpdb->insert_id;
	}

	/**
	 * Get a single campaign
	 *
	 * @param int $id Campaign ID
	 * @return object|WP_Error
	 */
	public static function get_campaign( $id ) {
		global $wpdb;

		$campaign = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.*, i.title as issue_title
			FROM {$wpdb->prefix}ns_advocacy_campaigns c
			LEFT JOIN {$wpdb->prefix}ns_advocacy_issues i ON c.issue_id = i.id
			WHERE c.id = %d",
			$id
		) );

		if ( ! $campaign ) {
			return new WP_Error( 'not_found', __( 'Campaign not found.', 'nonprofitsuite' ) );
		}

		return $campaign;
	}

	/**
	 * Get campaigns with filters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_campaigns( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'issue_id' => null,
			'status' => null,
			'campaign_type' => null,
			'orderby' => 'start_date',
			'order' => 'DESC',
			'limit' => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['issue_id'] ) ) {
			$where[] = 'c.issue_id = %d';
			$params[] = $args['issue_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'c.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['campaign_type'] ) ) {
			$where[] = 'c.campaign_type = %s';
			$params[] = $args['campaign_type'];
		}

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$limit = absint( $args['limit'] );

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare(
				"SELECT c.*, i.title as issue_title
				FROM {$wpdb->prefix}ns_advocacy_campaigns c
				LEFT JOIN {$wpdb->prefix}ns_advocacy_issues i ON c.issue_id = i.id
				WHERE $where_clause
				ORDER BY $orderby
				LIMIT %d",
				array_merge( $params, array( $limit ) )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT c.*, i.title as issue_title
				FROM {$wpdb->prefix}ns_advocacy_campaigns c
				LEFT JOIN {$wpdb->prefix}ns_advocacy_issues i ON c.issue_id = i.id
				WHERE $where_clause
				ORDER BY $orderby
				LIMIT %d",
				$limit
			);
		}

		$results = $wpdb->get_results( $sql );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch campaigns.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Update campaign
	 *
	 * @param int $id Campaign ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_campaign( $id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage advocacy campaigns' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$allowed_fields = array(
			'campaign_name', 'description', 'status', 'end_date',
			'call_to_action', 'goal_count', 'email_template',
			'letter_template', 'talking_points',
		);

		$update_data = array();
		$update_format = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				if ( $key === 'goal_count' ) {
					$update_data[ $key ] = absint( $value );
					$update_format[] = '%d';
				} elseif ( $key === 'end_date' ) {
					$update_data[ $key ] = $value;
					$update_format[] = '%s';
				} elseif ( in_array( $key, array( 'description', 'call_to_action', 'email_template', 'letter_template', 'talking_points' ), true ) ) {
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
			$wpdb->prefix . 'ns_advocacy_campaigns',
			$update_data,
			array( 'id' => absint( $id ) ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update campaign.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'advocacy_campaigns' );
		return true;
	}

	/**
	 * Calculate campaign progress toward goal
	 *
	 * @param int $id Campaign ID
	 * @return int Percentage (0-100)
	 */
	public static function calculate_progress( $id ) {
		$campaign = self::get_campaign( $id );

		if ( is_wp_error( $campaign ) || empty( $campaign->goal_count ) ) {
			return 0;
		}

		return min( 100, round( ( $campaign->action_count / $campaign->goal_count ) * 100 ) );
	}

	/**
	 * Get active campaigns
	 *
	 * @return array|WP_Error
	 */
	public static function get_active_campaigns() {
		return self::get_campaigns( array(
			'status' => 'active',
			'orderby' => 'start_date',
			'order' => 'DESC',
		) );
	}

	/**
	 * Increment campaign action count
	 *
	 * @param int $campaign_id Campaign ID
	 * @return bool
	 */
	public static function increment_action_count( $campaign_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage advocacy campaigns' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ns_advocacy_campaigns
			SET action_count = action_count + 1
			WHERE id = %d",
			$campaign_id
		) );

		NonprofitSuite_Cache::invalidate_module( 'advocacy_campaigns' );
		return true;
	}

	/**
	 * Get campaign report
	 *
	 * @param int $id Campaign ID
	 * @return array|WP_Error
	 */
	public static function get_campaign_report( $id ) {
		$campaign = self::get_campaign( $id );

		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		// Get actions from the Actions module
		$actions = NonprofitSuite_Advocacy_Actions::get_actions( $id );
		$summary = NonprofitSuite_Advocacy_Actions::get_action_summary( $id );
		$progress = self::calculate_progress( $id );

		return array(
			'campaign' => $campaign,
			'total_actions' => is_array( $actions ) ? count( $actions ) : 0,
			'action_breakdown' => $summary,
			'progress_percent' => $progress,
			'participants' => is_array( $actions ) ? count( array_unique( wp_list_pluck( $actions, 'person_id' ) ) ) : 0,
		);
	}

	/**
	 * Get advocacy dashboard overview
	 *
	 * @return array
	 */
	public static function get_advocacy_dashboard() {
		global $wpdb;

		return array(
			'active_issues' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_advocacy_issues WHERE status = 'active_campaign'" ),
			'active_campaigns' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_advocacy_campaigns WHERE status = 'active'" ),
			'total_actions' => $wpdb->get_var( "SELECT SUM(action_count) FROM {$wpdb->prefix}ns_advocacy_campaigns" ),
			'victories' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_advocacy_issues WHERE status = 'victory'" ),
		);
	}
}
