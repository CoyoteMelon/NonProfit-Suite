<?php
/**
 * Dropbox Business Storage Adapter
 *
 * Provides team file storage and collaboration using Dropbox Business API.
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
 * Class NonprofitSuite_Storage_Dropbox_Adapter
 *
 * Dropbox Business cloud storage implementation.
 *
 * Features:
 * - Team folder support
 * - File versioning (30-day history for Standard, unlimited for Advanced)
 * - Shared links for public access
 * - OAuth 2.0 authentication
 * - Team member collaboration
 *
 * @since 1.0.0
 */
class NonprofitSuite_Storage_Dropbox_Adapter implements NonprofitSuite_Storage_Adapter {

    /**
     * Dropbox API endpoint
     *
     * @var string
     */
    const API_ENDPOINT = 'https://api.dropboxapi.com/2';

    /**
     * Dropbox content endpoint
     *
     * @var string
     */
    const CONTENT_ENDPOINT = 'https://content.dropboxapi.com/2';

    /**
     * Dropbox access token
     *
     * @var string
     */
    private $access_token;

    /**
     * Dropbox refresh token
     *
     * @var string
     */
    private $refresh_token;

    /**
     * Dropbox app key
     *
     * @var string
     */
    private $app_key;

    /**
     * Dropbox app secret
     *
     * @var string
     */
    private $app_secret;

    /**
     * Root folder path in Dropbox
     *
     * @var string
     */
    private $root_folder;

    /**
     * Whether Dropbox is configured
     *
     * @var bool
     */
    private $is_configured = false;

    /**
     * Constructor
     *
     * @param array $config Configuration array with Dropbox credentials.
     */
    public function __construct($config = array()) {
        $this->access_token = isset($config['access_token']) ? $config['access_token'] : get_option('ns_dropbox_access_token', '');
        $this->refresh_token = isset($config['refresh_token']) ? $config['refresh_token'] : get_option('ns_dropbox_refresh_token', '');
        $this->app_key = isset($config['app_key']) ? $config['app_key'] : get_option('ns_dropbox_app_key', '');
        $this->app_secret = isset($config['app_secret']) ? $config['app_secret'] : get_option('ns_dropbox_app_secret', '');
        $this->root_folder = isset($config['root_folder']) ? $config['root_folder'] : get_option('ns_dropbox_root_folder', '/NonprofitSuite');

        $this->is_configured = !empty($this->access_token) && !empty($this->app_key);
    }

    /**
     * Upload a file to Dropbox
     *
     * @param string $file_path Path to the local file.
     * @param array  $args      Additional arguments.
     * @return array|WP_Error Upload result with file_id and metadata, or error.
     */
    public function upload($file_path, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('dropbox_not_configured', 'Dropbox is not properly configured.');
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found: ' . $file_path);
        }

        $file_id = isset($args['file_id']) ? $args['file_id'] : wp_generate_uuid4();
        $filename = isset($args['filename']) ? $args['filename'] : basename($file_path);

        // Build Dropbox path
        $dropbox_path = $this->build_dropbox_path($file_id, $filename, $args);

        $file_size = filesize($file_path);
        $file_content = file_get_contents($file_path);

        // For files under 150MB, use simple upload. For larger, use upload session
        if ($file_size < 150 * 1024 * 1024) {
            $result = $this->simple_upload($dropbox_path, $file_content, $args);
        } else {
            $result = $this->session_upload($dropbox_path, $file_path, $args);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'file_id' => $file_id,
            'dropbox_path' => $dropbox_path,
            'dropbox_id' => isset($result['id']) ? $result['id'] : '',
            'size' => $file_size,
            'rev' => isset($result['rev']) ? $result['rev'] : '',
        );
    }

    /**
     * Simple upload for files under 150MB
     *
     * @param string $path    Dropbox path.
     * @param string $content File content.
     * @param array  $args    Additional arguments.
     * @return array|WP_Error Result or error.
     */
    private function simple_upload($path, $content, $args = array()) {
        $mode = isset($args['overwrite']) && $args['overwrite'] ? 'overwrite' : 'add';

        $response = wp_remote_post(self::CONTENT_ENDPOINT . '/files/upload', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode(array(
                    'path' => $path,
                    'mode' => $mode,
                    'autorename' => false,
                    'mute' => false,
                )),
            ),
            'body' => $content,
            'timeout' => 300,
        ));

        return $this->handle_response($response);
    }

    /**
     * Session upload for files over 150MB
     *
     * @param string $path      Dropbox path.
     * @param string $file_path Local file path.
     * @param array  $args      Additional arguments.
     * @return array|WP_Error Result or error.
     */
    private function session_upload($path, $file_path, $args = array()) {
        $chunk_size = 8 * 1024 * 1024; // 8MB chunks
        $file_size = filesize($file_path);
        $handle = fopen($file_path, 'rb');

        if (!$handle) {
            return new WP_Error('file_open_failed', 'Failed to open file for reading.');
        }

        // Start upload session
        $start_response = wp_remote_post(self::CONTENT_ENDPOINT . '/files/upload_session/start', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/octet-stream',
            ),
            'body' => '',
            'timeout' => 60,
        ));

        $start_result = $this->handle_response($start_response);
        if (is_wp_error($start_result)) {
            fclose($handle);
            return $start_result;
        }

        $session_id = $start_result['session_id'];
        $offset = 0;

        // Upload chunks
        while (!feof($handle)) {
            $chunk = fread($handle, $chunk_size);
            $chunk_size_actual = strlen($chunk);

            $append_response = wp_remote_post(self::CONTENT_ENDPOINT . '/files/upload_session/append_v2', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/octet-stream',
                    'Dropbox-API-Arg' => json_encode(array(
                        'cursor' => array(
                            'session_id' => $session_id,
                            'offset' => $offset,
                        ),
                    )),
                ),
                'body' => $chunk,
                'timeout' => 300,
            ));

            if (is_wp_error($append_response)) {
                fclose($handle);
                return $append_response;
            }

            $offset += $chunk_size_actual;
        }

        fclose($handle);

        // Finish upload session
        $mode = isset($args['overwrite']) && $args['overwrite'] ? 'overwrite' : 'add';

        $finish_response = wp_remote_post(self::CONTENT_ENDPOINT . '/files/upload_session/finish', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode(array(
                    'cursor' => array(
                        'session_id' => $session_id,
                        'offset' => $offset,
                    ),
                    'commit' => array(
                        'path' => $path,
                        'mode' => $mode,
                        'autorename' => false,
                        'mute' => false,
                    ),
                )),
            ),
            'body' => '',
            'timeout' => 300,
        ));

        return $this->handle_response($finish_response);
    }

    /**
     * Download a file from Dropbox
     *
     * @param string      $file_id     File identifier.
     * @param string|null $destination Destination path, or null to return file contents.
     * @param array       $args        Additional arguments.
     * @return string|bool|WP_Error File path/contents on success, false/error on failure.
     */
    public function download($file_id, $destination = null, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('dropbox_not_configured', 'Dropbox is not properly configured.');
        }

        $dropbox_path = isset($args['dropbox_path']) ? $args['dropbox_path'] : $this->get_dropbox_path_from_file_id($file_id);

        if (!$dropbox_path) {
            return new WP_Error('dropbox_path_not_found', 'Could not determine Dropbox path for file.');
        }

        $response = wp_remote_post(self::CONTENT_ENDPOINT . '/files/download', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Dropbox-API-Arg' => json_encode(array(
                    'path' => $dropbox_path,
                )),
            ),
            'timeout' => 300,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error('dropbox_download_failed', 'Dropbox download failed with code: ' . $code);
        }

        if ($destination) {
            $written = file_put_contents($destination, $body);
            return $written !== false ? $destination : new WP_Error('write_failed', 'Failed to write file to destination.');
        }

        return $body;
    }

    /**
     * Delete a file from Dropbox
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function delete($file_id, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('dropbox_not_configured', 'Dropbox is not properly configured.');
        }

        $dropbox_path = isset($args['dropbox_path']) ? $args['dropbox_path'] : $this->get_dropbox_path_from_file_id($file_id);

        if (!$dropbox_path) {
            return new WP_Error('dropbox_path_not_found', 'Could not determine Dropbox path for file.');
        }

        $response = wp_remote_post(self::API_ENDPOINT . '/files/delete_v2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'path' => $dropbox_path,
            )),
            'timeout' => 60,
        ));

        $result = $this->handle_response($response);

        return is_wp_error($result) ? $result : true;
    }

    /**
     * Get URL for a file in Dropbox
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return string|WP_Error URL or error.
     */
    public function get_url($file_id, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('dropbox_not_configured', 'Dropbox is not properly configured.');
        }

        $dropbox_path = isset($args['dropbox_path']) ? $args['dropbox_path'] : $this->get_dropbox_path_from_file_id($file_id);

        if (!$dropbox_path) {
            return new WP_Error('dropbox_path_not_found', 'Could not determine Dropbox path for file.');
        }

        // Create temporary shared link
        $response = wp_remote_post(self::API_ENDPOINT . '/files/get_temporary_link', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'path' => $dropbox_path,
            )),
            'timeout' => 60,
        ));

        $result = $this->handle_response($response);

        if (is_wp_error($result)) {
            return $result;
        }

        return isset($result['link']) ? $result['link'] : new WP_Error('no_link', 'No link returned from Dropbox.');
    }

    /**
     * Get metadata for a file in Dropbox
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return array|WP_Error Metadata array or error.
     */
    public function get_metadata($file_id, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('dropbox_not_configured', 'Dropbox is not properly configured.');
        }

        $dropbox_path = isset($args['dropbox_path']) ? $args['dropbox_path'] : $this->get_dropbox_path_from_file_id($file_id);

        if (!$dropbox_path) {
            return new WP_Error('dropbox_path_not_found', 'Could not determine Dropbox path for file.');
        }

        $response = wp_remote_post(self::API_ENDPOINT . '/files/get_metadata', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'path' => $dropbox_path,
            )),
            'timeout' => 60,
        ));

        $result = $this->handle_response($response);

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'name' => isset($result['name']) ? $result['name'] : '',
            'size' => isset($result['size']) ? $result['size'] : 0,
            'rev' => isset($result['rev']) ? $result['rev'] : '',
            'id' => isset($result['id']) ? $result['id'] : '',
            'client_modified' => isset($result['client_modified']) ? $result['client_modified'] : '',
            'server_modified' => isset($result['server_modified']) ? $result['server_modified'] : '',
        );
    }

    /**
     * Test connection to Dropbox
     *
     * @return bool|WP_Error True if connected, error otherwise.
     */
    public function test_connection() {
        if (!$this->is_configured) {
            return new WP_Error('dropbox_not_configured', 'Dropbox credentials not configured.');
        }

        $response = wp_remote_post(self::API_ENDPOINT . '/users/get_current_account', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
            'timeout' => 30,
        ));

        $result = $this->handle_response($response);

        return is_wp_error($result) ? $result : true;
    }

    /**
     * Build Dropbox path from file ID and filename
     *
     * @param string $file_id  File UUID.
     * @param string $filename Original filename.
     * @param array  $args     Additional arguments.
     * @return string Dropbox path.
     */
    private function build_dropbox_path($file_id, $filename, $args = array()) {
        $version_id = isset($args['version_id']) ? $args['version_id'] : 1;

        // Use UUID-based directory structure
        $uuid_parts = explode('-', $file_id);
        $dir1 = substr($uuid_parts[0], 0, 2);
        $dir2 = substr($uuid_parts[0], 2, 2);

        // Format: /NonprofitSuite/ab/cd/abcd1234-uuid_v1_filename.ext
        return $this->root_folder . '/' . $dir1 . '/' . $dir2 . '/' . $file_id . '_v' . $version_id . '_' . sanitize_file_name($filename);
    }

    /**
     * Get Dropbox path from file ID
     *
     * @param string $file_id File UUID.
     * @return string|false Dropbox path or false if not found.
     */
    private function get_dropbox_path_from_file_id($file_id) {
        global $wpdb;

        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ns_storage_locations
            WHERE file_id = %s AND tier = 'cloud' AND provider = 'dropbox'
            ORDER BY version_id DESC LIMIT 1",
            $file_id
        ));

        return $location ? $location->remote_path : false;
    }

    /**
     * Handle API response
     *
     * @param array|WP_Error $response API response.
     * @return array|WP_Error Parsed result or error.
     */
    private function handle_response($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 200) {
            $result = json_decode($body, true);
            return $result !== null ? $result : new WP_Error('parse_failed', 'Failed to parse response.');
        }

        // Handle errors
        $error = json_decode($body, true);
        $message = isset($error['error_summary']) ? $error['error_summary'] : 'Unknown error';

        return new WP_Error('dropbox_api_error', 'Dropbox API error: ' . $message, array('code' => $code));
    }

    /**
     * Refresh access token using refresh token
     *
     * @return bool|WP_Error True on success, error on failure.
     */
    public function refresh_access_token() {
        if (empty($this->refresh_token) || empty($this->app_key) || empty($this->app_secret)) {
            return new WP_Error('missing_credentials', 'Missing refresh token or app credentials.');
        }

        $response = wp_remote_post('https://api.dropbox.com/oauth2/token', array(
            'body' => array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refresh_token,
                'client_id' => $this->app_key,
                'client_secret' => $this->app_secret,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            update_option('ns_dropbox_access_token', $this->access_token);
            return true;
        }

        return new WP_Error('token_refresh_failed', 'Failed to refresh access token.');
    }
}
