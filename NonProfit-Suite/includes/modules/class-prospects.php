<?php
/**
 * Prospects Management Module
 *
 * Research potential major donors, manage prospect pipeline,
 * track interactions and engagement.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Prospects {

	/**
	 * Create a prospect
	 *
	 * @param int $person_id Person ID
	 * @param array $data Prospect data
	 * @return int|WP_Error Prospect ID or error
	 */
	public static function create_prospect( $person_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_prospects();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'organization_id' => null,
			'prospect_type' => 'individual',
			'stage' => 'identification',
			'rating' => null,
			'estimated_capacity' => null,
			'likelihood' => null,
			'ask_amount' => null,
			'target_ask_date' => null,
			'assigned_to' => null,
			'source' => null,
			'interests' => null,
			'notes' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_prospects',
			array(
				'person_id' => absint( $person_id ),
				'organization_id' => absint( $data['organization_id'] ),
				'prospect_type' => sanitize_text_field( $data['prospect_type'] ),
				'stage' => sanitize_text_field( $data['stage'] ),
				'rating' => sanitize_text_field( $data['rating'] ),
				'estimated_capacity' => $data['estimated_capacity'],
				'likelihood' => sanitize_text_field( $data['likelihood'] ),
				'ask_amount' => $data['ask_amount'],
				'target_ask_date' => $data['target_ask_date'],
				'assigned_to' => absint( $data['assigned_to'] ),
				'source' => sanitize_text_field( $data['source'] ),
				'interests' => sanitize_textarea_field( $data['interests'] ),
				'notes' => sanitize_textarea_field( $data['notes'] ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create prospect.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'prospects' );
		return $wpdb->insert_id;
	}

	/**
	 * Get a single prospect
	 *
	 * @param int $id Prospect ID
	 * @return object|WP_Error
	 */
	public static function get_prospect( $id ) {
		global $wpdb;

		$prospect = $wpdb->get_row( $wpdb->prepare(
			"SELECT pr.*, p.first_name, p.last_name, p.email
			FROM {$wpdb->prefix}ns_prospects pr
			LEFT JOIN {$wpdb->prefix}ns_people p ON pr.person_id = p.id
			WHERE pr.id = %d",
			$id
		) );

		if ( null === $prospect ) {
			return new WP_Error( 'not_found', __( 'Prospect not found.', 'nonprofitsuite' ) );
		}

		return $prospect;
	}

	/**
	 * Get prospects with filters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_prospects( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'stage' => null,
			'rating' => null,
			'assigned_to' => null,
			'prospect_type' => null,
			'orderby' => 'estimated_capacity',
			'order' => 'DESC',
			'limit' => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['stage'] ) ) {
			$where[] = 'pr.stage = %s';
			$params[] = $args['stage'];
		}

		if ( ! empty( $args['rating'] ) ) {
			$where[] = 'pr.rating = %s';
			$params[] = $args['rating'];
		}

		if ( ! empty( $args['assigned_to'] ) ) {
			$where[] = 'pr.assigned_to = %d';
			$params[] = $args['assigned_to'];
		}

		if ( ! empty( $args['prospect_type'] ) ) {
			$where[] = 'pr.prospect_type = %s';
			$params[] = $args['prospect_type'];
		}

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$limit = absint( $args['limit'] );

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare(
				"SELECT pr.*, p.first_name, p.last_name, p.email
				FROM {$wpdb->prefix}ns_prospects pr
				LEFT JOIN {$wpdb->prefix}ns_people p ON pr.person_id = p.id
				WHERE $where_clause
				ORDER BY $orderby
				LIMIT %d",
				array_merge( $params, array( $limit ) )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT pr.*, p.first_name, p.last_name, p.email
				FROM {$wpdb->prefix}ns_prospects pr
				LEFT JOIN {$wpdb->prefix}ns_people p ON pr.person_id = p.id
				WHERE $where_clause
				ORDER BY $orderby
				LIMIT %d",
				$limit
			);
		}

		$results = $wpdb->get_results( $sql );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch prospects.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Update prospect
	 *
	 * @param int $id Prospect ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_prospect( $id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_prospects();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$allowed_fields = array(
			'stage', 'rating', 'estimated_capacity', 'likelihood',
			'ask_amount', 'target_ask_date', 'assigned_to',
			'interests', 'notes',
		);

		$update_data = array();
		$update_format = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				if ( in_array( $key, array( 'estimated_capacity', 'ask_amount' ), true ) ) {
					$update_data[ $key ] = $value;
					$update_format[] = '%f';
				} elseif ( $key === 'assigned_to' ) {
					$update_data[ $key ] = absint( $value );
					$update_format[] = '%d';
				} elseif ( in_array( $key, array( 'interests', 'notes' ), true ) ) {
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
			$wpdb->prefix . 'ns_prospects',
			$update_data,
			array( 'id' => absint( $id ) ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update prospect.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'prospects' );
		return true;
	}

	/**
	 * Move prospect to a new stage
	 *
	 * @param int $id Prospect ID
	 * @param string $new_stage New stage
	 * @return bool|WP_Error
	 */
	public static function move_to_stage( $id, $new_stage ) {
		return self::update_prospect( $id, array( 'stage' => $new_stage ) );
	}

	/**
	 * Get pipeline summary with counts and totals by stage
	 *
	 * @return array
	 */
	public static function get_pipeline_summary() {
		global $wpdb;

		$summary = $wpdb->get_results(
			"SELECT
				stage,
				COUNT(*) as count,
				SUM(estimated_capacity) as total_capacity,
				SUM(ask_amount) as total_ask
			FROM {$wpdb->prefix}ns_prospects
			GROUP BY stage
			ORDER BY FIELD(stage, 'identification', 'qualification', 'cultivation', 'solicitation', 'stewardship', 'declined')"
		);

		$result = array();

		foreach ( $summary as $row ) {
			$result[ $row->stage ] = array(
				'count' => (int) $row->count,
				'capacity' => (float) $row->total_capacity,
				'ask' => (float) $row->total_ask,
			);
		}

		return $result;
	}

	/**
	 * Get major gift pipeline (A+, A, B rated prospects)
	 *
	 * @return array|WP_Error
	 */
	public static function get_major_gift_pipeline() {
		return self::get_prospects( array(
			'rating' => array( 'A+', 'A', 'B' ),
			'orderby' => 'estimated_capacity',
			'order' => 'DESC',
		) );
	}

	/**
	 * Log an interaction with a prospect
	 *
	 * @param int $prospect_id Prospect ID
	 * @param array $data Interaction data
	 * @return int|WP_Error Interaction ID or error
	 */
	public static function log_interaction( $prospect_id, $data ) {
		global $wpdb;

		$defaults = array(
			'interaction_type' => 'email',
			'interaction_date' => current_time( 'Y-m-d' ),
			'staff_person' => null,
			'purpose' => null,
			'outcome' => null,
			'next_steps' => null,
			'rating_change' => null,
			'stage_change' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_prospect_interactions',
			array(
				'prospect_id' => absint( $prospect_id ),
				'interaction_type' => sanitize_text_field( $data['interaction_type'] ),
				'interaction_date' => $data['interaction_date'],
				'staff_person' => absint( $data['staff_person'] ),
				'purpose' => sanitize_textarea_field( $data['purpose'] ),
				'outcome' => sanitize_textarea_field( $data['outcome'] ),
				'next_steps' => sanitize_textarea_field( $data['next_steps'] ),
				'rating_change' => sanitize_text_field( $data['rating_change'] ),
				'stage_change' => sanitize_text_field( $data['stage_change'] ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to log interaction.', 'nonprofitsuite' ) );
		}

		// Update prospect if stage or rating changed
		if ( ! empty( $data['stage_change'] ) || ! empty( $data['rating_change'] ) ) {
			$update_data = array();
			if ( ! empty( $data['stage_change'] ) ) {
				$update_data['stage'] = $data['stage_change'];
			}
			if ( ! empty( $data['rating_change'] ) ) {
				$update_data['rating'] = $data['rating_change'];
			}
			self::update_prospect( $prospect_id, $update_data );
		}

		NonprofitSuite_Cache::invalidate_module( 'prospect_interactions' );
		return $wpdb->insert_id;
	}

	/**
	 * Get interaction history for a prospect
	 *
	 * @param int $prospect_id Prospect ID
	 * @return array|WP_Error
	 */
	public static function get_interaction_history( $prospect_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.*, p.first_name, p.last_name
			FROM {$wpdb->prefix}ns_prospect_interactions i
			LEFT JOIN {$wpdb->prefix}ns_people p ON i.staff_person = p.id
			WHERE i.prospect_id = %d
			ORDER BY i.interaction_date DESC",
			$prospect_id
		) );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch interaction history.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Get last contact date for a prospect
	 *
	 * @param int $prospect_id Prospect ID
	 * @return string|null
	 */
	public static function get_last_contact_date( $prospect_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(interaction_date)
			FROM {$wpdb->prefix}ns_prospect_interactions
			WHERE prospect_id = %d",
			$prospect_id
		) );
	}

	/**
	 * Get prospects needing contact (no contact in X days)
	 *
	 * @param int $days_since Number of days since last contact
	 * @return array|WP_Error
	 */
	public static function get_prospects_needing_contact( $days_since = 30 ) {
		global $wpdb;

		$date_cutoff = date( 'Y-m-d', strtotime( "-$days_since days" ) );

		$sql = $wpdb->prepare(
			"SELECT pr.*, p.first_name, p.last_name, p.email,
					MAX(i.interaction_date) as last_contact
			FROM {$wpdb->prefix}ns_prospects pr
			LEFT JOIN {$wpdb->prefix}ns_people p ON pr.person_id = p.id
			LEFT JOIN {$wpdb->prefix}ns_prospect_interactions i ON pr.id = i.prospect_id
			WHERE pr.stage IN ('qualification', 'cultivation', 'solicitation')
			GROUP BY pr.id
			HAVING last_contact IS NULL OR last_contact < %s
			ORDER BY last_contact ASC",
			$date_cutoff
		);

		$results = $wpdb->get_results( $sql );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch prospects.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Screen prospect (placeholder for external API integration)
	 *
	 * @param int $person_id Person ID
	 * @return array|WP_Error
	 */
	public static function screen_prospect( $person_id ) {
		// This would integrate with external wealth screening APIs
		// WealthEngine, iWave, DonorSearch, etc.

		return new WP_Error( 'not_implemented', __( 'External API integration required.', 'nonprofitsuite' ) );
	}

	/**
	 * Find connections between prospect and board members
	 *
	 * @param int $prospect_id Prospect ID
	 * @return array
	 */
	public static function find_connections( $prospect_id ) {
		// Placeholder - would search for mutual contacts, shared organizations, etc.

		return array(
			'board_connections' => array(),
			'mutual_contacts' => array(),
			'organization_links' => array(),
		);
	}

	/**
	 * Generate briefing PDF for meeting prep
	 *
	 * @param int $prospect_id Prospect ID
	 * @return string|WP_Error PDF file path or error
	 */
	public static function generate_briefing( $prospect_id ) {
		// Placeholder - would generate PDF with prospect info, indicators, history

		return new WP_Error( 'not_implemented', __( 'PDF generation not yet implemented.', 'nonprofitsuite' ) );
	}
}
