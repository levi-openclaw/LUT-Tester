<?php
// Add a custom menu page to the WordPress admin
function custom_menu_page() {
    add_menu_page(
        'LUT Text', // Page title
        'LUT Text', // Menu title
        'manage_options', // Capability required to access the page
        'lut-text', // Page slug
        'custom_menu_page_callback', // Callback function to render the page
        'dashicons-admin-generic', // Icon URL or dashicon class
        7 // Position in the menu (7 is the top position)
    );
}
add_action('admin_menu', 'custom_menu_page');

// Callback function to render the custom menu page
function custom_menu_page_callback() {
    ?>
    <div class="wrap">
        <h2>Add Your Lut Text</h2>
        <form method="post" action="options.php">
            <?php settings_fields('custom_page_settings'); ?>
            <?php do_settings_sections('custom_page_settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register custom settings
function custom_settings() {
    register_setting('custom_page_settings', 'lut_title');
    register_setting('custom_page_settings', 'lut_description');
    register_setting('custom_page_settings', 'apply_luts_title');
    register_setting('custom_page_settings', 'apply_luts_description');
    register_setting('custom_page_settings', 'select_image_title');
    register_setting('custom_page_settings', 'select_image_description');
}
add_action('admin_init', 'custom_settings');

function custom_settings_fields() {
    add_settings_section(
        'custom_page_settings_section', // Section ID
        '', // Section title
        'custom_settings_section_callback', // Callback function to render the section
        'custom_page_settings' // Page slug
    );

    $fields = array(
        'lut_title' => 'LUT Title',
        'lut_description' => 'LUT Description',
        'apply_luts_title' => 'Apply LUTs Title',
        'apply_luts_description' => 'Apply LUTs Description',
        'select_image_title' => 'Select an Image Title',
        'select_image_description' => 'Select an Image Description',
    );

    foreach ($fields as $key => $label) {
        add_settings_field(
            $key, // Field ID
            $label, // Field title
            'custom_field_callback', // Callback function to render the field
            'custom_page_settings', // Page slug
            'custom_page_settings_section', // Section ID
            array('key' => $key) // Additional arguments
        );
    }
}
add_action('admin_init', 'custom_settings_fields');

function custom_field_callback($args) {
    $key = $args['key'];
    $value = get_option($key);

    if (strpos($key, 'title') !== false) {
        echo '<input type="text" name="' . $key . '" value="' . esc_attr($value) . '" size="48" />';
    } elseif (strpos($key, 'description') !== false) {
        echo '<textarea name="' . $key . '" cols="50" rows="7">' . esc_textarea($value) . '</textarea>';
    }
}