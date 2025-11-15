<?php
/**
 * Upload Files Admin View
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Views
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Upload Files', 'nonprofitsuite'); ?></h1>

    <div class="ns-upload-container">
        <div class="ns-upload-area" id="ns-drop-zone">
            <div class="ns-upload-icon">
                <span class="dashicons dashicons-upload"></span>
            </div>
            <h2><?php _e('Drop files here to upload', 'nonprofitsuite'); ?></h2>
            <p class="description"><?php _e('or click to select files', 'nonprofitsuite'); ?></p>
            <input type="file" id="ns-file-input" multiple style="display: none;" />
            <button type="button" class="button button-primary button-hero" id="ns-select-files">
                <?php _e('Select Files', 'nonprofitsuite'); ?>
            </button>
        </div>

        <div class="ns-upload-form" id="ns-upload-form" style="display: none;">
            <h3><?php _e('File Details', 'nonprofitsuite'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ns-category"><?php _e('Category', 'nonprofitsuite'); ?></label>
                    </th>
                    <td>
                        <select id="ns-category" class="regular-text">
                            <option value="general"><?php _e('General', 'nonprofitsuite'); ?></option>
                            <option value="legal"><?php _e('Legal', 'nonprofitsuite'); ?></option>
                            <option value="financial"><?php _e('Financial', 'nonprofitsuite'); ?></option>
                            <option value="meeting-minutes"><?php _e('Meeting Minutes', 'nonprofitsuite'); ?></option>
                            <option value="policy"><?php _e('Policy', 'nonprofitsuite'); ?></option>
                            <option value="grant"><?php _e('Grant', 'nonprofitsuite'); ?></option>
                            <option value="report"><?php _e('Report', 'nonprofitsuite'); ?></option>
                            <option value="correspondence"><?php _e('Correspondence', 'nonprofitsuite'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ns-visibility"><?php _e('Visibility', 'nonprofitsuite'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="ns-visibility" />
                            <?php _e('Make this file publicly accessible', 'nonprofitsuite'); ?>
                        </label>
                        <p class="description"><?php _e('Public files can be accessed without authentication.', 'nonprofitsuite'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ns-description"><?php _e('Description', 'nonprofitsuite'); ?></label>
                    </th>
                    <td>
                        <textarea id="ns-description" class="large-text" rows="3"></textarea>
                        <p class="description"><?php _e('Optional description of the file contents.', 'nonprofitsuite'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="ns-selected-files" id="ns-selected-files">
                <h4><?php _e('Selected Files', 'nonprofitsuite'); ?></h4>
                <ul id="ns-file-list"></ul>
            </div>

            <p class="submit">
                <button type="button" class="button button-primary button-large" id="ns-upload-btn">
                    <?php _e('Upload Files', 'nonprofitsuite'); ?>
                </button>
                <button type="button" class="button button-large" id="ns-cancel-btn">
                    <?php _e('Cancel', 'nonprofitsuite'); ?>
                </button>
            </p>

            <div class="ns-upload-progress" id="ns-upload-progress" style="display: none;">
                <div class="ns-progress-bar">
                    <div class="ns-progress-fill" id="ns-progress-fill"></div>
                </div>
                <p class="ns-progress-text" id="ns-progress-text"></p>
            </div>
        </div>
    </div>
</div>

<style>
.ns-upload-container {
    max-width: 800px;
    margin: 20px 0;
}

.ns-upload-area {
    border: 2px dashed #0073aa;
    border-radius: 8px;
    padding: 60px 40px;
    text-align: center;
    background: #f9f9f9;
    transition: all 0.3s ease;
}

.ns-upload-area:hover,
.ns-upload-area.drag-over {
    border-color: #005177;
    background: #f0f0f0;
}

.ns-upload-icon {
    margin-bottom: 20px;
}

.ns-upload-icon .dashicons {
    font-size: 80px;
    width: 80px;
    height: 80px;
    color: #0073aa;
}

.ns-upload-area h2 {
    margin: 10px 0;
    color: #23282d;
}

.ns-upload-form {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.ns-selected-files {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ns-selected-files h4 {
    margin-top: 0;
}

#ns-file-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

#ns-file-list li {
    padding: 8px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#ns-file-list li:last-child {
    border-bottom: none;
}

.ns-file-remove {
    color: #dc3232;
    cursor: pointer;
    text-decoration: none;
}

.ns-upload-progress {
    margin-top: 20px;
}

.ns-progress-bar {
    width: 100%;
    height: 30px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.ns-progress-fill {
    height: 100%;
    background: #0073aa;
    width: 0%;
    transition: width 0.3s ease;
}

.ns-progress-text {
    text-align: center;
    color: #646970;
    margin: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    var selectedFiles = [];

    // Drag and drop
    var $dropZone = $('#ns-drop-zone');

    $dropZone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });

    $dropZone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });

    $dropZone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');

        var files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    });

    // File selection
    $('#ns-select-files').on('click', function() {
        $('#ns-file-input').click();
    });

    $('#ns-file-input').on('change', function() {
        var files = this.files;
        handleFiles(files);
    });

    function handleFiles(files) {
        if (files.length === 0) return;

        selectedFiles = Array.from(files);
        displaySelectedFiles();
        $('#ns-upload-area').hide();
        $('#ns-upload-form').show();
    }

    function displaySelectedFiles() {
        var $list = $('#ns-file-list');
        $list.empty();

        selectedFiles.forEach(function(file, index) {
            var $item = $('<li>');
            $item.append('<span>' + file.name + ' (' + formatBytes(file.size) + ')</span>');
            $item.append('<a href="#" class="ns-file-remove" data-index="' + index + '"><?php _e('Remove', 'nonprofitsuite'); ?></a>');
            $list.append($item);
        });
    }

    $(document).on('click', '.ns-file-remove', function(e) {
        e.preventDefault();
        var index = $(this).data('index');
        selectedFiles.splice(index, 1);

        if (selectedFiles.length === 0) {
            $('#ns-upload-form').hide();
            $('#ns-upload-area').show();
        } else {
            displaySelectedFiles();
        }
    });

    $('#ns-cancel-btn').on('click', function() {
        selectedFiles = [];
        $('#ns-upload-form').hide();
        $('#ns-upload-area').show();
        $('#ns-file-input').val('');
    });

    $('#ns-upload-btn').on('click', function() {
        if (selectedFiles.length === 0) return;

        var category = $('#ns-category').val();
        var isPublic = $('#ns-visibility').is(':checked');
        var description = $('#ns-description').val();

        $(this).prop('disabled', true);
        $('#ns-upload-progress').show();

        uploadFiles(selectedFiles, category, isPublic, description);
    });

    function uploadFiles(files, category, isPublic, description) {
        var totalFiles = files.length;
        var uploadedFiles = 0;

        function uploadNext(index) {
            if (index >= totalFiles) {
                alert('<?php _e('All files uploaded successfully!', 'nonprofitsuite'); ?>');
                location.reload();
                return;
            }

            var file = files[index];
            var formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'ns_storage_upload');
            formData.append('nonce', nsStorage.nonce);
            formData.append('category', category);
            formData.append('is_public', isPublic ? '1' : '0');
            formData.append('description', description);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percentComplete = ((uploadedFiles + (e.loaded / e.total)) / totalFiles) * 100;
                            $('#ns-progress-fill').css('width', percentComplete + '%');
                            $('#ns-progress-text').text('Uploading ' + (index + 1) + ' of ' + totalFiles + ': ' + file.name);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    uploadedFiles++;
                    uploadNext(index + 1);
                },
                error: function() {
                    alert('Failed to upload: ' + file.name);
                    uploadNext(index + 1);
                }
            });
        }

        uploadNext(0);
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
});
</script>
