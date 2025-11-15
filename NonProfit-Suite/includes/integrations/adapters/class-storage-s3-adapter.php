<?php
/**
 * Amazon S3 Storage Adapter
 *
 * Adapter for Amazon S3 cloud storage with optional CloudFront CDN integration.
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
 * NonprofitSuite_Storage_S3_Adapter Class
 *
 * Implements storage integration with Amazon S3 and CloudFront.
 */
class NonprofitSuite_Storage_S3_Adapter implements NonprofitSuite_Storage_Adapter_Interface {

	/**
	 * S3 client instance
	 *
	 * @var object
	 */
	private $s3_client;

	/**
	 * S3 bucket name
	 *
	 * @var string
	 */
	private $bucket;

	/**
	 * S3 region
	 *
	 * @var string
	 */
	private $region;

	/**
	 * CloudFront distribution domain (optional)
	 *
	 * @var string
	 */
	private $cloudfront_domain;

	/**
	 * S3 credentials
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->load_configuration();
		$this->init_s3_client();
	}

	/**
	 * Upload a file
	 *
	 * @param string $file_path Local file path
	 * @param array  $args      Upload arguments
	 * @return array|WP_Error Upload result
	 */
	public function upload( $file_path, $args = array() ) {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 not configured', 'nonprofitsuite' ) );
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'folder'   => '',
			'filename' => basename( $file_path ),
			'public'   => false,
			'metadata' => array(),
		) );

		// Build S3 key (path)
		$s3_key = $this->build_s3_key( $args['folder'], $args['filename'] );

		// Prepare upload parameters
		$upload_params = array(
			'Bucket'      => $this->bucket,
			'Key'         => $s3_key,
			'SourceFile'  => $file_path,
			'ContentType' => wp_check_filetype( $file_path )['type'],
			'Metadata'    => $args['metadata'],
		);

		// Set ACL based on public flag
		if ( $args['public'] ) {
			$upload_params['ACL'] = 'public-read';
		} else {
			$upload_params['ACL'] = 'private';
		}

		try {
			// Upload to S3 using AWS SDK
			$result = $this->s3_put_object( $upload_params );

			// Generate URLs
			$s3_url = $this->get_s3_url( $s3_key );
			$cdn_url = $args['public'] && $this->cloudfront_domain
				? $this->get_cloudfront_url( $s3_key )
				: null;

			do_action( 'ns_s3_file_uploaded', $s3_key, $file_path, $args );

			return array(
				'file_id'  => $s3_key,
				'path'     => $s3_key,
				'url'      => $s3_url,
				'cdn_url'  => $cdn_url,
				'size'     => filesize( $file_path ),
				'mime_type' => $upload_params['ContentType'],
				'etag'     => isset( $result['ETag'] ) ? trim( $result['ETag'], '"' ) : '',
			);

		} catch ( Exception $e ) {
			return new WP_Error( 's3_upload_failed', $e->getMessage() );
		}
	}

	/**
	 * Download a file
	 *
	 * @param string $file_id     S3 key
	 * @param string $destination Destination path
	 * @return string|WP_Error Local file path
	 */
	public function download( $file_id, $destination = null ) {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 not configured', 'nonprofitsuite' ) );
		}

		// Use temp file if no destination specified
		if ( null === $destination ) {
			$destination = wp_tempnam();
		}

		try {
			$this->s3_get_object( array(
				'Bucket' => $this->bucket,
				'Key'    => $file_id,
				'SaveAs' => $destination,
			) );

			return $destination;

		} catch ( Exception $e ) {
			return new WP_Error( 's3_download_failed', $e->getMessage() );
		}
	}

	/**
	 * Delete a file
	 *
	 * @param string $file_id S3 key
	 * @return bool|WP_Error True on success
	 */
	public function delete( $file_id ) {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 not configured', 'nonprofitsuite' ) );
		}

		try {
			$this->s3_delete_object( array(
				'Bucket' => $this->bucket,
				'Key'    => $file_id,
			) );

			do_action( 'ns_s3_file_deleted', $file_id );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 's3_delete_failed', $e->getMessage() );
		}
	}

	/**
	 * Get file URL
	 *
	 * @param string $file_id S3 key
	 * @param array  $args    URL arguments
	 * @return string|WP_Error File URL
	 */
	public function get_url( $file_id, $args = array() ) {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 not configured', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'expiration' => 3600, // 1 hour default
			'download'   => false,
		) );

		try {
			// Check if file is public (via metadata or ACL check)
			// If public and CloudFront available, use CDN
			if ( $this->cloudfront_domain && $this->is_public_file( $file_id ) ) {
				return $this->get_cloudfront_url( $file_id );
			}

			// Generate pre-signed URL for private files
			$command = $this->s3_get_command( 'GetObject', array(
				'Bucket' => $this->bucket,
				'Key'    => $file_id,
			) );

			if ( $args['download'] ) {
				$command['ResponseContentDisposition'] = 'attachment; filename="' . basename( $file_id ) . '"';
			}

			return $this->s3_create_presigned_request( $command, '+' . $args['expiration'] . ' seconds' );

		} catch ( Exception $e ) {
			return new WP_Error( 's3_url_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Check if file exists
	 *
	 * @param string $file_id S3 key
	 * @return bool True if exists
	 */
	public function exists( $file_id ) {
		if ( ! $this->s3_client ) {
			return false;
		}

		try {
			$this->s3_head_object( array(
				'Bucket' => $this->bucket,
				'Key'    => $file_id,
			) );

			return true;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Get file metadata
	 *
	 * @param string $file_id S3 key
	 * @return array|WP_Error Metadata
	 */
	public function get_metadata( $file_id ) {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 not configured', 'nonprofitsuite' ) );
		}

		try {
			$result = $this->s3_head_object( array(
				'Bucket' => $this->bucket,
				'Key'    => $file_id,
			) );

			return array(
				'size'      => isset( $result['ContentLength'] ) ? $result['ContentLength'] : 0,
				'mime_type' => isset( $result['ContentType'] ) ? $result['ContentType'] : '',
				'modified'  => isset( $result['LastModified'] ) ? strtotime( $result['LastModified'] ) : time(),
				'metadata'  => isset( $result['Metadata'] ) ? $result['Metadata'] : array(),
				'etag'      => isset( $result['ETag'] ) ? trim( $result['ETag'], '"' ) : '',
			);

		} catch ( Exception $e ) {
			return new WP_Error( 's3_metadata_failed', $e->getMessage() );
		}
	}

	/**
	 * List files in a folder
	 *
	 * @param string $folder Folder path
	 * @param array  $args   List arguments
	 * @return array|WP_Error Array of file IDs
	 */
	public function list_files( $folder = '', $args = array() ) {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 not configured', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'limit'     => 1000,
			'offset'    => 0,
			'mime_type' => null,
		) );

		try {
			$params = array(
				'Bucket'  => $this->bucket,
				'Prefix'  => $folder ? trailingslashit( $folder ) : '',
				'MaxKeys' => $args['limit'],
			);

			$result = $this->s3_list_objects_v2( $params );

			$files = array();

			if ( isset( $result['Contents'] ) ) {
				foreach ( $result['Contents'] as $object ) {
					$files[] = $object['Key'];
				}
			}

			return $files;

		} catch ( Exception $e ) {
			return new WP_Error( 's3_list_failed', $e->getMessage() );
		}
	}

	/**
	 * Create a folder (S3 doesn't have folders, but we can create a marker)
	 *
	 * @param string $folder_path Folder path
	 * @return bool|WP_Error True on success
	 */
	public function create_folder( $folder_path ) {
		// S3 doesn't have real folders, they're just key prefixes
		// Create an empty object as a folder marker
		$folder_key = trailingslashit( $folder_path ) . '.foldermarker';

		try {
			$this->s3_put_object( array(
				'Bucket' => $this->bucket,
				'Key'    => $folder_key,
				'Body'   => '',
			) );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 's3_create_folder_failed', $e->getMessage() );
		}
	}

	/**
	 * Get storage usage statistics
	 *
	 * @return array|WP_Error Statistics
	 */
	public function get_usage() {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 not configured', 'nonprofitsuite' ) );
		}

		// Note: S3 doesn't provide real-time usage stats via API
		// This would need to use CloudWatch metrics or calculate from list
		// For now, return basic info

		try {
			$result = $this->s3_list_objects_v2( array(
				'Bucket' => $this->bucket,
			) );

			$total_size = 0;
			$file_count = 0;

			if ( isset( $result['Contents'] ) ) {
				foreach ( $result['Contents'] as $object ) {
					$total_size += $object['Size'];
					$file_count++;
				}
			}

			return array(
				'used'       => $total_size,
				'total'      => null, // S3 is essentially unlimited
				'file_count' => $file_count,
			);

		} catch ( Exception $e ) {
			return new WP_Error( 's3_usage_failed', $e->getMessage() );
		}
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		if ( ! $this->s3_client ) {
			return new WP_Error( 's3_not_configured', __( 'S3 credentials not configured', 'nonprofitsuite' ) );
		}

		try {
			// Try to head the bucket
			$this->s3_head_bucket( array(
				'Bucket' => $this->bucket,
			) );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 's3_connection_failed', $e->getMessage() );
		}
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return __( 'Amazon S3', 'nonprofitsuite' );
	}

	/**
	 * Load configuration from settings
	 */
	private function load_configuration() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$settings = $manager->get_provider_settings( 'storage', 's3', array() );

		$this->bucket = isset( $settings['bucket'] ) ? $settings['bucket'] : '';
		$this->region = isset( $settings['region'] ) ? $settings['region'] : 'us-east-1';
		$this->cloudfront_domain = isset( $settings['cloudfront_domain'] ) ? $settings['cloudfront_domain'] : '';

		$this->credentials = array(
			'key'    => isset( $settings['access_key'] ) ? $settings['access_key'] : '',
			'secret' => isset( $settings['secret_key'] ) ? $settings['secret_key'] : '',
		);
	}

	/**
	 * Initialize S3 client
	 */
	private function init_s3_client() {
		// Check if AWS SDK is available
		if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
			// Try to load composer autoloader
			$autoloader = NONPROFITSUITE_PATH . 'vendor/autoload.php';
			if ( file_exists( $autoloader ) ) {
				require_once $autoloader;
			}
		}

		if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
			$this->s3_client = null;
			return;
		}

		try {
			$this->s3_client = new Aws\S3\S3Client( array(
				'version'     => 'latest',
				'region'      => $this->region,
				'credentials' => array(
					'key'    => $this->credentials['key'],
					'secret' => $this->credentials['secret'],
				),
			) );
		} catch ( Exception $e ) {
			$this->s3_client = null;
		}
	}

	/**
	 * Build S3 key from folder and filename
	 *
	 * @param string $folder   Folder path
	 * @param string $filename Filename
	 * @return string S3 key
	 */
	private function build_s3_key( $folder, $filename ) {
		$key_parts = array();

		if ( ! empty( $folder ) ) {
			$key_parts[] = trim( $folder, '/' );
		}

		$key_parts[] = $filename;

		return implode( '/', $key_parts );
	}

	/**
	 * Get S3 URL for a key
	 *
	 * @param string $s3_key S3 key
	 * @return string S3 URL
	 */
	private function get_s3_url( $s3_key ) {
		return sprintf(
			'https://%s.s3.%s.amazonaws.com/%s',
			$this->bucket,
			$this->region,
			$s3_key
		);
	}

	/**
	 * Get CloudFront URL for a key
	 *
	 * @param string $s3_key S3 key
	 * @return string CloudFront URL
	 */
	private function get_cloudfront_url( $s3_key ) {
		return sprintf(
			'https://%s/%s',
			$this->cloudfront_domain,
			$s3_key
		);
	}

	/**
	 * Check if file is public
	 *
	 * @param string $file_id S3 key
	 * @return bool True if public
	 */
	private function is_public_file( $file_id ) {
		try {
			$acl = $this->s3_get_object_acl( array(
				'Bucket' => $this->bucket,
				'Key'    => $file_id,
			) );

			if ( isset( $acl['Grants'] ) ) {
				foreach ( $acl['Grants'] as $grant ) {
					if ( isset( $grant['Grantee']['URI'] ) &&
					     $grant['Grantee']['URI'] === 'http://acs.amazonaws.com/groups/global/AllUsers' ) {
						return true;
					}
				}
			}

			return false;

		} catch ( Exception $e ) {
			return false;
		}
	}

	// Wrapper methods for S3 SDK calls (allows easy mocking/testing)

	private function s3_put_object( $params ) {
		return $this->s3_client->putObject( $params );
	}

	private function s3_get_object( $params ) {
		return $this->s3_client->getObject( $params );
	}

	private function s3_delete_object( $params ) {
		return $this->s3_client->deleteObject( $params );
	}

	private function s3_head_object( $params ) {
		return $this->s3_client->headObject( $params );
	}

	private function s3_head_bucket( $params ) {
		return $this->s3_client->headBucket( $params );
	}

	private function s3_list_objects_v2( $params ) {
		return $this->s3_client->listObjectsV2( $params );
	}

	private function s3_get_object_acl( $params ) {
		return $this->s3_client->getObjectAcl( $params );
	}

	private function s3_get_command( $name, $params ) {
		return $this->s3_client->getCommand( $name, $params );
	}

	private function s3_create_presigned_request( $command, $expires ) {
		return (string) $this->s3_client->createPresignedRequest( $command, $expires )->getUri();
	}
}
