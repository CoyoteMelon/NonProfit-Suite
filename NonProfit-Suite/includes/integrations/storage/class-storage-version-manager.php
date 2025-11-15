<?php
/**
 * Storage Version Manager
 *
 * Handles document versioning, allowing tracking of changes over time
 * and reverting to previous versions.
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
 * NonprofitSuite_Storage_Version_Manager Class
 *
 * Manages file versions and version history.
 */
class NonprofitSuite_Storage_Version_Manager {

	/**
	 * Create a new version
	 *
	 * @param int   $file_id File ID
	 * @param int   $version_number Version number
	 * @param array $args    Version arguments
	 *                       - file_size: File size in bytes
	 *                       - checksum_md5: MD5 checksum
	 *                       - checksum_sha256: SHA256 checksum (optional)
	 *                       - change_description: Change description (optional)
	 * @return int|WP_Error Version ID or WP_Error
	 */
	public function create_version( $file_id, $version_number, $args ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_versions';

		$version_data = array(
			'file_id'            => $file_id,
			'version_number'     => $version_number,
			'file_size'          => $args['file_size'],
			'checksum_md5'       => $args['checksum_md5'],
			'checksum_sha256'    => isset( $args['checksum_sha256'] ) ? $args['checksum_sha256'] : null,
			'change_description' => isset( $args['change_description'] ) ? $args['change_description'] : '',
			'uploaded_by'        => get_current_user_id(),
			'uploaded_at'        => current_time( 'mysql' ),
			'is_current'         => 1,
		);

		// Mark all other versions as not current
		$wpdb->update(
			$table,
			array( 'is_current' => 0 ),
			array( 'file_id' => $file_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Insert new version
		$wpdb->insert( $table, $version_data );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		$version_id = $wpdb->insert_id;

		do_action( 'ns_storage_version_created', $version_id, $file_id, $version_number );

		return $version_id;
	}

	/**
	 * Get version info
	 *
	 * @param int $version_id Version ID
	 * @return array|WP_Error Version data or WP_Error
	 */
	public function get_version( $version_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_versions';

		$version = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$version_id
		), ARRAY_A );

		if ( ! $version ) {
			return new WP_Error( 'version_not_found', __( 'Version not found', 'nonprofitsuite' ) );
		}

		return $version;
	}

	/**
	 * Get all versions for a file
	 *
	 * @param int   $file_id File ID
	 * @param array $args    Query arguments
	 *                       - order: asc or desc (default desc)
	 *                       - limit: Maximum number of versions
	 * @return array Versions
	 */
	public function get_versions( $file_id, $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'order' => 'desc',
			'limit' => 100,
		) );

		$table = $wpdb->prefix . 'ns_storage_versions';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$limit = (int) $args['limit'];

		$versions = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE file_id = %d ORDER BY version_number {$order} LIMIT {$limit}",
			$file_id
		), ARRAY_A );

		return $versions ? $versions : array();
	}

	/**
	 * Get current version ID
	 *
	 * @param int $file_id File ID
	 * @return int|null Version ID or null
	 */
	public function get_current_version_id( $file_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_versions';

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE file_id = %d AND is_current = 1",
			$file_id
		) );
	}

	/**
	 * Get current version number
	 *
	 * @param int $file_id File ID
	 * @return int|null Version number or null
	 */
	public function get_current_version_number( $file_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_versions';

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT version_number FROM {$table} WHERE file_id = %d AND is_current = 1",
			$file_id
		) );
	}

	/**
	 * Revert to a previous version
	 *
	 * @param int    $file_id        File ID
	 * @param int    $version_number Version number to revert to
	 * @param string $reason         Reason for reversion (optional)
	 * @return int|WP_Error New version ID or WP_Error
	 */
	public function revert_to_version( $file_id, $version_number, $reason = '' ) {
		global $wpdb;

		// Get the version to revert to
		$table_versions = $wpdb->prefix . 'ns_storage_versions';
		$old_version = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_versions} WHERE file_id = %d AND version_number = %d",
			$file_id,
			$version_number
		), ARRAY_A );

		if ( ! $old_version ) {
			return new WP_Error( 'version_not_found', __( 'Version not found', 'nonprofitsuite' ) );
		}

		// Get current version number
		$current_version_number = $this->get_current_version_number( $file_id );

		// Create new version (revert creates a new version, not overwrites)
		$new_version_number = $current_version_number + 1;

		$change_description = sprintf(
			__( 'Reverted to version %d', 'nonprofitsuite' ),
			$version_number
		);

		if ( ! empty( $reason ) ) {
			$change_description .= ': ' . $reason;
		}

		$new_version_id = $this->create_version( $file_id, $new_version_number, array(
			'file_size'          => $old_version['file_size'],
			'checksum_md5'       => $old_version['checksum_md5'],
			'checksum_sha256'    => $old_version['checksum_sha256'],
			'change_description' => $change_description,
		) );

		if ( is_wp_error( $new_version_id ) ) {
			return $new_version_id;
		}

		// Copy locations from old version to new version
		$this->copy_version_locations( $old_version['id'], $new_version_id );

		// Update current version in files table
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$wpdb->update(
			$table_files,
			array( 'current_version' => $new_version_number ),
			array( 'id' => $file_id ),
			array( '%d' ),
			array( '%d' )
		);

		do_action( 'ns_storage_version_reverted', $file_id, $version_number, $new_version_id );

		return $new_version_id;
	}

	/**
	 * Compare two versions
	 *
	 * @param int $version_id_1 First version ID
	 * @param int $version_id_2 Second version ID
	 * @return array|WP_Error Comparison data or WP_Error
	 */
	public function compare_versions( $version_id_1, $version_id_2 ) {
		$version1 = $this->get_version( $version_id_1 );
		$version2 = $this->get_version( $version_id_2 );

		if ( is_wp_error( $version1 ) ) {
			return $version1;
		}

		if ( is_wp_error( $version2 ) ) {
			return $version2;
		}

		return array(
			'version1'        => $version1,
			'version2'        => $version2,
			'same_checksum'   => $version1['checksum_md5'] === $version2['checksum_md5'],
			'size_difference' => $version2['file_size'] - $version1['file_size'],
			'time_difference' => strtotime( $version2['uploaded_at'] ) - strtotime( $version1['uploaded_at'] ),
		);
	}

	/**
	 * Get version history summary
	 *
	 * @param int $file_id File ID
	 * @return array History summary
	 */
	public function get_history_summary( $file_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_versions';

		$summary = array(
			'total_versions'    => 0,
			'current_version'   => 0,
			'first_uploaded'    => null,
			'last_updated'      => null,
			'total_size_change' => 0,
			'unique_uploaders'  => 0,
		);

		// Get total versions
		$summary['total_versions'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE file_id = %d",
			$file_id
		) );

		// Get current version number
		$summary['current_version'] = $this->get_current_version_number( $file_id );

		// Get first and last upload dates
		$dates = $wpdb->get_row( $wpdb->prepare(
			"SELECT MIN(uploaded_at) as first_uploaded, MAX(uploaded_at) as last_updated
			FROM {$table} WHERE file_id = %d",
			$file_id
		), ARRAY_A );

		$summary['first_uploaded'] = $dates['first_uploaded'];
		$summary['last_updated'] = $dates['last_updated'];

		// Get size change (current - first)
		$sizes = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				(SELECT file_size FROM {$table} WHERE file_id = %d ORDER BY version_number DESC LIMIT 1) as current_size,
				(SELECT file_size FROM {$table} WHERE file_id = %d ORDER BY version_number ASC LIMIT 1) as first_size",
			$file_id,
			$file_id
		), ARRAY_A );

		$summary['total_size_change'] = $sizes['current_size'] - $sizes['first_size'];

		// Get unique uploaders
		$summary['unique_uploaders'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT uploaded_by) FROM {$table} WHERE file_id = %d",
			$file_id
		) );

		return $summary;
	}

	/**
	 * Prune old versions
	 *
	 * Keep only a specified number of recent versions.
	 *
	 * @param int $file_id         File ID
	 * @param int $keep_versions   Number of versions to keep
	 * @param bool $keep_milestones Keep milestone versions (v1, v10, v100, etc.)
	 * @return int Number of versions deleted
	 */
	public function prune_old_versions( $file_id, $keep_versions = 10, $keep_milestones = true ) {
		global $wpdb;

		$table_versions = $wpdb->prefix . 'ns_storage_versions';

		// Get all versions
		$all_versions = $this->get_versions( $file_id, array( 'order' => 'desc', 'limit' => 1000 ) );

		if ( count( $all_versions ) <= $keep_versions ) {
			return 0; // Nothing to prune
		}

		$versions_to_delete = array();
		$kept_count = 0;

		foreach ( $all_versions as $version ) {
			// Always keep current version
			if ( $version['is_current'] ) {
				continue;
			}

			// Keep milestone versions if requested
			if ( $keep_milestones && $this->is_milestone_version( $version['version_number'] ) ) {
				continue;
			}

			// Keep recent versions
			if ( $kept_count < $keep_versions ) {
				$kept_count++;
				continue;
			}

			// Mark for deletion
			$versions_to_delete[] = $version['id'];
		}

		// Delete old versions
		$deleted_count = 0;

		foreach ( $versions_to_delete as $version_id ) {
			// Delete version locations first
			$this->delete_version_locations( $version_id );

			// Delete version record
			$wpdb->delete( $table_versions, array( 'id' => $version_id ), array( '%d' ) );
			$deleted_count++;
		}

		do_action( 'ns_storage_versions_pruned', $file_id, $deleted_count );

		return $deleted_count;
	}

	/**
	 * Check if version number is a milestone
	 *
	 * Milestones: 1, 5, 10, 50, 100, 500, 1000, etc.
	 *
	 * @param int $version_number Version number
	 * @return bool True if milestone
	 */
	private function is_milestone_version( $version_number ) {
		$milestones = array( 1, 5, 10, 50, 100, 500, 1000 );

		return in_array( $version_number, $milestones, true );
	}

	/**
	 * Copy locations from one version to another
	 *
	 * @param int $from_version_id Source version ID
	 * @param int $to_version_id   Destination version ID
	 * @return bool True on success
	 */
	private function copy_version_locations( $from_version_id, $to_version_id ) {
		global $wpdb;

		$table_locations = $wpdb->prefix . 'ns_storage_locations';

		// Get source locations
		$locations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_locations} WHERE version_id = %d",
			$from_version_id
		), ARRAY_A );

		foreach ( $locations as $location ) {
			// Create new location for destination version
			$new_location = $location;
			unset( $new_location['id'] );
			$new_location['version_id'] = $to_version_id;
			$new_location['created_at'] = current_time( 'mysql' );

			$wpdb->insert( $table_locations, $new_location );
		}

		return true;
	}

	/**
	 * Delete all locations for a version
	 *
	 * @param int $version_id Version ID
	 * @return bool True on success
	 */
	private function delete_version_locations( $version_id ) {
		global $wpdb;

		$table_locations = $wpdb->prefix . 'ns_storage_locations';

		// Get locations to delete physical files
		$locations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_locations} WHERE version_id = %d",
			$version_id
		), ARRAY_A );

		// Delete physical files from each tier
		foreach ( $locations as $location ) {
			$this->delete_physical_file( $location );
		}

		// Delete location records
		$wpdb->delete( $table_locations, array( 'version_id' => $version_id ), array( '%d' ) );

		return true;
	}

	/**
	 * Delete physical file from storage tier
	 *
	 * @param array $location Location data
	 * @return bool True on success
	 */
	private function delete_physical_file( $location ) {
		$manager = NonprofitSuite_Integration_Manager::get_instance();

		switch ( $location['tier'] ) {
			case 'cloud':
			case 'cdn':
				$adapter = $manager->get_active_provider( 'storage' );
				if ( ! is_wp_error( $adapter ) ) {
					$adapter->delete( $location['provider_file_id'] );
				}
				break;

			case 'local':
			case 'cache':
				if ( file_exists( $location['provider_path'] ) ) {
					@unlink( $location['provider_path'] );
				}
				break;
		}

		return true;
	}
}
