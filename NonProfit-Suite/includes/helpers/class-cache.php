<?php
/**
 * Caching Helper Class
 *
 * Provides a unified interface for caching expensive operations using
 * WordPress transients and object cache.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Cache {

	/**
	 * Cache group name for object cache.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'nonprofitsuite';

	/**
	 * Default cache expiration time (1 hour).
	 *
	 * @var int
	 */
	const DEFAULT_EXPIRATION = 3600;

	/**
	 * Get cached value with automatic fallback.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Function to generate value if cache miss.
	 * @param int      $expiration Cache expiration in seconds.
	 * @param string   $type     Cache type: 'transient' or 'object'.
	 * @return mixed Cached or generated value.
	 */
	public static function remember( $key, $callback, $expiration = self::DEFAULT_EXPIRATION, $type = 'object' ) {
		// Try to get from cache
		$value = self::get( $key, $type );

		// Cache miss - generate and store
		if ( false === $value ) {
			$value = call_user_func( $callback );
			self::set( $key, $value, $expiration, $type );
		}

		return $value;
	}

	/**
	 * Get value from cache.
	 *
	 * @param string $key  Cache key.
	 * @param string $type Cache type: 'transient' or 'object'.
	 * @return mixed|false Cached value or false if not found.
	 */
	public static function get( $key, $type = 'object' ) {
		$key = self::prefix_key( $key );

		if ( 'transient' === $type ) {
			return get_transient( $key );
		}

		// Object cache (supports Redis, Memcached if configured)
		return wp_cache_get( $key, self::CACHE_GROUP );
	}

	/**
	 * Set value in cache.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration in seconds.
	 * @param string $type       Cache type: 'transient' or 'object'.
	 * @return bool True on success, false on failure.
	 */
	public static function set( $key, $value, $expiration = self::DEFAULT_EXPIRATION, $type = 'object' ) {
		$key = self::prefix_key( $key );

		if ( 'transient' === $type ) {
			return set_transient( $key, $value, $expiration );
		}

		// Object cache
		return wp_cache_set( $key, $value, self::CACHE_GROUP, $expiration );
	}

	/**
	 * Delete value from cache.
	 *
	 * @param string $key  Cache key.
	 * @param string $type Cache type: 'transient' or 'object'.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key, $type = 'object' ) {
		$key = self::prefix_key( $key );

		if ( 'transient' === $type ) {
			return delete_transient( $key );
		}

		return wp_cache_delete( $key, self::CACHE_GROUP );
	}

	/**
	 * Clear all cache for a specific pattern.
	 *
	 * Note: Pattern matching only works with transients, not object cache.
	 *
	 * @param string $pattern Pattern to match (e.g., 'ns_treasury_*').
	 * @return int Number of cache entries cleared.
	 */
	public static function clear_pattern( $pattern ) {
		global $wpdb;

		$pattern = self::prefix_key( $pattern );
		$pattern = str_replace( '*', '%', $pattern );

		// Clear transients matching pattern
		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_' . $pattern
			)
		);

		return $count;
	}

	/**
	 * Clear all NonprofitSuite cache.
	 *
	 * @return bool True on success.
	 */
	public static function clear_all() {
		// Clear all transients
		self::clear_pattern( '*' );

		// Clear object cache group
		wp_cache_flush_group( self::CACHE_GROUP );

		return true;
	}

	/**
	 * Invalidate cache for a specific module.
	 *
	 * WARNING: This clears ALL caches for the module. Consider using more
	 * granular methods like invalidate_item() or invalidate_lists() instead.
	 *
	 * @param string $module Module name (e.g., 'treasury', 'donors').
	 * @return int Number of cache entries cleared.
	 */
	public static function invalidate_module( $module ) {
		return self::clear_pattern( 'ns_' . $module . '_*' );
	}

	/**
	 * Invalidate cache for a specific item.
	 *
	 * More efficient than invalidate_module() - only clears cache for one item.
	 *
	 * @param string $module Module name.
	 * @param int    $id     Item ID.
	 * @return bool True on success.
	 */
	public static function invalidate_item( $module, $id ) {
		$key = self::item_key( $module, $id );
		return self::delete( $key );
	}

	/**
	 * Invalidate all list caches for a module.
	 *
	 * Clears list queries but preserves individual item caches.
	 *
	 * @param string $module Module name.
	 * @return int Number of cache entries cleared.
	 */
	public static function invalidate_lists( $module ) {
		return self::clear_pattern( 'ns_' . $module . '_list_*' );
	}

	/**
	 * Invalidate related caches when an item is created/updated/deleted.
	 *
	 * Best practice for most mutations: invalidates lists but preserves other items.
	 *
	 * @param string $module  Module name.
	 * @param int    $item_id Item ID (optional, for updates).
	 * @return int Number of cache entries cleared.
	 */
	public static function invalidate_related( $module, $item_id = null ) {
		$cleared = 0;

		// Clear all list caches
		$cleared += self::invalidate_lists( $module );

		// Clear specific item cache if ID provided
		if ( $item_id ) {
			self::invalidate_item( $module, $item_id );
			$cleared++;
		}

		// Clear dashboard/stats caches
		self::delete( self::stats_key( $module ) );
		$cleared++;

		return $cleared;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public static function get_stats() {
		global $wpdb;

		// Count transients
		$transient_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_ns_%'"
		);

		// Estimate total size
		$transient_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_ns_%'"
		);

		return array(
			'transient_count' => (int) $transient_count,
			'transient_size'  => self::format_bytes( $transient_size ),
			'cache_group'     => self::CACHE_GROUP,
		);
	}

	/**
	 * Prefix cache key with plugin identifier.
	 *
	 * @param string $key Original key.
	 * @return string Prefixed key.
	 */
	private static function prefix_key( $key ) {
		// Already prefixed
		if ( strpos( $key, 'ns_' ) === 0 ) {
			return $key;
		}

		return 'ns_' . $key;
	}

	/**
	 * Format bytes to human-readable size.
	 *
	 * @param int $bytes Bytes.
	 * @return string Formatted size.
	 */
	private static function format_bytes( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		} elseif ( $bytes < 1048576 ) {
			return round( $bytes / 1024, 2 ) . ' KB';
		} else {
			return round( $bytes / 1048576, 2 ) . ' MB';
		}
	}

	/**
	 * Cache key generators for common patterns.
	 */

	/**
	 * Generate cache key for list queries.
	 *
	 * @param string $module Module name.
	 * @param array  $args   Query arguments.
	 * @return string Cache key.
	 */
	public static function list_key( $module, $args = array() ) {
		return sprintf(
			'ns_%s_list_%s',
			$module,
			md5( serialize( $args ) )
		);
	}

	/**
	 * Generate cache key for single item.
	 *
	 * @param string $module Module name.
	 * @param int    $id     Item ID.
	 * @return string Cache key.
	 */
	public static function item_key( $module, $id ) {
		return sprintf( 'ns_%s_item_%d', $module, $id );
	}

	/**
	 * Generate cache key for dashboard/stats.
	 *
	 * @param string $context Context identifier.
	 * @return string Cache key.
	 */
	public static function stats_key( $context ) {
		return sprintf( 'ns_stats_%s', $context );
	}
}
