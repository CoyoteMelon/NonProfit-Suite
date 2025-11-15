<?php
/**
 * Events Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Events {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Events module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_event( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage events' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_events';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'event_name' => sanitize_text_field( $data['event_name'] ),
				'event_type' => isset( $data['event_type'] ) ? sanitize_text_field( $data['event_type'] ) : 'fundraiser',
				'event_date' => sanitize_text_field( $data['event_date'] ),
				'location' => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : null,
				'virtual_url' => isset( $data['virtual_url'] ) ? esc_url_raw( $data['virtual_url'] ) : null,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'capacity' => isset( $data['capacity'] ) ? absint( $data['capacity'] ) : null,
				'ticket_price' => isset( $data['ticket_price'] ) ? floatval( $data['ticket_price'] ) : 0,
				'revenue_goal' => isset( $data['revenue_goal'] ) ? floatval( $data['revenue_goal'] ) : 0,
				'status' => 'planned',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'events' );
		return $wpdb->insert_id;
	}

	public static function register_attendee( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_event_registrations';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'event_id' => absint( $data['event_id'] ),
				'person_id' => absint( $data['person_id'] ),
				'ticket_count' => isset( $data['ticket_count'] ) ? absint( $data['ticket_count'] ) : 1,
				'amount_paid' => isset( $data['amount_paid'] ) ? floatval( $data['amount_paid'] ) : 0,
				'payment_status' => isset( $data['payment_status'] ) ? sanitize_text_field( $data['payment_status'] ) : 'pending',
			),
			array( '%d', '%d', '%d', '%f', '%s' )
		);

		$registration_id = $wpdb->insert_id;

		// Update registered count
		self::update_event_counts( $data['event_id'] );

		NonprofitSuite_Cache::invalidate_module( 'events' );
		NonprofitSuite_Cache::invalidate_module( 'event_registrations' );
		return $registration_id;
	}

	public static function check_in_attendee( $registration_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_event_registrations';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array( 'checked_in' => 1 ),
			array( 'id' => $registration_id ),
			array( '%d' ),
			array( '%d' )
		) !== false;
	}

	public static function get_events( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_events';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'upcoming' => false );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";
		if ( $args['upcoming'] ) {
			$where .= " AND event_date >= NOW()";
		}

		// Use caching for event lists
		$cache_key = NonprofitSuite_Cache::list_key( 'events', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, event_name, event_type, event_date, location, virtual_url,
			               description, capacity, registered_count, ticket_price, revenue_goal,
			               total_revenue, status, created_at
			        FROM {$table} {$where}
			        ORDER BY event_date ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_event_registrations( $event_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_event_registrations';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Use caching for event registrations
		$cache_key = NonprofitSuite_Cache::list_key( 'event_registrations', array_merge( $args, array( 'event_id' => $event_id ) ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $event_id, $args ) {
			$sql = $wpdb->prepare(
				"SELECT id, event_id, person_id, ticket_count, amount_paid, payment_status,
				        checked_in, registration_date
				 FROM {$table}
				 WHERE event_id = %d
				 ORDER BY registration_date DESC
				 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
				$event_id
			);

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Get registrations for multiple events at once (batch method to prevent N+1 queries)
	 *
	 * @param array $event_ids Array of event IDs
	 * @return array Associative array keyed by event_id containing arrays of registrations
	 */
	public static function get_all_event_registrations( $event_ids ) {
		global $wpdb;
		$reg_table = $wpdb->prefix . 'ns_event_registrations';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		if ( empty( $event_ids ) ) {
			return array();
		}

		// Sanitize IDs
		$event_ids = array_map( 'absint', $event_ids );
		$placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );

		// Fetch all registrations for all events in one query
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.event_id, r.person_id, r.ticket_count, r.amount_paid,
				        r.payment_status, r.checked_in, r.registration_date,
				        p.first_name, p.last_name, p.email
				 FROM {$reg_table} r
				 JOIN {$people_table} p ON r.person_id = p.id
				 WHERE r.event_id IN ($placeholders)
				 ORDER BY r.event_id, r.registration_date DESC",
				$event_ids
			)
		);

		// Group registrations by event_id
		$grouped = array();
		foreach ( $results as $registration ) {
			$event_id = $registration->event_id;
			if ( ! isset( $grouped[ $event_id ] ) ) {
				$grouped[ $event_id ] = array();
			}
			$grouped[ $event_id ][] = $registration;
		}

		return $grouped;
	}

	private static function update_event_counts( $event_id ) {
		global $wpdb;
		$events_table = $wpdb->prefix . 'ns_events';
		$reg_table = $wpdb->prefix . 'ns_event_registrations';

		$counts = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as total_registered,
				SUM(amount_paid) as total_revenue
			FROM {$reg_table}
			WHERE event_id = %d",
			$event_id
		) );

		$wpdb->update(
			$events_table,
			array(
				'registered_count' => $counts->total_registered,
				'total_revenue' => $counts->total_revenue,
			),
			array( 'id' => $event_id ),
			array( '%d', '%f' ),
			array( '%d' )
		);
	}

	public static function get_event_types() {
		return array(
			'fundraiser' => __( 'Fundraiser', 'nonprofitsuite' ),
			'community' => __( 'Community Event', 'nonprofitsuite' ),
			'member' => __( 'Member Event', 'nonprofitsuite' ),
			'gala' => __( 'Gala', 'nonprofitsuite' ),
			'conference' => __( 'Conference', 'nonprofitsuite' ),
		);
	}
}
