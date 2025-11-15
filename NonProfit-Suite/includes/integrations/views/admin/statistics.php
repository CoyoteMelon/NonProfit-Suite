<?php
/**
 * Storage Statistics Admin View
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Views
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $total_files, $total_size, $public_files, $private_files, $cache_stats, $sync_stats, $category_stats, $provider_stats, $physical_stats
?>

<div class="wrap">
    <h1><?php _e('Storage Statistics', 'nonprofitsuite'); ?></h1>

    <!-- Overview Cards -->
    <div class="ns-stats-grid">
        <div class="ns-stat-card">
            <div class="ns-stat-icon dashicons dashicons-media-default"></div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($total_files); ?></div>
                <div class="ns-stat-label"><?php _e('Total Files', 'nonprofitsuite'); ?></div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon dashicons dashicons-database"></div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo size_format($total_size, 2); ?></div>
                <div class="ns-stat-label"><?php _e('Total Storage Used', 'nonprofitsuite'); ?></div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon dashicons dashicons-visibility"></div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($public_files); ?></div>
                <div class="ns-stat-label"><?php _e('Public Files', 'nonprofitsuite'); ?></div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon dashicons dashicons-lock"></div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($private_files); ?></div>
                <div class="ns-stat-label"><?php _e('Private Files', 'nonprofitsuite'); ?></div>
            </div>
        </div>
    </div>

    <!-- Cache Statistics -->
    <div class="ns-stats-section">
        <h2><?php _e('Cache Performance', 'nonprofitsuite'); ?></h2>
        <div class="ns-stats-grid">
            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value"><?php echo number_format($cache_stats['total_cached'] ?? 0); ?></div>
                    <div class="ns-stat-label"><?php _e('Cached Files', 'nonprofitsuite'); ?></div>
                </div>
            </div>

            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value"><?php echo size_format($cache_stats['cache_size'] ?? 0, 2); ?></div>
                    <div class="ns-stat-label"><?php _e('Cache Size', 'nonprofitsuite'); ?></div>
                </div>
            </div>

            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value">
                        <?php
                        $hits = $cache_stats['cache_hits'] ?? 0;
                        $misses = $cache_stats['cache_misses'] ?? 0;
                        $total_requests = $hits + $misses;
                        $hit_rate = $total_requests > 0 ? round(($hits / $total_requests) * 100, 1) : 0;
                        echo $hit_rate . '%';
                        ?>
                    </div>
                    <div class="ns-stat-label"><?php _e('Cache Hit Rate', 'nonprofitsuite'); ?></div>
                </div>
            </div>

            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value"><?php echo number_format($cache_stats['expired'] ?? 0); ?></div>
                    <div class="ns-stat-label"><?php _e('Expired Entries', 'nonprofitsuite'); ?></div>
                </div>
            </div>
        </div>

        <div class="ns-cache-actions">
            <button type="button" class="button" id="ns-warm-cache">
                <span class="dashicons dashicons-update"></span> <?php _e('Warm Cache', 'nonprofitsuite'); ?>
            </button>
            <button type="button" class="button" id="ns-clean-cache">
                <span class="dashicons dashicons-trash"></span> <?php _e('Clean Expired', 'nonprofitsuite'); ?>
            </button>
        </div>
    </div>

    <!-- Sync Queue Statistics -->
    <div class="ns-stats-section">
        <h2><?php _e('Sync Queue Status', 'nonprofitsuite'); ?></h2>
        <div class="ns-stats-grid">
            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value"><?php echo number_format($sync_stats['pending'] ?? 0); ?></div>
                    <div class="ns-stat-label"><?php _e('Pending', 'nonprofitsuite'); ?></div>
                </div>
            </div>

            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value"><?php echo number_format($sync_stats['processing'] ?? 0); ?></div>
                    <div class="ns-stat-label"><?php _e('Processing', 'nonprofitsuite'); ?></div>
                </div>
            </div>

            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value"><?php echo number_format($sync_stats['completed'] ?? 0); ?></div>
                    <div class="ns-stat-label"><?php _e('Completed', 'nonprofitsuite'); ?></div>
                </div>
            </div>

            <div class="ns-stat-card">
                <div class="ns-stat-content">
                    <div class="ns-stat-value"><?php echo number_format($sync_stats['failed'] ?? 0); ?></div>
                    <div class="ns-stat-label"><?php _e('Failed', 'nonprofitsuite'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Physical Document Statistics -->
    <?php if ($physical_stats && $physical_stats->total_with_physical > 0) : ?>
        <div class="ns-stats-section">
            <h2><?php _e('Physical Documents', 'nonprofitsuite'); ?></h2>
            <div class="ns-stats-grid">
                <div class="ns-stat-card">
                    <div class="ns-stat-content">
                        <div class="ns-stat-value"><?php echo number_format($physical_stats->total_with_physical); ?></div>
                        <div class="ns-stat-label"><?php _e('Total Physical Copies', 'nonprofitsuite'); ?></div>
                    </div>
                </div>

                <div class="ns-stat-card">
                    <div class="ns-stat-content">
                        <div class="ns-stat-value"><?php echo number_format($physical_stats->verified); ?></div>
                        <div class="ns-stat-label"><?php _e('Verified', 'nonprofitsuite'); ?></div>
                    </div>
                </div>

                <div class="ns-stat-card">
                    <div class="ns-stat-content">
                        <div class="ns-stat-value"><?php echo number_format($physical_stats->unverified); ?></div>
                        <div class="ns-stat-label"><?php _e('Needs Verification', 'nonprofitsuite'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Two Column Layout -->
    <div class="ns-stats-columns">
        <!-- Storage by Category -->
        <div class="ns-stats-column">
            <h2><?php _e('Storage by Category', 'nonprofitsuite'); ?></h2>
            <?php if (!empty($category_stats)) : ?>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Category', 'nonprofitsuite'); ?></th>
                            <th><?php _e('Files', 'nonprofitsuite'); ?></th>
                            <th><?php _e('Size', 'nonprofitsuite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_stats as $stat) : ?>
                            <tr>
                                <td>
                                    <span class="ns-category-badge ns-category-<?php echo esc_attr($stat->category ?? 'general'); ?>">
                                        <?php echo esc_html(ucfirst($stat->category ?? 'general')); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($stat->count); ?></td>
                                <td><?php echo size_format($stat->size, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="description"><?php _e('No files categorized yet.', 'nonprofitsuite'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Storage by Provider -->
        <div class="ns-stats-column">
            <h2><?php _e('Storage by Provider', 'nonprofitsuite'); ?></h2>
            <?php if (!empty($provider_stats)) : ?>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Provider', 'nonprofitsuite'); ?></th>
                            <th><?php _e('Tier', 'nonprofitsuite'); ?></th>
                            <th><?php _e('Files', 'nonprofitsuite'); ?></th>
                            <th><?php _e('Size', 'nonprofitsuite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($provider_stats as $stat) : ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($stat->provider)); ?></td>
                                <td>
                                    <span class="ns-tier-badge ns-tier-<?php echo esc_attr($stat->tier); ?>">
                                        <?php echo esc_html(ucfirst($stat->tier)); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($stat->count); ?></td>
                                <td><?php echo size_format($stat->size, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="description"><?php _e('No provider data available yet.', 'nonprofitsuite'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.ns-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.ns-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.ns-stat-icon {
    font-size: 40px;
    width: 40px;
    height: 40px;
    color: #0073aa;
}

.ns-stat-content {
    flex: 1;
}

.ns-stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #23282d;
    line-height: 1;
}

.ns-stat-label {
    font-size: 13px;
    color: #646970;
    margin-top: 5px;
}

.ns-stats-section {
    margin: 30px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ns-stats-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.ns-cache-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.ns-cache-actions .button {
    margin-right: 10px;
}

.ns-stats-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.ns-stats-column {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
}

.ns-stats-column h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
    font-size: 16px;
}

.ns-tier-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.ns-tier-cdn { background: #ff6b6b; color: #fff; }
.ns-tier-cloud { background: #4ecdc4; color: #fff; }
.ns-tier-cache { background: #45b7d1; color: #fff; }
.ns-tier-local { background: #95afc0; color: #fff; }
.ns-tier-collab { background: #f38181; color: #fff; }

@media (max-width: 782px) {
    .ns-stats-columns {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#ns-warm-cache').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('Warming...', 'nonprofitsuite'); ?>');

        $.post(ajaxurl, {
            action: 'ns_storage_warm_cache',
            nonce: nsStorage.nonce,
            limit: 50
        }, function(response) {
            if (response.success) {
                alert(response.data.message + ' (' + response.data.cached + ' files cached)');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        }).always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e('Warm Cache', 'nonprofitsuite'); ?>');
        });
    });

    $('#ns-clean-cache').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('Cleaning...', 'nonprofitsuite'); ?>');

        $.post(ajaxurl, {
            action: 'ns_storage_clean_cache',
            nonce: nsStorage.nonce,
            type: 'expired'
        }, function(response) {
            if (response.success) {
                alert(response.data.message + ' (' + response.data.removed + ' files removed)');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        }).always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Clean Expired', 'nonprofitsuite'); ?>');
        });
    });
});
</script>
