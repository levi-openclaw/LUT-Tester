<?php
/**
 * WooCommerce Product Page Integration.
 *
 * Automatically detects WooCommerce product pages that have a linked
 * LUT collection (via gamut_product_id term meta) and renders a
 * LUT previewer section on the product page.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Product_Integration {

    public function __construct() {
        // Only hook if WooCommerce is active.
        add_action( 'init', array( $this, 'maybe_hook_woocommerce' ) );
    }

    /**
     * Hook into WooCommerce product pages if WooCommerce is available.
     */
    public function maybe_hook_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Render the LUT section after the product summary.
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_product_lut_section' ), 15 );
    }

    /**
     * Find the collection linked to a WooCommerce product.
     *
     * Reverse-lookups gamut_product_id term meta to find which
     * gamut_lut_collection term is linked to the given product ID.
     *
     * @param int $product_id WooCommerce product ID.
     * @return WP_Term|null The collection term, or null if not found.
     */
    public static function get_collection_for_product( $product_id ) {
        $terms = get_terms( array(
            'taxonomy'   => 'gamut_lut_collection',
            'hide_empty' => true,
            'meta_query' => array(
                array(
                    'key'     => 'gamut_product_id',
                    'value'   => $product_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return null;
        }

        return $terms[0];
    }

    /**
     * Render the LUT preview section on a WooCommerce product page.
     */
    public function render_product_lut_section() {
        $product = wc_get_product();
        if ( ! $product ) {
            return;
        }

        $collection_term = self::get_collection_for_product( $product->get_id() );
        if ( ! $collection_term ) {
            return;
        }

        // Ensure assets are enqueued.
        $shortcode = new Gamut_LUT_Shortcode();
        // Use reflection or direct enqueue — the shortcode class handles dedup.
        do_shortcode( '[gamut_collection slug="' . esc_attr( $collection_term->slug ) . '"]' );

        // Build data for the template.
        $collection_slug = $collection_term->slug;
        $collection_name = $collection_term->name;
        $product_name    = $product->get_name();
        $product_price   = $product->get_price_html();
        $lut_count       = 0;
        $luts            = array();

        // Query LUTs in this collection.
        $query = new WP_Query( array(
            'post_type'      => 'gamut_lut_design',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'gamut_lut_collection',
                    'field'    => 'term_id',
                    'terms'    => $collection_term->term_id,
                ),
            ),
        ) );

        foreach ( $query->posts as $post ) {
            $luts[] = array(
                'id'    => $post->ID,
                'title' => Gamut_LUT_REST_API::clean_lut_title( $post->post_title ),
            );
        }
        $lut_count = count( $luts );

        // Render the product LUT section template.
        include GAMUT_LUT_PATH . 'templates/product-lut-section.php';
    }
}
