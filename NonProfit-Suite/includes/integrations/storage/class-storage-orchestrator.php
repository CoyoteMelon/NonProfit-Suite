<?php
/**
 * Storage Orchestrator
 *
 * Intelligent multi-tier storage coordinator that routes file operations
 * through the optimal tier based on availability and access patterns.
 *
 * Tier Priority for Serving:
 * 1. CDN (fastest, edge-cached public files)
 * 2. Cloud Primary (S3, Backblaze, Dropbox)
 * 3. Local Cache (bandwidth saver)
 * 4. Local Backup (disaster recovery)
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
 * NonprofitSuite_Storage_Orchestrator Class
 *
 * Manages multi-tier storage strategy with intelligent fallback.
 */
class NonprofitSuite_Storage_Orchestrator {

	/**
	 * Integration Manager instance
	 *
	 * @var NonprofitSuite_Integration_Manager
	 */
	private $manager;

	/**
	 * Cache layer instance
	 *
	 * @var NonprofitSuite_Storage_Cache
	 */
	private $cache;

	/**
	 * Version manager instance
	 *
	 * @var NonprofitSuite_Storage_Version_Manager
	 */
	private $version_manager;

	/**
	 * Tier priority order
	 *
	 * @var array
	 */
	private $tier_priority = array( 'cdn', 'cloud', 'cache', 'local' );

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->cache = new NonprofitSuite_Storage_Cache();
		$this->version_manager = new NonprofitSuite_Storage_Version_Manager();
	}

	/**
	 * Upload a file to storage (multi-tier)
	 *
	 * @param string $file_path Local file path
	 * @param array  $args      Upload arguments
	 *                          - filename: Desired filename (optional)
	 *                          - folder: Folder path (optional)
	 *                          - is_public: Public accessibility (default false)
	 *                          - version_note: Version change description (optional)
	 *                          - permissions: Permission settings (optional)
	 * @return array|WP_Error Upload result with keys: file_id, file_uuid, version_id, urls
	 */
	public function upload_file( $file_path, $args = array() ) {
		global $wpdb;

		// Validate file
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'filename'     => basename( $file_path ),
			'folder'       => '',
			'is_public'    => false,
			'version_note' => '',
			'permissions'  => array(),
		) );

		// Generate file metadata
		$file_size = filesize( $file_path );
		$mime_type = wp_check_filetype( $file_path )['type'];
		$checksum_md5 = md5_file( $file_path );
		$checksum_sha256 = hash_file( 'sha256', $file_path );
		$file_uuid = wp_generate_uuid4();

		// Create file record
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file_data = array(
			'file_uuid'         => $file_uuid,
			'filename'          => sanitize_file_name( $args['filename'] ),
			'original_filename' => basename( $file_path ),
			'mime_type'         => $mime_type,
			'file_size'         => $file_size,
			'checksum_md5'      => $checksum_md5,
			'checksum_sha256'   => $checksum_sha256,
			'folder_path'       => sanitize_text_field( $args['folder'] ),
			'is_public'         => (int) $args['is_public'],
			'current_version'   => 1,
			'created_by'        => get_current_user_id(),
			'created_at'        => current_time( 'mysql' ),
		);

		$wpdb->insert( $table_files, $file_data );
		$file_id = $wpdb->insert_id;

		if ( ! $file_id ) {
			return new WP_Error( 'db_error', __( 'Failed to create file record', 'nonprofitsuite' ) );
		}

		// Create version record
		$version_id = $this->version_manager->create_version( $file_id, 1, array(
			'file_size'          => $file_size,
			'checksum_md5'       => $checksum_md5,
			'checksum_sha256'    => $checksum_sha256,
			'change_description' => $args['version_note'] ?: __( 'Initial upload', 'nonprofitsuite' ),
		) );

		if ( is_wp_error( $version_id ) ) {
			// Rollback file record
			$wpdb->delete( $table_files, array( 'id' => $file_id ), array( '%d' ) );
			return $version_id;
		}

		// Upload to tiers based on public/private status
		$upload_results = array();

		// Always upload to cloud primary storage
		$cloud_result = $this->upload_to_cloud( $file_path, $file_id, $version_id, $args );
		if ( ! is_wp_error( $cloud_result ) ) {
			$upload_results['cloud'] = $cloud_result;
		}

		// If public, also upload to CDN
		if ( $args['is_public'] ) {
			$cdn_result = $this->upload_to_cdn( $file_path, $file_id, $version_id, $args );
			if ( ! is_wp_error( $cdn_result ) ) {
				$upload_results['cdn'] = $cdn_result;
			}
		}

		// Store in local backup
		$local_result = $this->upload_to_local_backup( $file_path, $file_id, $version_id, $args );
		if ( ! is_wp_error( $local_result ) ) {
			$upload_results['local'] = $local_result;
		}

		// Set permissions
		if ( ! empty( $args['permissions'] ) ) {
			$this->set_file_permissions( $file_id, $args['permissions'] );
		}

		// Default permission: creator can read/write
		$this->set_file_permissions( $file_id, array(
			array(
				'type'       => 'user',
				'user_id'    => get_current_user_id(),
				'can_read'   => true,
				'can_write'  => true,
				'can_delete' => true,
				'can_share'  => true,
			),
		) );

		/**
		 * Fires after file is uploaded to multi-tier storage
		 *
		 * @param int   $file_id        File ID
		 * @param int   $version_id     Version ID
		 * @param array $upload_results Results from each tier
		 */
		do_action( 'ns_storage_file_uploaded_multitier', $file_id, $version_id, $upload_results );

		return array(
			'file_id'    => $file_id,
			'file_uuid'  => $file_uuid,
			'version_id' => $version_id,
			'tiers'      => $upload_results,
		);
	}

	/**
	 * Get file URL (follows tier priority)
	 *
	 * @param int   $file_id File ID or UUID
	 * @param array $args    URL arguments
	 *                       - version: Specific version (optional)
	 *                       - download: Force download (optional)
	 *                       - expires: URL expiration in seconds (optional)
	 * @return string|WP_Error File URL
	 */
	public function get_file_url( $file_id, $args = array() ) {
		global $wpdb;

		// Get file record
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_files} WHERE id = %d OR file_uuid = %s",
			$file_id,
			$file_id
		), ARRAY_A );

		if ( ! $file ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		// Check permissions
		if ( ! $this->can_access_file( $file['id'], 'read' ) ) {
			return new WP_Error( 'permission_denied', __( 'Permission denied', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'version'  => null,
			'download' => false,
			'expires'  => 3600,
		) );

		// Determine version
		$version_id = $args['version'] ?: $this->version_manager->get_current_version_id( $file['id'] );

		// Try each tier in priority order
		foreach ( $this->tier_priority as $tier ) {
			$url = $this->get_url_from_tier( $file['id'], $version_id, $tier, $args );

			if ( ! is_wp_error( $url ) ) {
				// Track hit for cache warming
				if ( 'cache' === $tier || 'cdn' === $tier ) {
					$this->cache->record_hit( $file['id'], $version_id );
				}

				return $url;
			}
		}

		return new WP_Error( 'no_location_available', __( 'File not available from any storage tier', 'nonprofitsuite' ) );
	}

	/**
	 * Download file (retrieves to local temp)
	 *
	 * @param int    $file_id     File ID
	 * @param string $destination Destination path (optional)
	 * @param array  $args        Download arguments
	 * @return string|WP_Error Local file path
	 */
	public function download_file( $file_id, $destination = null, $args = array() ) {
		// Check permissions
		if ( ! $this->can_access_file( $file_id, 'read' ) ) {
			return new WP_Error( 'permission_denied', __( 'Permission denied', 'nonprofitsuite' ) );
		}

		// Try each tier
		foreach ( $this->tier_priority as $tier ) {
			$adapter = $this->get_tier_adapter( $tier );

			if ( is_wp_error( $adapter ) ) {
				continue;
			}

			$version_id = $this->version_manager->get_current_version_id( $file_id );
			$location = $this->get_file_location( $file_id, $version_id, $tier );

			if ( ! $location ) {
				continue;
			}

			$result = $adapter->download( $location['provider_file_id'], $destination );

			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
		}

		return new WP_Error( 'download_failed', __( 'Failed to download from any tier', 'nonprofitsuite' ) );
	}

	/**
	 * Delete file from all tiers
	 *
	 * @param int  $file_id File ID
	 * @param bool $soft_delete Soft delete (mark as deleted) vs hard delete
	 * @return bool|WP_Error True on success
	 */
	public function delete_file( $file_id, $soft_delete = true ) {
		global $wpdb;

		// Check permissions
		if ( ! $this->can_access_file( $file_id, 'delete' ) ) {
			return new WP_Error( 'permission_denied', __( 'Permission denied', 'nonprofitsuite' ) );
		}

		$table_files = $wpdb->prefix . 'ns_storage_files';

		if ( $soft_delete ) {
			// Soft delete: mark as deleted
			$result = $wpdb->update(
				$table_files,
				array( 'deleted_at' => current_time( 'mysql' ) ),
				array( 'id' => $file_id ),
				array( '%s' ),
				array( '%d' )
			);

			return false !== $result ? true : new WP_Error( 'delete_failed', __( 'Failed to delete file', 'nonprofitsuite' ) );
		}

		// Hard delete: remove from all tiers
		$locations = $this->get_all_file_locations( $file_id );

		foreach ( $locations as $location ) {
			$adapter = $this->get_tier_adapter( $location['tier'] );

			if ( ! is_wp_error( $adapter ) ) {
				$adapter->delete( $location['provider_file_id'] );
			}
		}

		// Delete database records
		$table_locations = $wpdb->prefix . 'ns_storage_locations';
		$table_versions = $wpdb->prefix . 'ns_storage_versions';
		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';

		$wpdb->delete( $table_locations, array( 'file_id' => $file_id ), array( '%d' ) );
		$wpdb->delete( $table_versions, array( 'file_id' => $file_id ), array( '%d' ) );
		$wpdb->delete( $table_permissions, array( 'file_id' => $file_id ), array( '%d' ) );
		$wpdb->delete( $table_files, array( 'id' => $file_id ), array( '%d' ) );

		do_action( 'ns_storage_file_deleted', $file_id );

		return true;
	}

	/**
	 * Upload to cloud tier
	 *
	 * @param string $file_path  Local file path
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param array  $args       Upload arguments
	 * @return array|WP_Error Upload result
	 */
	private function upload_to_cloud( $file_path, $file_id, $version_id, $args ) {
		$adapter = $this->manager->get_active_provider( 'storage' );

		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		// Upload via adapter
		$result = $adapter->upload( $file_path, array(
			'folder'   => $args['folder'],
			'filename' => $args['filename'],
			'public'   => $args['is_public'],
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Record location
		$this->record_file_location( $file_id, $version_id, 'cloud', $adapter->get_provider_name(), $result );

		return $result;
	}

	/**
	 * Upload to CDN tier (for public files)
	 *
	 * @param string $file_path  Local file path
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param array  $args       Upload arguments
	 * @return array|WP_Error Upload result
	 */
	private function upload_to_cdn( $file_path, $file_id, $version_id, $args ) {
		// CDN is typically configured on top of cloud storage (S3 + CloudFront)
		// This method handles CDN-specific configuration

		// For now, delegate to cloud adapter with public flag
		// Future: Implement CloudFront invalidation, edge caching, etc.

		return $this->upload_to_cloud( $file_path, $file_id, $version_id, $args );
	}

	/**
	 * Upload to local backup tier
	 *
	 * @param string $file_path  Local file path
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param array  $args       Upload arguments
	 * @return array|WP_Error Upload result
	 */
	private function upload_to_local_backup( $file_path, $file_id, $version_id, $args ) {
		// Use local storage adapter for backup
		$local_adapter = new NonprofitSuite_Storage_Local_Adapter();

		$result = $local_adapter->upload( $file_path, array(
			'folder'   => 'backups/' . $args['folder'],
			'filename' => $args['filename'],
			'public'   => false, // Backups are never public
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Record location
		$this->record_file_location( $file_id, $version_id, 'local', 'local', $result );

		return $result;
	}

	/**
	 * Get URL from specific tier
	 *
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param string $tier       Tier name
	 * @param array  $args       URL arguments
	 * @return string|WP_Error URL
	 */
	private function get_url_from_tier( $file_id, $version_id, $tier, $args ) {
		$location = $this->get_file_location( $file_id, $version_id, $tier );

		if ( ! $location ) {
			return new WP_Error( 'location_not_found', sprintf( __( 'File not found in tier: %s', 'nonprofitsuite' ), $tier ) );
		}

		// If CDN URL available, use it
		if ( 'cdn' === $tier && ! empty( $location['cdn_url'] ) ) {
			return $location['cdn_url'];
		}

		// Otherwise get URL from provider
		$adapter = $this->get_tier_adapter( $tier );

		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		return $adapter->get_url( $location['provider_file_id'], $args );
	}

	/**
	 * Get file location for specific tier
	 *
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param string $tier       Tier name
	 * @return array|null Location data or null
	 */
	private function get_file_location( $file_id, $version_id, $tier ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_locations';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE file_id = %d AND version_id = %d AND tier = %s AND sync_status = 'synced' LIMIT 1",
			$file_id,
			$version_id,
			$tier
		), ARRAY_A );
	}

	/**
	 * Get all locations for a file
	 *
	 * @param int $file_id File ID
	 * @return array Locations
	 */
	private function get_all_file_locations( $file_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_locations';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE file_id = %d",
			$file_id
		), ARRAY_A );
	}

	/**
	 * Record file location in database
	 *
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param string $tier       Tier name
	 * @param string $provider   Provider name
	 * @param array  $result     Upload result from adapter
	 * @return int|false Location ID or false
	 */
	private function record_file_location( $file_id, $version_id, $tier, $provider, $result ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_locations';

		$location_data = array(
			'file_id'          => $file_id,
			'version_id'       => $version_id,
			'tier'             => $tier,
			'provider'         => sanitize_text_field( $provider ),
			'provider_file_id' => isset( $result['file_id'] ) ? $result['file_id'] : '',
			'provider_path'    => isset( $result['path'] ) ? $result['path'] : '',
			'provider_url'     => isset( $result['url'] ) ? $result['url'] : '',
			'cdn_url'          => isset( $result['cdn_url'] ) ? $result['cdn_url'] : null,
			'sync_status'      => 'synced',
			'last_synced_at'   => current_time( 'mysql' ),
			'created_at'       => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $location_data );

		return $wpdb->insert_id;
	}

	/**
	 * Get adapter for tier
	 *
	 * @param string $tier Tier name
	 * @return object|WP_Error Adapter instance
	 */
	private function get_tier_adapter( $tier ) {
		switch ( $tier ) {
			case 'cdn':
			case 'cloud':
				return $this->manager->get_active_provider( 'storage' );

			case 'cache':
				return $this->cache->get_adapter();

			case 'local':
				return new NonprofitSuite_Storage_Local_Adapter();

			default:
				return new WP_Error( 'invalid_tier', __( 'Invalid storage tier', 'nonprofitsuite' ) );
		}
	}

	/**
	 * Check if user can access file
	 *
	 * @param int    $file_id    File ID
	 * @param string $permission Permission type (read, write, delete, share)
	 * @return bool True if allowed
	 */
	private function can_access_file( $file_id, $permission = 'read' ) {
		global $wpdb;

		$user_id = get_current_user_id();

		// Admin can do anything
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check if file is public and permission is read
		if ( 'read' === $permission ) {
			$table_files = $wpdb->prefix . 'ns_storage_files';
			$is_public = $wpdb->get_var( $wpdb->prepare(
				"SELECT is_public FROM {$table_files} WHERE id = %d",
				$file_id
			) );

			if ( $is_public ) {
				return true;
			}
		}

		// Check granular permissions
		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';
		$permission_col = 'can_' . $permission;

		$has_permission = $wpdb->get_var( $wpdb->prepare(
			"SELECT {$permission_col} FROM {$table_permissions}
			WHERE file_id = %d
			AND (
				(permission_type = 'user' AND user_id = %d)
				OR (permission_type = 'role' AND role IN (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'wp_capabilities'))
			)
			AND (expires_at IS NULL OR expires_at > NOW())
			LIMIT 1",
			$file_id,
			$user_id,
			$user_id
		) );

		return (bool) $has_permission;
	}

	/**
	 * Set file permissions
	 *
	 * @param int   $file_id     File ID
	 * @param array $permissions Permissions array
	 * @return bool True on success
	 */
	private function set_file_permissions( $file_id, $permissions ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_permissions';

		foreach ( $permissions as $permission ) {
			$permission_data = array(
				'file_id'         => $file_id,
				'permission_type' => $permission['type'],
				'user_id'         => isset( $permission['user_id'] ) ? $permission['user_id'] : null,
				'role'            => isset( $permission['role'] ) ? $permission['role'] : null,
				'can_read'        => isset( $permission['can_read'] ) ? (int) $permission['can_read'] : 1,
				'can_write'       => isset( $permission['can_write'] ) ? (int) $permission['can_write'] : 0,
				'can_delete'      => isset( $permission['can_delete'] ) ? (int) $permission['can_delete'] : 0,
				'can_share'       => isset( $permission['can_share'] ) ? (int) $permission['can_share'] : 0,
				'expires_at'      => isset( $permission['expires_at'] ) ? $permission['expires_at'] : null,
				'created_by'      => get_current_user_id(),
				'created_at'      => current_time( 'mysql' ),
			);

			$wpdb->insert( $table, $permission_data );
		}

		return true;
	}
}
