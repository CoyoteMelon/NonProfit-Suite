<?php
/**
 * Person entity class
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/entities
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Person entity for managing people records.
 */
class NonprofitSuite_Person {

	/**
	 * Get a person by ID.
	 *
	 * @param int $person_id The person ID.
	 * @return object|null Person object or null if not found.
	 */
	public static function get( $person_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		// Use caching for individual person lookups
		$cache_key = NonprofitSuite_Cache::item_key( 'person', $person_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $person_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, user_id, first_name, last_name, email, phone,
				        address, city, state, zip, notes, status,
				        created_at, updated_at
				 FROM {$table} WHERE id = %d AND status != 'deleted'",
				$person_id
			) );
		}, 600 );
	}

	/**
	 * Get all people.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of person objects.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		$defaults = array(
			'status' => 'active',
			'orderby' => 'last_name',
			'order' => 'ASC',
			'limit' => -1,
		);

		$args = wp_parse_args( $args, $defaults );

		// Use caching for people lists
		$cache_key = NonprofitSuite_Cache::list_key( 'people', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $args ) {
			$where = "WHERE status != 'deleted'";
			if ( $args['status'] ) {
				$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
			}

			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			// Apply safe limit to prevent unbounded queries
			$safe_limit = NonprofitSuite_Query_Optimizer::apply_safe_limit( $args['limit'], 'people' );
			$limit = $wpdb->prepare( "LIMIT %d", $safe_limit );

			$query = "SELECT id, user_id, first_name, last_name, email, phone,
			                 address, city, state, zip, notes, status,
			                 created_at, updated_at
			          FROM {$table} {$where} ORDER BY {$orderby} {$limit}";

			return $wpdb->get_results( $query );
		}, 300 );
	}

	/**
	 * Create a new person.
	 *
	 * @param array $data Person data.
	 * @return int|false Person ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		$defaults = array(
			'user_id' => null,
			'first_name' => '',
			'last_name' => '',
			'email' => '',
			'phone' => '',
			'address' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'notes' => '',
			'status' => 'active',
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$table,
			array(
				'user_id' => $data['user_id'],
				'first_name' => sanitize_text_field( $data['first_name'] ),
				'last_name' => sanitize_text_field( $data['last_name'] ),
				'email' => sanitize_email( $data['email'] ),
				'phone' => sanitize_text_field( $data['phone'] ),
				'address' => sanitize_textarea_field( $data['address'] ),
				'city' => sanitize_text_field( $data['city'] ),
				'state' => sanitize_text_field( $data['state'] ),
				'zip' => sanitize_text_field( $data['zip'] ),
				'notes' => sanitize_textarea_field( $data['notes'] ),
				'status' => sanitize_text_field( $data['status'] ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			NonprofitSuite_Cache::invalidate_module( 'people' );
		}

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a person.
	 *
	 * @param int   $person_id The person ID.
	 * @param array $data Person data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $person_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		$update_data = array();
		$format = array();

		$allowed_fields = array( 'user_id', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'state', 'zip', 'notes', 'status' );

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields ) ) {
				switch ( $key ) {
					case 'user_id':
						$update_data[ $key ] = $value;
						$format[] = '%d';
						break;
					case 'email':
						$update_data[ $key ] = sanitize_email( $value );
						$format[] = '%s';
						break;
					case 'address':
					case 'notes':
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
			array( 'id' => $person_id ),
			$format,
			array( '%d' )
		);

		if ( false !== $result ) {
			NonprofitSuite_Cache::invalidate_module( 'people' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'person', $person_id ) );
		}

		return $result !== false;
	}

	/**
	 * Delete a person (soft delete).
	 *
	 * @param int $person_id The person ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $person_id ) {
		return self::update( $person_id, array( 'status' => 'deleted' ) );
	}

	/**
	 * Get a person by user ID.
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return object|null Person object or null if not found.
	 */
	public static function get_by_user_id( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		// Use caching for user_id lookups
		$cache_key = 'person_by_user_' . $user_id;
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $user_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, user_id, first_name, last_name, email, phone,
				        address, city, state, zip, notes, status,
				        created_at, updated_at
				 FROM {$table} WHERE user_id = %d AND status != 'deleted'",
				$user_id
			) );
		}, 600 );
	}

	/**
	 * Get person's full name.
	 *
	 * @param object $person Person object.
	 * @return string Full name.
	 */
	public static function get_full_name( $person ) {
		if ( ! $person ) {
			return '';
		}

		return trim( $person->first_name . ' ' . $person->last_name );
	}

	/**
	 * Search people by name or email.
	 *
	 * @param string $search_term Search term.
	 * @return array Array of person objects.
	 */
	public static function search( $search_term ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		$search = '%' . $wpdb->esc_like( $search_term ) . '%';

		// Use shorter cache for search results
		$cache_key = 'person_search_' . md5( $search_term );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $search ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, user_id, first_name, last_name, email, phone,
				        address, city, state, zip, notes, status,
				        created_at, updated_at
				 FROM {$table}
				 WHERE status != 'deleted'
				 AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)
				 ORDER BY last_name, first_name
				 LIMIT 50",
				$search, $search, $search
			) );
		}, 180 );
	}
}
