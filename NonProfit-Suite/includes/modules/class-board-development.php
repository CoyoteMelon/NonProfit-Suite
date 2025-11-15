<?php
/**
 * Board Development Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */

defined( 'ABSPATH' ) or exit;

class NonprofitSuite_Board_Development {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Board Development module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	// PROSPECT MANAGEMENT

	public static function add_prospect( $person_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage board development' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_prospects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'person_id' => absint( $person_id ),
				'prospect_status' => 'identified',
				'source' => isset( $data['source'] ) ? sanitize_text_field( $data['source'] ) : null,
				'skills_expertise' => isset( $data['skills_expertise'] ) ? sanitize_textarea_field( $data['skills_expertise'] ) : null,
				'networks_connections' => isset( $data['networks_connections'] ) ? sanitize_textarea_field( $data['networks_connections'] ) : null,
				'giving_capacity' => isset( $data['giving_capacity'] ) ? sanitize_text_field( $data['giving_capacity'] ) : null,
				'cultivation_stage' => 'initial',
				'assigned_to' => isset( $data['assigned_to'] ) ? absint( $data['assigned_to'] ) : null,
				'target_join_date' => isset( $data['target_join_date'] ) ? sanitize_text_field( $data['target_join_date'] ) : null,
				'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'board_prospects' );
		return $wpdb->insert_id;
	}

	public static function get_prospects( $args = array() ) {
		global $wpdb;
		$prospects_table = $wpdb->prefix . 'ns_board_prospects';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$defaults = array(
			'stage' => null,
			'assigned_to' => null,
			'readiness_min' => null,
			'limit' => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = "WHERE 1=1";

		if ( $args['stage'] ) {
			$where .= $wpdb->prepare( " AND bp.prospect_status = %s", $args['stage'] );
		}

		if ( $args['assigned_to'] ) {
			$where .= $wpdb->prepare( " AND bp.assigned_to = %d", $args['assigned_to'] );
		}

		if ( $args['readiness_min'] ) {
			$where .= $wpdb->prepare( " AND bp.readiness_score >= %d", $args['readiness_min'] );
		}

		$limit = $args['limit'] > 0 ? $wpdb->prepare( "LIMIT %d", $args['limit'] ) : '';

		return $wpdb->get_results(
			"SELECT bp.*, p.first_name, p.last_name, p.email
			FROM {$prospects_table} bp
			JOIN {$people_table} p ON bp.person_id = p.id
			{$where}
			ORDER BY bp.readiness_score DESC, bp.created_at DESC
			{$limit}"
		);
	}

	public static function update_prospect_stage( $id, $stage ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage board development' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_prospects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$result = $wpdb->update(
			$table,
			array( 'prospect_status' => sanitize_text_field( $stage ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'board_prospects' );
		}

		return $result !== false;
	}

	public static function log_contact( $id, $note ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_prospects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$prospect = $wpdb->get_row( $wpdb->prepare(
			"SELECT contact_log FROM {$table} WHERE id = %d",
			$id
		) );

		if ( ! $prospect ) {
			return new WP_Error( 'prospect_not_found', __( 'Prospect not found.', 'nonprofitsuite' ) );
		}

		$contact_log = ! empty( $prospect->contact_log ) ? json_decode( $prospect->contact_log, true ) : array();

		if ( ! is_array( $contact_log ) ) {
			$contact_log = array();
		}

		$contact_log[] = array(
			'date' => current_time( 'mysql' ),
			'note' => sanitize_textarea_field( $note ),
		);

		$result = $wpdb->update(
			$table,
			array( 'contact_log' => json_encode( $contact_log ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'board_prospects' );
		}

		return $result !== false;
	}

	public static function calculate_readiness_score( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_prospects';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$prospect = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, skills_expertise, networks_connections, giving_capacity, contact_log
			 FROM {$table}
			 WHERE id = %d",
			$id
		) );

		if ( ! $prospect ) {
			return 0;
		}

		$score = 0;

		// Skills/expertise (0-25 points)
		if ( ! empty( $prospect->skills_expertise ) ) {
			$score += 25;
		}

		// Networks/connections (0-25 points)
		if ( ! empty( $prospect->networks_connections ) ) {
			$score += 25;
		}

		// Giving capacity (0-30 points)
		$giving_scores = array(
			'low' => 10,
			'medium' => 20,
			'high' => 30,
		);
		if ( isset( $giving_scores[ $prospect->giving_capacity ] ) ) {
			$score += $giving_scores[ $prospect->giving_capacity ];
		}

		// Contact log (0-20 points)
		$contact_log = ! empty( $prospect->contact_log ) ? json_decode( $prospect->contact_log, true ) : array();
		if ( is_array( $contact_log ) ) {
			$contact_count = count( $contact_log );
			$score += min( 20, $contact_count * 5 ); // 5 points per contact, max 20
		}

		// Update the score
		$wpdb->update(
			$table,
			array( 'readiness_score' => $score ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		NonprofitSuite_Cache::invalidate_module( 'board_prospects' );
		return $score;
	}

	// TERM MANAGEMENT

	public static function create_term( $person_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage board development' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_terms';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Get previous terms for this person to determine term number
		$term_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE person_id = %d",
			$person_id
		) );

		$wpdb->insert(
			$table,
			array(
				'person_id' => absint( $person_id ),
				'position' => sanitize_text_field( $data['position'] ),
				'term_start' => sanitize_text_field( $data['term_start'] ),
				'term_end' => sanitize_text_field( $data['term_end'] ),
				'term_number' => $term_count + 1,
				'status' => 'active',
				'committee_assignments' => isset( $data['committee_assignments'] ) ? sanitize_textarea_field( $data['committee_assignments'] ) : null,
				'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	public static function get_active_board_members() {
		global $wpdb;
		$terms_table = $wpdb->prefix . 'ns_board_terms';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->get_results(
			"SELECT bt.*, p.first_name, p.last_name, p.email, p.phone
			FROM {$terms_table} bt
			JOIN {$people_table} p ON bt.person_id = p.id
			WHERE bt.status = 'active'
			AND bt.term_end >= CURDATE()
			ORDER BY bt.position ASC, p.last_name ASC"
		);
	}

	public static function get_terms_expiring( $months_ahead = 3 ) {
		global $wpdb;
		$terms_table = $wpdb->prefix . 'ns_board_terms';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$date = date( 'Y-m-d', strtotime( "+{$months_ahead} months" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT bt.*, p.first_name, p.last_name, p.email
			FROM {$terms_table} bt
			JOIN {$people_table} p ON bt.person_id = p.id
			WHERE bt.status = 'active'
			AND bt.term_end >= CURDATE()
			AND bt.term_end <= %s
			ORDER BY bt.term_end ASC",
			$date
		) );
	}

	public static function end_term( $id, $reason = 'completed' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_terms';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$status_map = array(
			'completed' => 'completed',
			'resigned' => 'resigned',
			'removed' => 'removed',
		);

		$status = isset( $status_map[ $reason ] ) ? $status_map[ $reason ] : 'completed';

		return $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		) !== false;
	}

	public static function get_board_composition() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_terms';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$active_members = $wpdb->get_results(
			"SELECT position, COUNT(*) as count, AVG(TIMESTAMPDIFF(YEAR, term_start, CURDATE())) as avg_tenure
			FROM {$table}
			WHERE status = 'active'
			AND term_end >= CURDATE()
			GROUP BY position"
		);

		$total = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$table}
			WHERE status = 'active'
			AND term_end >= CURDATE()"
		);

		return array(
			'total_members' => $total,
			'by_position' => $active_members,
		);
	}

	public static function get_prospect_stages() {
		return array(
			'identified' => __( 'Identified', 'nonprofitsuite' ),
			'cultivating' => __( 'Cultivating', 'nonprofitsuite' ),
			'invited' => __( 'Invited', 'nonprofitsuite' ),
			'accepted' => __( 'Accepted', 'nonprofitsuite' ),
			'onboarding' => __( 'Onboarding', 'nonprofitsuite' ),
			'seated' => __( 'Seated', 'nonprofitsuite' ),
		);
	}

	public static function get_positions() {
		return array(
			'chair' => __( 'Chair', 'nonprofitsuite' ),
			'vice_chair' => __( 'Vice Chair', 'nonprofitsuite' ),
			'treasurer' => __( 'Treasurer', 'nonprofitsuite' ),
			'secretary' => __( 'Secretary', 'nonprofitsuite' ),
			'member' => __( 'Member', 'nonprofitsuite' ),
			'ex_officio' => __( 'Ex-Officio', 'nonprofitsuite' ),
		);
	}
}
