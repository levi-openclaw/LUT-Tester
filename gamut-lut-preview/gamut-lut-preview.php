<?php
/**
 * Plugin Name: Gamut LUT Preview
 * Plugin URI:  https://gamut.io
 * Description: Client-side WebGL LUT preview tool. Users select sample images, choose a LUT collection and individual LUT, and see instant before/after previews with intensity control.
 * Version:     1.1.0
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

define( 'GAMUT_LUT_VERSION', '1.1.0' );
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
}
add_action( 'plugins_loaded', 'gamut_lut_init' );

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
