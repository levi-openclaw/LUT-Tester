<?php
/**
 * Taxonomy registration for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Taxonomies {

    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );

        // Term meta fields for gamut_lut_collection.
        add_action( 'gamut_lut_collection_add_form_fields', array( $this, 'add_collection_fields' ) );
        add_action( 'gamut_lut_collection_edit_form_fields', array( $this, 'edit_collection_fields' ) );
        add_action( 'created_gamut_lut_collection', array( $this, 'save_collection_fields' ) );
        add_action( 'edited_gamut_lut_collection', array( $this, 'save_collection_fields' ) );

        // Enqueue Select2 on the collection taxonomy screens.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_product_select_assets' ) );
    }

    /**
     * Register taxonomies.
     */
    public function register() {
        $this->register_image_category();
        $this->register_lut_collection();
    }

    /**
     * Register gamut_image_category taxonomy on gamut_sample_image.
     */
    private function register_image_category() {
        $labels = array(
            'name'              => __( 'Image Categories', 'gamut-lut-preview' ),
            'singular_name'     => __( 'Image Category', 'gamut-lut-preview' ),
            'search_items'      => __( 'Search Categories', 'gamut-lut-preview' ),
            'all_items'         => __( 'All Categories', 'gamut-lut-preview' ),
            'parent_item'       => __( 'Parent Category', 'gamut-lut-preview' ),
            'parent_item_colon' => __( 'Parent Category:', 'gamut-lut-preview' ),
            'edit_item'         => __( 'Edit Category', 'gamut-lut-preview' ),
            'update_item'       => __( 'Update Category', 'gamut-lut-preview' ),
            'add_new_item'      => __( 'Add New Category', 'gamut-lut-preview' ),
            'new_item_name'     => __( 'New Category Name', 'gamut-lut-preview' ),
            'menu_name'         => __( 'Image Categories', 'gamut-lut-preview' ),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'rewrite'           => false,
        );

        register_taxonomy( 'gamut_image_category', 'gamut_sample_image', $args );
    }

    /**
     * Register gamut_lut_collection taxonomy on gamut_lut_design.
     */
    private function register_lut_collection() {
        $labels = array(
            'name'              => __( 'LUT Collections', 'gamut-lut-preview' ),
            'singular_name'     => __( 'LUT Collection', 'gamut-lut-preview' ),
            'search_items'      => __( 'Search Collections', 'gamut-lut-preview' ),
            'all_items'         => __( 'All Collections', 'gamut-lut-preview' ),
            'parent_item'       => __( 'Parent Collection', 'gamut-lut-preview' ),
            'parent_item_colon' => __( 'Parent Collection:', 'gamut-lut-preview' ),
            'edit_item'         => __( 'Edit Collection', 'gamut-lut-preview' ),
            'update_item'       => __( 'Update Collection', 'gamut-lut-preview' ),
            'add_new_item'      => __( 'Add New Collection', 'gamut-lut-preview' ),
            'new_item_name'     => __( 'New Collection Name', 'gamut-lut-preview' ),
            'menu_name'         => __( 'LUT Collections', 'gamut-lut-preview' ),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'rewrite'           => false,
        );

        register_taxonomy( 'gamut_lut_collection', 'gamut_lut_design', $args );
    }

    /**
     * Enqueue Select2 assets on the LUT Collection taxonomy screens.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_product_select_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'edit-tags.php', 'term.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'gamut_lut_collection' !== $screen->taxonomy ) {
            return;
        }

        // Use WooCommerce's bundled Select2 (selectWoo) if available, otherwise skip enhancement.
        if ( wp_script_is( 'selectWoo', 'registered' ) ) {
            wp_enqueue_script( 'selectWoo' );
            wp_enqueue_style( 'select2' );
        }

        wp_add_inline_script( 'selectWoo', '
            jQuery(function($) {
                function initProductSelect() {
                    var $select = $("#gamut_product_id");
                    if ($select.length && $.fn.selectWoo) {
                        $select.selectWoo({
                            placeholder: "' . esc_js( __( 'Search for a product...', 'gamut-lut-preview' ) ) . '",
                            allowClear: true,
                            width: "100%"
                        });
                    }
                }
                initProductSelect();
                $(document).ajaxComplete(function(event, xhr, settings) {
                    if (settings.data && settings.data.indexOf("action=add-tag") !== -1) {
                        setTimeout(initProductSelect, 200);
                    }
                });
            });
        ' );
    }

    /**
     * Get published WooCommerce products for the dropdown.
     *
     * @return array Array of [ id => title ] pairs.
     */
    private function get_product_options() {
        $products = array();

        if ( ! post_type_exists( 'product' ) ) {
            return $products;
        }

        $query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        if ( $query->posts ) {
            foreach ( $query->posts as $id ) {
                $price = get_post_meta( $id, '_price', true );
                $label = get_the_title( $id );
                if ( $price ) {
                    $label .= ' — $' . number_format( (float) $price, 2 );
                }
                $label .= ' (#' . $id . ')';
                $products[ $id ] = $label;
            }
        }

        return $products;
    }

    /**
     * Render the product select dropdown HTML.
     *
     * @param int $selected_id Currently selected product ID.
     */
    private function render_product_select( $selected_id = 0 ) {
        $products = $this->get_product_options();

        if ( empty( $products ) && ! post_type_exists( 'product' ) ) {
            echo '<input type="number" name="gamut_product_id" id="gamut_product_id" value="' . esc_attr( $selected_id ) . '" min="0" step="1">';
            echo '<p class="description">' . esc_html__( 'WooCommerce is not active. Enter a product ID manually.', 'gamut-lut-preview' ) . '</p>';
            return;
        }

        echo '<select name="gamut_product_id" id="gamut_product_id" style="min-width: 300px;">';
        echo '<option value="0">' . esc_html__( '— No product linked —', 'gamut-lut-preview' ) . '</option>';

        foreach ( $products as $id => $label ) {
            printf(
                '<option value="%d" %s>%s</option>',
                $id,
                selected( $selected_id, $id, false ),
                esc_html( $label )
            );
        }

        echo '</select>';
    }

    /**
     * Add product ID field to the "Add New Collection" form.
     */
    public function add_collection_fields() {
        ?>
        <div class="form-field">
            <label for="gamut_product_id"><?php esc_html_e( 'WooCommerce Product', 'gamut-lut-preview' ); ?></label>
            <?php $this->render_product_select( 0 ); ?>
            <p class="description"><?php esc_html_e( 'Link this collection to a WooCommerce product for the Add to Cart button.', 'gamut-lut-preview' ); ?></p>
        </div>
        <?php
    }

    /**
     * Add product ID field to the "Edit Collection" form.
     *
     * @param WP_Term $term Current taxonomy term object.
     */
    public function edit_collection_fields( $term ) {
        $product_id = absint( get_term_meta( $term->term_id, 'gamut_product_id', true ) );
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="gamut_product_id"><?php esc_html_e( 'WooCommerce Product', 'gamut-lut-preview' ); ?></label>
            </th>
            <td>
                <?php $this->render_product_select( $product_id ); ?>
                <p class="description"><?php esc_html_e( 'Link this collection to a WooCommerce product for the Add to Cart button.', 'gamut-lut-preview' ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save the product ID term meta.
     *
     * @param int $term_id Term ID.
     */
    public function save_collection_fields( $term_id ) {
        if ( isset( $_POST['gamut_product_id'] ) ) {
            $product_id = absint( $_POST['gamut_product_id'] );
            update_term_meta( $term_id, 'gamut_product_id', $product_id );
        }
    }
}
