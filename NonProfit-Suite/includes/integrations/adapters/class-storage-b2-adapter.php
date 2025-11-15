<?php
/**
 * Backblaze B2 Storage Adapter
 *
 * Provides S3-compatible cloud storage using Backblaze B2.
 * Cost-effective alternative to Amazon S3 with similar API.
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Adapters
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NonprofitSuite_Storage_B2_Adapter
 *
 * Backblaze B2 cloud storage implementation using S3-compatible API.
 *
 * Features:
 * - S3-compatible API (can use AWS SDK)
 * - Cost-effective pricing ($5/TB/month storage, $10/TB egress)
 * - No egress fees for first 3x storage amount
 * - Supports public and private buckets
 * - CDN integration via Cloudflare or Backblaze CDN
 *
 * @since 1.0.0
 */
class NonprofitSuite_Storage_B2_Adapter implements NonprofitSuite_Storage_Adapter {

    /**
     * B2 application key ID
     *
     * @var string
     */
    private $key_id;

    /**
     * B2 application key
     *
     * @var string
     */
    private $app_key;

    /**
     * B2 bucket name
     *
     * @var string
     */
    private $bucket_name;

    /**
     * B2 bucket ID
     *
     * @var string
     */
    private $bucket_id;

    /**
     * B2 endpoint (region)
     *
     * @var string
     */
    private $endpoint;

    /**
     * CDN domain (optional, for public files)
     *
     * @var string
     */
    private $cdn_domain;

    /**
     * S3 client instance (B2 is S3-compatible)
     *
     * @var object|null
     */
    private $s3_client;

    /**
     * Whether B2 is configured
     *
     * @var bool
     */
    private $is_configured = false;

    /**
     * Constructor
     *
     * @param array $config Configuration array with B2 credentials.
     */
    public function __construct($config = array()) {
        $this->key_id = isset($config['key_id']) ? $config['key_id'] : get_option('ns_b2_key_id', '');
        $this->app_key = isset($config['app_key']) ? $config['app_key'] : get_option('ns_b2_app_key', '');
        $this->bucket_name = isset($config['bucket_name']) ? $config['bucket_name'] : get_option('ns_b2_bucket_name', '');
        $this->bucket_id = isset($config['bucket_id']) ? $config['bucket_id'] : get_option('ns_b2_bucket_id', '');
        $this->endpoint = isset($config['endpoint']) ? $config['endpoint'] : get_option('ns_b2_endpoint', 's3.us-west-001.backblazeb2.com');
        $this->cdn_domain = isset($config['cdn_domain']) ? $config['cdn_domain'] : get_option('ns_b2_cdn_domain', '');

        $this->is_configured = !empty($this->key_id) && !empty($this->app_key) && !empty($this->bucket_name);

        if ($this->is_configured) {
            $this->initialize_client();
        }
    }

    /**
     * Initialize B2 S3-compatible client
     *
     * @return void
     */
    private function initialize_client() {
        // Check if AWS SDK is available
        if (!class_exists('Aws\S3\S3Client')) {
            // Try to load from composer if available
            $composer_autoload = WP_PLUGIN_DIR . '/nonprofitsuite/vendor/autoload.php';
            if (file_exists($composer_autoload)) {
                require_once $composer_autoload;
            }
        }

        if (class_exists('Aws\S3\S3Client')) {
            try {
                $this->s3_client = new Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => 'us-west-001', // B2 requires a region but uses custom endpoint
                    'endpoint' => 'https://' . $this->endpoint,
                    'credentials' => [
                        'key' => $this->key_id,
                        'secret' => $this->app_key,
                    ],
                    'use_path_style_endpoint' => false,
                ]);
            } catch (Exception $e) {
                error_log('B2 S3 Client initialization failed: ' . $e->getMessage());
                $this->s3_client = null;
            }
        }
    }

    /**
     * Upload a file to B2
     *
     * @param string $file_path Path to the local file.
     * @param array  $args      Additional arguments.
     * @return array|WP_Error Upload result with file_id and metadata, or error.
     */
    public function upload($file_path, $args = array()) {
        if (!$this->is_configured || !$this->s3_client) {
            return new WP_Error('b2_not_configured', 'Backblaze B2 is not properly configured or AWS SDK is not available.');
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found: ' . $file_path);
        }

        $file_id = isset($args['file_id']) ? $args['file_id'] : wp_generate_uuid4();
        $filename = isset($args['filename']) ? $args['filename'] : basename($file_path);
        $is_public = isset($args['is_public']) ? $args['is_public'] : false;
        $mime_type = isset($args['mime_type']) ? $args['mime_type'] : mime_content_type($file_path);

        // Build B2 key (path in bucket)
        $b2_key = $this->build_b2_key($file_id, $filename, $args);

        try {
            $upload_args = [
                'Bucket' => $this->bucket_name,
                'Key' => $b2_key,
                'SourceFile' => $file_path,
                'ContentType' => $mime_type,
                'ACL' => $is_public ? 'public-read' : 'private',
            ];

            // Add metadata
            if (isset($args['metadata']) && is_array($args['metadata'])) {
                $upload_args['Metadata'] = $args['metadata'];
            }

            $result = $this->s3_client->putObject($upload_args);

            return array(
                'file_id' => $file_id,
                'b2_key' => $b2_key,
                'url' => $this->get_b2_url($b2_key, $is_public),
                'size' => filesize($file_path),
                'mime_type' => $mime_type,
                'etag' => isset($result['ETag']) ? trim($result['ETag'], '"') : '',
            );

        } catch (Exception $e) {
            return new WP_Error('b2_upload_failed', 'Failed to upload to B2: ' . $e->getMessage());
        }
    }

    /**
     * Download a file from B2
     *
     * @param string      $file_id     File identifier.
     * @param string|null $destination Destination path, or null to return file contents.
     * @param array       $args        Additional arguments.
     * @return string|bool|WP_Error File path/contents on success, false/error on failure.
     */
    public function download($file_id, $destination = null, $args = array()) {
        if (!$this->is_configured || !$this->s3_client) {
            return new WP_Error('b2_not_configured', 'Backblaze B2 is not properly configured.');
        }

        $b2_key = isset($args['b2_key']) ? $args['b2_key'] : $this->get_b2_key_from_file_id($file_id);

        if (!$b2_key) {
            return new WP_Error('b2_key_not_found', 'Could not determine B2 key for file.');
        }

        try {
            $download_args = [
                'Bucket' => $this->bucket_name,
                'Key' => $b2_key,
            ];

            if ($destination) {
                $download_args['SaveAs'] = $destination;
                $this->s3_client->getObject($download_args);
                return $destination;
            } else {
                $result = $this->s3_client->getObject($download_args);
                return (string) $result['Body'];
            }

        } catch (Exception $e) {
            return new WP_Error('b2_download_failed', 'Failed to download from B2: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file from B2
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function delete($file_id, $args = array()) {
        if (!$this->is_configured || !$this->s3_client) {
            return new WP_Error('b2_not_configured', 'Backblaze B2 is not properly configured.');
        }

        $b2_key = isset($args['b2_key']) ? $args['b2_key'] : $this->get_b2_key_from_file_id($file_id);

        if (!$b2_key) {
            return new WP_Error('b2_key_not_found', 'Could not determine B2 key for file.');
        }

        try {
            $this->s3_client->deleteObject([
                'Bucket' => $this->bucket_name,
                'Key' => $b2_key,
            ]);

            return true;

        } catch (Exception $e) {
            return new WP_Error('b2_delete_failed', 'Failed to delete from B2: ' . $e->getMessage());
        }
    }

    /**
     * Get URL for a file in B2
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return string|WP_Error URL or error.
     */
    public function get_url($file_id, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('b2_not_configured', 'Backblaze B2 is not properly configured.');
        }

        $b2_key = isset($args['b2_key']) ? $args['b2_key'] : $this->get_b2_key_from_file_id($file_id);
        $is_public = isset($args['is_public']) ? $args['is_public'] : $this->is_public_file($file_id);
        $expires = isset($args['expires']) ? $args['expires'] : 3600; // 1 hour default

        if (!$b2_key) {
            return new WP_Error('b2_key_not_found', 'Could not determine B2 key for file.');
        }

        // For public files, return CDN or direct B2 URL
        if ($is_public) {
            return $this->get_b2_url($b2_key, true);
        }

        // For private files, generate pre-signed URL
        if (!$this->s3_client) {
            return new WP_Error('b2_client_unavailable', 'B2 client not available for generating pre-signed URLs.');
        }

        try {
            $cmd = $this->s3_client->getCommand('GetObject', [
                'Bucket' => $this->bucket_name,
                'Key' => $b2_key,
            ]);

            $request = $this->s3_client->createPresignedRequest($cmd, '+' . $expires . ' seconds');

            return (string) $request->getUri();

        } catch (Exception $e) {
            return new WP_Error('b2_url_failed', 'Failed to generate B2 URL: ' . $e->getMessage());
        }
    }

    /**
     * Get metadata for a file in B2
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return array|WP_Error Metadata array or error.
     */
    public function get_metadata($file_id, $args = array()) {
        if (!$this->is_configured || !$this->s3_client) {
            return new WP_Error('b2_not_configured', 'Backblaze B2 is not properly configured.');
        }

        $b2_key = isset($args['b2_key']) ? $args['b2_key'] : $this->get_b2_key_from_file_id($file_id);

        if (!$b2_key) {
            return new WP_Error('b2_key_not_found', 'Could not determine B2 key for file.');
        }

        try {
            $result = $this->s3_client->headObject([
                'Bucket' => $this->bucket_name,
                'Key' => $b2_key,
            ]);

            return array(
                'size' => isset($result['ContentLength']) ? $result['ContentLength'] : 0,
                'mime_type' => isset($result['ContentType']) ? $result['ContentType'] : '',
                'etag' => isset($result['ETag']) ? trim($result['ETag'], '"') : '',
                'last_modified' => isset($result['LastModified']) ? $result['LastModified']->format('Y-m-d H:i:s') : '',
                'metadata' => isset($result['Metadata']) ? $result['Metadata'] : array(),
            );

        } catch (Exception $e) {
            return new WP_Error('b2_metadata_failed', 'Failed to get B2 metadata: ' . $e->getMessage());
        }
    }

    /**
     * Test connection to B2
     *
     * @return bool|WP_Error True if connected, error otherwise.
     */
    public function test_connection() {
        if (!$this->is_configured) {
            return new WP_Error('b2_not_configured', 'Backblaze B2 credentials not configured.');
        }

        if (!$this->s3_client) {
            return new WP_Error('b2_sdk_unavailable', 'AWS SDK for PHP is not available. Install via: composer require aws/aws-sdk-php');
        }

        try {
            // Try to list objects with limit 1 to test credentials and bucket access
            $this->s3_client->listObjects([
                'Bucket' => $this->bucket_name,
                'MaxKeys' => 1,
            ]);

            return true;

        } catch (Exception $e) {
            return new WP_Error('b2_connection_failed', 'B2 connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Build B2 key (object path) from file ID and filename
     *
     * @param string $file_id  File UUID.
     * @param string $filename Original filename.
     * @param array  $args     Additional arguments.
     * @return string B2 key.
     */
    private function build_b2_key($file_id, $filename, $args = array()) {
        $prefix = isset($args['prefix']) ? trim($args['prefix'], '/') . '/' : 'nonprofitsuite/';
        $version_id = isset($args['version_id']) ? $args['version_id'] : 1;

        // Use UUID-based directory structure for distribution
        $uuid_parts = explode('-', $file_id);
        $dir1 = substr($uuid_parts[0], 0, 2);
        $dir2 = substr($uuid_parts[0], 2, 2);

        // Format: nonprofitsuite/ab/cd/abcd1234-uuid_v1_filename.ext
        return $prefix . $dir1 . '/' . $dir2 . '/' . $file_id . '_v' . $version_id . '_' . sanitize_file_name($filename);
    }

    /**
     * Get B2 key from file ID
     *
     * @param string $file_id File UUID.
     * @return string|false B2 key or false if not found.
     */
    private function get_b2_key_from_file_id($file_id) {
        global $wpdb;

        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ns_storage_locations
            WHERE file_id = %s AND tier = 'cloud' AND provider = 'b2'
            ORDER BY version_id DESC LIMIT 1",
            $file_id
        ));

        return $location ? $location->remote_path : false;
    }

    /**
     * Get B2 URL for a key
     *
     * @param string $b2_key    B2 object key.
     * @param bool   $is_public Whether file is public.
     * @return string URL.
     */
    private function get_b2_url($b2_key, $is_public = false) {
        // If CDN domain is configured for public files, use it
        if ($is_public && !empty($this->cdn_domain)) {
            return 'https://' . $this->cdn_domain . '/' . $b2_key;
        }

        // Otherwise use direct B2 URL
        return 'https://' . $this->endpoint . '/' . $this->bucket_name . '/' . $b2_key;
    }

    /**
     * Check if file is public
     *
     * @param string $file_id File UUID.
     * @return bool True if public.
     */
    private function is_public_file($file_id) {
        global $wpdb;

        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT is_public FROM {$wpdb->prefix}ns_storage_files WHERE file_id = %s",
            $file_id
        ));

        return $file ? (bool) $file->is_public : false;
    }
}
