<?php
/**
 * WooCommerce cart integration for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Cart {

    public function __construct() {
        add_action( 'wp_ajax_gamut_add_to_cart', array( $this, 'add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_gamut_add_to_cart', array( $this, 'add_to_cart' ) );
    }

    /**
     * AJAX handler for adding a LUT collection product to the WooCommerce cart.
     */
    public function add_to_cart() {
        check_ajax_referer( 'gamut_lut_nonce', 'nonce' );

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array(
                'message' => __( 'WooCommerce is not available.', 'gamut-lut-preview' ),
            ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid product.', 'gamut-lut-preview' ),
            ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array(
                'message' => __( 'Product not found.', 'gamut-lut-preview' ),
            ) );
        }

        // Check if product is already in cart.
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( $cart_item['product_id'] === $product_id ) {
                wp_send_json_success( array(
                    'message'  => __( 'This collection is already in your cart.', 'gamut-lut-preview' ),
                    'in_cart'  => true,
                    'cart_url' => wc_get_cart_url(),
                ) );
            }
        }

        $added = WC()->cart->add_to_cart( $product_id );

        if ( $added ) {
            wp_send_json_success( array(
                'message'  => __( 'Added to cart!', 'gamut-lut-preview' ),
                'in_cart'  => false,
                'cart_url' => wc_get_cart_url(),
            ) );
        }

        wp_send_json_error( array(
            'message' => __( 'Could not add to cart. Please try again.', 'gamut-lut-preview' ),
        ) );
    }
}
