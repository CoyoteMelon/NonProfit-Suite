<?php
/**
 * Organization entity class
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/entities
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Organization entity for managing organization records.
 */
class NonprofitSuite_Organization {

	/**
	 * Get an organization by ID.
	 *
	 * @param int $org_id The organization ID.
	 * @return object|null Organization object or null if not found.
	 */
	public static function get( $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_organizations';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT id, name, type, email, phone, address, city, state, zip,
			        website, notes, status, created_at, updated_at
			 FROM {$table} WHERE id = %d AND status != 'deleted'",
			$org_id
		) );
	}

	/**
	 * Get all organizations.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of organization objects.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_organizations';

		$defaults = array(
			'status' => 'active',
			'type' => '',
			'orderby' => 'name',
			'order' => 'ASC',
			'limit' => -1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = "WHERE status != 'deleted'";
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}
		if ( $args['type'] ) {
			$where .= $wpdb->prepare( " AND type = %s", $args['type'] );
		}

		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

		// Apply safe limit to prevent unbounded queries
		$safe_limit = NonprofitSuite_Query_Optimizer::apply_safe_limit( $args['limit'], 'organizations' );
		$limit = $wpdb->prepare( "LIMIT %d", $safe_limit );

		$query = "SELECT id, name, type, email, phone, address, city, state, zip,
		                 website, notes, status, created_at, updated_at
		          FROM {$table} {$where} ORDER BY {$orderby} {$limit}";

		return $wpdb->get_results( $query );
	}

	/**
	 * Create a new organization.
	 *
	 * @param array $data Organization data.
	 * @return int|false Organization ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_organizations';

		$defaults = array(
			'name' => '',
			'type' => '',
			'email' => '',
			'phone' => '',
			'address' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'website' => '',
			'notes' => '',
			'status' => 'active',
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$table,
			array(
				'name' => sanitize_text_field( $data['name'] ),
				'type' => sanitize_text_field( $data['type'] ),
				'email' => sanitize_email( $data['email'] ),
				'phone' => sanitize_text_field( $data['phone'] ),
				'address' => sanitize_textarea_field( $data['address'] ),
				'city' => sanitize_text_field( $data['city'] ),
				'state' => sanitize_text_field( $data['state'] ),
				'zip' => sanitize_text_field( $data['zip'] ),
				'website' => esc_url_raw( $data['website'] ),
				'notes' => sanitize_textarea_field( $data['notes'] ),
				'status' => sanitize_text_field( $data['status'] ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an organization.
	 *
	 * @param int   $org_id The organization ID.
	 * @param array $data Organization data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $org_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_organizations';

		$update_data = array();
		$format = array();

		$allowed_fields = array( 'name', 'type', 'email', 'phone', 'address', 'city', 'state', 'zip', 'website', 'notes', 'status' );

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields ) ) {
				switch ( $key ) {
					case 'email':
						$update_data[ $key ] = sanitize_email( $value );
						$format[] = '%s';
						break;
					case 'website':
						$update_data[ $key ] = esc_url_raw( $value );
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

		return $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $org_id ),
			$format,
			array( '%d' )
		) !== false;
	}

	/**
	 * Delete an organization (soft delete).
	 *
	 * @param int $org_id The organization ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $org_id ) {
		return self::update( $org_id, array( 'status' => 'deleted' ) );
	}

	/**
	 * Search organizations by name.
	 *
	 * @param string $search_term Search term.
	 * @return array Array of organization objects.
	 */
	public static function search( $search_term ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_organizations';

		$search = '%' . $wpdb->esc_like( $search_term ) . '%';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, name, type, email, phone, address, city, state, zip,
			        website, notes, status, created_at, updated_at
			 FROM {$table}
			 WHERE status != 'deleted'
			 AND (name LIKE %s OR email LIKE %s)
			 ORDER BY name
			 LIMIT 50",
			$search, $search
		) );
	}
}
