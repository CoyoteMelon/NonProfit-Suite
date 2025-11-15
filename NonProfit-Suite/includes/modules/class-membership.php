<?php
/**
 * Membership Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Membership {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Membership module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_member( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage membership' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_members';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'person_id' => absint( $data['person_id'] ),
				'membership_type' => isset( $data['membership_type'] ) ? sanitize_text_field( $data['membership_type'] ) : null,
				'membership_level' => isset( $data['membership_level'] ) ? sanitize_text_field( $data['membership_level'] ) : null,
				'join_date' => sanitize_text_field( $data['join_date'] ),
				'expiration_date' => isset( $data['expiration_date'] ) ? sanitize_text_field( $data['expiration_date'] ) : null,
				'dues_amount' => isset( $data['dues_amount'] ) ? floatval( $data['dues_amount'] ) : 0,
				'payment_frequency' => isset( $data['payment_frequency'] ) ? sanitize_text_field( $data['payment_frequency'] ) : 'annual',
				'status' => 'active',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'members' );
		NonprofitSuite_Cache::invalidate_module( 'members_expiring' );
		return $wpdb->insert_id;
	}

	public static function renew_membership( $member_id, $renewal_date, $new_expiration ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_members';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array(
				'renewal_date' => $renewal_date,
				'expiration_date' => $new_expiration,
				'status' => 'active',
			),
			array( 'id' => $member_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		) !== false;
	}

	public static function get_members( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_members';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'status' => 'active' );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		// Use caching for member lists
		$cache_key = NonprofitSuite_Cache::list_key( 'members', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, person_id, membership_type, membership_level, join_date,
			               expiration_date, renewal_date, dues_amount, payment_frequency,
			               status, created_at
			        FROM {$table} {$where}
			        ORDER BY join_date DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_expiring_soon( $days = 30, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_members';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$date = date( 'Y-m-d', strtotime( "+{$days} days" ) );

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Use caching for expiring memberships
		$cache_key = NonprofitSuite_Cache::list_key( 'members_expiring', array_merge( $args, array( 'days' => $days ) ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $date, $args ) {
			$sql = $wpdb->prepare(
				"SELECT id, person_id, membership_type, membership_level, join_date,
				        expiration_date, renewal_date, dues_amount, payment_frequency,
				        status, created_at
				 FROM {$table}
				 WHERE status = 'active'
				 AND expiration_date <= %s
				 AND expiration_date >= CURDATE()
				 ORDER BY expiration_date ASC
				 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
				$date
			);

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_membership_types() {
		return array(
			'individual' => __( 'Individual', 'nonprofitsuite' ),
			'family' => __( 'Family', 'nonprofitsuite' ),
			'corporate' => __( 'Corporate', 'nonprofitsuite' ),
			'lifetime' => __( 'Lifetime', 'nonprofitsuite' ),
		);
	}

	public static function get_membership_levels() {
		return array(
			'basic' => __( 'Basic', 'nonprofitsuite' ),
			'silver' => __( 'Silver', 'nonprofitsuite' ),
			'gold' => __( 'Gold', 'nonprofitsuite' ),
			'platinum' => __( 'Platinum', 'nonprofitsuite' ),
		);
	}
}
