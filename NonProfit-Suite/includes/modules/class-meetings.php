<?php
/**
 * Meetings Module
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Meetings module for managing organizational meetings.
 */
class NonprofitSuite_Meetings {

	/**
	 * Get a meeting by ID.
	 *
	 * @param int $meeting_id The meeting ID.
	 * @return object|null Meeting object or null if not found.
	 */
	public static function get( $meeting_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_meetings';

		// Use caching for individual meetings
		$cache_key = NonprofitSuite_Cache::item_key( 'meeting', $meeting_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $meeting_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, title, meeting_type, meeting_date, location, virtual_url,
				        description, status, quorum_required, quorum_met, created_by, created_at
				 FROM {$table}
				 WHERE id = %d",
				$meeting_id
			) );
		}, 300 );
	}

	/**
	 * Get all meetings.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of meeting objects.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_meetings';

		$defaults = array(
			'meeting_type' => '',
			'status' => '',
			'upcoming' => false,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";
		if ( $args['meeting_type'] ) {
			$where .= $wpdb->prepare( " AND meeting_type = %s", $args['meeting_type'] );
		}
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}
		if ( $args['upcoming'] ) {
			$where .= " AND meeting_date >= NOW()";
		}

		// Use caching for meeting lists
		$cache_key = NonprofitSuite_Cache::list_key( 'meetings', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			$query = "SELECT id, title, meeting_type, meeting_date, location, virtual_url,
			                 description, status, quorum_required, quorum_met, created_by, created_at
			          FROM {$table} {$where}
			          ORDER BY {$orderby}
			          " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $query );
		}, 300 );
	}

	/**
	 * Create a new meeting.
	 *
	 * @param array $data Meeting data.
	 * @return int|false Meeting ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_meetings';

		$defaults = array(
			'title' => '',
			'meeting_type' => 'board',
			'meeting_date' => '',
			'location' => '',
			'virtual_url' => '',
			'description' => '',
			'status' => 'scheduled',
			'quorum_required' => null,
			'quorum_met' => null,
			'created_by' => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$table,
			array(
				'title' => sanitize_text_field( $data['title'] ),
				'meeting_type' => sanitize_text_field( $data['meeting_type'] ),
				'meeting_date' => sanitize_text_field( $data['meeting_date'] ),
				'location' => sanitize_text_field( $data['location'] ),
				'virtual_url' => esc_url_raw( $data['virtual_url'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'status' => sanitize_text_field( $data['status'] ),
				'quorum_required' => $data['quorum_required'],
				'quorum_met' => $data['quorum_met'],
				'created_by' => absint( $data['created_by'] ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'meetings' );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a meeting.
	 *
	 * @param int   $meeting_id The meeting ID.
	 * @param array $data Meeting data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $meeting_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_meetings';

		$update_data = array();
		$format = array();

		$allowed_fields = array( 'title', 'meeting_type', 'meeting_date', 'location', 'virtual_url', 'description', 'status', 'quorum_required', 'quorum_met' );

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields ) ) {
				switch ( $key ) {
					case 'quorum_required':
					case 'quorum_met':
						$update_data[ $key ] = $value !== null ? absint( $value ) : null;
						$format[] = '%d';
						break;
					case 'virtual_url':
						$update_data[ $key ] = esc_url_raw( $value );
						$format[] = '%s';
						break;
					case 'description':
						$update_data[ $key ] = sanitize_textarea_field( $value );
						$format[] = '%s';
						break;
					default:
						$update_data[ $key ] = sanitize_text_field( $value );
						$format[] = '%s';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $meeting_id ),
			$format,
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'meetings' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'meeting', $meeting_id ) );
		}

		return $result;
	}

	/**
	 * Delete a meeting.
	 *
	 * @param int $meeting_id The meeting ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $meeting_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_meetings';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $meeting_id ),
			array( '%d' )
		) !== false;

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'meetings' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'meeting', $meeting_id ) );
		}

		return $result;
	}

	/**
	 * Get meeting types.
	 *
	 * @return array Array of meeting types.
	 */
	public static function get_meeting_types() {
		return array(
			'board' => __( 'Board Meeting', 'nonprofitsuite' ),
			'committee' => __( 'Committee Meeting', 'nonprofitsuite' ),
			'special' => __( 'Special Meeting', 'nonprofitsuite' ),
			'annual' => __( 'Annual Meeting', 'nonprofitsuite' ),
		);
	}

	/**
	 * Get meeting statuses.
	 *
	 * @return array Array of meeting statuses.
	 */
	public static function get_statuses() {
		return array(
			'scheduled' => __( 'Scheduled', 'nonprofitsuite' ),
			'completed' => __( 'Completed', 'nonprofitsuite' ),
			'cancelled' => __( 'Cancelled', 'nonprofitsuite' ),
		);
	}

	/**
	 * Get upcoming meetings.
	 *
	 * @param int $limit Number of meetings to retrieve.
	 * @return array Array of meeting objects.
	 */
	public static function get_upcoming( $limit = 5 ) {
		return self::get_all( array(
			'upcoming' => true,
			'status' => 'scheduled',
			'order' => 'ASC',
			'limit' => $limit,
		) );
	}
}
