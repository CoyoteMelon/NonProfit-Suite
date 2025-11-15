<?php
/**
 * Agenda Module
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Agenda {

	public static function get_items( $meeting_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_agenda_items';

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Use caching for agenda items
		$cache_key = NonprofitSuite_Cache::list_key( 'agenda_items', array_merge( $args, array( 'meeting_id' => $meeting_id ) ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $meeting_id, $args ) {
			$sql = $wpdb->prepare(
				"SELECT id, meeting_id, item_type, title, description, presenter_id,
				        time_allocated, sort_order, created_at
				 FROM {$table}
				 WHERE meeting_id = %d
				 ORDER BY sort_order ASC
				 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
				$meeting_id
			);

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public function save_item( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_agenda_items';

		$item_data = array(
			'meeting_id' => absint( $data['meeting_id'] ),
			'item_type' => sanitize_text_field( $data['item_type'] ),
			'title' => sanitize_text_field( $data['title'] ),
			'description' => sanitize_textarea_field( $data['description'] ),
			'presenter_id' => isset( $data['presenter_id'] ) ? absint( $data['presenter_id'] ) : null,
			'time_allocated' => isset( $data['time_allocated'] ) ? absint( $data['time_allocated'] ) : null,
			'sort_order' => isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0,
		);

		if ( isset( $data['id'] ) && $data['id'] > 0 ) {
			$wpdb->update( $table, $item_data, array( 'id' => absint( $data['id'] ) ) );
			NonprofitSuite_Cache::invalidate_module( 'agenda_items' );
			return absint( $data['id'] );
		} else {
			$wpdb->insert( $table, $item_data );
			NonprofitSuite_Cache::invalidate_module( 'agenda_items' );
			return $wpdb->insert_id;
		}
	}

	public function delete_item( $item_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_agenda_items';
		$result = $wpdb->delete( $table, array( 'id' => $item_id ), array( '%d' ) ) !== false;
		NonprofitSuite_Cache::invalidate_module( 'agenda_items' );
		return $result;
	}

	public function reorder_items( $order ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_agenda_items';

		foreach ( $order as $sort => $item_id ) {
			$wpdb->update(
				$table,
				array( 'sort_order' => $sort ),
				array( 'id' => $item_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return true;
	}

	public static function get_item_types() {
		return array(
			'call_to_order' => __( 'Call to Order', 'nonprofitsuite' ),
			'approval_of_minutes' => __( 'Approval of Minutes', 'nonprofitsuite' ),
			'discussion' => __( 'Discussion', 'nonprofitsuite' ),
			'vote' => __( 'Vote', 'nonprofitsuite' ),
			'report' => __( 'Report', 'nonprofitsuite' ),
			'presentation' => __( 'Presentation', 'nonprofitsuite' ),
			'new_business' => __( 'New Business', 'nonprofitsuite' ),
			'adjournment' => __( 'Adjournment', 'nonprofitsuite' ),
		);
	}
}
