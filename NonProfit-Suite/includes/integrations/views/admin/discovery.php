<?php
/**
 * Document Discovery Admin View
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Views
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $discoveries, $total, $stats, $status, $per_page, $page
?>

<div class="wrap">
    <h1><?php _e('Document Discovery', 'nonprofitsuite'); ?></h1>
    <p class="description"><?php _e('AI-powered document analysis and organization. Review AI suggestions and accept or reject them.', 'nonprofitsuite'); ?></p>

    <!-- Statistics Overview -->
    <div class="ns-discovery-stats">
        <div class="ns-discovery-stat">
            <span class="count"><?php echo number_format($stats->total ?? 0); ?></span>
            <span class="label"><?php _e('Total', 'nonprofitsuite'); ?></span>
        </div>
        <div class="ns-discovery-stat pending">
            <span class="count"><?php echo number_format($stats->pending ?? 0); ?></span>
            <span class="label"><?php _e('Pending', 'nonprofitsuite'); ?></span>
        </div>
        <div class="ns-discovery-stat processing">
            <span class="count"><?php echo number_format($stats->processing ?? 0); ?></span>
            <span class="label"><?php _e('Processing', 'nonprofitsuite'); ?></span>
        </div>
        <div class="ns-discovery-stat needs-review">
            <span class="count"><?php echo number_format($stats->needs_review ?? 0); ?></span>
            <span class="label"><?php _e('Needs Review', 'nonprofitsuite'); ?></span>
        </div>
        <div class="ns-discovery-stat reviewed">
            <span class="count"><?php echo number_format($stats->reviewed ?? 0); ?></span>
            <span class="label"><?php _e('Reviewed', 'nonprofitsuite'); ?></span>
        </div>
    </div>

    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=ns-storage-discovery&status=needs_review'); ?>" class="nav-tab <?php echo $status === 'needs_review' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Needs Review', 'nonprofitsuite'); ?>
            <?php if (($stats->needs_review ?? 0) > 0) : ?>
                <span class="count">(<?php echo number_format($stats->needs_review); ?>)</span>
            <?php endif; ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ns-storage-discovery&status=pending'); ?>" class="nav-tab <?php echo $status === 'pending' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Pending', 'nonprofitsuite'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ns-storage-discovery&status=processing'); ?>" class="nav-tab <?php echo $status === 'processing' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Processing', 'nonprofitsuite'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ns-storage-discovery&status=reviewed'); ?>" class="nav-tab <?php echo $status === 'reviewed' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Reviewed', 'nonprofitsuite'); ?>
        </a>
    </nav>

    <!-- Documents List -->
    <?php if (empty($discoveries)) : ?>
        <div class="notice notice-info" style="margin-top: 20px;">
            <p>
                <?php
                switch ($status) {
                    case 'needs_review':
                        _e('No documents need review. Great job!', 'nonprofitsuite');
                        break;
                    case 'pending':
                        _e('No documents in queue. Discovery will run automatically when you upload files.', 'nonprofitsuite');
                        break;
                    case 'processing':
                        _e('No documents currently being processed.', 'nonprofitsuite');
                        break;
                    case 'reviewed':
                        _e('No documents have been reviewed yet.', 'nonprofitsuite');
                        break;
                }
                ?>
            </p>
        </div>
    <?php else : ?>
        <div class="ns-discovery-list">
            <?php foreach ($discoveries as $discovery) : ?>
                <div class="ns-discovery-item" data-file-id="<?php echo esc_attr($discovery->file_uuid); ?>">
                    <div class="ns-discovery-header">
                        <div class="ns-discovery-filename">
                            <span class="dashicons dashicons-media-document"></span>
                            <strong><?php echo esc_html($discovery->filename); ?></strong>
                            <span class="ns-discovery-type"><?php echo esc_html($discovery->mime_type); ?></span>
                        </div>
                        <div class="ns-discovery-confidence">
                            <?php
                            $confidence = floatval($discovery->confidence_score ?? 0);
                            $confidence_pct = round($confidence * 100);
                            $confidence_class = 'low';
                            if ($confidence >= 0.75) {
                                $confidence_class = 'high';
                            } elseif ($confidence >= 0.5) {
                                $confidence_class = 'medium';
                            }
                            ?>
                            <span class="ns-confidence-badge ns-confidence-<?php echo esc_attr($confidence_class); ?>">
                                <?php echo $confidence_pct; ?>% <?php _e('Confidence', 'nonprofitsuite'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="ns-discovery-body">
                        <div class="ns-discovery-section">
                            <h4><?php _e('AI Suggestions', 'nonprofitsuite'); ?></h4>
                            <div class="ns-discovery-grid">
                                <div class="ns-discovery-field">
                                    <label><?php _e('Category', 'nonprofitsuite'); ?>:</label>
                                    <div class="ns-discovery-value">
                                        <?php if (!empty($discovery->discovered_category)) : ?>
                                            <span class="ns-category-badge ns-category-<?php echo esc_attr($discovery->discovered_category); ?>">
                                                <?php echo esc_html(ucfirst($discovery->discovered_category)); ?>
                                            </span>
                                            <?php if (!empty($discovery->discovered_subcategory)) : ?>
                                                <span class="ns-subcategory">(<?php echo esc_html($discovery->discovered_subcategory); ?>)</span>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="description"><?php _e('Not identified', 'nonprofitsuite'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($discovery->current_category) && $discovery->current_category !== $discovery->discovered_category) : ?>
                                    <div class="ns-discovery-field">
                                        <label><?php _e('Current Category', 'nonprofitsuite'); ?>:</label>
                                        <div class="ns-discovery-value">
                                            <span class="ns-category-badge ns-category-<?php echo esc_attr($discovery->current_category); ?>">
                                                <?php echo esc_html(ucfirst($discovery->current_category)); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($discovery->content_summary)) : ?>
                                <div class="ns-discovery-field">
                                    <label><?php _e('Summary', 'nonprofitsuite'); ?>:</label>
                                    <div class="ns-discovery-value">
                                        <p><?php echo esc_html($discovery->content_summary); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php
                            $tags = !empty($discovery->auto_tags) ? json_decode($discovery->auto_tags, true) : array();
                            if (!empty($tags)) :
                            ?>
                                <div class="ns-discovery-field">
                                    <label><?php _e('Suggested Tags', 'nonprofitsuite'); ?>:</label>
                                    <div class="ns-discovery-value">
                                        <?php foreach ($tags as $tag) : ?>
                                            <span class="ns-tag"><?php echo esc_html($tag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php
                            $entities = !empty($discovery->key_entities) ? json_decode($discovery->key_entities, true) : array();
                            if (!empty($entities['people'])) :
                            ?>
                                <div class="ns-discovery-field">
                                    <label><?php _e('People Mentioned', 'nonprofitsuite'); ?>:</label>
                                    <div class="ns-discovery-value">
                                        <?php echo esc_html(implode(', ', array_slice($entities['people'], 0, 5))); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($discovery->document_date)) : ?>
                                <div class="ns-discovery-field">
                                    <label><?php _e('Document Date', 'nonprofitsuite'); ?>:</label>
                                    <div class="ns-discovery-value">
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($discovery->document_date))); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($status === 'needs_review' || $discovery->needs_review) : ?>
                            <div class="ns-discovery-actions">
                                <button type="button" class="button button-primary ns-accept-discovery" data-file-id="<?php echo esc_attr($discovery->file_uuid); ?>">
                                    <span class="dashicons dashicons-yes"></span> <?php _e('Accept & Apply', 'nonprofitsuite'); ?>
                                </button>
                                <button type="button" class="button ns-reject-discovery" data-file-id="<?php echo esc_attr($discovery->file_uuid); ?>">
                                    <span class="dashicons dashicons-no"></span> <?php _e('Reject', 'nonprofitsuite'); ?>
                                </button>
                            </div>
                        <?php elseif ($discovery->discovery_status === 'pending') : ?>
                            <div class="ns-discovery-actions">
                                <button type="button" class="button button-primary ns-process-discovery" data-file-id="<?php echo esc_attr($discovery->file_uuid); ?>">
                                    <span class="dashicons dashicons-update"></span> <?php _e('Process Now', 'nonprofitsuite'); ?>
                                </button>
                            </div>
                        <?php elseif ($discovery->discovery_status === 'reviewed') : ?>
                            <div class="ns-discovery-meta">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Reviewed', 'nonprofitsuite'); ?>
                                <?php if (!empty($discovery->reviewed_at)) : ?>
                                    - <?php echo esc_html(human_time_diff(strtotime($discovery->reviewed_at), current_time('timestamp'))); ?> ago
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

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
.ns-discovery-stats {
    display: flex;
    gap: 15px;
    margin: 20px 0;
}

.ns-discovery-stat {
    flex: 1;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    text-align: center;
}

.ns-discovery-stat .count {
    display: block;
    font-size: 32px;
    font-weight: 600;
    color: #23282d;
    line-height: 1;
}

.ns-discovery-stat .label {
    display: block;
    font-size: 13px;
    color: #646970;
    margin-top: 5px;
}

.ns-discovery-stat.pending { border-left: 4px solid #f0b849; }
.ns-discovery-stat.processing { border-left: 4px solid #0073aa; }
.ns-discovery-stat.needs-review { border-left: 4px solid #dc3232; }
.ns-discovery-stat.reviewed { border-left: 4px solid #46b450; }

.ns-discovery-list {
    margin-top: 20px;
}

.ns-discovery-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
    overflow: hidden;
}

.ns-discovery-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f9f9f9;
    border-bottom: 1px solid #ddd;
}

.ns-discovery-filename {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ns-discovery-filename .dashicons {
    color: #0073aa;
}

.ns-discovery-type {
    font-size: 11px;
    color: #646970;
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 3px;
}

.ns-confidence-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.ns-confidence-high {
    background: #d1f5e1;
    color: #0f7d3a;
}

.ns-confidence-medium {
    background: #fff4d1;
    color: #876400;
}

.ns-confidence-low {
    background: #fdd;
    color: #a00;
}

.ns-discovery-body {
    padding: 20px;
}

.ns-discovery-section h4 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
    color: #23282d;
}

.ns-discovery-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin: 15px 0;
}

.ns-discovery-field {
    margin: 10px 0;
}

.ns-discovery-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #23282d;
}

.ns-discovery-value {
    color: #646970;
}

.ns-subcategory {
    font-size: 12px;
    color: #646970;
    margin-left: 5px;
}

.ns-tag {
    display: inline-block;
    background: #f0f0f0;
    padding: 3px 10px;
    border-radius: 3px;
    font-size: 12px;
    margin-right: 5px;
    margin-bottom: 5px;
}

.ns-discovery-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.ns-discovery-actions .button {
    margin-right: 10px;
}

.ns-discovery-meta {
    color: #46b450;
    padding: 10px;
    background: #f0f9f0;
    border-radius: 3px;
    margin-top: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.ns-accept-discovery').on('click', function() {
        var $btn = $(this);
        var fileId = $btn.data('file-id');
        var $item = $btn.closest('.ns-discovery-item');

        if (!confirm('<?php _e('Apply AI suggestions to this file?', 'nonprofitsuite'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('Applying...', 'nonprofitsuite'); ?>');

        $.post(ajaxurl, {
            action: 'ns_storage_accept_discovery',
            nonce: nsStorage.nonce,
            file_id: fileId
        }, function(response) {
            if (response.success) {
                $item.fadeOut(function() {
                    $(this).remove();
                });
                alert(response.data.message);
            } else {
                alert('Error: ' + response.data.message);
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <?php _e('Accept & Apply', 'nonprofitsuite'); ?>');
            }
        });
    });

    $('.ns-reject-discovery').on('click', function() {
        var $btn = $(this);
        var fileId = $btn.data('file-id');
        var $item = $btn.closest('.ns-discovery-item');

        if (!confirm('<?php _e('Reject AI suggestions for this file?', 'nonprofitsuite'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('Rejecting...', 'nonprofitsuite'); ?>');

        $.post(ajaxurl, {
            action: 'ns_storage_reject_discovery',
            nonce: nsStorage.nonce,
            file_id: fileId
        }, function(response) {
            if (response.success) {
                $item.fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert('Error: ' + response.data.message);
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> <?php _e('Reject', 'nonprofitsuite'); ?>');
            }
        });
    });

    $('.ns-process-discovery').on('click', function() {
        var $btn = $(this);
        var fileId = $btn.data('file-id');

        $btn.prop('disabled', true).text('<?php _e('Processing...', 'nonprofitsuite'); ?>');

        $.post(ajaxurl, {
            action: 'ns_storage_process_discovery',
            nonce: nsStorage.nonce,
            file_id: fileId
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e('Process Now', 'nonprofitsuite'); ?>');
            }
        });
    });
});
</script>
