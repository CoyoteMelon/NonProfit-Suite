<?php
/**
 * Minutes Module
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Minutes {

	public static function get_by_meeting( $meeting_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_minutes';

		// Use caching for meeting minutes
		$cache_key = NonprofitSuite_Cache::item_key( 'minutes_by_meeting', $meeting_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $meeting_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, meeting_id, content, status, version, created_by, approved_by, approved_at, created_at
				 FROM {$table}
				 WHERE meeting_id = %d
				 ORDER BY version DESC
				 LIMIT 1",
				$meeting_id
			) );
		}, 300 );
	}

	public function auto_save( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_minutes';

		$minutes_data = array(
			'meeting_id' => absint( $data['meeting_id'] ),
			'content' => wp_kses_post( $data['content'] ),
			'status' => 'draft',
			'created_by' => get_current_user_id(),
		);

		if ( isset( $data['id'] ) && $data['id'] > 0 ) {
			$wpdb->update( $table, $minutes_data, array( 'id' => absint( $data['id'] ) ) );
			NonprofitSuite_Cache::invalidate_module( 'minutes' );
			return absint( $data['id'] );
		} else {
			$wpdb->insert( $table, $minutes_data );
			NonprofitSuite_Cache::invalidate_module( 'minutes' );
			return $wpdb->insert_id;
		}
	}

	public static function save_attendance( $meeting_id, $attendance_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_attendance';

		// Delete existing attendance
		$wpdb->delete( $table, array( 'meeting_id' => $meeting_id ) );

		// Insert new attendance
		foreach ( $attendance_data as $person_id => $status ) {
			$wpdb->insert(
				$table,
				array(
					'meeting_id' => $meeting_id,
					'person_id' => $person_id,
					'status' => sanitize_text_field( $status ),
				),
				array( '%d', '%d', '%s' )
			);
		}

		return true;
	}

	public static function save_vote( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_votes';

		$vote_data = array(
			'meeting_id' => absint( $data['meeting_id'] ),
			'motion_text' => sanitize_textarea_field( $data['motion_text'] ),
			'moved_by' => absint( $data['moved_by'] ),
			'seconded_by' => absint( $data['seconded_by'] ),
			'vote_count_for' => absint( $data['vote_count_for'] ),
			'vote_count_against' => absint( $data['vote_count_against'] ),
			'vote_count_abstain' => absint( $data['vote_count_abstain'] ),
			'result' => sanitize_text_field( $data['result'] ),
			'vote_data' => wp_json_encode( $data['vote_data'] ),
		);

		$wpdb->insert( $table, $vote_data );
		return $wpdb->insert_id;
	}

	public static function approve( $minutes_id, $approver_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'ns_approve_minutes', 'approve minutes' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_minutes';

		$result = $wpdb->update(
			$table,
			array(
				'status' => 'approved',
				'approved_by' => $approver_id,
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $minutes_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'minutes' );
		}

		return $result;
	}
}
