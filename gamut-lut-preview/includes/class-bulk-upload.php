<?php
/**
 * Bulk .cube file upload for LUT Collections.
 *
 * Adds a bulk upload section to the Edit Collection term screen.
 * Allows selecting multiple .cube files at once, which creates
 * gamut_lut_design posts automatically assigned to the collection.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Bulk_Upload {

    public function __construct() {
        // Add bulk upload UI to the Edit Collection form.
        add_action( 'gamut_lut_collection_edit_form_fields', array( $this, 'render_bulk_upload_field' ), 20 );

        // Enqueue admin assets on taxonomy screens.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX handler for batch LUT creation.
        add_action( 'wp_ajax_gamut_bulk_create_luts', array( $this, 'handle_bulk_create' ) );
    }

    /**
     * Render the bulk upload section on the Edit Collection form.
     *
     * @param WP_Term $term Current taxonomy term object.
     */
    public function render_bulk_upload_field( $term ) {
        // Count existing LUTs in this collection.
        $existing = new WP_Query( array(
            'post_type'      => 'gamut_lut_design',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'gamut_lut_collection',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ),
            ),
        ) );
        $existing_count = $existing->found_posts;
        ?>
        <tr class="form-field">
            <th scope="row">
                <label><?php esc_html_e( 'Bulk Upload LUTs', 'gamut-lut-preview' ); ?></label>
            </th>
            <td>
                <div id="gamut-bulk-upload" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" data-term-name="<?php echo esc_attr( $term->name ); ?>">

                    <p class="description" style="margin-bottom: 10px;">
                        <?php
                        printf(
                            esc_html__( 'This collection currently has %d LUT(s). Select multiple .cube files to add them all at once.', 'gamut-lut-preview' ),
                            $existing_count
                        );
                        ?>
                    </p>

                    <button type="button" class="button" id="gamut-bulk-upload-btn">
                        <?php esc_html_e( 'Select .cube Files', 'gamut-lut-preview' ); ?>
                    </button>

                    <!-- File list preview before submission -->
                    <div id="gamut-bulk-file-list" style="display: none; margin-top: 12px;">
                        <h4 style="margin: 0 0 8px;"><?php esc_html_e( 'Selected Files:', 'gamut-lut-preview' ); ?></h4>
                        <ul id="gamut-bulk-files" style="margin: 0; list-style: disc; padding-left: 20px;"></ul>
                        <p style="margin-top: 12px;">
                            <button type="button" class="button button-primary" id="gamut-bulk-create-btn">
                                <?php esc_html_e( 'Create LUT Designs', 'gamut-lut-preview' ); ?>
                            </button>
                            <button type="button" class="button" id="gamut-bulk-cancel-btn">
                                <?php esc_html_e( 'Cancel', 'gamut-lut-preview' ); ?>
                            </button>
                            <span id="gamut-bulk-spinner" class="spinner" style="float: none;"></span>
                        </p>
                    </div>

                    <!-- Results after creation -->
                    <div id="gamut-bulk-results" style="display: none; margin-top: 12px;"></div>

                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Enqueue admin assets on the taxonomy edit screen.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Load on taxonomy term edit screen.
        if ( 'term.php' !== $hook_suffix && 'edit-tags.php' !== $hook_suffix ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'gamut_lut_collection' !== $screen->taxonomy ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'gamut-lut-bulk-upload',
            GAMUT_LUT_URL . 'assets/admin/bulk-upload.js',
            array( 'jquery' ),
            GAMUT_LUT_VERSION,
            true
        );

        wp_localize_script( 'gamut-lut-bulk-upload', 'gamutBulkUpload', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gamut_bulk_create_luts' ),
        ) );

        wp_enqueue_style(
            'gamut-lut-admin',
            GAMUT_LUT_URL . 'assets/admin/admin.css',
            array(),
            GAMUT_LUT_VERSION
        );
    }

    /**
     * AJAX handler: create multiple gamut_lut_design posts from uploaded .cube files.
     *
     * Expects POST data:
     *   - attachments[]: array of { id, url, filename }
     *   - term_id: gamut_lut_collection term ID
     */
    public function handle_bulk_create() {
        check_ajax_referer( 'gamut_bulk_create_luts', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gamut-lut-preview' ) ) );
        }

        $term_id     = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
        $attachments = isset( $_POST['attachments'] ) ? $_POST['attachments'] : array();

        if ( ! $term_id || empty( $attachments ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing term ID or attachments.', 'gamut-lut-preview' ) ) );
        }

        // Verify the term exists.
        $term = get_term( $term_id, 'gamut_lut_collection' );
        if ( ! $term || is_wp_error( $term ) ) {
            wp_send_json_error( array( 'message' => __( 'Collection not found.', 'gamut-lut-preview' ) ) );
        }

        $created = array();
        $errors  = array();

        foreach ( $attachments as $att ) {
            $att_id  = isset( $att['id'] ) ? absint( $att['id'] ) : 0;
            $att_url = isset( $att['url'] ) ? esc_url_raw( $att['url'] ) : '';
            $filename = isset( $att['filename'] ) ? sanitize_text_field( $att['filename'] ) : '';

            if ( ! $att_id || ! $att_url ) {
                $errors[] = sprintf( __( 'Skipped invalid attachment: %s', 'gamut-lut-preview' ), $filename );
                continue;
            }

            // Generate a clean title from the filename.
            $title = $this->filename_to_title( $filename, $term->name );

            // Create the gamut_lut_design post.
            $post_id = wp_insert_post( array(
                'post_type'   => 'gamut_lut_design',
                'post_title'  => $title,
                'post_status' => 'publish',
            ), true );

            if ( is_wp_error( $post_id ) ) {
                $errors[] = sprintf( __( 'Failed to create "%s": %s', 'gamut-lut-preview' ), $title, $post_id->get_error_message() );
                continue;
            }

            // Set post meta.
            update_post_meta( $post_id, '_gamut_cube_file_url', $att_url );
            update_post_meta( $post_id, '_gamut_cube_file_id', $att_id );

            // Parse file size and LUT grid size.
            $file_path = get_attached_file( $att_id );
            if ( $file_path && file_exists( $file_path ) ) {
                update_post_meta( $post_id, '_gamut_cube_file_size', filesize( $file_path ) );
                $lut_size = $this->parse_lut_size( $file_path );
                if ( $lut_size ) {
                    update_post_meta( $post_id, '_gamut_lut_size', $lut_size );
                }
            }

            // Assign to the collection taxonomy.
            wp_set_object_terms( $post_id, $term_id, 'gamut_lut_collection' );

            $created[] = array(
                'id'    => $post_id,
                'title' => $title,
                'edit'  => get_edit_post_link( $post_id, 'raw' ),
            );
        }

        wp_send_json_success( array(
            'created' => $created,
            'errors'  => $errors,
        ) );
    }

    /**
     * Convert a .cube filename into a clean post title.
     *
     * Examples:
     *   "dolce-01.cube" → "Dolce 01"
     *   "apricity_warm_03.cube" → "Apricity Warm 03"
     *   "EMBER-GOLDEN.cube" → "Ember Golden"
     *
     * If the filename starts with the collection name, keep it.
     * Otherwise, prepend the collection name.
     *
     * @param string $filename The .cube filename.
     * @param string $collection_name The collection term name.
     * @return string Clean title.
     */
    private function filename_to_title( $filename, $collection_name ) {
        // Remove .cube extension.
        $name = preg_replace( '/\.cube$/i', '', $filename );

        // Replace hyphens and underscores with spaces.
        $name = str_replace( array( '-', '_' ), ' ', $name );

        // Collapse multiple spaces.
        $name = preg_replace( '/\s+/', ' ', trim( $name ) );

        // Title case.
        $name = ucwords( strtolower( $name ) );

        return $name;
    }

    /**
     * Parse LUT_3D_SIZE from a .cube file header.
     *
     * @param string $file_path Absolute path to the .cube file.
     * @return int|false LUT grid size or false if not found.
     */
    private function parse_lut_size( $file_path ) {
        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return false;
        }

        $lines_read = 0;
        $lut_size   = false;

        while ( ( $line = fgets( $handle ) ) !== false && $lines_read < 50 ) {
            $line = trim( $line );
            if ( preg_match( '/^LUT_3D_SIZE\s+(\d+)/i', $line, $matches ) ) {
                $lut_size = absint( $matches[1] );
                break;
            }
            $lines_read++;
        }

        fclose( $handle );
        return $lut_size;
    }
}
