<?php
/**
 * Meta box for .cube file upload on LUT Design posts.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Meta_Boxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_gamut_lut_design', array( $this, 'save_cube_file' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Register meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'gamut_cube_file',
            __( 'LUT Cube File', 'gamut-lut-preview' ),
            array( $this, 'render_cube_meta_box' ),
            'gamut_lut_design',
            'normal',
            'high'
        );
    }

    /**
     * Render the .cube file upload meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_cube_meta_box( $post ) {
        wp_nonce_field( 'gamut_save_cube_file', 'gamut_cube_nonce' );

        $file_url  = get_post_meta( $post->ID, '_gamut_cube_file_url', true );
        $file_id   = get_post_meta( $post->ID, '_gamut_cube_file_id', true );
        $file_size = get_post_meta( $post->ID, '_gamut_cube_file_size', true );
        $lut_size  = get_post_meta( $post->ID, '_gamut_lut_size', true );
        ?>
        <div id="gamut-cube-upload">
            <input type="hidden" name="gamut_cube_file_url" id="gamut_cube_file_url" value="<?php echo esc_attr( $file_url ); ?>">
            <input type="hidden" name="gamut_cube_file_id" id="gamut_cube_file_id" value="<?php echo esc_attr( $file_id ); ?>">

            <div id="gamut-cube-info" style="<?php echo $file_url ? '' : 'display:none;'; ?>">
                <p>
                    <strong><?php esc_html_e( 'Current file:', 'gamut-lut-preview' ); ?></strong>
                    <span id="gamut-cube-filename"><?php echo $file_url ? esc_html( basename( $file_url ) ) : ''; ?></span>
                </p>
                <?php if ( $file_size ) : ?>
                    <p>
                        <strong><?php esc_html_e( 'File size:', 'gamut-lut-preview' ); ?></strong>
                        <?php echo esc_html( size_format( $file_size ) ); ?>
                    </p>
                <?php endif; ?>
                <?php if ( $lut_size ) : ?>
                    <p>
                        <strong><?php esc_html_e( 'LUT grid size:', 'gamut-lut-preview' ); ?></strong>
                        <?php echo esc_html( $lut_size . '×' . $lut_size . '×' . $lut_size ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <p>
                <button type="button" class="button" id="gamut-cube-upload-btn">
                    <?php echo $file_url ? esc_html__( 'Replace .cube File', 'gamut-lut-preview' ) : esc_html__( 'Upload .cube File', 'gamut-lut-preview' ); ?>
                </button>
                <button type="button" class="button" id="gamut-cube-remove-btn" style="<?php echo $file_url ? '' : 'display:none;'; ?>">
                    <?php esc_html_e( 'Remove', 'gamut-lut-preview' ); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Save cube file meta data on post save.
     *
     * @param int $post_id Post ID.
     */
    public function save_cube_file( $post_id ) {
        if ( ! isset( $_POST['gamut_cube_nonce'] ) || ! wp_verify_nonce( $_POST['gamut_cube_nonce'], 'gamut_save_cube_file' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $file_url = isset( $_POST['gamut_cube_file_url'] ) ? esc_url_raw( $_POST['gamut_cube_file_url'] ) : '';
        $file_id  = isset( $_POST['gamut_cube_file_id'] ) ? absint( $_POST['gamut_cube_file_id'] ) : 0;

        if ( $file_url ) {
            update_post_meta( $post_id, '_gamut_cube_file_url', $file_url );
            update_post_meta( $post_id, '_gamut_cube_file_id', $file_id );

            // Auto-parse file size and LUT grid size from the .cube file.
            if ( $file_id ) {
                $file_path = get_attached_file( $file_id );
                if ( $file_path && file_exists( $file_path ) ) {
                    update_post_meta( $post_id, '_gamut_cube_file_size', filesize( $file_path ) );
                    $lut_size = $this->parse_lut_size( $file_path );
                    if ( $lut_size ) {
                        update_post_meta( $post_id, '_gamut_lut_size', $lut_size );
                    }
                }
            }
        } else {
            delete_post_meta( $post_id, '_gamut_cube_file_url' );
            delete_post_meta( $post_id, '_gamut_cube_file_id' );
            delete_post_meta( $post_id, '_gamut_cube_file_size' );
            delete_post_meta( $post_id, '_gamut_lut_size' );
        }
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

    /**
     * Enqueue admin scripts for the meta box media uploader.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'gamut_lut_design' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'gamut-lut-admin',
            GAMUT_LUT_URL . 'assets/admin/admin.js',
            array( 'jquery' ),
            GAMUT_LUT_VERSION,
            true
        );

        wp_enqueue_style(
            'gamut-lut-admin',
            GAMUT_LUT_URL . 'assets/admin/admin.css',
            array(),
            GAMUT_LUT_VERSION
        );
    }
}
