<?php
/*
Plugin Name: Custom LUT Processor
Description: A completely blank custom page template to build on.
Version: 0.2
Requires at least: 5.0
Author: Aidy Ch
Author URI: https://sertechs.com/
License: Public Domain
License URI: https://sertechs.com/
Text Domain: custom-lut-processor
*/

// block direct access to this file
if ( !defined( 'ABSPATH' ) ) {
	http_response_code( 404 );
	die();
}

// get the template
add_filter( 'page_template', 'blank_template' );
function blank_template( $page_template ) {
	if ( get_page_template_slug() == 'templates/custom-lut-processor.php' ) {
		$page_template = dirname( __FILE__ ) . '/templates/custom-lut-processor.php';
    }
	return $page_template;
}

// add the template select
add_filter( 'theme_page_templates', 'blank_select', 10, 4 );
function blank_select( $post_templates, $wp_theme, $post, $post_type ) {
	$post_templates['templates/custom-lut-processor.php'] = __( 'Custom LUT Processor', 'custom-lut-processor' );
    return $post_templates;
}

require_once( plugin_dir_path( __FILE__ ) . 'functions.php' );

require_once( plugin_dir_path( __FILE__ ) . 'dynamic-content.php' );

function custom_lut_processor_enqueue_styles() {
    // Enqueue the CSS file with a cache version
    wp_enqueue_style('custom-lut-processor-styles', plugin_dir_url(__FILE__) . 'templates/css/style.css', array(), filemtime(plugin_dir_path(__FILE__) . 'templates/css/style.css'));
}
add_action('wp_enqueue_scripts', 'custom_lut_processor_enqueue_styles');

function custom_lut_processor_enqueue_scripts() {
    // Enqueue the JavaScript file with a cache version
    wp_enqueue_script('custom-lut-processor-script', plugin_dir_url(__FILE__) . 'templates/js/lut.js', array('jquery'), filemtime(plugin_dir_path(__FILE__) . 'templates/js/lut.js'), true);
}
add_action('wp_enqueue_scripts', 'custom_lut_processor_enqueue_scripts');


function get_lut_posts() {
    // Get the selected category from the AJAX request
    $selected_category = $_POST['category'];
    $dropdown_id = $_POST['dropdownId'];

    // Query posts based on the selected category
    $args = array(
        'post_type' => 'lut-design',
        'posts_per_page' => -1, // Retrieve all posts
        'tax_query' => array(
            array(
                'taxonomy' => 'lut_design_category',
                'field' => 'slug',
                'terms' => $selected_category,
            ),
        ),
        'orderby' => 'title', // Sort by title
        'order' => 'ASC', // in ascending order
    );
    $query = new WP_Query($args);

    // Output the posts as an HTML string
    if ($query->have_posts()) {
        echo '<option value="">Select a LUT</option>';
        while ($query->have_posts()) {
            $query->the_post();

                $lut_cube_file_url = get_post_meta(get_the_ID(), '_lut_cube_file_url', true);

                if (!empty($lut_cube_file_url)) {
                    echo '<option value="' . get_the_ID() . '">' . get_the_title() . '</option>';
                }

        }
        wp_reset_postdata();
    } else {
        echo '<option value="">Select a LUT</option>';
    }
    

    // Always exit to prevent extra output
    exit;
}

add_action('wp_ajax_get_lut_posts', 'get_lut_posts');
add_action('wp_ajax_nopriv_get_lut_posts', 'get_lut_posts'); 


function get_filtered_posts() {
    $category = $_POST['category'];

    $args = array(
        'post_type' => 'lut_image',
        'posts_per_page' => -1,
    );

    if ( $category != '' ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'lut_image_category',
                'field' => 'slug',
                'terms' => $category,
            ),
        );
    }

    $query = new WP_Query( $args );

    $posts = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( $post_id ) );
            $posts[] = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'image' => $featured_image_url,
            );
        }
        wp_reset_postdata();
    }

    wp_send_json( $posts );
    exit;
}
add_action( 'wp_ajax_get_filtered_posts', 'get_filtered_posts' );
add_action( 'wp_ajax_nopriv_get_filtered_posts', 'get_filtered_posts' );
