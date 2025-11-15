<?php
/**
 * Committee Management Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Committees {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Committee Management module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_committee( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage committees' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_committees';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'committee_name' => sanitize_text_field( $data['committee_name'] ),
				'committee_type' => isset( $data['committee_type'] ) ? sanitize_text_field( $data['committee_type'] ) : 'standing',
				'chair_person_id' => isset( $data['chair_person_id'] ) ? absint( $data['chair_person_id'] ) : null,
				'status' => 'active',
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'meeting_frequency' => isset( $data['meeting_frequency'] ) ? sanitize_text_field( $data['meeting_frequency'] ) : 'monthly',
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'committees' );
		return $wpdb->insert_id;
	}

	public static function add_member( $committee_id, $person_id, $role = 'member' ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage committees' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_committee_members';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Check if already a member
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE committee_id = %d AND person_id = %d",
			$committee_id,
			$person_id
		) );

		if ( $exists ) {
			return new WP_Error( 'already_member', __( 'Person is already a committee member.', 'nonprofitsuite' ) );
		}

		$wpdb->insert(
			$table,
			array(
				'committee_id' => absint( $committee_id ),
				'person_id' => absint( $person_id ),
				'role' => sanitize_text_field( $role ),
				'status' => 'active',
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	public static function remove_member( $committee_id, $person_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage committees' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_committee_members';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array( 'status' => 'inactive' ),
			array(
				'committee_id' => $committee_id,
				'person_id' => $person_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		) !== false;
	}

	public static function get_committees( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_committees';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'status' => 'active' );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = $args['status'] ? $wpdb->prepare( "WHERE status = %s", $args['status'] ) : "WHERE 1=1";

		// Use caching for committee lists
		$cache_key = NonprofitSuite_Cache::list_key( 'committees', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, committee_name, committee_type, chair_person_id, status,
			               description, meeting_frequency, member_count, created_at
			        FROM {$table} {$where}
			        ORDER BY committee_name ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_committee_members( $committee_id, $status = 'active' ) {
		global $wpdb;
		$members_table = $wpdb->prefix . 'ns_committee_members';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$where = $status ? $wpdb->prepare( "AND cm.status = %s", $status ) : "";

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT cm.*, p.first_name, p.last_name, p.email
			FROM {$members_table} cm
			JOIN {$people_table} p ON cm.person_id = p.id
			WHERE cm.committee_id = %d {$where}
			ORDER BY cm.role ASC, p.last_name ASC",
			$committee_id
		) );
	}

	/**
	 * Get members for multiple committees at once (batch method to prevent N+1 queries)
	 *
	 * @param array $committee_ids Array of committee IDs
	 * @param string $status Member status filter
	 * @return array Associative array keyed by committee_id containing arrays of members
	 */
	public static function get_all_committee_members( $committee_ids, $status = 'active' ) {
		global $wpdb;
		$members_table = $wpdb->prefix . 'ns_committee_members';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		if ( empty( $committee_ids ) ) {
			return array();
		}

		// Sanitize IDs
		$committee_ids = array_map( 'absint', $committee_ids );
		$placeholders = implode( ',', array_fill( 0, count( $committee_ids ), '%d' ) );

		$where = $status ? $wpdb->prepare( "AND cm.status = %s", $status ) : "";

		// Fetch all members for all committees in one query
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cm.committee_id, cm.person_id, cm.role, cm.joined_date, cm.status,
				        p.first_name, p.last_name, p.email
				 FROM {$members_table} cm
				 JOIN {$people_table} p ON cm.person_id = p.id
				 WHERE cm.committee_id IN ($placeholders) {$where}
				 ORDER BY cm.committee_id, cm.role ASC, p.last_name ASC",
				$committee_ids
			)
		);

		// Group members by committee_id
		$grouped = array();
		foreach ( $results as $member ) {
			$committee_id = $member->committee_id;
			if ( ! isset( $grouped[ $committee_id ] ) ) {
				$grouped[ $committee_id ] = array();
			}
			$grouped[ $committee_id ][] = $member;
		}

		return $grouped;
	}

	public static function update_member_role( $committee_id, $person_id, $new_role ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage committees' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_committee_members';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array( 'role' => sanitize_text_field( $new_role ) ),
			array(
				'committee_id' => $committee_id,
				'person_id' => $person_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		) !== false;
	}

	public static function get_committee_types() {
		return array(
			'standing' => __( 'Standing Committee', 'nonprofitsuite' ),
			'ad_hoc' => __( 'Ad Hoc Committee', 'nonprofitsuite' ),
			'advisory' => __( 'Advisory Committee', 'nonprofitsuite' ),
			'executive' => __( 'Executive Committee', 'nonprofitsuite' ),
		);
	}

	public static function get_member_roles() {
		return array(
			'chair' => __( 'Chair', 'nonprofitsuite' ),
			'vice_chair' => __( 'Vice Chair', 'nonprofitsuite' ),
			'secretary' => __( 'Secretary', 'nonprofitsuite' ),
			'member' => __( 'Member', 'nonprofitsuite' ),
		);
	}
}
