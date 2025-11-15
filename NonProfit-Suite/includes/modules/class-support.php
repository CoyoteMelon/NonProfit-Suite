<?php
/**
 * Plugin Support System Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Support {

	/**
	 * Generate unique ticket number
	 *
	 * @return string Ticket number
	 */
	private static function generate_ticket_number() {
		global $wpdb;

		do {
			$number = 'NS' . date( 'Ymd' ) . wp_rand( 1000, 9999 );
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ns_support_tickets WHERE ticket_number = %s",
				$number
			) );
		} while ( $exists );

		return $number;
	}

	/**
	 * Create support ticket
	 *
	 * @param array $data Ticket data
	 * @return array|WP_Error Ticket info or error
	 */
	public static function create_ticket( $data ) {
		// Check permissions FIRST - any logged-in user can create support tickets
		$permission_check = NonprofitSuite_Security::check_capability( 'read', 'create support ticket' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$ticket_number = self::generate_ticket_number();
		$user_id = get_current_user_id();

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_support_tickets',
			array(
				'ticket_number' => $ticket_number,
				'user_id' => $user_id,
				'subject' => sanitize_text_field( $data['subject'] ),
				'description' => wp_kses_post( $data['description'] ),
				'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
				'priority' => isset( $data['priority'] ) ? sanitize_text_field( $data['priority'] ) : 'normal',
				'status' => 'open',
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create ticket', 'nonprofitsuite' ) );
		}

		$ticket_id = $wpdb->insert_id;

		// Send to SilverHost.net API (implement based on API specs)
		// self::send_to_silverhost( $ticket_id, $data );

		NonprofitSuite_Cache::invalidate_module( 'support_tickets' );
		return array(
			'ticket_id' => $ticket_id,
			'ticket_number' => $ticket_number,
			'message' => __( 'Support ticket created successfully. Ticket #:', 'nonprofitsuite' ) . $ticket_number,
		);
	}

	/**
	 * Get tickets
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of tickets or error
	 */
	public static function get_tickets( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'user_id' => null,
			'status' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['user_id'] ) {
			$where[] = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for support tickets
		$cache_key = NonprofitSuite_Cache::list_key( 'support_tickets', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, ticket_number, user_id, subject, description, category, priority, status,
			               assigned_to, last_response_date, resolved_date, created_at
			        FROM {$wpdb->prefix}ns_support_tickets
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
	 * Add response to ticket
	 *
	 * @param int    $ticket_id Ticket ID
	 * @param string $message Response message
	 * @param string $author_type user|support
	 * @return int|WP_Error Response ID or error
	 */
	public static function add_response( $ticket_id, $message, $author_type = 'user' ) {
		// Check permissions FIRST - any logged-in user can add responses to tickets
		$permission_check = NonprofitSuite_Security::check_capability( 'read', 'respond to support ticket' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_support_responses',
			array(
				'ticket_id' => absint( $ticket_id ),
				'author_type' => sanitize_text_field( $author_type ),
				'author_id' => get_current_user_id(),
				'message' => wp_kses_post( $message ),
				'is_internal' => 0,
			),
			array( '%d', '%s', '%d', '%s', '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to add response', 'nonprofitsuite' ) );
		}

		// Update ticket last response date
		$wpdb->update(
			$wpdb->prefix . 'ns_support_tickets',
			array( 'last_response_date' => current_time( 'mysql' ) ),
			array( 'id' => absint( $ticket_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		NonprofitSuite_Cache::invalidate_module( 'support_tickets' );
		return $wpdb->insert_id;
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
		$user_id = get_current_user_id();

		// Use caching for support dashboard
		$cache_key = NonprofitSuite_Cache::item_key( 'support_dashboard', $user_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $user_id ) {
			$open_tickets = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_support_tickets WHERE user_id = %d AND status IN ('open', 'in_progress')",
				$user_id
			) );

			$recent_tickets = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, ticket_number, user_id, subject, description, category, priority, status,
				        assigned_to, last_response_date, resolved_date, created_at
				 FROM {$wpdb->prefix}ns_support_tickets WHERE user_id = %d ORDER BY created_at DESC LIMIT 5",
				$user_id
			) );

			return array(
				'open_tickets' => absint( $open_tickets ),
				'recent_tickets' => $recent_tickets,
			);
		}, 300 );
	}
}
