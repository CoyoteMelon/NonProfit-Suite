<?php
/**
 * Orchestrator Automation
 *
 * Automated tier management based on file activity and configured presets.
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
 * Class NonprofitSuite_Orchestrator_Automation
 *
 * Smart tier management that automatically moves files between storage tiers
 * based on access patterns, demand, and budget constraints.
 *
 * Presets:
 * - Budget Conscious: Start local, move to cloud when active
 * - Performance First: Always CDN/Cloud
 * - Balanced: Smart tier selection based on usage
 * - Archive Mode: Move inactive to cheapest storage
 *
 * @since 1.0.0
 */
class NonprofitSuite_Orchestrator_Automation {

    /**
     * Singleton instance
     *
     * @var NonprofitSuite_Orchestrator_Automation
     */
    private static $instance = null;

    /**
     * Orchestrator instance
     *
     * @var NonprofitSuite_Storage_Orchestrator
     */
    private $orchestrator;

    /**
     * Active preset
     *
     * @var string
     */
    private $active_preset;

    /**
     * Custom rules
     *
     * @var array
     */
    private $custom_rules = array();

    /**
     * Get singleton instance
     *
     * @return NonprofitSuite_Orchestrator_Automation
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
        $this->orchestrator = NonprofitSuite_Storage_Orchestrator::get_instance();
        $this->active_preset = get_option('ns_storage_automation_preset', 'balanced');
        $this->custom_rules = get_option('ns_storage_automation_rules', array());

        // Schedule automation cron
        add_action('ns_storage_automation_check', array($this, 'run_automation'));

        if (!wp_next_scheduled('ns_storage_automation_check')) {
            wp_schedule_event(time(), 'hourly', 'ns_storage_automation_check');
        }
    }

    /**
     * Get available presets
     *
     * @return array Presets.
     */
    public function get_presets() {
        return array(
            'budget-conscious' => array(
                'name' => __('Budget Conscious', 'nonprofitsuite'),
                'description' => __('Start local, move to cloud when demand increases. Minimizes cloud costs.', 'nonprofitsuite'),
                'rules' => array(
                    'initial_tier' => 'local',
                    'active_threshold' => 10, // requests per hour
                    'active_tier' => 'cloud',
                    'inactive_days' => 7,
                    'inactive_tier' => 'local',
                    'public_threshold' => 50, // requests per hour
                    'public_tier' => 'cdn',
                ),
            ),

            'performance-first' => array(
                'name' => __('Performance First', 'nonprofitsuite'),
                'description' => __('Always serve from CDN/Cloud for maximum speed. Higher costs.', 'nonprofitsuite'),
                'rules' => array(
                    'initial_tier' => 'cloud',
                    'public_threshold' => 1, // Any public file goes to CDN
                    'public_tier' => 'cdn',
                    'private_tier' => 'cloud',
                    'backup_tier' => 'local',
                ),
            ),

            'balanced' => array(
                'name' => __('Balanced', 'nonprofitsuite'),
                'description' => __('Smart tier selection based on usage patterns. Good mix of cost and performance.', 'nonprofitsuite'),
                'rules' => array(
                    'initial_tier' => 'cache',
                    'hot_threshold' => 25, // requests per day
                    'hot_tier' => 'cloud',
                    'warm_threshold' => 5,
                    'warm_tier' => 'cache',
                    'cold_days' => 30,
                    'cold_tier' => 'local',
                    'public_threshold' => 100,
                    'public_tier' => 'cdn',
                ),
            ),

            'archive-mode' => array(
                'name' => __('Archive Mode', 'nonprofitsuite'),
                'description' => __('Move inactive files to cheapest storage. Best for historical documents.', 'nonprofitsuite'),
                'rules' => array(
                    'initial_tier' => 'local',
                    'archive_days' => 90,
                    'archive_tier' => 'local',
                    'on_demand_tier' => 'cloud',
                    'max_cloud_time' => 24, // hours
                ),
            ),

            'custom' => array(
                'name' => __('Custom Rules', 'nonprofitsuite'),
                'description' => __('Define your own automation rules.', 'nonprofitsuite'),
                'rules' => array(), // User-defined
            ),
        );
    }

    /**
     * Set active preset
     *
     * @param string $preset Preset name.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function set_preset($preset) {
        $presets = $this->get_presets();

        if (!isset($presets[$preset])) {
            return new WP_Error('invalid_preset', __('Invalid preset specified.', 'nonprofitsuite'));
        }

        update_option('ns_storage_automation_preset', $preset);
        $this->active_preset = $preset;

        do_action('ns_storage_preset_changed', $preset);

        return true;
    }

    /**
     * Get current preset configuration
     *
     * @return array Preset configuration.
     */
    public function get_active_preset_config() {
        $presets = $this->get_presets();
        return $presets[$this->active_preset] ?? $presets['balanced'];
    }

    /**
     * Run automation check
     *
     * Analyzes all files and moves them between tiers based on rules.
     *
     * @return array Results.
     */
    public function run_automation() {
        $preset_config = $this->get_active_preset_config();
        $rules = $preset_config['rules'];

        $results = array(
            'checked' => 0,
            'moved' => 0,
            'skipped' => 0,
            'errors' => 0,
        );

        // Get files that need automation check
        $files = $this->get_files_for_automation();

        foreach ($files as $file) {
            $results['checked']++;

            $action = $this->determine_action($file, $rules);

            if ($action) {
                $result = $this->execute_action($file, $action);

                if (is_wp_error($result)) {
                    $results['errors']++;
                } else {
                    $results['moved']++;
                }
            } else {
                $results['skipped']++;
            }
        }

        // Log results
        update_option('ns_storage_automation_last_run', array(
            'time' => current_time('mysql'),
            'results' => $results,
        ));

        do_action('ns_storage_automation_completed', $results);

        return $results;
    }

    /**
     * Get files that need automation check
     *
     * @return array Files.
     */
    private function get_files_for_automation() {
        global $wpdb;

        // Get files with their current tier and usage stats
        $query = "SELECT f.*,
                  l.tier as current_tier,
                  COALESCE(u.access_count, 0) as access_count,
                  COALESCE(u.last_accessed_at, f.created_at) as last_accessed_at,
                  DATEDIFF(NOW(), COALESCE(u.last_accessed_at, f.created_at)) as days_since_access
                  FROM {$wpdb->prefix}ns_storage_files f
                  LEFT JOIN {$wpdb->prefix}ns_storage_locations l ON f.id = l.file_id AND l.tier IN ('cdn', 'cloud', 'cache', 'local')
                  LEFT JOIN {$wpdb->prefix}ns_storage_file_usage u ON f.id = u.file_id
                  WHERE f.deleted_at IS NULL
                  ORDER BY f.id ASC";

        return $wpdb->get_results($query);
    }

    /**
     * Determine action for a file based on rules
     *
     * @param object $file  File record.
     * @param array  $rules Preset rules.
     * @return array|false Action array or false if no action needed.
     */
    private function determine_action($file, $rules) {
        // Budget Conscious Logic
        if ($this->active_preset === 'budget-conscious') {
            // Move to cloud if becoming active
            if ($file->access_count >= $rules['active_threshold'] && $file->current_tier === 'local') {
                return array('action' => 'move', 'to_tier' => 'cloud', 'reason' => 'increased_demand');
            }

            // Move to CDN if very popular and public
            if ($file->is_public && $file->access_count >= $rules['public_threshold'] && $file->current_tier !== 'cdn') {
                return array('action' => 'move', 'to_tier' => 'cdn', 'reason' => 'high_public_demand');
            }

            // Move back to local if inactive
            if ($file->days_since_access >= $rules['inactive_days'] && in_array($file->current_tier, array('cloud', 'cdn'))) {
                return array('action' => 'move', 'to_tier' => 'local', 'reason' => 'inactive');
            }
        }

        // Performance First Logic
        elseif ($this->active_preset === 'performance-first') {
            // Public files always on CDN
            if ($file->is_public && $file->current_tier !== 'cdn') {
                return array('action' => 'move', 'to_tier' => 'cdn', 'reason' => 'public_file');
            }

            // Private files on cloud
            if (!$file->is_public && $file->current_tier !== 'cloud') {
                return array('action' => 'move', 'to_tier' => 'cloud', 'reason' => 'performance_tier');
            }
        }

        // Balanced Logic
        elseif ($this->active_preset === 'balanced') {
            $requests_per_day = $file->access_count; // Simplified - would track per-day in production

            // Hot files to cloud
            if ($requests_per_day >= $rules['hot_threshold'] && $file->current_tier !== 'cloud') {
                return array('action' => 'move', 'to_tier' => 'cloud', 'reason' => 'hot_file');
            }

            // Very hot public files to CDN
            if ($file->is_public && $requests_per_day >= $rules['public_threshold'] && $file->current_tier !== 'cdn') {
                return array('action' => 'move', 'to_tier' => 'cdn', 'reason' => 'very_hot_public');
            }

            // Warm files to cache
            if ($requests_per_day >= $rules['warm_threshold'] && $requests_per_day < $rules['hot_threshold'] && $file->current_tier !== 'cache') {
                return array('action' => 'move', 'to_tier' => 'cache', 'reason' => 'warm_file');
            }

            // Cold files to local
            if ($file->days_since_access >= $rules['cold_days'] && $file->current_tier !== 'local') {
                return array('action' => 'move', 'to_tier' => 'local', 'reason' => 'cold_file');
            }
        }

        // Archive Mode Logic
        elseif ($this->active_preset === 'archive-mode') {
            // Archive old files to local
            if ($file->days_since_access >= $rules['archive_days'] && $file->current_tier !== 'local') {
                return array('action' => 'move', 'to_tier' => 'local', 'reason' => 'archival');
            }

            // On-demand move to cloud for recent access
            if ($file->days_since_access < 1 && $file->current_tier === 'local') {
                return array('action' => 'move', 'to_tier' => 'cloud', 'reason' => 'on_demand');
            }
        }

        return false;
    }

    /**
     * Execute automation action
     *
     * @param object $file   File record.
     * @param array  $action Action to execute.
     * @return bool|WP_Error True on success, error on failure.
     */
    private function execute_action($file, $action) {
        if ($action['action'] === 'move') {
            // Use sync queue for tier migration
            $sync_manager = NonprofitSuite_Storage_Sync_Manager::get_instance();

            $result = $sync_manager->queue_sync(
                $file->file_uuid,
                $file->current_version,
                'sync',
                $file->current_tier,
                $action['to_tier'],
                array(
                    'priority' => 10,
                    'reason' => $action['reason'],
                    'automated' => true,
                )
            );

            // Log the automation action
            $this->log_action($file->id, $action);

            return $result;
        }

        return false;
    }

    /**
     * Log automation action
     *
     * @param int   $file_id File ID.
     * @param array $action  Action taken.
     * @return void
     */
    private function log_action($file_id, $action) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ns_storage_automation_log',
            array(
                'file_id' => $file_id,
                'preset' => $this->active_preset,
                'action' => $action['action'],
                'from_tier' => $action['from_tier'] ?? null,
                'to_tier' => $action['to_tier'] ?? null,
                'reason' => $action['reason'],
                'executed_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get automation statistics
     *
     * @param int $days Number of days to look back.
     * @return array Statistics.
     */
    public function get_automation_stats($days = 30) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_actions,
                SUM(CASE WHEN action = 'move' THEN 1 ELSE 0 END) as moves,
                COUNT(DISTINCT file_id) as files_affected
            FROM {$wpdb->prefix}ns_storage_automation_log
            WHERE executed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Get actions by reason
        $by_reason = $wpdb->get_results($wpdb->prepare(
            "SELECT reason, COUNT(*) as count
            FROM {$wpdb->prefix}ns_storage_automation_log
            WHERE executed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY reason
            ORDER BY count DESC",
            $days
        ));

        return array(
            'total_actions' => (int) $stats->total_actions,
            'moves' => (int) $stats->moves,
            'files_affected' => (int) $stats->files_affected,
            'by_reason' => $by_reason,
            'last_run' => get_option('ns_storage_automation_last_run'),
        );
    }

    /**
     * Add custom rule
     *
     * @param array $rule Rule configuration.
     * @return bool True on success.
     */
    public function add_custom_rule($rule) {
        $this->custom_rules[] = $rule;
        update_option('ns_storage_automation_rules', $this->custom_rules);

        // Switch to custom preset if not already
        if ($this->active_preset !== 'custom') {
            $this->set_preset('custom');
        }

        return true;
    }

    /**
     * Get custom rules
     *
     * @return array Custom rules.
     */
    public function get_custom_rules() {
        return $this->custom_rules;
    }

    /**
     * Manually trigger tier migration for a file
     *
     * @param string $file_id File UUID.
     * @param string $to_tier Target tier.
     * @param string $reason  Reason for migration.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function manual_migrate($file_id, $to_tier, $reason = 'manual') {
        global $wpdb;

        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT f.*, l.tier as current_tier
            FROM {$wpdb->prefix}ns_storage_files f
            LEFT JOIN {$wpdb->prefix}ns_storage_locations l ON f.id = l.file_id
            WHERE f.file_uuid = %s
            LIMIT 1",
            $file_id
        ));

        if (!$file) {
            return new WP_Error('file_not_found', __('File not found.', 'nonprofitsuite'));
        }

        $action = array(
            'action' => 'move',
            'from_tier' => $file->current_tier,
            'to_tier' => $to_tier,
            'reason' => $reason,
        );

        return $this->execute_action($file, $action);
    }
}
