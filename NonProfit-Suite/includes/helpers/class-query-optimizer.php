<?php
/**
 * Query Optimizer
 *
 * Optimizes database queries for better performance with large datasets
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Optimizer class for performance improvements
 */
class NonprofitSuite_Query_Optimizer {

	/**
	 * Maximum records to fetch without explicit limit
	 *
	 * @var int
	 */
	private static $default_max_records = 1000;

	/**
	 * Slow query threshold in seconds
	 *
	 * @var float
	 */
	private static $slow_query_threshold = 1.0;

	/**
	 * Initialize query optimization
	 */
	public static function init() {
		// Add query monitoring in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'shutdown', array( __CLASS__, 'log_slow_queries' ) );
		}

		// Add query result limiting filter
		add_filter( 'nonprofitsuite_query_limit', array( __CLASS__, 'apply_safe_limit' ), 10, 2 );
	}

	/**
	 * Apply safe limit to queries
	 *
	 * @param int    $limit Requested limit
	 * @param string $context Query context
	 * @return int Safe limit
	 */
	public static function apply_safe_limit( $limit, $context = 'general' ) {
		$max_limit = apply_filters( 'nonprofitsuite_max_query_limit', self::$default_max_records, $context );

		// If no limit or -1 (unlimited), apply default max
		if ( $limit === -1 || $limit === 0 || $limit > $max_limit ) {
			return $max_limit;
		}

		return absint( $limit );
	}

	/**
	 * Log slow queries for performance monitoring
	 */
	public static function log_slow_queries() {
		global $wpdb;

		if ( ! isset( $wpdb->queries ) || empty( $wpdb->queries ) ) {
			return;
		}

		$slow_queries = array();

		foreach ( $wpdb->queries as $query ) {
			$time = isset( $query[1] ) ? floatval( $query[1] ) : 0;

			if ( $time > self::$slow_query_threshold ) {
				$slow_queries[] = array(
					'query' => $query[0],
					'time'  => $time,
					'stack' => isset( $query[2] ) ? $query[2] : '',
				);
			}
		}

		if ( ! empty( $slow_queries ) ) {
			error_log( '[NonprofitSuite] Found ' . count( $slow_queries ) . ' slow queries:' );
			foreach ( $slow_queries as $index => $query ) {
				error_log( sprintf(
					'[NonprofitSuite] Slow Query #%d (%.4fs): %s',
					$index + 1,
					$query['time'],
					substr( $query['query'], 0, 200 )
				) );
			}

			// Allow custom handling
			do_action( 'nonprofitsuite_slow_queries_detected', $slow_queries );
		}
	}

	/**
	 * Optimize query with proper LIMIT and OFFSET
	 *
	 * @param string $query SQL query
	 * @param array  $args Pagination arguments
	 * @return string Optimized query
	 */
	public static function add_pagination( $query, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'page'     => 1,
			'per_page' => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize pagination
		$page = max( 1, absint( $args['page'] ) );
		$per_page = self::apply_safe_limit( $args['per_page'], 'pagination' );
		$offset = ( $page - 1 ) * $per_page;

		// Add LIMIT clause if not present
		if ( stripos( $query, 'LIMIT' ) === false ) {
			$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );
		}

		return $query;
	}

	/**
	 * Check if query should use cache
	 *
	 * @param string $query SQL query
	 * @return bool True if query should be cached
	 */
	public static function should_cache( $query ) {
		// Don't cache if query has RAND()
		if ( stripos( $query, 'RAND()' ) !== false ) {
			return false;
		}

		// Don't cache time-sensitive queries
		if ( preg_match( '/WHERE.*created_at|updated_at.*>.*NOW\(\)/i', $query ) ) {
			return false;
		}

		// Cache SELECT queries that don't have variables
		if ( stripos( $query, 'SELECT' ) === 0 && stripos( $query, '%' ) === false ) {
			return true;
		}

		return false;
	}

	/**
	 * Add INDEX hint for better query performance
	 *
	 * @param string $table Table name
	 * @param string $index Index name
	 * @return string Index hint SQL
	 */
	public static function use_index( $table, $index ) {
		return " USE INDEX ({$index})";
	}

	/**
	 * Optimize JOIN queries by forcing specific join order
	 *
	 * @param array $tables Tables in join order
	 * @return string SQL hint
	 */
	public static function force_join_order( $tables ) {
		return " STRAIGHT_JOIN";
	}

	/**
	 * Get query statistics
	 *
	 * @return array Query statistics
	 */
	public static function get_stats() {
		global $wpdb;

		$stats = array(
			'total_queries' => 0,
			'total_time' => 0,
			'slow_queries' => 0,
			'cache_hits' => 0,
		);

		if ( isset( $wpdb->queries ) && is_array( $wpdb->queries ) ) {
			$stats['total_queries'] = count( $wpdb->queries );

			foreach ( $wpdb->queries as $query ) {
				$time = isset( $query[1] ) ? floatval( $query[1] ) : 0;
				$stats['total_time'] += $time;

				if ( $time > self::$slow_query_threshold ) {
					$stats['slow_queries']++;
				}
			}
		}

		return $stats;
	}

	/**
	 * Check if table has proper indexes
	 *
	 * @param string $table Table name
	 * @return array List of indexes
	 */
	public static function check_indexes( $table ) {
		global $wpdb;

		// Validate table name against allowed tables to prevent SQL injection
		$allowed_tables = array(
			$wpdb->prefix . 'ns_meetings',
			$wpdb->prefix . 'ns_agenda_items',
			$wpdb->prefix . 'ns_minutes',
			$wpdb->prefix . 'ns_attendance',
			$wpdb->prefix . 'ns_votes',
			$wpdb->prefix . 'ns_tasks',
			$wpdb->prefix . 'ns_documents',
			$wpdb->prefix . 'ns_people',
			$wpdb->prefix . 'ns_organizations',
			$wpdb->prefix . 'ns_donors',
			$wpdb->prefix . 'ns_donations',
			$wpdb->prefix . 'ns_volunteers',
			$wpdb->prefix . 'ns_volunteer_hours',
			$wpdb->prefix . 'ns_transactions',
			$wpdb->prefix . 'ns_accounts',
			$wpdb->prefix . 'ns_grants',
			$wpdb->prefix . 'ns_programs',
			$wpdb->prefix . 'ns_members',
			$wpdb->prefix . 'ns_events',
			$wpdb->prefix . 'ns_committees',
			$wpdb->prefix . 'ns_compliance_items',
			$wpdb->prefix . 'ns_employees',
			$wpdb->prefix . 'ns_policies',
		);

		if ( ! in_array( $table, $allowed_tables, true ) ) {
			return array();
		}

		$indexes = $wpdb->get_results( $wpdb->prepare(
			"SHOW INDEX FROM `{$table}`"
		), ARRAY_A );

		$index_info = array();
		foreach ( $indexes as $index ) {
			$key_name = $index['Key_name'];
			if ( ! isset( $index_info[ $key_name ] ) ) {
				$index_info[ $key_name ] = array(
					'columns' => array(),
					'unique' => $index['Non_unique'] == 0,
				);
			}
			$index_info[ $key_name ]['columns'][] = $index['Column_name'];
		}

		return $index_info;
	}

	/**
	 * Analyze query and provide optimization suggestions
	 *
	 * @param string $query SQL query
	 * @return array Suggestions
	 */
	public static function analyze_query( $query ) {
		$suggestions = array();

		// Check for SELECT *
		if ( preg_match( '/SELECT\s+\*/i', $query ) ) {
			$suggestions[] = __( 'Consider selecting only needed columns instead of SELECT *', 'nonprofitsuite' );
		}

		// Check for missing LIMIT
		if ( stripos( $query, 'SELECT' ) === 0 && stripos( $query, 'LIMIT' ) === false ) {
			$suggestions[] = __( 'Add LIMIT clause to prevent fetching too many records', 'nonprofitsuite' );
		}

		// Check for OR in WHERE clause
		if ( preg_match( '/WHERE.*OR/i', $query ) ) {
			$suggestions[] = __( 'OR conditions can prevent index usage. Consider using UNION or IN()', 'nonprofitsuite' );
		}

		// Check for function on indexed column
		if ( preg_match( '/WHERE\s+\w+\([^\)]+\)\s*=/i', $query ) ) {
			$suggestions[] = __( 'Functions on indexed columns prevent index usage', 'nonprofitsuite' );
		}

		// Check for wildcard at start of LIKE
		if ( preg_match( '/LIKE\s+[\'"]%/i', $query ) ) {
			$suggestions[] = __( 'Leading wildcard in LIKE prevents index usage', 'nonprofitsuite' );
		}

		return $suggestions;
	}

	/**
	 * Get recommended pagination settings for table size
	 *
	 * @param int $total_rows Total rows in table
	 * @return array Recommended settings
	 */
	public static function get_recommended_pagination( $total_rows ) {
		if ( $total_rows < 100 ) {
			return array( 'per_page' => 50 );
		} elseif ( $total_rows < 1000 ) {
			return array( 'per_page' => 100 );
		} elseif ( $total_rows < 10000 ) {
			return array( 'per_page' => 50 );
		} else {
			return array( 'per_page' => 25 );
		}
	}
}

// Initialize optimizer
NonprofitSuite_Query_Optimizer::init();
