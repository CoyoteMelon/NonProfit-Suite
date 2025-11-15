<?php
/**
 * Compliance & Regulatory Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Compliance {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Compliance module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_item( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage compliance' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_compliance_items';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'item_name' => sanitize_text_field( $data['item_name'] ),
				'item_type' => isset( $data['item_type'] ) ? sanitize_text_field( $data['item_type'] ) : 'filing',
				'due_date' => sanitize_text_field( $data['due_date'] ),
				'responsible_person_id' => isset( $data['responsible_person_id'] ) ? absint( $data['responsible_person_id'] ) : null,
				'status' => 'pending',
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'recurrence' => isset( $data['recurrence'] ) ? sanitize_text_field( $data['recurrence'] ) : 'none',
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'compliance' );
		return $wpdb->insert_id;
	}

	public static function mark_completed( $item_id, $completion_date = null ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage compliance' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_compliance_items';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		if ( ! $completion_date ) {
			$completion_date = current_time( 'mysql' );
		}

		$result = $wpdb->update(
			$table,
			array(
				'completion_date' => $completion_date,
				'status' => 'completed',
			),
			array( 'id' => $item_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Handle recurring items - create next occurrence
		if ( $result !== false ) {
			$item = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, item_name, item_type, due_date, responsible_person_id,
				        status, description, recurrence, created_at
				 FROM {$table}
				 WHERE id = %d",
				$item_id
			) );

			if ( $item && $item->recurrence !== 'none' ) {
				self::create_recurring_item( $item );
			}

			NonprofitSuite_Cache::invalidate_module( 'compliance' );
		}

		return $result !== false;
	}

	private static function create_recurring_item( $item ) {
		$next_due_date = self::calculate_next_due_date( $item->due_date, $item->recurrence );

		if ( $next_due_date ) {
			self::create_item( array(
				'item_name' => $item->item_name,
				'item_type' => $item->item_type,
				'due_date' => $next_due_date,
				'responsible_person_id' => $item->responsible_person_id,
				'description' => $item->description,
				'recurrence' => $item->recurrence,
			) );
		}
	}

	private static function calculate_next_due_date( $current_due_date, $recurrence ) {
		$date = new DateTime( $current_due_date );

		switch ( $recurrence ) {
			case 'monthly':
				$date->modify( '+1 month' );
				break;
			case 'quarterly':
				$date->modify( '+3 months' );
				break;
			case 'annually':
				$date->modify( '+1 year' );
				break;
			default:
				return null;
		}

		return $date->format( 'Y-m-d' );
	}

	public static function get_upcoming( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_compliance_items';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$date = date( 'Y-m-d', strtotime( "+{$days} days" ) );

		// Use caching for upcoming compliance items
		$cache_key = NonprofitSuite_Cache::list_key( 'compliance_upcoming', array( 'days' => $days ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $date ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, item_name, item_type, due_date, responsible_person_id,
				        status, description, recurrence, completion_date, created_at
				 FROM {$table}
				 WHERE status = 'pending'
				 AND due_date <= %s
				 AND due_date >= CURDATE()
				 ORDER BY due_date ASC",
				$date
			) );
		}, 300 );
	}

	public static function get_overdue() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_compliance_items';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for overdue compliance items
		$cache_key = NonprofitSuite_Cache::list_key( 'compliance_overdue', array() );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table ) {
			return $wpdb->get_results(
				"SELECT id, item_name, item_type, due_date, responsible_person_id,
				        status, description, recurrence, completion_date, created_at
				 FROM {$table}
				 WHERE status = 'pending'
				 AND due_date < CURDATE()
				 ORDER BY due_date ASC"
			);
		}, 300 );
	}

	public static function get_items( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_compliance_items';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$defaults = array(
			'status' => null,
			'type' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";

		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		if ( $args['type'] ) {
			$where .= $wpdb->prepare( " AND item_type = %s", $args['type'] );
		}

		// Use caching for compliance item lists
		$cache_key = NonprofitSuite_Cache::list_key( 'compliance_items', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, item_name, item_type, due_date, responsible_person_id,
			               status, description, recurrence, completion_date, created_at
			        FROM {$table} {$where}
			        ORDER BY due_date ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_compliance_types() {
		return array(
			'filing' => __( 'Tax Filing (Form 990)', 'nonprofitsuite' ),
			'report' => __( 'Annual Report', 'nonprofitsuite' ),
			'renewal' => __( 'License/Registration Renewal', 'nonprofitsuite' ),
			'audit' => __( 'Financial Audit', 'nonprofitsuite' ),
			'insurance' => __( 'Insurance Renewal', 'nonprofitsuite' ),
			'policy' => __( 'Policy Review', 'nonprofitsuite' ),
			'training' => __( 'Required Training', 'nonprofitsuite' ),
		);
	}

	public static function get_recurrence_options() {
		return array(
			'none' => __( 'One-time', 'nonprofitsuite' ),
			'monthly' => __( 'Monthly', 'nonprofitsuite' ),
			'quarterly' => __( 'Quarterly', 'nonprofitsuite' ),
			'annually' => __( 'Annually', 'nonprofitsuite' ),
		);
	}
}
