/**
 * Gamut LUT Preview — Bulk Upload JS
 *
 * Handles multi-file .cube upload on the LUT Collection edit screen.
 * Opens wp.media with multiple: true, then sends selected attachments
 * to an AJAX handler that creates gamut_lut_design posts in batch.
 *
 * @package Gamut_LUT_Preview
 */
(function($) {
    'use strict';

    var frame = null;
    var selectedFiles = [];

    /**
     * Open the WordPress media uploader with multi-select.
     */
    function openBulkUploader(e) {
        e.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Select .cube Files',
            button: { text: 'Add Selected Files' },
            library: { type: 'text/plain' },
            multiple: true
        });

        frame.on('select', function() {
            var selection = frame.state().get('selection');
            selectedFiles = [];

            selection.each(function(attachment) {
                var data = attachment.toJSON();
                // Only include files that look like .cube files.
                if (data.filename && data.filename.match(/\.cube$/i)) {
                    selectedFiles.push({
                        id: data.id,
                        url: data.url,
                        filename: data.filename
                    });
                }
            });

            if (selectedFiles.length === 0) {
                alert('No .cube files were selected. Please select files with the .cube extension.');
                return;
            }

            showFileList();
        });

        frame.open();
    }

    /**
     * Show the list of selected files before creation.
     */
    function showFileList() {
        var $list = $('#gamut-bulk-files');
        $list.empty();

        selectedFiles.forEach(function(file) {
            $list.append('<li>' + escapeHtml(file.filename) + '</li>');
        });

        $('#gamut-bulk-file-list').show();
        $('#gamut-bulk-results').hide();
    }

    /**
     * Send the selected files to the AJAX handler for batch creation.
     */
    function createLutDesigns(e) {
        e.preventDefault();

        var $container = $('#gamut-bulk-upload');
        var termId = $container.data('term-id');

        if (!termId || selectedFiles.length === 0) return;

        // Show spinner, disable buttons.
        $('#gamut-bulk-spinner').addClass('is-active');
        $('#gamut-bulk-create-btn').prop('disabled', true);
        $('#gamut-bulk-cancel-btn').prop('disabled', true);
        $('#gamut-bulk-upload-btn').prop('disabled', true);

        $.ajax({
            url: gamutBulkUpload.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gamut_bulk_create_luts',
                nonce: gamutBulkUpload.nonce,
                term_id: termId,
                attachments: selectedFiles
            },
            success: function(response) {
                $('#gamut-bulk-spinner').removeClass('is-active');
                $('#gamut-bulk-create-btn').prop('disabled', false);
                $('#gamut-bulk-cancel-btn').prop('disabled', false);
                $('#gamut-bulk-upload-btn').prop('disabled', false);

                if (response.success) {
                    showResults(response.data);
                } else {
                    showError(response.data.message || 'Unknown error occurred.');
                }
            },
            error: function() {
                $('#gamut-bulk-spinner').removeClass('is-active');
                $('#gamut-bulk-create-btn').prop('disabled', false);
                $('#gamut-bulk-cancel-btn').prop('disabled', false);
                $('#gamut-bulk-upload-btn').prop('disabled', false);
                showError('Network error. Please try again.');
            }
        });
    }

    /**
     * Show creation results.
     */
    function showResults(data) {
        var $results = $('#gamut-bulk-results');
        var html = '';

        if (data.created && data.created.length > 0) {
            html += '<div class="gamut-bulk-success">';
            html += '<strong>' + data.created.length + ' LUT design(s) created:</strong>';
            html += '<ul style="margin: 8px 0; list-style: disc; padding-left: 20px;">';
            data.created.forEach(function(item) {
                html += '<li><a href="' + escapeHtml(item.edit) + '" target="_blank">' + escapeHtml(item.title) + '</a></li>';
            });
            html += '</ul></div>';
        }

        if (data.errors && data.errors.length > 0) {
            html += '<div class="gamut-bulk-errors">';
            html += '<strong>Errors:</strong>';
            html += '<ul style="margin: 8px 0; list-style: disc; padding-left: 20px;">';
            data.errors.forEach(function(err) {
                html += '<li>' + escapeHtml(err) + '</li>';
            });
            html += '</ul></div>';
        }

        $results.html(html).show();
        $('#gamut-bulk-file-list').hide();
        selectedFiles = [];
    }

    /**
     * Show an error message.
     */
    function showError(message) {
        var $results = $('#gamut-bulk-results');
        $results.html('<div class="gamut-bulk-errors"><strong>Error:</strong> ' + escapeHtml(message) + '</div>').show();
    }

    /**
     * Cancel the current selection.
     */
    function cancelSelection(e) {
        e.preventDefault();
        selectedFiles = [];
        $('#gamut-bulk-file-list').hide();
        $('#gamut-bulk-results').hide();
    }

    /**
     * Escape HTML entities.
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    $(document).ready(function() {
        $('#gamut-bulk-upload-btn').on('click', openBulkUploader);
        $('#gamut-bulk-create-btn').on('click', createLutDesigns);
        $('#gamut-bulk-cancel-btn').on('click', cancelSelection);
    });

})(jQuery);
