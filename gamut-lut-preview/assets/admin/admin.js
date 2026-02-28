/**
 * Gamut LUT Preview — Admin Meta Box JS
 *
 * Handles the .cube file upload via the WordPress media uploader.
 *
 * @package Gamut_LUT_Preview
 */
(function($) {
    'use strict';

    var frame = null;

    /**
     * Open the WordPress media uploader for .cube file selection.
     */
    function openMediaUploader(e) {
        e.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Select .cube File',
            button: { text: 'Use this file' },
            library: { type: 'text/plain' },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();

            $('#gamut_cube_file_url').val(attachment.url);
            $('#gamut_cube_file_id').val(attachment.id);

            // Update display.
            $('#gamut-cube-filename').text(attachment.filename);
            $('#gamut-cube-info').show();
            $('#gamut-cube-remove-btn').show();
            $('#gamut-cube-upload-btn').text('Replace .cube File');
        });

        frame.open();
    }

    /**
     * Remove the selected .cube file.
     */
    function removeCubeFile(e) {
        e.preventDefault();

        $('#gamut_cube_file_url').val('');
        $('#gamut_cube_file_id').val('');
        $('#gamut-cube-info').hide();
        $('#gamut-cube-remove-btn').hide();
        $('#gamut-cube-upload-btn').text('Upload .cube File');
    }

    $(document).ready(function() {
        $('#gamut-cube-upload-btn').on('click', openMediaUploader);
        $('#gamut-cube-remove-btn').on('click', removeCubeFile);
    });

})(jQuery);
