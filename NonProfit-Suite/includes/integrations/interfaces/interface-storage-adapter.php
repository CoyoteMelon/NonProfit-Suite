<?php
/**
 * Storage Adapter Interface
 *
 * Defines the contract for storage providers (S3, Google Drive, Dropbox, etc.)
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
 * Storage Adapter Interface
 *
 * All storage adapters must implement this interface.
 */
interface NonprofitSuite_Storage_Adapter_Interface {

	/**
	 * Upload a file
	 *
	 * @param string $file_path    Local file path
	 * @param array  $args         Upload arguments
	 *                             - folder: Destination folder (optional)
	 *                             - filename: Custom filename (optional)
	 *                             - public: Whether file should be publicly accessible (optional)
	 *                             - metadata: Additional metadata (optional)
	 * @return array|WP_Error Upload result with keys: file_id, url, size, mime_type
	 */
	public function upload( $file_path, $args = array() );

	/**
	 * Download a file
	 *
	 * @param string $file_id      File identifier
	 * @param string $destination  Local destination path (optional)
	 * @return string|WP_Error Local file path or WP_Error on failure
	 */
	public function download( $file_id, $destination = null );

	/**
	 * Delete a file
	 *
	 * @param string $file_id File identifier
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete( $file_id );

	/**
	 * Get file URL
	 *
	 * @param string $file_id File identifier
	 * @param array  $args    URL arguments
	 *                        - expiration: URL expiration in seconds (for pre-signed URLs)
	 *                        - download: Force download vs inline display
	 * @return string|WP_Error File URL or WP_Error on failure
	 */
	public function get_url( $file_id, $args = array() );

	/**
	 * Check if file exists
	 *
	 * @param string $file_id File identifier
	 * @return bool True if exists
	 */
	public function exists( $file_id );

	/**
	 * Get file metadata
	 *
	 * @param string $file_id File identifier
	 * @return array|WP_Error Metadata array or WP_Error on failure
	 *                        - size: File size in bytes
	 *                        - mime_type: MIME type
	 *                        - modified: Last modified timestamp
	 *                        - metadata: Additional provider-specific metadata
	 */
	public function get_metadata( $file_id );

	/**
	 * List files in a folder
	 *
	 * @param string $folder     Folder path (optional, defaults to root)
	 * @param array  $args       List arguments
	 *                           - limit: Maximum number of files (optional)
	 *                           - offset: Pagination offset (optional)
	 *                           - mime_type: Filter by MIME type (optional)
	 * @return array|WP_Error Array of file IDs or WP_Error on failure
	 */
	public function list_files( $folder = '', $args = array() );

	/**
	 * Create a folder
	 *
	 * @param string $folder_path Folder path
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function create_folder( $folder_path );

	/**
	 * Get storage usage statistics
	 *
	 * @return array|WP_Error Statistics array or WP_Error on failure
	 *                        - used: Bytes used
	 *                        - total: Total bytes available (null if unlimited)
	 *                        - file_count: Number of files
	 */
	public function get_usage();

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "Amazon S3", "Google Drive")
	 */
	public function get_provider_name();
}
