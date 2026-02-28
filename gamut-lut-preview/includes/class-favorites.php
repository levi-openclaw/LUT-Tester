<?php
/**
 * User favorites persistence for Gamut LUT Preview.
 *
 * Syncs favorites to user meta for logged-in users.
 * Guests use localStorage only (handled in JS).
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Favorites {

    const META_KEY = 'gamut_lut_favorites';

    public function __construct() {
        add_action( 'wp_ajax_gamut_save_favorites', array( $this, 'save_favorites' ) );
        add_action( 'wp_ajax_gamut_get_favorites', array( $this, 'get_favorites' ) );
    }

    /**
     * AJAX handler: save favorites to user meta.
     */
    public function save_favorites() {
        check_ajax_referer( 'gamut_lut_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => 'Not logged in.' ) );
        }

        $favorites = isset( $_POST['favorites'] ) ? $_POST['favorites'] : '[]';
        $decoded   = json_decode( sanitize_text_field( wp_unslash( $favorites ) ), true );

        if ( ! is_array( $decoded ) ) {
            $decoded = array();
        }

        // Sanitize: only keep integer IDs.
        $clean = array_values( array_unique( array_map( 'absint', $decoded ) ) );
        $clean = array_filter( $clean );

        update_user_meta( $user_id, self::META_KEY, $clean );

        wp_send_json_success( array( 'favorites' => $clean ) );
    }

    /**
     * AJAX handler: get favorites from user meta.
     */
    public function get_favorites() {
        check_ajax_referer( 'gamut_lut_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_success( array( 'favorites' => array(), 'logged_in' => false ) );
        }

        $favorites = get_user_meta( $user_id, self::META_KEY, true );
        if ( ! is_array( $favorites ) ) {
            $favorites = array();
        }

        wp_send_json_success( array( 'favorites' => $favorites, 'logged_in' => true ) );
    }
}
