<?php
/**
 * Local Storage Adapter
 *
 * Built-in storage adapter that uses WordPress media library and uploads directory.
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
 * NonprofitSuite_Storage_Local_Adapter Class
 *
 * Implements local file storage using WordPress uploads directory.
 */
class NonprofitSuite_Storage_Local_Adapter implements NonprofitSuite_Storage_Adapter_Interface {

	/**
	 * Upload directory path
	 *
	 * @var string
	 */
	private $upload_dir;

	/**
	 * Upload directory URL
	 *
	 * @var string
	 */
	private $upload_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$upload_info = wp_upload_dir();
		$this->upload_dir = trailingslashit( $upload_info['basedir'] ) . 'nonprofitsuite/';
		$this->upload_url = trailingslashit( $upload_info['baseurl'] ) . 'nonprofitsuite/';

		// Ensure upload directory exists
		$this->ensure_directory_exists( $this->upload_dir );
	}

	/**
	 * Upload a file
	 *
	 * @param string $file_path Local file path
	 * @param array  $args      Upload arguments
	 * @return array|WP_Error Upload result
	 */
	public function upload( $file_path, $args = array() ) {
		// Validate file exists
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		// Parse arguments
		$args = wp_parse_args( $args, array(
			'folder'   => '',
			'filename' => basename( $file_path ),
			'public'   => true,
			'metadata' => array(),
		) );

		// Sanitize filename
		$filename = sanitize_file_name( $args['filename'] );

		// Determine destination directory
		$folder = sanitize_file_name( $args['folder'] );
		$dest_dir = $this->upload_dir;
		if ( ! empty( $folder ) ) {
			$dest_dir .= trailingslashit( $folder );
			$this->ensure_directory_exists( $dest_dir );
		}

		// Generate unique filename if file already exists
		$dest_path = $dest_dir . $filename;
		$counter = 1;
		$pathinfo = pathinfo( $filename );
		while ( file_exists( $dest_path ) ) {
			$filename = $pathinfo['filename'] . '-' . $counter . '.' . $pathinfo['extension'];
			$dest_path = $dest_dir . $filename;
			$counter++;
		}

		// Copy file
		if ( ! copy( $file_path, $dest_path ) ) {
			return new WP_Error( 'copy_failed', __( 'Failed to copy file', 'nonprofitsuite' ) );
		}

		// Get file info
		$file_size = filesize( $dest_path );
		$mime_type = wp_check_filetype( $dest_path )['type'];

		// Generate file ID (relative path from upload_dir)
		$file_id = str_replace( $this->upload_dir, '', $dest_path );

		// Create WordPress attachment if public
		$attachment_id = null;
		if ( $args['public'] ) {
			$attachment_id = $this->create_attachment( $dest_path, $filename, $mime_type );
		}

		/**
		 * Fires after file is uploaded to local storage
		 *
		 * @param string $file_id      File identifier
		 * @param string $dest_path    Destination file path
		 * @param array  $args         Upload arguments
		 */
		do_action( 'ns_storage_file_uploaded', $file_id, $dest_path, $args );

		return array(
			'file_id'        => $file_id,
			'url'            => $this->get_url( $file_id ),
			'size'           => $file_size,
			'mime_type'      => $mime_type,
			'attachment_id'  => $attachment_id,
		);
	}

	/**
	 * Download a file
	 *
	 * @param string $file_id     File identifier
	 * @param string $destination Destination path
	 * @return string|WP_Error Local file path or WP_Error
	 */
	public function download( $file_id, $destination = null ) {
		$source_path = $this->upload_dir . $file_id;

		if ( ! file_exists( $source_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		// If no destination, return source path (already local)
		if ( null === $destination ) {
			return $source_path;
		}

		// Copy to destination
		if ( ! copy( $source_path, $destination ) ) {
			return new WP_Error( 'copy_failed', __( 'Failed to copy file', 'nonprofitsuite' ) );
		}

		return $destination;
	}

	/**
	 * Delete a file
	 *
	 * @param string $file_id File identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete( $file_id ) {
		$file_path = $this->upload_dir . $file_id;

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		// Delete file
		if ( ! unlink( $file_path ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete file', 'nonprofitsuite' ) );
		}

		/**
		 * Fires after file is deleted from local storage
		 *
		 * @param string $file_id File identifier
		 */
		do_action( 'ns_storage_file_deleted', $file_id );

		return true;
	}

	/**
	 * Get file URL
	 *
	 * @param string $file_id File identifier
	 * @param array  $args    URL arguments
	 * @return string|WP_Error File URL
	 */
	public function get_url( $file_id, $args = array() ) {
		$file_path = $this->upload_dir . $file_id;

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		return $this->upload_url . $file_id;
	}

	/**
	 * Check if file exists
	 *
	 * @param string $file_id File identifier
	 * @return bool True if exists
	 */
	public function exists( $file_id ) {
		return file_exists( $this->upload_dir . $file_id );
	}

	/**
	 * Get file metadata
	 *
	 * @param string $file_id File identifier
	 * @return array|WP_Error Metadata array
	 */
	public function get_metadata( $file_id ) {
		$file_path = $this->upload_dir . $file_id;

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		return array(
			'size'      => filesize( $file_path ),
			'mime_type' => wp_check_filetype( $file_path )['type'],
			'modified'  => filemtime( $file_path ),
			'metadata'  => array(),
		);
	}

	/**
	 * List files in a folder
	 *
	 * @param string $folder Folder path
	 * @param array  $args   List arguments
	 * @return array|WP_Error Array of file IDs
	 */
	public function list_files( $folder = '', $args = array() ) {
		$args = wp_parse_args( $args, array(
			'limit'     => 100,
			'offset'    => 0,
			'mime_type' => null,
		) );

		$search_dir = $this->upload_dir;
		if ( ! empty( $folder ) ) {
			$search_dir .= trailingslashit( sanitize_file_name( $folder ) );
		}

		if ( ! is_dir( $search_dir ) ) {
			return array();
		}

		$files = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $search_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$file_id = str_replace( $this->upload_dir, '', $file->getPathname() );

				// Filter by MIME type if specified
				if ( $args['mime_type'] ) {
					$file_mime = wp_check_filetype( $file->getPathname() )['type'];
					if ( $file_mime !== $args['mime_type'] ) {
						continue;
					}
				}

				$files[] = $file_id;
			}
		}

		// Apply limit and offset
		$files = array_slice( $files, $args['offset'], $args['limit'] );

		return $files;
	}

	/**
	 * Create a folder
	 *
	 * @param string $folder_path Folder path
	 * @return bool|WP_Error True on success
	 */
	public function create_folder( $folder_path ) {
		$folder_path = sanitize_file_name( $folder_path );
		$full_path = $this->upload_dir . $folder_path;

		if ( is_dir( $full_path ) ) {
			return true; // Already exists
		}

		if ( ! wp_mkdir_p( $full_path ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Failed to create folder', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get storage usage statistics
	 *
	 * @return array|WP_Error Statistics array
	 */
	public function get_usage() {
		$total_size = 0;
		$file_count = 0;

		if ( ! is_dir( $this->upload_dir ) ) {
			return array(
				'used'       => 0,
				'total'      => null, // Unlimited (depends on hosting)
				'file_count' => 0,
			);
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->upload_dir, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$total_size += $file->getSize();
				$file_count++;
			}
		}

		return array(
			'used'       => $total_size,
			'total'      => null, // Unlimited (depends on hosting)
			'file_count' => $file_count,
		);
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		// Test write permissions
		$test_file = $this->upload_dir . '.test';

		if ( ! wp_mkdir_p( $this->upload_dir ) ) {
			return new WP_Error( 'no_write_permission', __( 'Cannot create upload directory', 'nonprofitsuite' ) );
		}

		if ( ! is_writable( $this->upload_dir ) ) {
			return new WP_Error( 'no_write_permission', __( 'Upload directory is not writable', 'nonprofitsuite' ) );
		}

		// Try to write test file
		if ( ! file_put_contents( $test_file, 'test' ) ) {
			return new WP_Error( 'write_failed', __( 'Cannot write to upload directory', 'nonprofitsuite' ) );
		}

		// Clean up test file
		@unlink( $test_file );

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return __( 'Built-in Local Storage', 'nonprofitsuite' );
	}

	/**
	 * Ensure directory exists and is protected
	 *
	 * @param string $dir Directory path
	 */
	private function ensure_directory_exists( $dir ) {
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );

			// Add .htaccess for security (prevent PHP execution)
			$htaccess = $dir . '.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\nOptions -Indexes" );
			}

			// Add index.php to prevent directory listing
			$index = $dir . 'index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, '<?php // Silence is golden.' );
			}
		}
	}

	/**
	 * Create WordPress attachment for file
	 *
	 * @param string $file_path File path
	 * @param string $filename  Filename
	 * @param string $mime_type MIME type
	 * @return int|null Attachment ID or null
	 */
	private function create_attachment( $file_path, $filename, $mime_type ) {
		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );

		if ( ! is_wp_error( $attachment_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
			wp_update_attachment_metadata( $attachment_id, $attach_data );

			return $attachment_id;
		}

		return null;
	}
}
