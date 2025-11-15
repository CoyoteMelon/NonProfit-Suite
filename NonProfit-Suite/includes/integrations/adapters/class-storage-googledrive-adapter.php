<?php
/**
 * Google Drive Storage Adapter
 *
 * IMPORTANT: This adapter is ONLY for collaboration and backup purposes.
 * Files stored in Google Drive CANNOT be served to the public.
 * Use S3, B2, or CDN adapters for public file serving.
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
 * Class NonprofitSuite_Storage_GoogleDrive_Adapter
 *
 * Google Drive cloud storage implementation for collaboration and backup.
 *
 * CRITICAL: This adapter is NOT for public file serving.
 * - Use for: Team collaboration, document backup, disaster recovery
 * - Do NOT use for: Public downloads, website assets, user-facing documents
 *
 * Features:
 * - Team Drive (Shared Drive) support
 * - Real-time collaboration on Google Workspace docs
 * - Automatic versioning (Google Drive native)
 * - OAuth 2.0 authentication
 * - Team member access control
 * - Unlimited storage for Google Workspace Business Plus and higher
 *
 * @since 1.0.0
 */
class NonprofitSuite_Storage_GoogleDrive_Adapter implements NonprofitSuite_Storage_Adapter {

    /**
     * Google Drive API endpoint
     *
     * @var string
     */
    const API_ENDPOINT = 'https://www.googleapis.com/drive/v3';

    /**
     * Google Drive upload endpoint
     *
     * @var string
     */
    const UPLOAD_ENDPOINT = 'https://www.googleapis.com/upload/drive/v3';

    /**
     * Google access token
     *
     * @var string
     */
    private $access_token;

    /**
     * Google refresh token
     *
     * @var string
     */
    private $refresh_token;

    /**
     * Google client ID
     *
     * @var string
     */
    private $client_id;

    /**
     * Google client secret
     *
     * @var string
     */
    private $client_secret;

    /**
     * Root folder ID in Google Drive
     *
     * @var string
     */
    private $root_folder_id;

    /**
     * Team Drive (Shared Drive) ID
     *
     * @var string
     */
    private $team_drive_id;

    /**
     * Whether Google Drive is configured
     *
     * @var bool
     */
    private $is_configured = false;

    /**
     * Constructor
     *
     * @param array $config Configuration array with Google Drive credentials.
     */
    public function __construct($config = array()) {
        $this->access_token = isset($config['access_token']) ? $config['access_token'] : get_option('ns_gdrive_access_token', '');
        $this->refresh_token = isset($config['refresh_token']) ? $config['refresh_token'] : get_option('ns_gdrive_refresh_token', '');
        $this->client_id = isset($config['client_id']) ? $config['client_id'] : get_option('ns_gdrive_client_id', '');
        $this->client_secret = isset($config['client_secret']) ? $config['client_secret'] : get_option('ns_gdrive_client_secret', '');
        $this->root_folder_id = isset($config['root_folder_id']) ? $config['root_folder_id'] : get_option('ns_gdrive_root_folder_id', '');
        $this->team_drive_id = isset($config['team_drive_id']) ? $config['team_drive_id'] : get_option('ns_gdrive_team_drive_id', '');

        $this->is_configured = !empty($this->access_token) && !empty($this->client_id);
    }

    /**
     * Upload a file to Google Drive
     *
     * IMPORTANT: This upload is for COLLABORATION/BACKUP only.
     * Files uploaded here should NOT be served to public users.
     *
     * @param string $file_path Path to the local file.
     * @param array  $args      Additional arguments.
     * @return array|WP_Error Upload result with file_id and metadata, or error.
     */
    public function upload($file_path, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('gdrive_not_configured', 'Google Drive is not properly configured.');
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found: ' . $file_path);
        }

        // WARNING: Prevent accidental public serving
        if (isset($args['is_public']) && $args['is_public']) {
            return new WP_Error(
                'gdrive_public_not_allowed',
                'Google Drive cannot be used for public file serving. Use S3, B2, or CDN for public files.',
                array('severity' => 'critical')
            );
        }

        $file_id = isset($args['file_id']) ? $args['file_id'] : wp_generate_uuid4();
        $filename = isset($args['filename']) ? $args['filename'] : basename($file_path);
        $mime_type = isset($args['mime_type']) ? $args['mime_type'] : mime_content_type($file_path);

        // Get or create folder structure
        $folder_id = $this->get_or_create_folder_structure($file_id, $args);

        if (is_wp_error($folder_id)) {
            return $folder_id;
        }

        // Build metadata for file
        $metadata = array(
            'name' => $this->build_gdrive_filename($file_id, $filename, $args),
            'parents' => array($folder_id),
            'description' => 'NonprofitSuite collaboration file - ID: ' . $file_id,
        );

        // Add to Team Drive if configured
        if (!empty($this->team_drive_id)) {
            $metadata['driveId'] = $this->team_drive_id;
        }

        // Use multipart upload for files under 5MB, resumable for larger
        $file_size = filesize($file_path);

        if ($file_size < 5 * 1024 * 1024) {
            $result = $this->multipart_upload($file_path, $metadata, $mime_type);
        } else {
            $result = $this->resumable_upload($file_path, $metadata, $mime_type);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'file_id' => $file_id,
            'gdrive_id' => isset($result['id']) ? $result['id'] : '',
            'gdrive_name' => isset($result['name']) ? $result['name'] : '',
            'size' => $file_size,
            'mime_type' => $mime_type,
            'web_view_link' => isset($result['webViewLink']) ? $result['webViewLink'] : '',
        );
    }

    /**
     * Multipart upload for small files
     *
     * @param string $file_path Local file path.
     * @param array  $metadata  File metadata.
     * @param string $mime_type MIME type.
     * @return array|WP_Error Result or error.
     */
    private function multipart_upload($file_path, $metadata, $mime_type) {
        $boundary = wp_generate_password(32, false);
        $delimiter = "\r\n--" . $boundary . "\r\n";
        $close_delim = "\r\n--" . $boundary . "--";

        $multipart_body = $delimiter;
        $multipart_body .= 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n";
        $multipart_body .= json_encode($metadata);
        $multipart_body .= $delimiter;
        $multipart_body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
        $multipart_body .= file_get_contents($file_path);
        $multipart_body .= $close_delim;

        $url = self::UPLOAD_ENDPOINT . '/files?uploadType=multipart';

        if (!empty($this->team_drive_id)) {
            $url .= '&supportsAllDrives=true';
        }

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
            ),
            'body' => $multipart_body,
            'timeout' => 300,
        ));

        return $this->handle_response($response);
    }

    /**
     * Resumable upload for large files
     *
     * @param string $file_path Local file path.
     * @param array  $metadata  File metadata.
     * @param string $mime_type MIME type.
     * @return array|WP_Error Result or error.
     */
    private function resumable_upload($file_path, $metadata, $mime_type) {
        // Start resumable session
        $url = self::UPLOAD_ENDPOINT . '/files?uploadType=resumable';

        if (!empty($this->team_drive_id)) {
            $url .= '&supportsAllDrives=true';
        }

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => $mime_type,
                'X-Upload-Content-Length' => filesize($file_path),
            ),
            'body' => json_encode($metadata),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $session_uri = wp_remote_retrieve_header($response, 'location');

        if (empty($session_uri)) {
            return new WP_Error('no_session_uri', 'Failed to get upload session URI.');
        }

        // Upload file content
        $file_size = filesize($file_path);
        $chunk_size = 5 * 1024 * 1024; // 5MB chunks
        $handle = fopen($file_path, 'rb');

        if (!$handle) {
            return new WP_Error('file_open_failed', 'Failed to open file for reading.');
        }

        $offset = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunk_size);
            $chunk_size_actual = strlen($chunk);

            $response = wp_remote_request($session_uri, array(
                'method' => 'PUT',
                'headers' => array(
                    'Content-Length' => $chunk_size_actual,
                    'Content-Range' => 'bytes ' . $offset . '-' . ($offset + $chunk_size_actual - 1) . '/' . $file_size,
                ),
                'body' => $chunk,
                'timeout' => 300,
            ));

            if (is_wp_error($response)) {
                fclose($handle);
                return $response;
            }

            $offset += $chunk_size_actual;
        }

        fclose($handle);

        return $this->handle_response($response);
    }

    /**
     * Download a file from Google Drive
     *
     * @param string      $file_id     File identifier.
     * @param string|null $destination Destination path, or null to return file contents.
     * @param array       $args        Additional arguments.
     * @return string|bool|WP_Error File path/contents on success, false/error on failure.
     */
    public function download($file_id, $destination = null, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('gdrive_not_configured', 'Google Drive is not properly configured.');
        }

        $gdrive_id = isset($args['gdrive_id']) ? $args['gdrive_id'] : $this->get_gdrive_id_from_file_id($file_id);

        if (!$gdrive_id) {
            return new WP_Error('gdrive_id_not_found', 'Could not determine Google Drive ID for file.');
        }

        $url = self::API_ENDPOINT . '/files/' . $gdrive_id . '?alt=media';

        if (!empty($this->team_drive_id)) {
            $url .= '&supportsAllDrives=true';
        }

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
            'timeout' => 300,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error('gdrive_download_failed', 'Google Drive download failed with code: ' . $code);
        }

        if ($destination) {
            $written = file_put_contents($destination, $body);
            return $written !== false ? $destination : new WP_Error('write_failed', 'Failed to write file to destination.');
        }

        return $body;
    }

    /**
     * Delete a file from Google Drive
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function delete($file_id, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('gdrive_not_configured', 'Google Drive is not properly configured.');
        }

        $gdrive_id = isset($args['gdrive_id']) ? $args['gdrive_id'] : $this->get_gdrive_id_from_file_id($file_id);

        if (!$gdrive_id) {
            return new WP_Error('gdrive_id_not_found', 'Could not determine Google Drive ID for file.');
        }

        $url = self::API_ENDPOINT . '/files/' . $gdrive_id;

        if (!empty($this->team_drive_id)) {
            $url .= '?supportsAllDrives=true';
        }

        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        return $code === 204 ? true : new WP_Error('gdrive_delete_failed', 'Failed to delete from Google Drive.');
    }

    /**
     * Get URL for a file in Google Drive
     *
     * WARNING: This returns a webViewLink for INTERNAL collaboration only.
     * This URL requires Google Drive authentication and CANNOT be used for public access.
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return string|WP_Error URL or error.
     */
    public function get_url($file_id, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('gdrive_not_configured', 'Google Drive is not properly configured.');
        }

        // Block any attempt to get public URLs
        if (isset($args['is_public']) && $args['is_public']) {
            return new WP_Error(
                'gdrive_public_not_allowed',
                'Google Drive URLs cannot be used for public access. Files must be served from S3, B2, or CDN.',
                array('severity' => 'critical')
            );
        }

        $gdrive_id = isset($args['gdrive_id']) ? $args['gdrive_id'] : $this->get_gdrive_id_from_file_id($file_id);

        if (!$gdrive_id) {
            return new WP_Error('gdrive_id_not_found', 'Could not determine Google Drive ID for file.');
        }

        $metadata = $this->get_metadata($file_id, array('gdrive_id' => $gdrive_id));

        if (is_wp_error($metadata)) {
            return $metadata;
        }

        // Return webViewLink for collaboration (requires Google account)
        return isset($metadata['web_view_link']) ? $metadata['web_view_link'] : new WP_Error('no_link', 'No collaboration link available.');
    }

    /**
     * Get metadata for a file in Google Drive
     *
     * @param string $file_id File identifier.
     * @param array  $args    Additional arguments.
     * @return array|WP_Error Metadata array or error.
     */
    public function get_metadata($file_id, $args = array()) {
        if (!$this->is_configured) {
            return new WP_Error('gdrive_not_configured', 'Google Drive is not properly configured.');
        }

        $gdrive_id = isset($args['gdrive_id']) ? $args['gdrive_id'] : $this->get_gdrive_id_from_file_id($file_id);

        if (!$gdrive_id) {
            return new WP_Error('gdrive_id_not_found', 'Could not determine Google Drive ID for file.');
        }

        $url = self::API_ENDPOINT . '/files/' . $gdrive_id . '?fields=id,name,mimeType,size,createdTime,modifiedTime,webViewLink,version';

        if (!empty($this->team_drive_id)) {
            $url .= '&supportsAllDrives=true';
        }

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
            'timeout' => 60,
        ));

        $result = $this->handle_response($response);

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'id' => isset($result['id']) ? $result['id'] : '',
            'name' => isset($result['name']) ? $result['name'] : '',
            'mime_type' => isset($result['mimeType']) ? $result['mimeType'] : '',
            'size' => isset($result['size']) ? (int) $result['size'] : 0,
            'created_time' => isset($result['createdTime']) ? $result['createdTime'] : '',
            'modified_time' => isset($result['modifiedTime']) ? $result['modifiedTime'] : '',
            'web_view_link' => isset($result['webViewLink']) ? $result['webViewLink'] : '',
            'version' => isset($result['version']) ? $result['version'] : '',
        );
    }

    /**
     * Test connection to Google Drive
     *
     * @return bool|WP_Error True if connected, error otherwise.
     */
    public function test_connection() {
        if (!$this->is_configured) {
            return new WP_Error('gdrive_not_configured', 'Google Drive credentials not configured.');
        }

        $response = wp_remote_get(self::API_ENDPOINT . '/about?fields=user,storageQuota', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
            'timeout' => 30,
        ));

        $result = $this->handle_response($response);

        return is_wp_error($result) ? $result : true;
    }

    /**
     * Get or create folder structure in Google Drive
     *
     * @param string $file_id File UUID.
     * @param array  $args    Additional arguments.
     * @return string|WP_Error Folder ID or error.
     */
    private function get_or_create_folder_structure($file_id, $args = array()) {
        $uuid_parts = explode('-', $file_id);
        $dir1 = substr($uuid_parts[0], 0, 2);
        $dir2 = substr($uuid_parts[0], 2, 2);

        $parent_id = !empty($this->root_folder_id) ? $this->root_folder_id : 'root';

        // Create dir1 if needed
        $dir1_id = $this->get_or_create_folder($dir1, $parent_id);
        if (is_wp_error($dir1_id)) {
            return $dir1_id;
        }

        // Create dir2 if needed
        $dir2_id = $this->get_or_create_folder($dir2, $dir1_id);
        if (is_wp_error($dir2_id)) {
            return $dir2_id;
        }

        return $dir2_id;
    }

    /**
     * Get or create a folder
     *
     * @param string $name      Folder name.
     * @param string $parent_id Parent folder ID.
     * @return string|WP_Error Folder ID or error.
     */
    private function get_or_create_folder($name, $parent_id) {
        // Search for existing folder
        $query = "name='" . $name . "' and '" . $parent_id . "' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
        $url = self::API_ENDPOINT . '/files?q=' . urlencode($query) . '&fields=files(id)';

        if (!empty($this->team_drive_id)) {
            $url .= '&driveId=' . $this->team_drive_id . '&includeItemsFromAllDrives=true&supportsAllDrives=true&corpora=drive';
        }

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
            'timeout' => 60,
        ));

        $result = $this->handle_response($response);

        if (is_wp_error($result)) {
            return $result;
        }

        // Folder exists
        if (!empty($result['files'])) {
            return $result['files'][0]['id'];
        }

        // Create folder
        $metadata = array(
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => array($parent_id),
        );

        if (!empty($this->team_drive_id)) {
            $metadata['driveId'] = $this->team_drive_id;
        }

        $create_url = self::API_ENDPOINT . '/files';
        if (!empty($this->team_drive_id)) {
            $create_url .= '?supportsAllDrives=true';
        }

        $response = wp_remote_post($create_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($metadata),
            'timeout' => 60,
        ));

        $create_result = $this->handle_response($response);

        if (is_wp_error($create_result)) {
            return $create_result;
        }

        return isset($create_result['id']) ? $create_result['id'] : new WP_Error('folder_create_failed', 'Failed to create folder.');
    }

    /**
     * Build Google Drive filename
     *
     * @param string $file_id  File UUID.
     * @param string $filename Original filename.
     * @param array  $args     Additional arguments.
     * @return string Filename.
     */
    private function build_gdrive_filename($file_id, $filename, $args = array()) {
        $version_id = isset($args['version_id']) ? $args['version_id'] : 1;
        return $file_id . '_v' . $version_id . '_' . sanitize_file_name($filename);
    }

    /**
     * Get Google Drive ID from file ID
     *
     * @param string $file_id File UUID.
     * @return string|false Google Drive ID or false if not found.
     */
    private function get_gdrive_id_from_file_id($file_id) {
        global $wpdb;

        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ns_storage_locations
            WHERE file_id = %s AND tier = 'collab' AND provider = 'googledrive'
            ORDER BY version_id DESC LIMIT 1",
            $file_id
        ));

        return $location ? $location->remote_id : false;
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

        if ($code >= 200 && $code < 300) {
            $result = json_decode($body, true);
            return $result !== null ? $result : array();
        }

        // Handle errors
        $error = json_decode($body, true);
        $message = isset($error['error']['message']) ? $error['error']['message'] : 'Unknown error';

        return new WP_Error('gdrive_api_error', 'Google Drive API error: ' . $message, array('code' => $code));
    }

    /**
     * Refresh access token using refresh token
     *
     * @return bool|WP_Error True on success, error on failure.
     */
    public function refresh_access_token() {
        if (empty($this->refresh_token) || empty($this->client_id) || empty($this->client_secret)) {
            return new WP_Error('missing_credentials', 'Missing refresh token or client credentials.');
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refresh_token,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            update_option('ns_gdrive_access_token', $this->access_token);
            return true;
        }

        return new WP_Error('token_refresh_failed', 'Failed to refresh access token.');
    }
}
