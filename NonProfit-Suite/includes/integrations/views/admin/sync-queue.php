<?php
/**
 * Sync Queue Admin View
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Views
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $queue_items, $total, $status, $per_page, $page
?>

<div class="wrap">
    <h1><?php _e('Sync Queue', 'nonprofitsuite'); ?></h1>
    <p class="description"><?php _e('Monitor file synchronization between storage tiers (CDN, Cloud, Cache, Local).', 'nonprofitsuite'); ?></p>

    <!-- Status Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=ns-storage-sync&status=pending'); ?>" class="nav-tab <?php echo $status === 'pending' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Pending', 'nonprofitsuite'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ns-storage-sync&status=processing'); ?>" class="nav-tab <?php echo $status === 'processing' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Processing', 'nonprofitsuite'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ns-storage-sync&status=completed'); ?>" class="nav-tab <?php echo $status === 'completed' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Completed', 'nonprofitsuite'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ns-storage-sync&status=failed'); ?>" class="nav-tab <?php echo $status === 'failed' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Failed', 'nonprofitsuite'); ?>
        </a>
    </nav>

    <!-- Queue Items -->
    <?php if (empty($queue_items)) : ?>
        <div class="notice notice-info" style="margin-top: 20px;">
            <p>
                <?php
                switch ($status) {
                    case 'pending':
                        _e('No items pending synchronization. All files are in sync!', 'nonprofitsuite');
                        break;
                    case 'processing':
                        _e('No items currently being processed.', 'nonprofitsuite');
                        break;
                    case 'completed':
                        _e('No completed sync operations in recent history.', 'nonprofitsuite');
                        break;
                    case 'failed':
                        _e('No failed sync operations. Great!', 'nonprofitsuite');
                        break;
                }
                ?>
            </p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th><?php _e('Operation', 'nonprofitsuite'); ?></th>
                    <th><?php _e('File ID', 'nonprofitsuite'); ?></th>
                    <th><?php _e('From → To', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Priority', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Attempts', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Status', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Queued', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Last Attempt', 'nonprofitsuite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queue_items as $item) : ?>
                    <tr>
                        <td>
                            <?php
                            $operations = array(
                                'upload' => __('Upload', 'nonprofitsuite'),
                                'delete' => __('Delete', 'nonprofitsuite'),
                                'sync' => __('Sync', 'nonprofitsuite'),
                                'verify' => __('Verify', 'nonprofitsuite'),
                            );
                            echo esc_html($operations[$item->operation] ?? $item->operation);
                            ?>
                        </td>
                        <td>
                            <code><?php echo esc_html(substr($item->file_id, 0, 8)); ?>...</code>
                        </td>
                        <td>
                            <span class="ns-tier-badge ns-tier-<?php echo esc_attr($item->from_tier); ?>">
                                <?php echo esc_html(ucfirst($item->from_tier)); ?>
                            </span>
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <span class="ns-tier-badge ns-tier-<?php echo esc_attr($item->to_tier); ?>">
                                <?php echo esc_html(ucfirst($item->to_tier)); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $priority_labels = array(
                                1 => __('Critical', 'nonprofitsuite'),
                                5 => __('High', 'nonprofitsuite'),
                                10 => __('Normal', 'nonprofitsuite'),
                                20 => __('Low', 'nonprofitsuite'),
                            );
                            $priority_class = '';
                            if ($item->priority <= 1) {
                                $priority_class = 'critical';
                            } elseif ($item->priority <= 5) {
                                $priority_class = 'high';
                            } elseif ($item->priority <= 10) {
                                $priority_class = 'normal';
                            } else {
                                $priority_class = 'low';
                            }
                            ?>
                            <span class="ns-priority-badge ns-priority-<?php echo esc_attr($priority_class); ?>">
                                <?php echo $item->priority; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($item->attempts); ?> / 3
                            <?php if ($item->attempts >= 3) : ?>
                                <span class="dashicons dashicons-warning" style="color: #dc3232;" title="<?php _e('Max attempts reached', 'nonprofitsuite'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status_colors = array(
                                'pending' => '#f0b849',
                                'processing' => '#0073aa',
                                'completed' => '#46b450',
                                'failed' => '#dc3232',
                            );
                            $color = $status_colors[$item->status] ?? '#999';
                            ?>
                            <span class="ns-status-badge" style="background-color: <?php echo esc_attr($color); ?>;">
                                <?php echo esc_html(ucfirst($item->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html(human_time_diff(strtotime($item->queued_at), current_time('timestamp'))); ?> ago
                            <br><small class="description"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->queued_at))); ?></small>
                        </td>
                        <td>
                            <?php if (!empty($item->last_attempt_at)) : ?>
                                <?php echo esc_html(human_time_diff(strtotime($item->last_attempt_at), current_time('timestamp'))); ?> ago
                                <br><small class="description"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->last_attempt_at))); ?></small>
                                <?php if (!empty($item->error_message)) : ?>
                                    <br><span class="description" style="color: #dc3232;" title="<?php echo esc_attr($item->error_message); ?>">
                                        <span class="dashicons dashicons-info"></span> <?php _e('Error', 'nonprofitsuite'); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="description"><?php _e('Not attempted yet', 'nonprofitsuite'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) :
            $page_links = paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $page,
            ));

            if ($page_links) :
        ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php echo $page_links; ?>
                    </div>
                </div>
        <?php
            endif;
        endif;
        ?>
    <?php endif; ?>
</div>

<style>
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

.ns-priority-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.ns-priority-critical { background: #dc3232; color: #fff; }
.ns-priority-high { background: #f0b849; color: #fff; }
.ns-priority-normal { background: #0073aa; color: #fff; }
.ns-priority-low { background: #999; color: #fff; }

.ns-status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 3px;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
</style>
