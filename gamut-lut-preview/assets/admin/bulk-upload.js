/**
 * Gamut LUT Preview — Admin Collection Manager + Bulk Upload
 *
 * Handles:
 *  - Inline LUT table with rename, delete, drag-to-reorder
 *  - Bulk .cube upload that inserts rows into the table instantly
 *  - Bulk sample image upload on the Sample Images list screen
 *
 * @package Gamut_LUT_Preview
 */
(function($) {
    'use strict';

    // =========================================================================
    // Collection Manager — LUT Table
    // =========================================================================

    var cubeFrame = null;

    /**
     * Initialize the collection manager (called on Edit Collection screen).
     */
    function initCollectionManager() {
        var $manager = $('#gamut-collection-manager');
        if (!$manager.length) return;

        // Drag-to-reorder via jQuery UI Sortable.
        $('#gamut-lut-tbody').sortable({
            handle: '.gamut-lut-table__drag',
            axis: 'y',
            containment: '#gamut-lut-table',
            placeholder: 'gamut-lut-table__placeholder',
            items: '.gamut-lut-table__row',
            update: function() {
                saveOrder();
            }
        });

        // Bulk upload button.
        $manager.on('click', '#gamut-bulk-upload-btn', openCubeUploader);

        // Inline rename.
        $manager.on('click', '.gamut-rename-btn', startRename);
        $manager.on('click', '.gamut-save-rename-btn', saveRename);
        $manager.on('click', '.gamut-cancel-rename-btn', cancelRename);
        $manager.on('keydown', '.gamut-lut-table__input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('tr').find('.gamut-save-rename-btn').click();
            }
            if (e.key === 'Escape') {
                $(this).closest('tr').find('.gamut-cancel-rename-btn').click();
            }
        });

        // Delete.
        $manager.on('click', '.gamut-delete-btn', deleteLut);
    }

    /**
     * Open wp.media for multi-select .cube files, then create LUTs via AJAX.
     * No confirmation step — select files and they're created immediately.
     */
    function openCubeUploader(e) {
        e.preventDefault();

        if (cubeFrame) {
            cubeFrame.open();
            return;
        }

        cubeFrame = wp.media({
            title: 'Select .cube Files',
            button: { text: 'Upload & Create LUTs' },
            library: { type: 'text/plain' },
            multiple: true
        });

        cubeFrame.on('select', function() {
            var selection = cubeFrame.state().get('selection');
            var files = [];

            selection.each(function(attachment) {
                var data = attachment.toJSON();
                if (data.filename && data.filename.match(/\.cube$/i)) {
                    files.push({ id: data.id, url: data.url, filename: data.filename });
                }
            });

            if (files.length === 0) {
                alert('No .cube files found in selection.');
                return;
            }

            bulkCreateLuts(files);
        });

        cubeFrame.open();
    }

    /**
     * Send files to AJAX, add resulting rows to table instantly.
     */
    function bulkCreateLuts(files) {
        var $manager = $('#gamut-collection-manager');
        var termId = $manager.data('term-id');

        setStatus('#gamut-bulk-spinner', '#gamut-bulk-status', gamutAdmin.uploading, true);
        $('#gamut-bulk-upload-btn').prop('disabled', true);

        $.ajax({
            url: gamutAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gamut_bulk_create_luts',
                nonce: gamutAdmin.nonce,
                term_id: termId,
                attachments: files
            },
            success: function(response) {
                $('#gamut-bulk-upload-btn').prop('disabled', false);
                if (response.success && response.data.created) {
                    $('#gamut-lut-empty-row').remove();
                    response.data.created.forEach(function(lut) {
                        appendLutRow(lut);
                    });
                    setStatus('#gamut-bulk-spinner', '#gamut-bulk-status', response.data.created.length + ' LUT(s) added', false);
                    fadeStatus('#gamut-bulk-status');
                } else {
                    setStatus('#gamut-bulk-spinner', '#gamut-bulk-status', 'Error creating LUTs', false);
                }
            },
            error: function() {
                $('#gamut-bulk-upload-btn').prop('disabled', false);
                setStatus('#gamut-bulk-spinner', '#gamut-bulk-status', 'Network error', false);
            }
        });
    }

    /**
     * Append a new LUT row to the table.
     */
    function appendLutRow(lut) {
        var gridDisplay = lut.lut_size ? lut.lut_size + '\u00b3' : '\u2014';
        var sizeDisplay = lut.file_size || '\u2014';

        var html = '<tr class="gamut-lut-table__row" data-post-id="' + lut.id + '">' +
            '<td class="gamut-lut-table__drag"><span class="dashicons dashicons-menu"></span></td>' +
            '<td class="gamut-lut-table__title">' +
                '<span class="gamut-lut-table__name">' + escapeHtml(lut.title) + '</span>' +
                '<input type="text" class="gamut-lut-table__input" value="' + escapeHtml(lut.title) + '" style="display:none;">' +
                '<div class="row-actions">' +
                    '<span class="inline"><a href="#" class="gamut-rename-btn">Rename</a> | </span>' +
                    '<span class="delete"><a href="#" class="gamut-delete-btn">Delete</a></span>' +
                '</div>' +
            '</td>' +
            '<td class="gamut-lut-table__grid">' + escapeHtml(gridDisplay) + '</td>' +
            '<td class="gamut-lut-table__size">' + escapeHtml(sizeDisplay) + '</td>' +
            '<td class="gamut-lut-table__actions">' +
                '<button type="button" class="button-link gamut-save-rename-btn" style="display:none;">Save</button>' +
                '<button type="button" class="button-link gamut-cancel-rename-btn" style="display:none;">Cancel</button>' +
            '</td>' +
        '</tr>';

        $('#gamut-lut-tbody').append(html);
        $('#gamut-lut-tbody').sortable('refresh');
    }

    // ---- Inline Rename ----

    function startRename(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        $row.find('.gamut-lut-table__name').hide();
        $row.find('.row-actions').hide();
        $row.find('.gamut-lut-table__input').show().focus().select();
        $row.find('.gamut-save-rename-btn, .gamut-cancel-rename-btn').show();
    }

    function saveRename(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var postId = $row.data('post-id');
        var newTitle = $row.find('.gamut-lut-table__input').val().trim();

        if (!newTitle) {
            cancelRename.call(this, e);
            return;
        }

        $row.find('.gamut-save-rename-btn').text(gamutAdmin.saving);

        $.ajax({
            url: gamutAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gamut_rename_lut',
                nonce: gamutAdmin.nonce,
                post_id: postId,
                title: newTitle
            },
            success: function(response) {
                if (response.success) {
                    $row.find('.gamut-lut-table__name').text(response.data.title).show();
                    $row.find('.gamut-lut-table__input').val(response.data.title).hide();
                } else {
                    $row.find('.gamut-lut-table__name').show();
                    $row.find('.gamut-lut-table__input').hide();
                }
                $row.find('.row-actions').show();
                $row.find('.gamut-save-rename-btn').text('Save').hide();
                $row.find('.gamut-cancel-rename-btn').hide();
            }
        });
    }

    function cancelRename(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var currentTitle = $row.find('.gamut-lut-table__name').text();
        $row.find('.gamut-lut-table__input').val(currentTitle).hide();
        $row.find('.gamut-lut-table__name').show();
        $row.find('.row-actions').show();
        $row.find('.gamut-save-rename-btn, .gamut-cancel-rename-btn').hide();
    }

    // ---- Delete ----

    function deleteLut(e) {
        e.preventDefault();
        if (!confirm(gamutAdmin.confirmDelete)) return;

        var $row = $(this).closest('tr');
        var postId = $row.data('post-id');

        $row.css('opacity', '0.5');

        $.ajax({
            url: gamutAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gamut_delete_lut',
                nonce: gamutAdmin.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(200, function() {
                        $(this).remove();
                        if ($('#gamut-lut-tbody .gamut-lut-table__row').length === 0) {
                            $('#gamut-lut-tbody').append(
                                '<tr class="gamut-lut-table__empty" id="gamut-lut-empty-row">' +
                                '<td colspan="5">No LUTs yet. Upload .cube files below.</td></tr>'
                            );
                        }
                    });
                } else {
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                $row.css('opacity', '1');
            }
        });
    }

    // ---- Reorder (auto-saves on drop) ----

    function saveOrder() {
        var order = [];
        $('#gamut-lut-tbody .gamut-lut-table__row').each(function() {
            order.push($(this).data('post-id'));
        });

        $.ajax({
            url: gamutAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gamut_reorder_luts',
                nonce: gamutAdmin.nonce,
                order: order
            }
        });
    }

    // =========================================================================
    // Bulk Sample Image Upload
    // =========================================================================

    var imageFrame = null;

    function initBulkImages() {
        var $wrap = $('#gamut-bulk-images');
        if (!$wrap.length) return;

        $wrap.on('click', '#gamut-bulk-images-btn', openImageUploader);
    }

    /**
     * Open wp.media for multi-select images. Select → created immediately.
     */
    function openImageUploader(e) {
        e.preventDefault();

        if (imageFrame) {
            imageFrame.open();
            return;
        }

        imageFrame = wp.media({
            title: 'Select Sample Images',
            button: { text: 'Add as Sample Images' },
            library: { type: 'image' },
            multiple: true
        });

        imageFrame.on('select', function() {
            var selection = imageFrame.state().get('selection');
            var images = [];

            selection.each(function(attachment) {
                var data = attachment.toJSON();
                images.push({ id: data.id, title: data.title || data.filename });
            });

            if (images.length === 0) return;

            bulkCreateImages(images);
        });

        imageFrame.open();
    }

    function bulkCreateImages(images) {
        var categoryId = $('#gamut-bulk-image-category').val() || 0;

        setStatus('#gamut-bulk-images-spinner', '#gamut-bulk-images-status', gamutAdmin.uploadingImages, true);
        $('#gamut-bulk-images-btn').prop('disabled', true);

        $.ajax({
            url: gamutAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gamut_bulk_create_images',
                nonce: gamutAdmin.nonce,
                images: images,
                category_id: categoryId
            },
            success: function(response) {
                $('#gamut-bulk-images-btn').prop('disabled', false);
                if (response.success) {
                    setStatus('#gamut-bulk-images-spinner', '#gamut-bulk-images-status', response.data.message, false);
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    setStatus('#gamut-bulk-images-spinner', '#gamut-bulk-images-status', response.data.message || 'Error', false);
                }
            },
            error: function() {
                $('#gamut-bulk-images-btn').prop('disabled', false);
                setStatus('#gamut-bulk-images-spinner', '#gamut-bulk-images-status', 'Network error', false);
            }
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    function setStatus(spinnerSel, statusSel, text, spinning) {
        if (spinning) {
            $(spinnerSel).addClass('is-active');
        } else {
            $(spinnerSel).removeClass('is-active');
        }
        $(statusSel).text(text);
    }

    function fadeStatus(statusSel) {
        setTimeout(function() {
            $(statusSel).fadeOut(400, function() {
                $(this).text('').show();
            });
        }, 2500);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    // =========================================================================
    // Init
    // =========================================================================

    $(document).ready(function() {
        initCollectionManager();
        initBulkImages();
    });

})(jQuery);
