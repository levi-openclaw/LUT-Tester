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
     * Add product ID field to the "Add New Collection" form.
     */
    public function add_collection_fields() {
        ?>
        <div class="form-field">
            <label for="gamut_product_id"><?php esc_html_e( 'WooCommerce Product ID', 'gamut-lut-preview' ); ?></label>
            <input type="number" name="gamut_product_id" id="gamut_product_id" value="" min="0" step="1">
            <p class="description"><?php esc_html_e( 'The WooCommerce product ID for this LUT collection. Used for the Add to Cart button.', 'gamut-lut-preview' ); ?></p>
        </div>
        <?php
    }

    /**
     * Add product ID field to the "Edit Collection" form.
     *
     * @param WP_Term $term Current taxonomy term object.
     */
    public function edit_collection_fields( $term ) {
        $product_id = get_term_meta( $term->term_id, 'gamut_product_id', true );
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="gamut_product_id"><?php esc_html_e( 'WooCommerce Product ID', 'gamut-lut-preview' ); ?></label>
            </th>
            <td>
                <input type="number" name="gamut_product_id" id="gamut_product_id" value="<?php echo esc_attr( $product_id ); ?>" min="0" step="1">
                <p class="description"><?php esc_html_e( 'The WooCommerce product ID for this LUT collection. Used for the Add to Cart button.', 'gamut-lut-preview' ); ?></p>
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
