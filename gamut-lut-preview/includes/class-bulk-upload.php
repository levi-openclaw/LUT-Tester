<?php
/**
 * Collection Manager — inline LUT management + bulk upload.
 *
 * On the Edit Collection screen, renders:
 *   - A live table of all LUTs in this collection (inline rename, delete, sort)
 *   - Bulk .cube file upload that adds rows to the table instantly
 *
 * Also handles bulk sample image upload on the Sample Images list screen.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Bulk_Upload {

    public function __construct() {
        // Collection manager on Edit Collection screen.
        add_action( 'gamut_lut_collection_edit_form_fields', array( $this, 'render_collection_manager' ), 20 );

        // Bulk sample image upload on Sample Images list screen.
        add_action( 'admin_notices', array( $this, 'render_bulk_images_ui' ) );

        // Enqueue admin assets.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_gamut_bulk_create_luts', array( $this, 'handle_bulk_create' ) );
        add_action( 'wp_ajax_gamut_rename_lut', array( $this, 'handle_rename_lut' ) );
        add_action( 'wp_ajax_gamut_delete_lut', array( $this, 'handle_delete_lut' ) );
        add_action( 'wp_ajax_gamut_reorder_luts', array( $this, 'handle_reorder_luts' ) );
        add_action( 'wp_ajax_gamut_bulk_create_images', array( $this, 'handle_bulk_create_images' ) );
    }

    // =========================================================================
    // Collection Manager (Edit Collection Screen)
    // =========================================================================

    /**
     * Render the full collection manager on the Edit Collection form.
     *
     * @param WP_Term $term Current taxonomy term.
     */
    public function render_collection_manager( $term ) {
        $luts = $this->get_collection_luts( $term->term_id );
        ?>
        <tr class="form-field gamut-manager-row">
            <th scope="row">
                <label><?php esc_html_e( 'LUTs in Collection', 'gamut-lut-preview' ); ?></label>
            </th>
            <td>
                <div id="gamut-collection-manager"
                     data-term-id="<?php echo esc_attr( $term->term_id ); ?>"
                     data-term-name="<?php echo esc_attr( $term->name ); ?>">

                    <!-- LUT Table -->
                    <table class="gamut-lut-table widefat" id="gamut-lut-table">
                        <thead>
                            <tr>
                                <th class="gamut-lut-table__drag" width="30"></th>
                                <th class="gamut-lut-table__title"><?php esc_html_e( 'LUT Name', 'gamut-lut-preview' ); ?></th>
                                <th class="gamut-lut-table__grid" width="80"><?php esc_html_e( 'Grid', 'gamut-lut-preview' ); ?></th>
                                <th class="gamut-lut-table__size" width="80"><?php esc_html_e( 'Size', 'gamut-lut-preview' ); ?></th>
                                <th class="gamut-lut-table__actions" width="80"></th>
                            </tr>
                        </thead>
                        <tbody id="gamut-lut-tbody">
                            <?php if ( empty( $luts ) ) : ?>
                                <tr class="gamut-lut-table__empty" id="gamut-lut-empty-row">
                                    <td colspan="5"><?php esc_html_e( 'No LUTs yet. Upload .cube files below.', 'gamut-lut-preview' ); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $luts as $lut ) : ?>
                                    <tr class="gamut-lut-table__row" data-post-id="<?php echo esc_attr( $lut['id'] ); ?>">
                                        <td class="gamut-lut-table__drag"><span class="dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'gamut-lut-preview' ); ?>"></span></td>
                                        <td class="gamut-lut-table__title">
                                            <span class="gamut-lut-table__name"><?php echo esc_html( $lut['title'] ); ?></span>
                                            <input type="text" class="gamut-lut-table__input" value="<?php echo esc_attr( $lut['title'] ); ?>" style="display:none;">
                                            <div class="row-actions">
                                                <span class="inline"><a href="#" class="gamut-rename-btn"><?php esc_html_e( 'Rename', 'gamut-lut-preview' ); ?></a> | </span>
                                                <span class="delete"><a href="#" class="gamut-delete-btn"><?php esc_html_e( 'Delete', 'gamut-lut-preview' ); ?></a></span>
                                            </div>
                                        </td>
                                        <td class="gamut-lut-table__grid"><?php echo $lut['lut_size'] ? esc_html( $lut['lut_size'] . '³' ) : '—'; ?></td>
                                        <td class="gamut-lut-table__size"><?php echo $lut['file_size'] ? esc_html( size_format( $lut['file_size'] ) ) : '—'; ?></td>
                                        <td class="gamut-lut-table__actions">
                                            <button type="button" class="button-link gamut-save-rename-btn" style="display:none;"><?php esc_html_e( 'Save', 'gamut-lut-preview' ); ?></button>
                                            <button type="button" class="button-link gamut-cancel-rename-btn" style="display:none;"><?php esc_html_e( 'Cancel', 'gamut-lut-preview' ); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Bulk Upload -->
                    <div class="gamut-lut-table__footer">
                        <button type="button" class="button" id="gamut-bulk-upload-btn">
                            <span class="dashicons dashicons-upload" style="vertical-align: text-bottom;"></span>
                            <?php esc_html_e( 'Add .cube Files', 'gamut-lut-preview' ); ?>
                        </button>
                        <span id="gamut-bulk-spinner" class="spinner" style="float: none;"></span>
                        <span id="gamut-bulk-status"></span>
                    </div>

                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Get all LUTs for a collection, ordered by menu_order then title.
     *
     * @param int $term_id Collection term ID.
     * @return array
     */
    private function get_collection_luts( $term_id ) {
        $query = new WP_Query( array(
            'post_type'      => 'gamut_lut_design',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
            'tax_query'      => array(
                array(
                    'taxonomy' => 'gamut_lut_collection',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            ),
        ) );

        $luts = array();
        foreach ( $query->posts as $post ) {
            $luts[] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'lut_size'  => get_post_meta( $post->ID, '_gamut_lut_size', true ),
                'file_size' => get_post_meta( $post->ID, '_gamut_cube_file_size', true ),
            );
        }

        return $luts;
    }

    // =========================================================================
    // Bulk Sample Image Upload
    // =========================================================================

    /**
     * Render bulk image upload UI at the top of the Sample Images list screen.
     */
    public function render_bulk_images_ui() {
        $screen = get_current_screen();
        if ( ! $screen || 'edit-gamut_sample_image' !== $screen->id ) {
            return;
        }

        $categories = get_terms( array(
            'taxonomy'   => 'gamut_image_category',
            'hide_empty' => false,
        ) );
        ?>
        <div id="gamut-bulk-images" class="gamut-bulk-images-wrap">
            <h3><?php esc_html_e( 'Bulk Add Sample Images', 'gamut-lut-preview' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Select multiple photos from the media library. Each becomes a Sample Image post with the photo as its featured image.', 'gamut-lut-preview' ); ?></p>
            <div class="gamut-bulk-images-controls">
                <?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
                    <select id="gamut-bulk-image-category" class="postform">
                        <option value=""><?php esc_html_e( '— No Category —', 'gamut-lut-preview' ); ?></option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="button" class="button" id="gamut-bulk-images-btn">
                    <span class="dashicons dashicons-format-gallery" style="vertical-align: text-bottom;"></span>
                    <?php esc_html_e( 'Select Images', 'gamut-lut-preview' ); ?>
                </button>
                <span id="gamut-bulk-images-spinner" class="spinner" style="float: none;"></span>
                <span id="gamut-bulk-images-status"></span>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Enqueue Assets
    // =========================================================================

    /**
     * Enqueue admin assets on relevant screens.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $load = false;

        // Collection edit screen.
        if ( ( 'term.php' === $hook_suffix || 'edit-tags.php' === $hook_suffix ) && 'gamut_lut_collection' === $screen->taxonomy ) {
            $load = true;
        }

        // Sample Images list screen.
        if ( 'edit-gamut_sample_image' === $screen->id ) {
            $load = true;
        }

        if ( ! $load ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'gamut-lut-bulk-upload',
            GAMUT_LUT_URL . 'assets/admin/bulk-upload.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            GAMUT_LUT_VERSION,
            true
        );

        wp_localize_script( 'gamut-lut-bulk-upload', 'gamutAdmin', array(
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'gamut_admin_nonce' ),
            'confirmDelete'    => __( 'Delete this LUT? This cannot be undone.', 'gamut-lut-preview' ),
            'saving'           => __( 'Saving...', 'gamut-lut-preview' ),
            'saved'            => __( 'Saved', 'gamut-lut-preview' ),
            'uploading'        => __( 'Creating LUTs...', 'gamut-lut-preview' ),
            'uploadingImages'  => __( 'Creating images...', 'gamut-lut-preview' ),
        ) );

        wp_enqueue_style(
            'gamut-lut-admin',
            GAMUT_LUT_URL . 'assets/admin/admin.css',
            array(),
            GAMUT_LUT_VERSION
        );
    }

    // =========================================================================
    // AJAX: Bulk Create LUTs
    // =========================================================================

    public function handle_bulk_create() {
        check_ajax_referer( 'gamut_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gamut-lut-preview' ) ) );
        }

        $term_id     = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
        $attachments = isset( $_POST['attachments'] ) ? $_POST['attachments'] : array();

        if ( ! $term_id || empty( $attachments ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing data.', 'gamut-lut-preview' ) ) );
        }

        $term = get_term( $term_id, 'gamut_lut_collection' );
        if ( ! $term || is_wp_error( $term ) ) {
            wp_send_json_error( array( 'message' => __( 'Collection not found.', 'gamut-lut-preview' ) ) );
        }

        // Get current max menu_order for this collection.
        $existing = new WP_Query( array(
            'post_type'      => 'gamut_lut_design',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'menu_order',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'gamut_lut_collection',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            ),
        ) );
        $next_order = $existing->found_posts;

        $created = array();

        foreach ( $attachments as $att ) {
            $att_id   = isset( $att['id'] ) ? absint( $att['id'] ) : 0;
            $att_url  = isset( $att['url'] ) ? esc_url_raw( $att['url'] ) : '';
            $filename = isset( $att['filename'] ) ? sanitize_text_field( $att['filename'] ) : '';

            if ( ! $att_id || ! $att_url ) {
                continue;
            }

            $title = $this->filename_to_title( $filename );

            $post_id = wp_insert_post( array(
                'post_type'   => 'gamut_lut_design',
                'post_title'  => $title,
                'post_status' => 'publish',
                'menu_order'  => $next_order,
            ), true );

            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            $next_order++;

            update_post_meta( $post_id, '_gamut_cube_file_url', $att_url );
            update_post_meta( $post_id, '_gamut_cube_file_id', $att_id );

            $lut_size  = null;
            $file_size = null;
            $file_path = get_attached_file( $att_id );
            if ( $file_path && file_exists( $file_path ) ) {
                $file_size = filesize( $file_path );
                update_post_meta( $post_id, '_gamut_cube_file_size', $file_size );
                $lut_size = $this->parse_lut_size( $file_path );
                if ( $lut_size ) {
                    update_post_meta( $post_id, '_gamut_lut_size', $lut_size );
                }
            }

            wp_set_object_terms( $post_id, $term_id, 'gamut_lut_collection' );

            $created[] = array(
                'id'        => $post_id,
                'title'     => $title,
                'lut_size'  => $lut_size,
                'file_size' => $file_size ? size_format( $file_size ) : '—',
            );
        }

        wp_send_json_success( array( 'created' => $created ) );
    }

    // =========================================================================
    // AJAX: Rename LUT
    // =========================================================================

    public function handle_rename_lut() {
        check_ajax_referer( 'gamut_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gamut-lut-preview' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $title   = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';

        if ( ! $post_id || ! $title ) {
            wp_send_json_error( array( 'message' => __( 'Missing data.', 'gamut-lut-preview' ) ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || 'gamut_lut_design' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Post not found.', 'gamut-lut-preview' ) ) );
        }

        wp_update_post( array(
            'ID'         => $post_id,
            'post_title' => $title,
        ) );

        wp_send_json_success( array( 'title' => $title ) );
    }

    // =========================================================================
    // AJAX: Delete LUT
    // =========================================================================

    public function handle_delete_lut() {
        check_ajax_referer( 'gamut_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gamut-lut-preview' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing post ID.', 'gamut-lut-preview' ) ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || 'gamut_lut_design' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Post not found.', 'gamut-lut-preview' ) ) );
        }

        wp_delete_post( $post_id, true );

        wp_send_json_success();
    }

    // =========================================================================
    // AJAX: Reorder LUTs
    // =========================================================================

    public function handle_reorder_luts() {
        check_ajax_referer( 'gamut_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gamut-lut-preview' ) ) );
        }

        $order = isset( $_POST['order'] ) ? $_POST['order'] : array();

        if ( empty( $order ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing order data.', 'gamut-lut-preview' ) ) );
        }

        foreach ( $order as $index => $post_id ) {
            wp_update_post( array(
                'ID'         => absint( $post_id ),
                'menu_order' => absint( $index ),
            ) );
        }

        wp_send_json_success();
    }

    // =========================================================================
    // AJAX: Bulk Create Sample Images
    // =========================================================================

    public function handle_bulk_create_images() {
        check_ajax_referer( 'gamut_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gamut-lut-preview' ) ) );
        }

        $images      = isset( $_POST['images'] ) ? $_POST['images'] : array();
        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

        if ( empty( $images ) ) {
            wp_send_json_error( array( 'message' => __( 'No images selected.', 'gamut-lut-preview' ) ) );
        }

        $created = 0;

        foreach ( $images as $img ) {
            $att_id = isset( $img['id'] ) ? absint( $img['id'] ) : 0;
            $title  = isset( $img['title'] ) ? sanitize_text_field( $img['title'] ) : '';

            if ( ! $att_id ) {
                continue;
            }

            if ( ! $title ) {
                $title = get_the_title( $att_id );
            }
            if ( ! $title ) {
                $title = __( 'Sample Image', 'gamut-lut-preview' );
            }

            $post_id = wp_insert_post( array(
                'post_type'   => 'gamut_sample_image',
                'post_title'  => $title,
                'post_status' => 'publish',
            ), true );

            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            set_post_thumbnail( $post_id, $att_id );

            if ( $category_id ) {
                wp_set_object_terms( $post_id, $category_id, 'gamut_image_category' );
            }

            $created++;
        }

        wp_send_json_success( array(
            'created' => $created,
            'message' => sprintf( __( '%d sample image(s) created.', 'gamut-lut-preview' ), $created ),
        ) );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function filename_to_title( $filename ) {
        $name = preg_replace( '/\.cube$/i', '', $filename );
        $name = str_replace( array( '-', '_' ), ' ', $name );
        $name = preg_replace( '/\s+/', ' ', trim( $name ) );
        $name = ucwords( strtolower( $name ) );
        return $name;
    }

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
