<?php
/**
 * Retention Policy entity class
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/entities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retention Policy entity for managing document retention rules.
 */
class NonprofitSuite_Retention_Policy {

	/**
	 * Get a retention policy by ID.
	 *
	 * @param int $policy_id The policy ID.
	 * @return object|null Policy object or null if not found.
	 */
	public static function get( $policy_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_retention_policies';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT id, policy_name, policy_key, document_categories,
			        retention_years, auto_archive_after_days, description,
			        is_active, created_at, updated_at
			 FROM {$table} WHERE id = %d",
			$policy_id
		) );
	}

	/**
	 * Get a retention policy by key.
	 *
	 * @param string $policy_key The policy key.
	 * @return object|null Policy object or null if not found.
	 */
	public static function get_by_key( $policy_key ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_retention_policies';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT id, policy_name, policy_key, document_categories,
			        retention_years, auto_archive_after_days, description,
			        is_active, created_at, updated_at
			 FROM {$table} WHERE policy_key = %s",
			$policy_key
		) );
	}

	/**
	 * Get all retention policies.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of policy objects.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_retention_policies';

		$defaults = array(
			'is_active' => null,
			'orderby' => 'policy_name',
			'order' => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = "WHERE 1=1";
		if ( isset( $args['is_active'] ) && $args['is_active'] !== null ) {
			$where .= $wpdb->prepare( " AND is_active = %d", $args['is_active'] );
		}

		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

		$query = "SELECT id, policy_name, policy_key, document_categories,
		                 retention_years, auto_archive_after_days, description,
		                 is_active, created_at, updated_at
		          FROM {$table} {$where} ORDER BY {$orderby}";

		return $wpdb->get_results( $query );
	}

	/**
	 * Create a new retention policy.
	 *
	 * @param array $data Policy data.
	 * @return int|false Policy ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_retention_policies';

		$defaults = array(
			'policy_name' => '',
			'policy_key' => '',
			'document_categories' => '',
			'retention_years' => 0,
			'auto_archive_after_days' => 365,
			'description' => '',
			'is_active' => 1,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate policy_key is unique
		$existing = self::get_by_key( $data['policy_key'] );
		if ( $existing ) {
			return false;
		}

		// Encode categories if array
		if ( is_array( $data['document_categories'] ) ) {
			$data['document_categories'] = wp_json_encode( $data['document_categories'] );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'policy_name' => sanitize_text_field( $data['policy_name'] ),
				'policy_key' => sanitize_key( $data['policy_key'] ),
				'document_categories' => $data['document_categories'],
				'retention_years' => absint( $data['retention_years'] ),
				'auto_archive_after_days' => absint( $data['auto_archive_after_days'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'is_active' => absint( $data['is_active'] ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a retention policy.
	 *
	 * @param int   $policy_id The policy ID.
	 * @param array $data Policy data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $policy_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_retention_policies';

		$update_data = array();
		$format = array();

		$allowed_fields = array(
			'policy_name',
			'policy_key',
			'document_categories',
			'retention_years',
			'auto_archive_after_days',
			'description',
			'is_active',
		);

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields ) ) {
				switch ( $key ) {
					case 'retention_years':
					case 'auto_archive_after_days':
					case 'is_active':
						$update_data[ $key ] = absint( $value );
						$format[] = '%d';
						break;
					case 'policy_key':
						$update_data[ $key ] = sanitize_key( $value );
						$format[] = '%s';
						break;
					case 'document_categories':
						if ( is_array( $value ) ) {
							$value = wp_json_encode( $value );
						}
						$update_data[ $key ] = $value;
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

		return $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $policy_id ),
			$format,
			array( '%d' )
		) !== false;
	}

	/**
	 * Delete a retention policy.
	 *
	 * @param int $policy_id The policy ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $policy_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_retention_policies';

		return $wpdb->delete(
			$table,
			array( 'id' => $policy_id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Get the appropriate retention policy for a document.
	 *
	 * @param string $category Document category.
	 * @return object|null Policy object or null if not found.
	 */
	public static function get_policy_for_category( $category ) {
		$policies = self::get_all( array( 'is_active' => 1 ) );

		foreach ( $policies as $policy ) {
			$categories = json_decode( $policy->document_categories, true );
			if ( is_array( $categories ) && in_array( $category, $categories ) ) {
				return $policy;
			}
		}

		// Return default policy if no match found
		return self::get_by_key( 'notes' );
	}

	/**
	 * Get policy categories as array.
	 *
	 * @param object $policy Policy object.
	 * @return array Array of categories.
	 */
	public static function get_categories( $policy ) {
		if ( ! $policy ) {
			return array();
		}

		$categories = json_decode( $policy->document_categories, true );
		return is_array( $categories ) ? $categories : array();
	}
}
