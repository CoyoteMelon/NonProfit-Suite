<?php
/**
 * Abstract Base Class for NonprofitSuite Modules
 *
 * This class provides common CRUD functionality that all modules share,
 * dramatically reducing code duplication across 43 modules.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class NonprofitSuite_Module_Base {

	/**
	 * Table name (without prefix).
	 * Must be defined by child class.
	 *
	 * @var string
	 */
	protected static $table_name = '';

	/**
	 * Whether this module requires Pro license.
	 *
	 * @var bool
	 */
	protected static $requires_pro = false;

	/**
	 * Allowed fields for insert/update operations.
	 * Must be defined by child class.
	 *
	 * @var array
	 */
	protected static $fields = array();

	/**
	 * Field types for wpdb formatting (%s, %d, %f).
	 *
	 * @var array
	 */
	protected static $field_types = array();

	/**
	 * Default values for fields.
	 *
	 * @var array
	 */
	protected static $defaults = array();

	/**
	 * Get SELECT columns for queries.
	 * Child classes should override this to specify exact columns.
	 * Falls back to SELECT * if not overridden (less optimal).
	 *
	 * @return string Column list for SELECT statement
	 */
	protected static function get_select_columns() {
		// If child class defines specific columns, use them
		if ( ! empty( static::$fields ) ) {
			return implode( ', ', static::$fields );
		}
		// Fallback to SELECT * (less optimal, but works)
		return '*';
	}

	/**
	 * Check if Pro license is required and active.
	 *
	 * @return bool|WP_Error True if OK, WP_Error if license required and not active.
	 */
	protected static function check_pro() {
		if ( static::$requires_pro && ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error(
				'pro_required',
				sprintf(
					/* translators: %s: module name */
					__( '%s requires Pro license.', 'nonprofitsuite' ),
					static::get_module_name()
				)
			);
		}
		return true;
	}

	/**
	 * Get module name for error messages.
	 *
	 * @return string Module name.
	 */
	protected static function get_module_name() {
		$class = get_called_class();
		return str_replace( 'NonprofitSuite_', '', $class );
	}

	/**
	 * Get full table name with prefix.
	 *
	 * @return string Full table name.
	 */
	protected static function get_table() {
		global $wpdb;
		return $wpdb->prefix . static::$table_name;
	}

	/**
	 * Create a new record.
	 *
	 * @param array $data Record data.
	 * @return int|WP_Error Insert ID on success, WP_Error on failure.
	 */
	public static function create( $data ) {
		global $wpdb;

		$check = static::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Merge with defaults
		$data = wp_parse_args( $data, static::$defaults );

		// Sanitize data
		$sanitized = static::sanitize_data( $data );

		// Extract only allowed fields
		$insert_data = array();
		$insert_types = array();

		foreach ( static::$fields as $field ) {
			if ( isset( $sanitized[ $field ] ) ) {
				$insert_data[ $field ] = $sanitized[ $field ];
				$insert_types[] = isset( static::$field_types[ $field ] ) ? static::$field_types[ $field ] : '%s';
			}
		}

		// Insert
		$result = $wpdb->insert(
			static::get_table(),
			$insert_data,
			$insert_types
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				sprintf(
					/* translators: %s: module name */
					__( 'Failed to create %s record.', 'nonprofitsuite' ),
					static::get_module_name()
				)
			);
		}

		// Invalidate cache for this module
		$module_slug = strtolower( str_replace( '_', '-', static::get_module_name() ) );
		NonprofitSuite_Cache::invalidate_module( $module_slug );

		return $wpdb->insert_id;
	}

	/**
	 * Get a single record by ID.
	 *
	 * @param int $id Record ID.
	 * @return object|WP_Error Record object or WP_Error.
	 */
	public static function get( $id ) {
		global $wpdb;

		$check = static::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Try cache first
		$cache_key = NonprofitSuite_Cache::item_key( strtolower( static::get_module_name() ), $id );
		$cached = NonprofitSuite_Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Query database
		$columns = static::get_select_columns();
		$record = $wpdb->get_row( $wpdb->prepare(
			"SELECT {$columns} FROM " . static::get_table() . " WHERE id = %d",
			$id
		) );

		if ( ! $record ) {
			return new WP_Error(
				'not_found',
				sprintf(
					/* translators: %s: module name */
					__( '%s record not found.', 'nonprofitsuite' ),
					static::get_module_name()
				)
			);
		}

		// Cache for 5 minutes
		NonprofitSuite_Cache::set( $cache_key, $record, 300 );

		return $record;
	}

	/**
	 * Get multiple records with filters.
	 *
	 * @param array $args Query arguments.
	 * @return array|WP_Error Array of records or WP_Error.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$check = static::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Try cache
		$cache_key = NonprofitSuite_Cache::list_key( strtolower( static::get_module_name() ), $args );
		$cached = NonprofitSuite_Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Build WHERE clause (can be overridden by child classes)
		$where = static::build_where_clause( $args );

		// Apply safe limit to prevent unbounded queries
		if ( isset( $args['limit'] ) ) {
			$args['limit'] = NonprofitSuite_Query_Optimizer::apply_safe_limit(
				$args['limit'],
				strtolower( static::get_module_name() )
			);
		}

		// Build query with optimized column selection
		$columns = static::get_select_columns();
		$sql = "SELECT {$columns} FROM " . static::get_table() . "
		        {$where}
		        ORDER BY " . $args['orderby'] . "
		        " . NonprofitSuite_Utilities::build_limit_clause( $args );

		$results = $wpdb->get_results( $sql );

		// Cache for 5 minutes
		NonprofitSuite_Cache::set( $cache_key, $results, 300 );

		return $results;
	}

	/**
	 * Update a record.
	 *
	 * @param int   $id   Record ID.
	 * @param array $data Data to update.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$check = static::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Sanitize data
		$sanitized = static::sanitize_data( $data );

		// Extract only allowed fields
		$update_data = array();
		$update_types = array();

		foreach ( static::$fields as $field ) {
			if ( isset( $sanitized[ $field ] ) ) {
				$update_data[ $field ] = $sanitized[ $field ];
				$update_types[] = isset( static::$field_types[ $field ] ) ? static::$field_types[ $field ] : '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No valid fields to update.', 'nonprofitsuite' ) );
		}

		// Update
		$result = $wpdb->update(
			static::get_table(),
			$update_data,
			array( 'id' => $id ),
			$update_types,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				sprintf(
					/* translators: %s: module name */
					__( 'Failed to update %s record.', 'nonprofitsuite' ),
					static::get_module_name()
				)
			);
		}

		// Invalidate cache
		$module_slug = strtolower( str_replace( '_', '-', static::get_module_name() ) );
		NonprofitSuite_Cache::invalidate_module( $module_slug );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( $module_slug, $id ) );

		return true;
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$check = static::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$result = $wpdb->delete(
			static::get_table(),
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				sprintf(
					/* translators: %s: module name */
					__( 'Failed to delete %s record.', 'nonprofitsuite' ),
					static::get_module_name()
				)
			);
		}

		// Invalidate cache
		$module_slug = strtolower( str_replace( '_', '-', static::get_module_name() ) );
		NonprofitSuite_Cache::invalidate_module( $module_slug );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( $module_slug, $id ) );

		return true;
	}

	/**
	 * Sanitize data based on field types.
	 *
	 * Can be overridden by child classes for custom sanitization.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	protected static function sanitize_data( $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			// Skip if not in allowed fields
			if ( ! in_array( $key, static::$fields, true ) ) {
				continue;
			}

			// Get field type
			$type = isset( static::$field_types[ $key ] ) ? static::$field_types[ $key ] : '%s';

			// Sanitize based on type
			switch ( $type ) {
				case '%d':
					$sanitized[ $key ] = absint( $value );
					break;
				case '%f':
					$sanitized[ $key ] = floatval( $value );
					break;
				case '%s':
				default:
					// Check if it's a textarea field (contains 'description', 'notes', 'content', etc.)
					if ( preg_match( '/(description|notes|content|message|details)/i', $key ) ) {
						$sanitized[ $key ] = sanitize_textarea_field( $value );
					} else {
						$sanitized[ $key ] = sanitize_text_field( $value );
					}
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Build WHERE clause for queries.
	 *
	 * Can be overridden by child classes for custom filtering.
	 *
	 * @param array $args Query arguments.
	 * @return string WHERE clause.
	 */
	protected static function build_where_clause( $args ) {
		return "WHERE 1=1";
	}

	/**
	 * Count total records matching criteria.
	 *
	 * @param array $args Query arguments (same as get_all).
	 * @return int|WP_Error Count or WP_Error.
	 */
	public static function count( $args = array() ) {
		global $wpdb;

		$check = static::check_pro();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$where = static::build_where_clause( $args );

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM " . static::get_table() . " {$where}"
		);

		return (int) $count;
	}

	/**
	 * Check if a record exists.
	 *
	 * @param int $id Record ID.
	 * @return bool True if exists, false otherwise.
	 */
	public static function exists( $id ) {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . static::get_table() . " WHERE id = %d",
			$id
		) );

		return $count > 0;
	}
}
