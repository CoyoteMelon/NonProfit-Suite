<?php
/**
 * Storage Cache Layer
 *
 * Manages local file caching to minimize bandwidth usage and improve
 * performance for frequently accessed files.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Storage_Cache Class
 *
 * Handles cache operations for the storage system.
 */
class NonprofitSuite_Storage_Cache {

	/**
	 * Cache directory path
	 *
	 * @var string
	 */
	private $cache_dir;

	/**
	 * Cache expiration (in seconds)
	 *
	 * @var int
	 */
	private $cache_ttl = 604800; // 7 days default

	/**
	 * Constructor
	 */
	public function __construct() {
		$upload_info = wp_upload_dir();
		$this->cache_dir = trailingslashit( $upload_info['basedir'] ) . 'nonprofitsuite/cache/';
		$this->ensure_cache_directory();
	}

	/**
	 * Get cached file or fetch from cloud
	 *
	 * @param int   $file_id    File ID
	 * @param int   $version_id Version ID
	 * @param array $args       Cache arguments
	 * @return string|WP_Error Local cache path or WP_Error
	 */
	public function get_or_fetch( $file_id, $version_id, $args = array() ) {
		// Check if file is in cache
		$cache_entry = $this->get_cache_entry( $file_id, $version_id );

		if ( $cache_entry && $this->is_cache_valid( $cache_entry ) ) {
			// Update hit count and last accessed
			$this->record_hit( $file_id, $version_id );
			return $cache_entry['cache_path'];
		}

		// Cache miss - fetch from cloud
		return $this->fetch_and_cache( $file_id, $version_id, $args );
	}

	/**
	 * Cache a file from cloud storage
	 *
	 * @param int   $file_id    File ID
	 * @param int   $version_id Version ID
	 * @param array $args       Cache arguments
	 * @return string|WP_Error Local cache path or WP_Error
	 */
	public function fetch_and_cache( $file_id, $version_id, $args = array() ) {
		global $wpdb;

		// Get file info
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_files} WHERE id = %d",
			$file_id
		), ARRAY_A );

		if ( ! $file ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		// Download from cloud to temp location
		$temp_file = $this->download_from_cloud( $file_id, $version_id );

		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// Generate cache path
		$cache_path = $this->generate_cache_path( $file['file_uuid'], $version_id, $file['filename'] );

		// Ensure cache subdirectory exists
		$cache_subdir = dirname( $cache_path );
		if ( ! is_dir( $cache_subdir ) ) {
			wp_mkdir_p( $cache_subdir );
		}

		// Move to cache location
		if ( ! rename( $temp_file, $cache_path ) ) {
			@unlink( $temp_file );
			return new WP_Error( 'cache_failed', __( 'Failed to cache file', 'nonprofitsuite' ) );
		}

		// Record in cache table
		$this->record_cache_entry( $file_id, $version_id, $cache_path );

		return $cache_path;
	}

	/**
	 * Invalidate cache for a file
	 *
	 * @param int      $file_id    File ID
	 * @param int|null $version_id Version ID (null = all versions)
	 * @return bool True on success
	 */
	public function invalidate( $file_id, $version_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_cache';

		$where = array( 'file_id' => $file_id );
		$where_format = array( '%d' );

		if ( null !== $version_id ) {
			$where['version_id'] = $version_id;
			$where_format[] = '%d';
		}

		// Get cache entries to delete files
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE file_id = %d" . ( null !== $version_id ? " AND version_id = %d" : "" ),
				null !== $version_id ? array( $file_id, $version_id ) : array( $file_id )
			),
			ARRAY_A
		);

		// Delete physical cache files
		foreach ( $entries as $entry ) {
			if ( file_exists( $entry['cache_path'] ) ) {
				@unlink( $entry['cache_path'] );
			}
		}

		// Delete cache records
		$wpdb->delete( $table, $where, $where_format );

		do_action( 'ns_storage_cache_invalidated', $file_id, $version_id );

		return true;
	}

	/**
	 * Warm cache for popular files
	 *
	 * Pre-caches frequently accessed files based on hit count.
	 *
	 * @param int $limit Number of files to warm
	 * @return int Number of files cached
	 */
	public function warm_cache( $limit = 50 ) {
		global $wpdb;

		$table_cache = $wpdb->prefix . 'ns_storage_cache';
		$table_files = $wpdb->prefix . 'ns_storage_files';

		// Get most popular uncached public files
		$popular_files = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.id, f.current_version
			FROM {$table_files} f
			LEFT JOIN {$table_cache} c ON f.id = c.file_id AND f.current_version = c.version_id
			WHERE f.is_public = 1
			AND f.deleted_at IS NULL
			AND c.id IS NULL
			ORDER BY f.created_at DESC
			LIMIT %d",
			$limit
		), ARRAY_A );

		$cached_count = 0;

		foreach ( $popular_files as $file ) {
			$result = $this->fetch_and_cache( $file['id'], $file['current_version'] );

			if ( ! is_wp_error( $result ) ) {
				$cached_count++;
			}
		}

		return $cached_count;
	}

	/**
	 * Clean expired cache entries
	 *
	 * @return int Number of entries cleaned
	 */
	public function clean_expired() {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_cache';

		// Get expired entries
		$expired = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < NOW()",
			ARRAY_A
		);

		$cleaned_count = 0;

		foreach ( $expired as $entry ) {
			// Delete physical file
			if ( file_exists( $entry['cache_path'] ) ) {
				@unlink( $entry['cache_path'] );
			}

			// Delete record
			$wpdb->delete( $table, array( 'id' => $entry['id'] ), array( '%d' ) );
			$cleaned_count++;
		}

		return $cleaned_count;
	}

	/**
	 * Clean least recently used cache files
	 *
	 * @param int $target_size Target cache size in bytes
	 * @return int Number of files deleted
	 */
	public function clean_lru( $target_size ) {
		global $wpdb;

		$current_size = $this->get_cache_size();

		if ( $current_size <= $target_size ) {
			return 0;
		}

		$table = $wpdb->prefix . 'ns_storage_cache';

		// Get least recently used files
		$lru_files = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY last_accessed_at ASC",
			ARRAY_A
		);

		$deleted_count = 0;
		$freed_space = 0;

		foreach ( $lru_files as $entry ) {
			if ( $current_size - $freed_space <= $target_size ) {
				break;
			}

			// Delete physical file
			if ( file_exists( $entry['cache_path'] ) ) {
				$freed_space += filesize( $entry['cache_path'] );
				@unlink( $entry['cache_path'] );
			}

			// Delete record
			$wpdb->delete( $table, array( 'id' => $entry['id'] ), array( '%d' ) );
			$deleted_count++;
		}

		return $deleted_count;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache stats
	 */
	public function get_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_cache';

		$stats = array(
			'total_files'     => 0,
			'total_size'      => 0,
			'total_hits'      => 0,
			'expired_entries' => 0,
		);

		$stats['total_files'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$stats['total_size'] = $this->get_cache_size();
		$stats['total_hits'] = $wpdb->get_var( "SELECT SUM(hit_count) FROM {$table}" );
		$stats['expired_entries'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < NOW()"
		);

		return $stats;
	}

	/**
	 * Get adapter (for orchestrator compatibility)
	 *
	 * @return object Cache adapter
	 */
	public function get_adapter() {
		return new NonprofitSuite_Storage_Local_Adapter();
	}

	/**
	 * Record cache hit
	 *
	 * @param int $file_id    File ID
	 * @param int $version_id Version ID
	 * @return bool True on success
	 */
	public function record_hit( $file_id, $version_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_cache';

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			SET hit_count = hit_count + 1, last_accessed_at = NOW()
			WHERE file_id = %d AND version_id = %d",
			$file_id,
			$version_id
		) );

		return true;
	}

	/**
	 * Get cache entry
	 *
	 * @param int $file_id    File ID
	 * @param int $version_id Version ID
	 * @return array|null Cache entry or null
	 */
	private function get_cache_entry( $file_id, $version_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_cache';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE file_id = %d AND version_id = %d AND is_valid = 1",
			$file_id,
			$version_id
		), ARRAY_A );
	}

	/**
	 * Check if cache entry is valid
	 *
	 * @param array $entry Cache entry
	 * @return bool True if valid
	 */
	private function is_cache_valid( $entry ) {
		// Check if file exists
		if ( ! file_exists( $entry['cache_path'] ) ) {
			return false;
		}

		// Check if expired
		if ( $entry['expires_at'] && strtotime( $entry['expires_at'] ) < time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Record cache entry in database
	 *
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param string $cache_path Cache file path
	 * @return int|false Cache ID or false
	 */
	private function record_cache_entry( $file_id, $version_id, $cache_path ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_cache';

		$cache_data = array(
			'file_id'          => $file_id,
			'version_id'       => $version_id,
			'cache_path'       => $cache_path,
			'cache_size'       => filesize( $cache_path ),
			'hit_count'        => 0,
			'last_accessed_at' => current_time( 'mysql' ),
			'expires_at'       => date( 'Y-m-d H:i:s', time() + $this->cache_ttl ),
			'is_valid'         => 1,
			'created_at'       => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $cache_data );

		return $wpdb->insert_id;
	}

	/**
	 * Download file from cloud storage
	 *
	 * @param int $file_id    File ID
	 * @param int $version_id Version ID
	 * @return string|WP_Error Temp file path or WP_Error
	 */
	private function download_from_cloud( $file_id, $version_id ) {
		global $wpdb;

		// Get cloud location
		$table_locations = $wpdb->prefix . 'ns_storage_locations';
		$location = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_locations}
			WHERE file_id = %d AND version_id = %d AND tier = 'cloud' AND sync_status = 'synced'
			LIMIT 1",
			$file_id,
			$version_id
		), ARRAY_A );

		if ( ! $location ) {
			return new WP_Error( 'cloud_location_not_found', __( 'Cloud location not found', 'nonprofitsuite' ) );
		}

		// Get cloud adapter
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$adapter = $manager->get_active_provider( 'storage' );

		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		// Download to temp file
		$temp_file = wp_tempnam();
		$result = $adapter->download( $location['provider_file_id'], $temp_file );

		if ( is_wp_error( $result ) ) {
			@unlink( $temp_file );
			return $result;
		}

		return $temp_file;
	}

	/**
	 * Generate cache file path
	 *
	 * @param string $file_uuid UUID
	 * @param int    $version_id Version ID
	 * @param string $filename  Original filename
	 * @return string Cache path
	 */
	private function generate_cache_path( $file_uuid, $version_id, $filename ) {
		// Use UUID-based subdirectories to prevent too many files in one dir
		$subdir = substr( $file_uuid, 0, 2 ) . '/' . substr( $file_uuid, 2, 2 ) . '/';
		return $this->cache_dir . $subdir . $file_uuid . '_v' . $version_id . '_' . $filename;
	}

	/**
	 * Get total cache size
	 *
	 * @return int Total size in bytes
	 */
	private function get_cache_size() {
		if ( ! is_dir( $this->cache_dir ) ) {
			return 0;
		}

		$total_size = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->cache_dir, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$total_size += $file->getSize();
			}
		}

		return $total_size;
	}

	/**
	 * Ensure cache directory exists
	 */
	private function ensure_cache_directory() {
		if ( ! is_dir( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );

			// Add .htaccess for security
			$htaccess = $this->cache_dir . '.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\nOptions -Indexes" );
			}

			// Add index.php
			$index = $this->cache_dir . 'index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, '<?php // Silence is golden.' );
			}
		}
	}
}
