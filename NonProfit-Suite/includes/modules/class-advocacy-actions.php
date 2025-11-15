<?php
/**
 * Advocacy Actions Module
 *
 * Log individual supporter actions, track engagement,
 * and generate action reports and exports.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Advocacy_Actions {

	/**
	 * Log an advocacy action
	 *
	 * @param int $campaign_id Campaign ID
	 * @param array $data Action data
	 * @return int|WP_Error Action ID or error
	 */
	public static function log_action( $campaign_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'log advocacy actions' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'person_id' => null,
			'action_type' => '',
			'action_date' => current_time( 'mysql', false ),
			'target_name' => null,
			'target_type' => null,
			'message' => null,
			'outcome' => null,
			'notes' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['action_type'] ) ) {
			return new WP_Error( 'missing_required', __( 'Action type is required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_advocacy_actions',
			array(
				'campaign_id' => absint( $campaign_id ),
				'person_id' => absint( $data['person_id'] ),
				'action_type' => sanitize_text_field( $data['action_type'] ),
				'action_date' => $data['action_date'],
				'target_name' => sanitize_text_field( $data['target_name'] ),
				'target_type' => sanitize_text_field( $data['target_type'] ),
				'message' => sanitize_textarea_field( $data['message'] ),
				'outcome' => sanitize_text_field( $data['outcome'] ),
				'notes' => sanitize_textarea_field( $data['notes'] ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to log action.', 'nonprofitsuite' ) );
		}

		// Increment campaign action count
		NonprofitSuite_Advocacy_Campaigns::increment_action_count( $campaign_id );

		NonprofitSuite_Cache::invalidate_module( 'advocacy_actions' );
		return $wpdb->insert_id;
	}

	/**
	 * Get actions for a campaign
	 *
	 * @param int $campaign_id Campaign ID
	 * @return array|WP_Error
	 */
	public static function get_actions( $campaign_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, p.first_name, p.last_name, p.email
			FROM {$wpdb->prefix}ns_advocacy_actions a
			LEFT JOIN {$wpdb->prefix}ns_people p ON a.person_id = p.id
			WHERE a.campaign_id = %d
			ORDER BY a.action_date DESC",
			$campaign_id
		) );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch actions.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Get actions by a specific person
	 *
	 * @param int $person_id Person ID
	 * @return array|WP_Error
	 */
	public static function get_person_actions( $person_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, c.campaign_name
			FROM {$wpdb->prefix}ns_advocacy_actions a
			LEFT JOIN {$wpdb->prefix}ns_advocacy_campaigns c ON a.campaign_id = c.id
			WHERE a.person_id = %d
			ORDER BY a.action_date DESC",
			$person_id
		) );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch actions.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Get action summary breakdown by type
	 *
	 * @param int $campaign_id Campaign ID
	 * @return array
	 */
	public static function get_action_summary( $campaign_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT action_type, COUNT(*) as count
			FROM {$wpdb->prefix}ns_advocacy_actions
			WHERE campaign_id = %d
			GROUP BY action_type
			ORDER BY count DESC",
			$campaign_id
		) );

		$summary = array();
		foreach ( $results as $row ) {
			$summary[ $row->action_type ] = (int) $row->count;
		}

		return $summary;
	}

	/**
	 * Export action list to CSV
	 *
	 * @param int $campaign_id Campaign ID
	 * @return string|WP_Error CSV content or error
	 */
	public static function export_action_list( $campaign_id ) {
		$actions = self::get_actions( $campaign_id );

		if ( is_wp_error( $actions ) ) {
			return $actions;
		}

		$csv = "Date,Name,Email,Action Type,Target,Outcome\n";

		foreach ( $actions as $action ) {
			$csv .= sprintf(
				'"%s","%s","%s","%s","%s","%s"' . "\n",
				$action->action_date,
				$action->first_name . ' ' . $action->last_name,
				$action->email,
				$action->action_type,
				$action->target_name,
				$action->outcome
			);
		}

		return $csv;
	}
}
