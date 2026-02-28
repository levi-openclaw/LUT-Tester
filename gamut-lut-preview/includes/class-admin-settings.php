<?php
/**
 * Admin settings page for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Admin_Settings {

    const OPTION_GROUP = 'gamut_lut_settings';
    const PAGE_SLUG    = 'gamut-lut-preview';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add settings page under Settings menu.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'LUT Preview Settings', 'gamut-lut-preview' ),
            __( 'LUT Preview', 'gamut-lut-preview' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings() {
        register_setting( self::OPTION_GROUP, 'gamut_lut_title', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Preview Our LUTs',
        ) );
        register_setting( self::OPTION_GROUP, 'gamut_lut_description', array(
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        ) );
        register_setting( self::OPTION_GROUP, 'gamut_lut_images_title', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Select an Image',
        ) );
        register_setting( self::OPTION_GROUP, 'gamut_lut_images_description', array(
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        ) );

        add_settings_section(
            'gamut_lut_main_section',
            __( 'Preview Page Content', 'gamut-lut-preview' ),
            array( $this, 'render_section_description' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'gamut_lut_title',
            __( 'Main Title', 'gamut-lut-preview' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'gamut_lut_main_section',
            array( 'option' => 'gamut_lut_title' )
        );

        add_settings_field(
            'gamut_lut_description',
            __( 'Main Description', 'gamut-lut-preview' ),
            array( $this, 'render_textarea_field' ),
            self::PAGE_SLUG,
            'gamut_lut_main_section',
            array( 'option' => 'gamut_lut_description' )
        );

        add_settings_field(
            'gamut_lut_images_title',
            __( 'Images Section Title', 'gamut-lut-preview' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'gamut_lut_main_section',
            array( 'option' => 'gamut_lut_images_title' )
        );

        add_settings_field(
            'gamut_lut_images_description',
            __( 'Images Section Description', 'gamut-lut-preview' ),
            array( $this, 'render_textarea_field' ),
            self::PAGE_SLUG,
            'gamut_lut_main_section',
            array( 'option' => 'gamut_lut_images_description' )
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LUT Preview Settings', 'gamut-lut-preview' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Shortcode Usage', 'gamut-lut-preview' ); ?></h2>
            <p><?php esc_html_e( 'Add the following shortcode to any page or post to display the LUT preview tool:', 'gamut-lut-preview' ); ?></p>
            <p><code>[gamut_lut_preview]</code></p>
            <p><?php esc_html_e( 'The shortcode will only load its scripts and styles on pages where it is used.', 'gamut-lut-preview' ); ?></p>
        </div>
        <?php
    }

    /**
     * Render section description.
     */
    public function render_section_description() {
        echo '<p>' . esc_html__( 'Configure the titles and descriptions displayed on the LUT preview page.', 'gamut-lut-preview' ) . '</p>';
    }

    /**
     * Render a text input field.
     *
     * @param array $args Field arguments containing 'option' key.
     */
    public function render_text_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, '' );
        printf(
            '<input type="text" name="%s" value="%s" class="regular-text">',
            esc_attr( $option ),
            esc_attr( $value )
        );
    }

    /**
     * Render a textarea field.
     *
     * @param array $args Field arguments containing 'option' key.
     */
    public function render_textarea_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, '' );
        printf(
            '<textarea name="%s" rows="4" class="large-text">%s</textarea>',
            esc_attr( $option ),
            esc_textarea( $value )
        );
    }
}
