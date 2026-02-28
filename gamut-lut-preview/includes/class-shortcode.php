<?php
/**
 * Shortcode registration and asset enqueuing for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Shortcode {

    private $enqueued = false;
    private $embed_count = 0;

    public function __construct() {
        add_shortcode( 'gamut_lut_preview', array( $this, 'render' ) );
        add_shortcode( 'gamut_collection', array( $this, 'render_collection_embed' ) );
    }

    /**
     * Render the full preview shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render( $atts ) {
        if ( ! $this->enqueued ) {
            $this->enqueue_assets();
            $this->enqueued = true;
        }

        ob_start();
        include GAMUT_LUT_PATH . 'templates/preview-template.php';
        return ob_get_clean();
    }

    /**
     * Render a collection embed shortcode for blog posts.
     *
     * Usage: [gamut_collection slug="cinematic"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_collection_embed( $atts ) {
        $atts = shortcode_atts( array(
            'slug' => '',
        ), $atts, 'gamut_collection' );

        if ( empty( $atts['slug'] ) ) {
            return '<p>' . esc_html__( 'Please specify a collection slug: [gamut_collection slug="your-collection"]', 'gamut-lut-preview' ) . '</p>';
        }

        if ( ! $this->enqueued ) {
            $this->enqueue_assets();
            $this->enqueued = true;
        }

        $this->embed_count++;
        $collection_slug = sanitize_title( $atts['slug'] );
        $instance_id     = 'gamut-collection-embed-' . $this->embed_count;

        ob_start();
        include GAMUT_LUT_PATH . 'templates/collection-embed-template.php';
        return ob_get_clean();
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    private function enqueue_assets() {
        // CSS.
        wp_enqueue_style(
            'gamut-lut-preview',
            GAMUT_LUT_URL . 'assets/css/preview.css',
            array(),
            GAMUT_LUT_VERSION
        );

        // JS — cube parser (no dependencies).
        wp_enqueue_script(
            'gamut-cube-parser',
            GAMUT_LUT_URL . 'assets/js/cube-parser.js',
            array(),
            GAMUT_LUT_VERSION,
            true
        );

        // JS — WebGL LUT engine (depends on cube parser).
        wp_enqueue_script(
            'gamut-lut-engine',
            GAMUT_LUT_URL . 'assets/js/lut-engine.js',
            array( 'gamut-cube-parser' ),
            GAMUT_LUT_VERSION,
            true
        );

        // JS — comparison slider (no dependencies).
        wp_enqueue_script(
            'gamut-comparison-slider',
            GAMUT_LUT_URL . 'assets/js/comparison-slider.js',
            array(),
            GAMUT_LUT_VERSION,
            true
        );

        // JS — main preview UI (depends on all above).
        wp_enqueue_script(
            'gamut-preview-ui',
            GAMUT_LUT_URL . 'assets/js/preview-ui.js',
            array( 'gamut-cube-parser', 'gamut-lut-engine', 'gamut-comparison-slider' ),
            GAMUT_LUT_VERSION,
            true
        );

        // Pass config data to JS.
        wp_localize_script( 'gamut-preview-ui', 'gamutLutConfig', array(
            'restUrl'   => esc_url_raw( rest_url( 'gamut/v1' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'cartNonce' => wp_create_nonce( 'gamut_lut_nonce' ),
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'pageUrl'    => get_permalink(),
            'isLoggedIn' => is_user_logged_in(),
            'settings'   => array(
                'title'             => get_option( 'gamut_lut_title', 'Preview Our LUTs' ),
                'description'       => get_option( 'gamut_lut_description', '' ),
                'imagesTitle'       => get_option( 'gamut_lut_images_title', 'Select an Image' ),
                'imagesDescription' => get_option( 'gamut_lut_images_description', '' ),
            ),
        ) );
    }
}
