<?php
/**
 * Custom Post Type registration for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Post_Types {

    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );
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
     * Curated sample photos for LUT preview.
     */
    private function register_sample_image() {
        $labels = array(
            'name'               => __( 'Sample Images', 'gamut-lut-preview' ),
            'singular_name'      => __( 'Sample Image', 'gamut-lut-preview' ),
            'add_new'            => __( 'Add New', 'gamut-lut-preview' ),
            'add_new_item'       => __( 'Add New Sample Image', 'gamut-lut-preview' ),
            'edit_item'          => __( 'Edit Sample Image', 'gamut-lut-preview' ),
            'new_item'           => __( 'New Sample Image', 'gamut-lut-preview' ),
            'view_item'          => __( 'View Sample Image', 'gamut-lut-preview' ),
            'search_items'       => __( 'Search Sample Images', 'gamut-lut-preview' ),
            'not_found'          => __( 'No sample images found', 'gamut-lut-preview' ),
            'not_found_in_trash' => __( 'No sample images found in Trash', 'gamut-lut-preview' ),
            'menu_name'          => __( 'Sample Images', 'gamut-lut-preview' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'supports'            => array( 'title', 'thumbnail' ),
            'menu_icon'           => 'dashicons-format-image',
            'menu_position'       => 25,
            'show_in_rest'        => false,
        );

        register_post_type( 'gamut_sample_image', $args );
    }

    /**
     * Register the gamut_lut_design post type.
     * Individual LUT files within a collection.
     */
    private function register_lut_design() {
        $labels = array(
            'name'               => __( 'LUT Designs', 'gamut-lut-preview' ),
            'singular_name'      => __( 'LUT Design', 'gamut-lut-preview' ),
            'add_new'            => __( 'Add New', 'gamut-lut-preview' ),
            'add_new_item'       => __( 'Add New LUT Design', 'gamut-lut-preview' ),
            'edit_item'          => __( 'Edit LUT Design', 'gamut-lut-preview' ),
            'new_item'           => __( 'New LUT Design', 'gamut-lut-preview' ),
            'view_item'          => __( 'View LUT Design', 'gamut-lut-preview' ),
            'search_items'       => __( 'Search LUT Designs', 'gamut-lut-preview' ),
            'not_found'          => __( 'No LUT designs found', 'gamut-lut-preview' ),
            'not_found_in_trash' => __( 'No LUT designs found in Trash', 'gamut-lut-preview' ),
            'menu_name'          => __( 'LUT Designs', 'gamut-lut-preview' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'supports'            => array( 'title' ),
            'menu_icon'           => 'dashicons-art',
            'menu_position'       => 26,
            'show_in_rest'        => false,
        );

        register_post_type( 'gamut_lut_design', $args );
    }
}
