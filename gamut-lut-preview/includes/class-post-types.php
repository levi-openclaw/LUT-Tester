<?php
/**
 * Custom Post Type registration and unified admin menu for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Post_Types {

    /**
     * Top-level menu slug shared across all plugin pages.
     */
    const MENU_SLUG = 'gamut-lut-tester';

    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_filter( 'parent_file', array( $this, 'fix_parent_file' ) );
        add_filter( 'submenu_file', array( $this, 'fix_submenu_file' ), 10, 2 );
        add_action( 'in_admin_header', array( $this, 'render_tab_bar' ) );
    }

    /**
     * Register custom post types.
     */
    public function register() {
        $this->register_sample_image();
        $this->register_lut_design();
    }

    /**
     * Register the gamut_sample_image post type.
     */
    private function register_sample_image() {
        $labels = array(
            'name'               => __( 'Images', 'gamut-lut-preview' ),
            'singular_name'      => __( 'Image', 'gamut-lut-preview' ),
            'add_new'            => __( 'Add New', 'gamut-lut-preview' ),
            'add_new_item'       => __( 'Add New Image', 'gamut-lut-preview' ),
            'edit_item'          => __( 'Edit Image', 'gamut-lut-preview' ),
            'new_item'           => __( 'New Image', 'gamut-lut-preview' ),
            'view_item'          => __( 'View Image', 'gamut-lut-preview' ),
            'search_items'       => __( 'Search Images', 'gamut-lut-preview' ),
            'not_found'          => __( 'No images found', 'gamut-lut-preview' ),
            'not_found_in_trash' => __( 'No images found in Trash', 'gamut-lut-preview' ),
            'menu_name'          => __( 'Images', 'gamut-lut-preview' ),
        );

        register_post_type( 'gamut_sample_image', array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'supports'            => array( 'title', 'thumbnail' ),
            'show_in_rest'        => false,
        ) );
    }

    /**
     * Register the gamut_lut_design post type (labelled "Looks").
     */
    private function register_lut_design() {
        $labels = array(
            'name'               => __( 'Looks', 'gamut-lut-preview' ),
            'singular_name'      => __( 'Look', 'gamut-lut-preview' ),
            'add_new'            => __( 'Add New', 'gamut-lut-preview' ),
            'add_new_item'       => __( 'Add New Look', 'gamut-lut-preview' ),
            'edit_item'          => __( 'Edit Look', 'gamut-lut-preview' ),
            'new_item'           => __( 'New Look', 'gamut-lut-preview' ),
            'view_item'          => __( 'View Look', 'gamut-lut-preview' ),
            'search_items'       => __( 'Search Looks', 'gamut-lut-preview' ),
            'not_found'          => __( 'No looks found', 'gamut-lut-preview' ),
            'not_found_in_trash' => __( 'No looks found in Trash', 'gamut-lut-preview' ),
            'menu_name'          => __( 'Looks', 'gamut-lut-preview' ),
        );

        register_post_type( 'gamut_lut_design', array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'supports'            => array( 'title' ),
            'show_in_rest'        => false,
        ) );
    }

    // =========================================================================
    // Unified Admin Menu
    // =========================================================================

    /**
     * Register the top-level "LUT Tester" menu and all submenu items.
     */
    public function register_admin_menu() {
        // Top-level menu — renders the Getting Started dashboard.
        add_menu_page(
            __( 'LUT Tester', 'gamut-lut-preview' ),
            __( 'LUT Tester', 'gamut-lut-preview' ),
            'edit_posts',
            self::MENU_SLUG,
            array( $this, 'render_dashboard' ),
            'dashicons-art',
            25
        );

        // Rename the auto-generated first submenu to "Getting Started".
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Getting Started', 'gamut-lut-preview' ),
            __( 'Getting Started', 'gamut-lut-preview' ),
            'edit_posts',
            self::MENU_SLUG
        );

        // Images.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Images', 'gamut-lut-preview' ),
            __( 'Images', 'gamut-lut-preview' ),
            'edit_posts',
            'edit.php?post_type=gamut_sample_image'
        );

        // Image Categories.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Image Categories', 'gamut-lut-preview' ),
            __( 'Image Categories', 'gamut-lut-preview' ),
            'manage_categories',
            'edit-tags.php?taxonomy=gamut_image_category&post_type=gamut_sample_image'
        );

        // Looks (formerly "LUT Designs").
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Looks', 'gamut-lut-preview' ),
            __( 'Looks', 'gamut-lut-preview' ),
            'edit_posts',
            'edit.php?post_type=gamut_lut_design'
        );

        // Collections.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Collections', 'gamut-lut-preview' ),
            __( 'Collections', 'gamut-lut-preview' ),
            'manage_categories',
            'edit-tags.php?taxonomy=gamut_lut_collection&post_type=gamut_lut_design'
        );

        // Settings and Analytics are added by their own classes
        // using self::MENU_SLUG as the parent.
    }

    // =========================================================================
    // Parent / Submenu File Filters (highlight correct menu item)
    // =========================================================================

    /**
     * Fix parent menu highlighting for CPT and taxonomy screens.
     *
     * @param string $parent_file Current parent file.
     * @return string
     */
    public function fix_parent_file( $parent_file ) {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return $parent_file;
        }

        if ( in_array( $screen->post_type, array( 'gamut_sample_image', 'gamut_lut_design' ), true ) ) {
            return self::MENU_SLUG;
        }
        if ( in_array( $screen->taxonomy, array( 'gamut_lut_collection', 'gamut_image_category' ), true ) ) {
            return self::MENU_SLUG;
        }

        return $parent_file;
    }

    /**
     * Fix submenu highlighting for CPT and taxonomy screens.
     *
     * @param string $submenu_file Current submenu file.
     * @param string $parent_file  Current parent file.
     * @return string
     */
    public function fix_submenu_file( $submenu_file, $parent_file ) {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return $submenu_file;
        }

        // Post type list or edit screens.
        if ( 'gamut_sample_image' === $screen->post_type ) {
            return 'edit.php?post_type=gamut_sample_image';
        }
        if ( 'gamut_lut_design' === $screen->post_type && ! $screen->taxonomy ) {
            return 'edit.php?post_type=gamut_lut_design';
        }

        // Taxonomy screens.
        if ( 'gamut_image_category' === $screen->taxonomy ) {
            return 'edit-tags.php?taxonomy=gamut_image_category&post_type=gamut_sample_image';
        }
        if ( 'gamut_lut_collection' === $screen->taxonomy ) {
            return 'edit-tags.php?taxonomy=gamut_lut_collection&post_type=gamut_lut_design';
        }

        return $submenu_file;
    }

    // =========================================================================
    // Tab Bar
    // =========================================================================

    /**
     * Detect which tab is active based on the current screen.
     *
     * @return string|false Tab key or false if not a plugin screen.
     */
    private function get_active_tab() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }

        // Dashboard.
        if ( 'toplevel_page_' . self::MENU_SLUG === $screen->id ) {
            return 'dashboard';
        }

        // Images (list + edit + add new).
        if ( 'gamut_sample_image' === $screen->post_type && ! $screen->taxonomy ) {
            return 'images';
        }
        if ( 'gamut_image_category' === $screen->taxonomy ) {
            return 'images';
        }

        // Looks (list + edit + add new).
        if ( 'gamut_lut_design' === $screen->post_type && ! $screen->taxonomy ) {
            return 'looks';
        }

        // Collections.
        if ( 'gamut_lut_collection' === $screen->taxonomy ) {
            return 'collections';
        }

        // Settings.
        if ( 'lut-tester_page_gamut-lut-settings' === $screen->id ) {
            return 'settings';
        }

        // Analytics.
        if ( 'lut-tester_page_gamut-lut-analytics' === $screen->id ) {
            return 'analytics';
        }

        return false;
    }

    /**
     * Render horizontal tab navigation on all plugin admin screens.
     */
    public function render_tab_bar() {
        $active = $this->get_active_tab();
        if ( false === $active ) {
            return;
        }

        $tabs = array(
            'dashboard'   => array(
                'label' => __( 'Getting Started', 'gamut-lut-preview' ),
                'url'   => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
            ),
            'images'      => array(
                'label' => __( 'Images', 'gamut-lut-preview' ),
                'url'   => admin_url( 'edit.php?post_type=gamut_sample_image' ),
            ),
            'looks'       => array(
                'label' => __( 'Looks', 'gamut-lut-preview' ),
                'url'   => admin_url( 'edit.php?post_type=gamut_lut_design' ),
            ),
            'collections' => array(
                'label' => __( 'Collections', 'gamut-lut-preview' ),
                'url'   => admin_url( 'edit-tags.php?taxonomy=gamut_lut_collection&post_type=gamut_lut_design' ),
            ),
            'settings'    => array(
                'label' => __( 'Settings', 'gamut-lut-preview' ),
                'url'   => admin_url( 'admin.php?page=gamut-lut-settings' ),
            ),
            'analytics'   => array(
                'label' => __( 'Analytics', 'gamut-lut-preview' ),
                'url'   => admin_url( 'admin.php?page=gamut-lut-analytics' ),
            ),
        );

        echo '<div class="gamut-admin-tabs-wrap">';
        echo '<h1 class="gamut-admin-title">' . esc_html__( 'LUT Tester', 'gamut-lut-preview' ) . '</h1>';
        echo '<nav class="nav-tab-wrapper gamut-admin-tabs">';
        foreach ( $tabs as $key => $tab ) {
            $class = ( $key === $active ) ? 'nav-tab nav-tab-active' : 'nav-tab';
            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url( $tab['url'] ),
                esc_attr( $class ),
                esc_html( $tab['label'] )
            );
        }
        echo '</nav>';
        echo '</div>';

        // Hide the default WordPress page title since we provide our own.
        echo '<style>.gamut-admin-tabs-wrap{background:#fff;border-bottom:1px solid #c3c4c7;margin:-1px -1px 0 -1px;padding:10px 20px 0;}.gamut-admin-title{font-size:23px;font-weight:400;margin:0 0 10px;padding:0;}.gamut-admin-tabs .nav-tab{border-bottom-color:transparent;}.gamut-admin-tabs .nav-tab-active{border-bottom-color:#fff;background:#fff;}.wrap>h1:first-of-type,.wrap>.wp-heading-inline{display:none;}.wrap>.page-title-action{display:none;}</style>';
    }

    // =========================================================================
    // Getting Started Dashboard
    // =========================================================================

    /**
     * Render the Getting Started / installation guide page.
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $image_count      = wp_count_posts( 'gamut_sample_image' )->publish;
        $look_count       = wp_count_posts( 'gamut_lut_design' )->publish;
        $collection_count = wp_count_terms( array( 'taxonomy' => 'gamut_lut_collection', 'hide_empty' => false ) );
        if ( is_wp_error( $collection_count ) ) {
            $collection_count = 0;
        }
        ?>
        <div class="wrap">
            <div class="gamut-dashboard">

                <div class="gamut-dashboard__welcome">
                    <h2><?php esc_html_e( 'Welcome to LUT Tester', 'gamut-lut-preview' ); ?></h2>
                    <p><?php esc_html_e( 'A WebGL-powered LUT preview tool for your WordPress site. Let customers see your color grades applied to sample images in real-time, with before/after comparison, intensity control, and one-click add to cart.', 'gamut-lut-preview' ); ?></p>
                </div>

                <hr>

                <h3><?php esc_html_e( 'Quick Setup', 'gamut-lut-preview' ); ?></h3>

                <ol class="gamut-dashboard__steps">
                    <li class="<?php echo $image_count > 0 ? 'gamut-step--done' : ''; ?>">
                        <strong><?php esc_html_e( 'Upload sample images', 'gamut-lut-preview' ); ?></strong>
                        <p>
                            <?php esc_html_e( 'Add high-quality photos that customers will preview your LUTs on. Go to the Images tab and use the bulk upload button to add multiple images at once.', 'gamut-lut-preview' ); ?>
                            <?php if ( ! $image_count ) : ?>
                                <br><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=gamut_sample_image' ) ); ?>" class="button"><?php esc_html_e( 'Add Images', 'gamut-lut-preview' ); ?></a>
                            <?php else : ?>
                                <br><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php printf( esc_html__( '%d image(s) uploaded', 'gamut-lut-preview' ), $image_count ); ?>
                            <?php endif; ?>
                        </p>
                    </li>

                    <li class="<?php echo $collection_count > 0 ? 'gamut-step--done' : ''; ?>">
                        <strong><?php esc_html_e( 'Create a collection', 'gamut-lut-preview' ); ?></strong>
                        <p>
                            <?php esc_html_e( 'Collections group your LUT files together (e.g. "Cinematic Pack", "Travel Bundle"). Go to the Collections tab and add a new collection. Optionally link it to a WooCommerce product for the Add to Cart button.', 'gamut-lut-preview' ); ?>
                            <?php if ( ! $collection_count ) : ?>
                                <br><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=gamut_lut_collection&post_type=gamut_lut_design' ) ); ?>" class="button"><?php esc_html_e( 'Create Collection', 'gamut-lut-preview' ); ?></a>
                            <?php else : ?>
                                <br><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php printf( esc_html__( '%d collection(s) created', 'gamut-lut-preview' ), $collection_count ); ?>
                            <?php endif; ?>
                        </p>
                    </li>

                    <li class="<?php echo $look_count > 0 ? 'gamut-step--done' : ''; ?>">
                        <strong><?php esc_html_e( 'Upload .cube LUT files', 'gamut-lut-preview' ); ?></strong>
                        <p>
                            <?php esc_html_e( 'Go to a collection\'s edit page and use the bulk upload tool to add your .cube files. Each file becomes a "Look" that customers can preview.', 'gamut-lut-preview' ); ?>
                            <?php if ( ! $look_count ) : ?>
                                <?php if ( $collection_count > 0 ) : ?>
                                    <br><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=gamut_lut_collection&post_type=gamut_lut_design' ) ); ?>" class="button"><?php esc_html_e( 'Manage Collections', 'gamut-lut-preview' ); ?></a>
                                <?php endif; ?>
                            <?php else : ?>
                                <br><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php printf( esc_html__( '%d look(s) uploaded', 'gamut-lut-preview' ), $look_count ); ?>
                            <?php endif; ?>
                        </p>
                    </li>

                    <li>
                        <strong><?php esc_html_e( 'Add the shortcode to a page', 'gamut-lut-preview' ); ?></strong>
                        <p>
                            <?php esc_html_e( 'Create a new page (or edit an existing one) and add:', 'gamut-lut-preview' ); ?>
                            <br><code>[gamut_lut_preview]</code>
                            <br><?php esc_html_e( 'This displays the full LUT preview tool with all collections, images, comparisons, and cart integration.', 'gamut-lut-preview' ); ?>
                        </p>
                    </li>
                </ol>

                <hr>

                <h3><?php esc_html_e( 'Shortcodes', 'gamut-lut-preview' ); ?></h3>

                <table class="widefat striped" style="max-width: 700px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Shortcode', 'gamut-lut-preview' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'gamut-lut-preview' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[gamut_lut_preview]</code></td>
                            <td><?php esc_html_e( 'Full preview page — all collections, all images, comparison modes, favorites, cart.', 'gamut-lut-preview' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>[gamut_collection slug="slug"]</code></td>
                            <td><?php esc_html_e( 'Compact embed — single collection preview for blog posts or product pages. Replace "slug" with the collection slug.', 'gamut-lut-preview' ); ?></td>
                        </tr>
                    </tbody>
                </table>

                <p class="description" style="margin-top: 10px;">
                    <?php esc_html_e( 'Both shortcodes only load assets on pages where they are used.', 'gamut-lut-preview' ); ?>
                </p>

                <hr>

                <h3><?php esc_html_e( 'At a Glance', 'gamut-lut-preview' ); ?></h3>
                <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                    <div style="background: #fff; padding: 15px 25px; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 140px;">
                        <div style="font-size: 28px; font-weight: 600;"><?php echo intval( $image_count ); ?></div>
                        <div style="color: #666;"><?php esc_html_e( 'Images', 'gamut-lut-preview' ); ?></div>
                    </div>
                    <div style="background: #fff; padding: 15px 25px; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 140px;">
                        <div style="font-size: 28px; font-weight: 600;"><?php echo intval( $look_count ); ?></div>
                        <div style="color: #666;"><?php esc_html_e( 'Looks', 'gamut-lut-preview' ); ?></div>
                    </div>
                    <div style="background: #fff; padding: 15px 25px; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 140px;">
                        <div style="font-size: 28px; font-weight: 600;"><?php echo intval( $collection_count ); ?></div>
                        <div style="color: #666;"><?php esc_html_e( 'Collections', 'gamut-lut-preview' ); ?></div>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
