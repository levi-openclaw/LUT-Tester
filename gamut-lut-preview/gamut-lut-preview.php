<?php
/**
 * Plugin Name: Gamut LUT Preview
 * Plugin URI:  https://gamut.io
 * Description: Client-side WebGL LUT preview tool. Users select sample images, choose a LUT collection and individual LUT, and see instant before/after previews with intensity control.
 * Version:     1.5.1
 * Author:      Gamut
 * Author URI:  https://gamut.io
 * Text Domain: gamut-lut-preview
 * License:     GPL-2.0-or-later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GAMUT_LUT_VERSION', '1.5.1' );
define( 'GAMUT_LUT_PATH', plugin_dir_path( __FILE__ ) );
define( 'GAMUT_LUT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Allow .cube file uploads.
 */
function gamut_lut_allow_cube_upload( $mimes ) {
    $mimes['cube'] = 'text/plain';
    return $mimes;
}
add_filter( 'upload_mimes', 'gamut_lut_allow_cube_upload' );

/**
 * Include all class files.
 */
require_once GAMUT_LUT_PATH . 'includes/class-utils.php';
require_once GAMUT_LUT_PATH . 'includes/class-post-types.php';
require_once GAMUT_LUT_PATH . 'includes/class-taxonomies.php';
require_once GAMUT_LUT_PATH . 'includes/class-meta-boxes.php';
require_once GAMUT_LUT_PATH . 'includes/class-rest-api.php';
require_once GAMUT_LUT_PATH . 'includes/class-admin-settings.php';
require_once GAMUT_LUT_PATH . 'includes/class-shortcode.php';
require_once GAMUT_LUT_PATH . 'includes/class-cart.php';
require_once GAMUT_LUT_PATH . 'includes/class-bulk-upload.php';
require_once GAMUT_LUT_PATH . 'includes/class-analytics.php';
require_once GAMUT_LUT_PATH . 'includes/class-favorites.php';
require_once GAMUT_LUT_PATH . 'includes/class-product-integration.php';

/**
 * Initialize all plugin classes.
 */
function gamut_lut_init() {
    new Gamut_LUT_Post_Types();
    new Gamut_LUT_Taxonomies();
    new Gamut_LUT_Meta_Boxes();
    new Gamut_LUT_REST_API();
    new Gamut_LUT_Admin_Settings();
    new Gamut_LUT_Shortcode();
    new Gamut_LUT_Cart();
    new Gamut_LUT_Bulk_Upload();
    new Gamut_LUT_Analytics();
    new Gamut_LUT_Favorites();
    new Gamut_LUT_Product_Integration();
}
add_action( 'plugins_loaded', 'gamut_lut_init' );

/**
 * Inject Open Graph meta tags for shared LUT preview links.
 *
 * When a share URL contains gamut_collection + gamut_lut parameters,
 * outputs og:title, og:image, og:url, and og:description meta tags
 * so social platforms / iMessage show a rich preview.
 */
function gamut_lut_og_meta() {
    // Only act on share links with LUT parameters.
    if ( empty( $_GET['gamut_collection'] ) || empty( $_GET['gamut_lut'] ) ) {
        return;
    }

    $collection_slug = sanitize_title( wp_unslash( $_GET['gamut_collection'] ) );
    $lut_id          = absint( $_GET['gamut_lut'] );
    $image_id        = ! empty( $_GET['gamut_image'] ) ? absint( $_GET['gamut_image'] ) : 0;

    // Get LUT post for the title.
    $lut_post = get_post( $lut_id );
    if ( ! $lut_post || 'gamut_lut_design' !== $lut_post->post_type ) {
        return;
    }

    $lut_title = Gamut_LUT_REST_API::clean_lut_title( $lut_post->post_title );
    $og_title  = sprintf( 'Check out %s from Gamut', $lut_title );

    // Get the sample image URL for og:image.
    $og_image = '';
    if ( $image_id ) {
        $sample_post = get_post( $image_id );
        if ( $sample_post && 'gamut_sample_image' === $sample_post->post_type ) {
            $thumb_id = get_post_thumbnail_id( $sample_post->ID );
            if ( $thumb_id ) {
                $img_src = wp_get_attachment_image_src( $thumb_id, 'large' );
                if ( $img_src ) {
                    $og_image = $img_src[0];
                }
            }
        }
    }

    // Build canonical URL.
    $og_url = add_query_arg( array(
        'gamut_collection' => $collection_slug,
        'gamut_lut'        => $lut_id,
        'gamut_image'      => $image_id ? $image_id : false,
    ), get_permalink() );

    $og_description = sprintf( 'Preview the %s look on Gamut\'s LUT Tester.', $lut_title );

    echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
    echo '<meta property="og:type" content="website" />' . "\n";
    echo '<meta property="og:site_name" content="Gamut" />' . "\n";

    if ( $og_image ) {
        echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    }

    echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $og_description ) . '" />' . "\n";

    if ( $og_image ) {
        echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '" />' . "\n";
    }
}
add_action( 'wp_head', 'gamut_lut_og_meta', 5 );

/**
 * Flush rewrite rules on activation.
 */
function gamut_lut_activate() {
    $post_types = new Gamut_LUT_Post_Types();
    $post_types->register();
    $taxonomies = new Gamut_LUT_Taxonomies();
    $taxonomies->register();
    Gamut_LUT_Analytics::create_table();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'gamut_lut_activate' );

/**
 * Flush rewrite rules on deactivation.
 */
function gamut_lut_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gamut_lut_deactivate' );
