<?php
/**
 * File Browser Admin View
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Views
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $files, $total, $search, $category, $is_public, $per_page, $page
?>

<div class="wrap">
    <h1><?php _e('File Browser', 'nonprofitsuite'); ?></h1>

    <!-- Search and Filters -->
    <form method="get" class="ns-storage-filters">
        <input type="hidden" name="page" value="ns-storage" />

        <p class="search-box">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search files...', 'nonprofitsuite'); ?>" />
        </p>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="category">
                    <option value=""><?php _e('All Categories', 'nonprofitsuite'); ?></option>
                    <option value="legal" <?php selected($category, 'legal'); ?>><?php _e('Legal', 'nonprofitsuite'); ?></option>
                    <option value="financial" <?php selected($category, 'financial'); ?>><?php _e('Financial', 'nonprofitsuite'); ?></option>
                    <option value="meeting-minutes" <?php selected($category, 'meeting-minutes'); ?>><?php _e('Meeting Minutes', 'nonprofitsuite'); ?></option>
                    <option value="policy" <?php selected($category, 'policy'); ?>><?php _e('Policy', 'nonprofitsuite'); ?></option>
                    <option value="grant" <?php selected($category, 'grant'); ?>><?php _e('Grant', 'nonprofitsuite'); ?></option>
                    <option value="report" <?php selected($category, 'report'); ?>><?php _e('Report', 'nonprofitsuite'); ?></option>
                    <option value="correspondence" <?php selected($category, 'correspondence'); ?>><?php _e('Correspondence', 'nonprofitsuite'); ?></option>
                    <option value="general" <?php selected($category, 'general'); ?>><?php _e('General', 'nonprofitsuite'); ?></option>
                </select>

                <select name="visibility">
                    <option value=""><?php _e('All Visibility', 'nonprofitsuite'); ?></option>
                    <option value="public" <?php selected($_GET['visibility'] ?? '', 'public'); ?>><?php _e('Public', 'nonprofitsuite'); ?></option>
                    <option value="private" <?php selected($_GET['visibility'] ?? '', 'private'); ?>><?php _e('Private', 'nonprofitsuite'); ?></option>
                </select>

                <button type="submit" class="button"><?php _e('Filter', 'nonprofitsuite'); ?></button>
            </div>

            <div class="alignright">
                <a href="<?php echo admin_url('admin.php?page=ns-storage-upload'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span> <?php _e('Upload Files', 'nonprofitsuite'); ?>
                </a>
            </div>
        </div>
    </form>

    <!-- Files Table -->
    <?php if (empty($files)) : ?>
        <div class="notice notice-info">
            <p><?php _e('No files found. Upload your first file to get started!', 'nonprofitsuite'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php _e('Icon', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Filename', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Category', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Author', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Status', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Size', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Uploaded', 'nonprofitsuite'); ?></th>
                    <th><?php _e('Actions', 'nonprofitsuite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file) : ?>
                    <tr data-file-id="<?php echo esc_attr($file->file_uuid); ?>">
                        <td>
                            <?php
                            $icon_class = 'dashicons-media-default';
                            if (strpos($file->mime_type, 'image/') === 0) {
                                $icon_class = 'dashicons-format-image';
                            } elseif (strpos($file->mime_type, 'pdf') !== false) {
                                $icon_class = 'dashicons-pdf';
                            } elseif (strpos($file->mime_type, 'word') !== false || strpos($file->mime_type, 'document') !== false) {
                                $icon_class = 'dashicons-media-document';
                            } elseif (strpos($file->mime_type, 'sheet') !== false || strpos($file->mime_type, 'excel') !== false) {
                                $icon_class = 'dashicons-media-spreadsheet';
                            }
                            ?>
                            <span class="dashicons <?php echo esc_attr($icon_class); ?>" style="font-size: 32px; width: 32px; height: 32px;"></span>
                        </td>
                        <td>
                            <strong><?php echo esc_html($file->filename); ?></strong>
                            <?php if ($file->is_public) : ?>
                                <span class="dashicons dashicons-visibility" title="<?php _e('Public', 'nonprofitsuite'); ?>"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-lock" title="<?php _e('Private', 'nonprofitsuite'); ?>"></span>
                            <?php endif; ?>
                            <?php if ($file->has_physical_copy) : ?>
                                <span class="dashicons dashicons-archive" title="<?php _e('Has physical copy', 'nonprofitsuite'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ns-category-badge ns-category-<?php echo esc_attr($file->category ?? 'general'); ?>">
                                <?php echo esc_html(ucfirst($file->category ?? 'general')); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($file->document_author)) : ?>
                                <?php echo esc_html($file->document_author); ?>
                                <?php if (!empty($file->document_author_type)) : ?>
                                    <br><small class="description">(<?php echo esc_html($file->document_author_type); ?>)</small>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="description"><?php _e('Not set', 'nonprofitsuite'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status = $file->document_status ?? 'draft';
                            $status_colors = array(
                                'draft' => '#999',
                                'revised' => '#0073aa',
                                'final' => '#00a0d2',
                                'approved' => '#46b450',
                                'rejected' => '#dc3232',
                                'archived' => '#826eb4',
                            );
                            $color = $status_colors[$status] ?? '#999';
                            ?>
                            <span class="ns-status-badge" style="background-color: <?php echo esc_attr($color); ?>;">
                                <?php echo esc_html(ucfirst($status)); ?>
                            </span>
                        </td>
                        <td><?php echo size_format($file->file_size, 2); ?></td>
                        <td>
                            <?php echo esc_html(human_time_diff(strtotime($file->created_at), current_time('timestamp'))); ?> ago
                            <br><small class="description"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($file->created_at))); ?></small>
                        </td>
                        <td>
                            <button type="button" class="button button-small ns-view-file" data-file-id="<?php echo esc_attr($file->file_uuid); ?>">
                                <?php _e('View', 'nonprofitsuite'); ?>
                            </button>
                            <button type="button" class="button button-small ns-edit-file" data-file-id="<?php echo esc_attr($file->file_uuid); ?>">
                                <?php _e('Edit', 'nonprofitsuite'); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete ns-delete-file" data-file-id="<?php echo esc_attr($file->file_uuid); ?>">
                                <?php _e('Delete', 'nonprofitsuite'); ?>
                            </button>
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
.ns-category-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.ns-category-legal { background: #e3f2fd; color: #1565c0; }
.ns-category-financial { background: #f3e5f5; color: #6a1b9a; }
.ns-category-meeting-minutes { background: #e8f5e9; color: #2e7d32; }
.ns-category-policy { background: #fff3e0; color: #e65100; }
.ns-category-grant { background: #fce4ec; color: #c2185b; }
.ns-category-report { background: #e0f2f1; color: #00695c; }
.ns-category-correspondence { background: #ede7f6; color: #4527a0; }
.ns-category-general { background: #f5f5f5; color: #616161; }

.ns-status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 3px;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.ns-storage-filters {
    margin-bottom: 20px;
}

.ns-storage-filters .tablenav {
    margin-top: 10px;
}
</style>
