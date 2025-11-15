<?php
/**
 * Storage Admin UI
 *
 * Provides WordPress admin interface for managing multi-tier storage system.
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Storage
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NonprofitSuite_Storage_Admin_UI
 *
 * Admin interface for file management, storage configuration, and statistics.
 *
 * Features:
 * - File browser with search and filters
 * - Upload interface with drag-and-drop
 * - Version history viewer
 * - Permission management
 * - Physical document tracking
 * - Storage statistics dashboard
 * - Provider configuration
 * - Sync queue monitoring
 *
 * @since 1.0.0
 */
class NonprofitSuite_Storage_Admin_UI {

    /**
     * Storage Orchestrator instance
     *
     * @var NonprofitSuite_Storage_Orchestrator
     */
    private $orchestrator;

    /**
     * Version Manager instance
     *
     * @var NonprofitSuite_Storage_Version_Manager
     */
    private $version_manager;

    /**
     * Cache instance
     *
     * @var NonprofitSuite_Storage_Cache
     */
    private $cache;

    /**
     * Sync Manager instance
     *
     * @var NonprofitSuite_Storage_Sync_Manager
     */
    private $sync_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->orchestrator = NonprofitSuite_Storage_Orchestrator::get_instance();
        $this->version_manager = NonprofitSuite_Storage_Version_Manager::get_instance();
        $this->cache = NonprofitSuite_Storage_Cache::get_instance();
        $this->sync_manager = NonprofitSuite_Storage_Sync_Manager::get_instance();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_ns_storage_upload', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_ns_storage_delete', array($this, 'ajax_delete_file'));
        add_action('wp_ajax_ns_storage_get_versions', array($this, 'ajax_get_versions'));
        add_action('wp_ajax_ns_storage_revert_version', array($this, 'ajax_revert_version'));
        add_action('wp_ajax_ns_storage_update_permissions', array($this, 'ajax_update_permissions'));
        add_action('wp_ajax_ns_storage_update_physical', array($this, 'ajax_update_physical'));
        add_action('wp_ajax_ns_storage_update_author', array($this, 'ajax_update_author'));
        add_action('wp_ajax_ns_storage_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_ns_storage_get_status_history', array($this, 'ajax_get_status_history'));
        add_action('wp_ajax_ns_storage_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_ns_storage_warm_cache', array($this, 'ajax_warm_cache'));
        add_action('wp_ajax_ns_storage_clean_cache', array($this, 'ajax_clean_cache'));
        add_action('wp_ajax_ns_storage_process_discovery', array($this, 'ajax_process_discovery'));
        add_action('wp_ajax_ns_storage_accept_discovery', array($this, 'ajax_accept_discovery'));
        add_action('wp_ajax_ns_storage_reject_discovery', array($this, 'ajax_reject_discovery'));
    }

    /**
     * Add admin menu pages
     *
     * @return void
     */
    public function add_admin_menu() {
        // Main storage menu
        add_menu_page(
            __('Storage Manager', 'nonprofitsuite'),
            __('Storage', 'nonprofitsuite'),
            'manage_options',
            'ns-storage',
            array($this, 'render_file_browser'),
            'dashicons-portfolio',
            30
        );

        // File Browser (default)
        add_submenu_page(
            'ns-storage',
            __('File Browser', 'nonprofitsuite'),
            __('Files', 'nonprofitsuite'),
            'manage_options',
            'ns-storage',
            array($this, 'render_file_browser')
        );

        // Upload
        add_submenu_page(
            'ns-storage',
            __('Upload Files', 'nonprofitsuite'),
            __('Upload', 'nonprofitsuite'),
            'manage_options',
            'ns-storage-upload',
            array($this, 'render_upload_page')
        );

        // Statistics
        add_submenu_page(
            'ns-storage',
            __('Storage Statistics', 'nonprofitsuite'),
            __('Statistics', 'nonprofitsuite'),
            'manage_options',
            'ns-storage-stats',
            array($this, 'render_statistics_page')
        );

        // Sync Queue
        add_submenu_page(
            'ns-storage',
            __('Sync Queue', 'nonprofitsuite'),
            __('Sync Queue', 'nonprofitsuite'),
            'manage_options',
            'ns-storage-sync',
            array($this, 'render_sync_queue_page')
        );

        // Document Discovery
        add_submenu_page(
            'ns-storage',
            __('Document Discovery', 'nonprofitsuite'),
            __('Discovery', 'nonprofitsuite'),
            'manage_options',
            'ns-storage-discovery',
            array($this, 'render_discovery_page')
        );

        // Settings (integrates with existing integration settings)
        add_submenu_page(
            'ns-storage',
            __('Storage Settings', 'nonprofitsuite'),
            __('Settings', 'nonprofitsuite'),
            'manage_options',
            'ns-integrations&category=storage',
            '__return_null' // Handled by Integration_Settings
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'ns-storage') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('ns-storage-admin', plugins_url('assets/css/storage-admin.css', __FILE__), array(), '1.0.0');
        wp_enqueue_script('ns-storage-admin', plugins_url('assets/js/storage-admin.js', __FILE__), array('jquery', 'wp-util'), '1.0.0', true);

        wp_localize_script('ns-storage-admin', 'nsStorage', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ns_storage_action'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this file?', 'nonprofitsuite'),
                'confirmRevert' => __('Are you sure you want to revert to this version?', 'nonprofitsuite'),
                'uploadSuccess' => __('File uploaded successfully', 'nonprofitsuite'),
                'uploadError' => __('Upload failed', 'nonprofitsuite'),
            ),
        ));
    }

    /**
     * Render file browser page
     *
     * @return void
     */
    public function render_file_browser() {
        global $wpdb;

        // Get filters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $is_public = isset($_GET['visibility']) ? ($_GET['visibility'] === 'public' ? 1 : 0) : null;
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Build query
        $where = array('deleted_at IS NULL');
        $params = array();

        if (!empty($search)) {
            $where[] = '(filename LIKE %s OR category LIKE %s)';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if (!empty($category)) {
            $where[] = 'category = %s';
            $params[] = $category;
        }

        if ($is_public !== null) {
            $where[] = 'is_public = %d';
            $params[] = $is_public;
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where);

        // Get files
        $query = "SELECT * FROM {$wpdb->prefix}ns_storage_files {$where_clause} ORDER BY uploaded_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $files = $wpdb->get_results($wpdb->prepare($query, $params));

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ns_storage_files {$where_clause}";
        $total = $wpdb->get_var(empty($params) ? $count_query : $wpdb->prepare($count_query, array_slice($params, 0, -2)));

        include plugin_dir_path(__FILE__) . '../views/admin/file-browser.php';
    }

    /**
     * Render upload page
     *
     * @return void
     */
    public function render_upload_page() {
        include plugin_dir_path(__FILE__) . '../views/admin/upload.php';
    }

    /**
     * Render statistics page
     *
     * @return void
     */
    public function render_statistics_page() {
        global $wpdb;

        // Get overall statistics
        $total_files = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ns_storage_files WHERE deleted_at IS NULL");
        $total_size = $wpdb->get_var("SELECT SUM(file_size) FROM {$wpdb->prefix}ns_storage_files WHERE deleted_at IS NULL");
        $public_files = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ns_storage_files WHERE is_public = 1 AND deleted_at IS NULL");
        $private_files = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ns_storage_files WHERE is_public = 0 AND deleted_at IS NULL");

        // Get cache statistics
        $cache_stats = $this->cache->get_stats();

        // Get sync queue statistics
        $sync_stats = $this->sync_manager->get_queue_stats();

        // Get storage by category
        $category_stats = $wpdb->get_results(
            "SELECT category, COUNT(*) as count, SUM(file_size) as size
            FROM {$wpdb->prefix}ns_storage_files
            WHERE deleted_at IS NULL
            GROUP BY category
            ORDER BY size DESC"
        );

        // Get storage by provider
        $provider_stats = $wpdb->get_results(
            "SELECT provider, tier, COUNT(*) as count, SUM(file_size) as size
            FROM {$wpdb->prefix}ns_storage_locations
            GROUP BY provider, tier
            ORDER BY size DESC"
        );

        // Get physical document statistics
        $physical_stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_with_physical,
                COUNT(CASE WHEN physical_verified_at IS NOT NULL THEN 1 END) as verified,
                COUNT(CASE WHEN physical_verified_at IS NULL THEN 1 END) as unverified
            FROM {$wpdb->prefix}ns_storage_files
            WHERE has_physical_copy = 1 AND deleted_at IS NULL"
        );

        include plugin_dir_path(__FILE__) . '../views/admin/statistics.php';
    }

    /**
     * Render sync queue page
     *
     * @return void
     */
    public function render_sync_queue_page() {
        global $wpdb;

        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';
        $per_page = 50;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        $queue_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ns_storage_sync_queue
            WHERE status = %s
            ORDER BY priority DESC, queued_at ASC
            LIMIT %d OFFSET %d",
            $status,
            $per_page,
            $offset
        ));

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ns_storage_sync_queue WHERE status = %s",
            $status
        ));

        include plugin_dir_path(__FILE__) . '../views/admin/sync-queue.php';
    }

    /**
     * AJAX: Upload file
     *
     * @return void
     */
    public function ajax_upload_file() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }

        $file = $_FILES['file'];
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'general';
        $is_public = isset($_POST['is_public']) ? (bool) $_POST['is_public'] : false;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

        $result = $this->orchestrator->upload_file($file['tmp_name'], array(
            'filename' => $file['name'],
            'category' => $category,
            'is_public' => $is_public,
            'description' => $description,
            'uploaded_by' => get_current_user_id(),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'File uploaded successfully',
            'file' => $result,
        ));
    }

    /**
     * AJAX: Delete file
     *
     * @return void
     */
    public function ajax_delete_file() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $hard_delete = isset($_POST['hard_delete']) ? (bool) $_POST['hard_delete'] : false;

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        $result = $this->orchestrator->delete_file($file_id, !$hard_delete);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'File deleted successfully'));
    }

    /**
     * AJAX: Get file versions
     *
     * @return void
     */
    public function ajax_get_versions() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        $versions = $this->version_manager->get_versions($file_id);

        if (is_wp_error($versions)) {
            wp_send_json_error(array('message' => $versions->get_error_message()));
        }

        wp_send_json_success(array('versions' => $versions));
    }

    /**
     * AJAX: Revert to version
     *
     * @return void
     */
    public function ajax_revert_version() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $version_number = isset($_POST['version_number']) ? intval($_POST['version_number']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : 'Reverted via admin UI';

        if (empty($file_id) || $version_number < 1) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        $result = $this->version_manager->revert_to_version($file_id, $version_number, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Reverted to version ' . $version_number,
            'new_version' => $result,
        ));
    }

    /**
     * AJAX: Update file permissions
     *
     * @return void
     */
    public function ajax_update_permissions() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $is_public = isset($_POST['is_public']) ? (bool) $_POST['is_public'] : false;

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        global $wpdb;

        $updated = $wpdb->update(
            $wpdb->prefix . 'ns_storage_files',
            array('is_public' => $is_public ? 1 : 0),
            array('file_id' => $file_id),
            array('%d'),
            array('%s')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to update permissions'));
        }

        wp_send_json_success(array('message' => 'Permissions updated'));
    }

    /**
     * AJAX: Update physical document information
     *
     * @return void
     */
    public function ajax_update_physical() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $has_physical = isset($_POST['has_physical_copy']) ? (bool) $_POST['has_physical_copy'] : false;
        $location = isset($_POST['physical_location']) ? sanitize_text_field($_POST['physical_location']) : '';
        $details = isset($_POST['physical_location_details']) ? sanitize_textarea_field($_POST['physical_location_details']) : '';
        $verified = isset($_POST['verify']) ? (bool) $_POST['verify'] : false;

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        global $wpdb;

        $data = array(
            'has_physical_copy' => $has_physical ? 1 : 0,
            'physical_location' => $location,
            'physical_location_details' => $details,
        );

        if ($verified) {
            $data['physical_verified_at'] = current_time('mysql');
            $data['physical_verified_by'] = get_current_user_id();
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'ns_storage_files',
            $data,
            array('file_id' => $file_id),
            array('%d', '%s', '%s', '%s', '%d'),
            array('%s')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to update physical document info'));
        }

        wp_send_json_success(array('message' => 'Physical document info updated'));
    }

    /**
     * AJAX: Get statistics
     *
     * @return void
     */
    public function ajax_get_stats() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $stats = array(
            'cache' => $this->cache->get_stats(),
            'sync' => $this->sync_manager->get_queue_stats(),
        );

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Warm cache
     *
     * @return void
     */
    public function ajax_warm_cache() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;

        $result = $this->cache->warm_cache($limit);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Cache warmed',
            'cached' => $result,
        ));
    }

    /**
     * AJAX: Clean cache
     *
     * @return void
     */
    public function ajax_clean_cache() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'expired';

        if ($type === 'expired') {
            $result = $this->cache->clean_expired();
        } else {
            wp_send_json_error(array('message' => 'Invalid clean type'));
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Cache cleaned',
            'removed' => $result,
        ));
    }

    /**
     * AJAX: Update document author
     *
     * @return void
     */
    public function ajax_update_author() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $author = isset($_POST['document_author']) ? sanitize_text_field($_POST['document_author']) : '';
        $author_type = isset($_POST['document_author_type']) ? sanitize_text_field($_POST['document_author_type']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        global $wpdb;

        $data = array(
            'document_author' => $author,
            'document_author_type' => $author_type,
            'updated_at' => current_time('mysql'),
        );

        $updated = $wpdb->update(
            $wpdb->prefix . 'ns_storage_files',
            $data,
            array('file_uuid' => $file_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to update author info'));
        }

        wp_send_json_success(array('message' => 'Author info updated'));
    }

    /**
     * AJAX: Update document status
     *
     * Logs status change to history table for audit trail.
     *
     * @return void
     */
    public function ajax_update_status() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $new_status = isset($_POST['document_status']) ? sanitize_text_field($_POST['document_status']) : '';
        $change_note = isset($_POST['change_note']) ? sanitize_textarea_field($_POST['change_note']) : '';

        if (empty($file_id) || empty($new_status)) {
            wp_send_json_error(array('message' => 'File ID and status required'));
        }

        // Validate status
        $valid_statuses = array('draft', 'revised', 'final', 'approved', 'rejected', 'archived');
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(array('message' => 'Invalid status value'));
        }

        global $wpdb;

        // Get current status
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT document_status FROM {$wpdb->prefix}ns_storage_files WHERE file_uuid = %s",
            $file_id
        ));

        if (!$current) {
            wp_send_json_error(array('message' => 'File not found'));
        }

        $previous_status = $current->document_status;

        // Update file status
        $updated = $wpdb->update(
            $wpdb->prefix . 'ns_storage_files',
            array(
                'document_status' => $new_status,
                'document_status_changed_at' => current_time('mysql'),
                'document_status_changed_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
            ),
            array('file_uuid' => $file_id),
            array('%s', '%s', '%d', '%s'),
            array('%s')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to update status'));
        }

        // Log to status history
        $wpdb->insert(
            $wpdb->prefix . 'ns_storage_status_history',
            array(
                'file_id' => $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ns_storage_files WHERE file_uuid = %s",
                    $file_id
                )),
                'previous_status' => $previous_status,
                'new_status' => $new_status,
                'changed_by' => get_current_user_id(),
                'changed_at' => current_time('mysql'),
                'change_note' => $change_note,
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );

        wp_send_json_success(array(
            'message' => 'Status updated',
            'previous_status' => $previous_status,
            'new_status' => $new_status,
        ));
    }

    /**
     * AJAX: Get document status history
     *
     * @return void
     */
    public function ajax_get_status_history() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        global $wpdb;

        // Get file internal ID
        $file_internal_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ns_storage_files WHERE file_uuid = %s",
            $file_id
        ));

        if (!$file_internal_id) {
            wp_send_json_error(array('message' => 'File not found'));
        }

        // Get status history with user info
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, u.display_name as changed_by_name
            FROM {$wpdb->prefix}ns_storage_status_history h
            LEFT JOIN {$wpdb->users} u ON h.changed_by = u.ID
            WHERE h.file_id = %d
            ORDER BY h.changed_at DESC",
            $file_internal_id
        ));

        wp_send_json_success(array('history' => $history));
    }

    /**
     * Render document discovery page
     *
     * @return void
     */
    public function render_discovery_page() {
        global $wpdb;

        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'needs_review';
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where = '';
        if ($status === 'needs_review') {
            $where = 'WHERE d.needs_review = 1 AND d.discovery_status = "completed"';
        } elseif ($status === 'reviewed') {
            $where = 'WHERE d.needs_review = 0';
        } elseif ($status === 'pending') {
            $where = 'WHERE d.discovery_status = "pending"';
        } elseif ($status === 'processing') {
            $where = 'WHERE d.discovery_status = "processing"';
        }

        $discoveries = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, f.filename, f.file_uuid, f.mime_type, f.category as current_category
            FROM {$wpdb->prefix}ns_document_discovery d
            INNER JOIN {$wpdb->prefix}ns_storage_files f ON d.file_id = f.id
            {$where}
            ORDER BY d.discovered_at DESC
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ns_document_discovery d {$where}");

        // Get statistics
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN discovery_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN discovery_status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN discovery_status = 'completed' AND needs_review = 1 THEN 1 ELSE 0 END) as needs_review,
                SUM(CASE WHEN needs_review = 0 THEN 1 ELSE 0 END) as reviewed
            FROM {$wpdb->prefix}ns_document_discovery"
        );

        include plugin_dir_path(__FILE__) . '../views/admin/discovery.php';
    }

    /**
     * AJAX: Process discovery for a file
     *
     * @return void
     */
    public function ajax_process_discovery() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        $discovery = NonprofitSuite_Document_Discovery::get_instance();
        $result = $discovery->process_discovery($file_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Discovery completed',
            'result' => $result,
        ));
    }

    /**
     * AJAX: Accept discovery suggestions
     *
     * @return void
     */
    public function ajax_accept_discovery() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        $discovery = NonprofitSuite_Document_Discovery::get_instance();
        $result = $discovery->accept_discovery($file_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'Discovery accepted and applied'));
    }

    /**
     * AJAX: Reject discovery suggestions
     *
     * @return void
     */
    public function ajax_reject_discovery() {
        check_ajax_referer('ns_storage_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';

        if (empty($file_id)) {
            wp_send_json_error(array('message' => 'File ID required'));
        }

        global $wpdb;

        // Get file internal ID
        $file_internal_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ns_storage_files WHERE file_uuid = %s",
            $file_id
        ));

        if (!$file_internal_id) {
            wp_send_json_error(array('message' => 'File not found'));
        }

        // Mark as reviewed without applying suggestions
        $updated = $wpdb->update(
            $wpdb->prefix . 'ns_document_discovery',
            array(
                'needs_review' => 0,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql'),
            ),
            array('file_id' => $file_internal_id),
            array('%d', '%d', '%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to reject discovery'));
        }

        wp_send_json_success(array('message' => 'Discovery rejected'));
    }
}
