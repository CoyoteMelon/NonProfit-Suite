<?php
/**
 * Duplicate Detection System
 *
 * Detects duplicate files based on checksum matching before upload.
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
 * Class NonprofitSuite_Duplicate_Detector
 *
 * Prevents duplicate file uploads by checking MD5 and SHA256 checksums.
 *
 * Features:
 * - Pre-upload duplicate checking
 * - Multiple match strategies (exact, similar)
 * - Deduplication options (skip, replace, keep both, link)
 * - Duplicate reporting and cleanup tools
 *
 * @since 1.0.0
 */
class NonprofitSuite_Duplicate_Detector {

    /**
     * Singleton instance
     *
     * @var NonprofitSuite_Duplicate_Detector
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return NonprofitSuite_Duplicate_Detector
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook into upload process
        add_filter('ns_storage_before_upload', array($this, 'check_duplicate_before_upload'), 10, 2);
    }

    /**
     * Check for duplicate before upload
     *
     * @param array  $file_data File data array.
     * @param string $file_path Path to file being uploaded.
     * @return array|WP_Error Modified file data or error if duplicate.
     */
    public function check_duplicate_before_upload($file_data, $file_path) {
        // Calculate checksums
        $md5 = md5_file($file_path);
        $sha256 = hash_file('sha256', $file_path);

        // Check for existing file with same checksum
        $duplicate = $this->find_duplicate($md5, $sha256);

        if ($duplicate) {
            // Allow filtering of duplicate action
            $action = apply_filters('ns_duplicate_action', 'warn', $duplicate, $file_data);

            switch ($action) {
                case 'skip':
                    return new WP_Error(
                        'duplicate_file',
                        sprintf(
                            __('Duplicate file detected: "%s" (uploaded %s)', 'nonprofitsuite'),
                            $duplicate->filename,
                            human_time_diff(strtotime($duplicate->created_at), current_time('timestamp')) . ' ago'
                        ),
                        array(
                            'duplicate' => $duplicate,
                            'action' => 'skip',
                        )
                    );

                case 'replace':
                    // Mark old version as replaced
                    $file_data['replace_file_id'] = $duplicate->file_uuid;
                    break;

                case 'link':
                    // Don't upload, just link to existing file
                    return new WP_Error(
                        'duplicate_file_link',
                        __('File already exists. Linking to existing file.', 'nonprofitsuite'),
                        array(
                            'duplicate' => $duplicate,
                            'action' => 'link',
                            'existing_file_id' => $duplicate->file_uuid,
                        )
                    );

                case 'keep_both':
                    // Continue with upload, add suffix to filename
                    $file_data['filename'] = $this->add_duplicate_suffix($file_data['filename']);
                    $file_data['is_duplicate'] = true;
                    $file_data['original_file_id'] = $duplicate->file_uuid;
                    break;

                case 'warn':
                default:
                    // Add warning but continue
                    $file_data['duplicate_warning'] = $duplicate;
                    break;
            }
        }

        return $file_data;
    }

    /**
     * Find duplicate file by checksum
     *
     * @param string $md5    MD5 checksum.
     * @param string $sha256 SHA256 checksum (optional).
     * @return object|false Duplicate file record or false.
     */
    public function find_duplicate($md5, $sha256 = null) {
        global $wpdb;

        $where = array('checksum_md5 = %s', 'deleted_at IS NULL');
        $params = array($md5);

        // If SHA256 is provided, use it for stronger match
        if ($sha256) {
            $where[] = 'checksum_sha256 = %s';
            $params[] = $sha256;
        }

        $where_clause = implode(' AND ', $where);

        $duplicate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ns_storage_files
            WHERE {$where_clause}
            ORDER BY created_at DESC
            LIMIT 1",
            $params
        ));

        return $duplicate ? $duplicate : false;
    }

    /**
     * Find all duplicates in the system
     *
     * @param array $args Query arguments.
     * @return array Array of duplicate groups.
     */
    public function find_all_duplicates($args = array()) {
        global $wpdb;

        $defaults = array(
            'match_type' => 'md5', // md5, sha256, both
            'min_count' => 2,
            'include_deleted' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array();
        if (!$args['include_deleted']) {
            $where[] = 'deleted_at IS NULL';
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Find duplicate groups
        $query = "SELECT checksum_md5, COUNT(*) as count, GROUP_CONCAT(id) as file_ids
                  FROM {$wpdb->prefix}ns_storage_files
                  {$where_clause}
                  GROUP BY checksum_md5
                  HAVING count >= %d
                  ORDER BY count DESC";

        $duplicate_groups = $wpdb->get_results($wpdb->prepare($query, $args['min_count']));

        // Enrich with file details
        $results = array();
        foreach ($duplicate_groups as $group) {
            $file_ids = explode(',', $group->file_ids);

            $files = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ns_storage_files
                WHERE id IN (" . implode(',', array_map('intval', $file_ids)) . ")
                ORDER BY created_at ASC"
            ));

            $results[] = array(
                'checksum' => $group->checksum_md5,
                'count' => $group->count,
                'files' => $files,
                'total_size' => array_sum(array_column($files, 'file_size')),
                'wasted_space' => (count($files) - 1) * $files[0]->file_size,
            );
        }

        return $results;
    }

    /**
     * Calculate file checksum
     *
     * @param string $file_path Path to file.
     * @param array  $algorithms Hash algorithms to use.
     * @return array Checksums.
     */
    public function calculate_checksum($file_path, $algorithms = array('md5', 'sha256')) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found for checksum calculation.');
        }

        $checksums = array();

        foreach ($algorithms as $algo) {
            if ($algo === 'md5') {
                $checksums['md5'] = md5_file($file_path);
            } elseif ($algo === 'sha256') {
                $checksums['sha256'] = hash_file('sha256', $file_path);
            }
        }

        return $checksums;
    }

    /**
     * Add duplicate suffix to filename
     *
     * @param string $filename Original filename.
     * @return string Filename with suffix.
     */
    private function add_duplicate_suffix($filename) {
        $pathinfo = pathinfo($filename);
        $basename = $pathinfo['filename'];
        $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

        return $basename . '-duplicate-' . substr(md5(microtime()), 0, 8) . $extension;
    }

    /**
     * Merge duplicate files
     *
     * Keeps the oldest file and removes duplicates, updating references.
     *
     * @param array $file_ids Array of duplicate file IDs.
     * @param array $options  Merge options.
     * @return array|WP_Error Merge results or error.
     */
    public function merge_duplicates($file_ids, $options = array()) {
        global $wpdb;

        if (count($file_ids) < 2) {
            return new WP_Error('insufficient_files', 'At least 2 files required for merging.');
        }

        $defaults = array(
            'keep' => 'oldest', // oldest, newest, largest, smallest
            'delete_method' => 'soft', // soft, hard
        );

        $options = wp_parse_args($options, $defaults);

        // Get all files
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ns_storage_files
            WHERE file_uuid IN (" . implode(',', array_fill(0, count($file_ids), '%s')) . ")",
            $file_ids
        ));

        if (count($files) !== count($file_ids)) {
            return new WP_Error('files_not_found', 'Some files were not found.');
        }

        // Determine which file to keep
        $keep_file = $this->determine_keep_file($files, $options['keep']);

        // Remove duplicates
        $removed = array();
        foreach ($files as $file) {
            if ($file->id === $keep_file->id) {
                continue;
            }

            if ($options['delete_method'] === 'hard') {
                // Hard delete
                $orchestrator = NonprofitSuite_Storage_Orchestrator::get_instance();
                $result = $orchestrator->delete_file($file->file_uuid, false);
            } else {
                // Soft delete
                $wpdb->update(
                    $wpdb->prefix . 'ns_storage_files',
                    array('deleted_at' => current_time('mysql')),
                    array('id' => $file->id),
                    array('%s'),
                    array('%d')
                );
            }

            $removed[] = $file->filename;
        }

        return array(
            'kept_file' => $keep_file,
            'removed_files' => $removed,
            'space_saved' => ($keep_file->file_size * (count($files) - 1)),
        );
    }

    /**
     * Determine which file to keep during merge
     *
     * @param array  $files Files to consider.
     * @param string $keep  Keep strategy.
     * @return object File to keep.
     */
    private function determine_keep_file($files, $keep) {
        switch ($keep) {
            case 'newest':
                usort($files, function($a, $b) {
                    return strtotime($b->created_at) - strtotime($a->created_at);
                });
                break;

            case 'largest':
                usort($files, function($a, $b) {
                    return $b->file_size - $a->file_size;
                });
                break;

            case 'smallest':
                usort($files, function($a, $b) {
                    return $a->file_size - $b->file_size;
                });
                break;

            case 'oldest':
            default:
                usort($files, function($a, $b) {
                    return strtotime($a->created_at) - strtotime($b->created_at);
                });
                break;
        }

        return $files[0];
    }

    /**
     * Get duplicate statistics
     *
     * @return array Statistics.
     */
    public function get_duplicate_stats() {
        $duplicates = $this->find_all_duplicates();

        $stats = array(
            'duplicate_groups' => count($duplicates),
            'total_duplicates' => 0,
            'wasted_space' => 0,
            'potential_savings' => 0,
        );

        foreach ($duplicates as $group) {
            $stats['total_duplicates'] += $group['count'] - 1; // -1 because we keep one
            $stats['wasted_space'] += $group['wasted_space'];
            $stats['potential_savings'] += $group['wasted_space'];
        }

        return $stats;
    }

    /**
     * Generate duplicate report
     *
     * @return array Detailed report.
     */
    public function generate_duplicate_report() {
        $duplicates = $this->find_all_duplicates();
        $stats = $this->get_duplicate_stats();

        return array(
            'summary' => $stats,
            'duplicate_groups' => $duplicates,
            'recommendations' => $this->get_deduplication_recommendations($duplicates),
        );
    }

    /**
     * Get deduplication recommendations
     *
     * @param array $duplicates Duplicate groups.
     * @return array Recommendations.
     */
    private function get_deduplication_recommendations($duplicates) {
        $recommendations = array();

        foreach ($duplicates as $group) {
            if ($group['wasted_space'] > 10 * 1024 * 1024) { // > 10MB wasted
                $recommendations[] = array(
                    'priority' => 'high',
                    'checksum' => $group['checksum'],
                    'message' => sprintf(
                        __('Remove %d duplicates to save %s', 'nonprofitsuite'),
                        $group['count'] - 1,
                        size_format($group['wasted_space'], 2)
                    ),
                    'action' => 'merge',
                    'file_ids' => array_column($group['files'], 'file_uuid'),
                );
            }
        }

        return $recommendations;
    }
}
