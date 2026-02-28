<?php


// Register Custom Post Type
function create_lut_image_post_type() {

    $labels = array(
        'name'                  => _x( 'LUT Images', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'LUT Image', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'LUT Images', 'text_domain' ),
        'name_admin_bar'        => __( 'LUT Image', 'text_domain' ),
        'archives'              => __( 'Item Archives', 'text_domain' ),
        'attributes'            => __( 'Item Attributes', 'text_domain' ),
        'parent_item_colon'     => __( 'Parent Item:', 'text_domain' ),
        'all_items'             => __( 'All Items', 'text_domain' ),
        'add_new_item'          => __( 'Add New Item', 'text_domain' ),
        'add_new'               => __( 'Add New', 'text_domain' ),
        'new_item'              => __( 'New Item', 'text_domain' ),
        'edit_item'             => __( 'Edit Item', 'text_domain' ),
        'update_item'           => __( 'Update Item', 'text_domain' ),
        'view_item'             => __( 'View Item', 'text_domain' ),
        'view_items'            => __( 'View Items', 'text_domain' ),
        'search_items'          => __( 'Search Item', 'text_domain' ),
        'not_found'             => __( 'Not found', 'text_domain' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
        'featured_image'        => __( 'Featured Image', 'text_domain' ),
        'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
        'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
        'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
        'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
        'uploaded_to_this_item' => __( 'Uploaded to this item', 'text_domain' ),
        'items_list'            => __( 'Items list', 'text_domain' ),
        'items_list_navigation' => __( 'Items list navigation', 'text_domain' ),
        'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'LUT Image', 'text_domain' ),
        'description'           => __( 'Post Type Description', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'taxonomies'            => array( 'lut_image_category', 'post_tag' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'page',
    );
    register_post_type( 'lut_image', $args );
    
    // Associate the category taxonomy with the custom post type
    // register_taxonomy_for_object_type( 'category', 'lut_image' );

    // Create custom taxonomy for LUT Images
    $labels = array(
        'name'              => _x( 'Categories', 'taxonomy general name', 'text_domain' ),
        'singular_name'     => _x( 'Category', 'taxonomy singular name', 'text_domain' ),
        'search_items'      => __( 'Search Categories', 'text_domain' ),
        'all_items'         => __( 'All Categories', 'text_domain' ),
        'parent_item'       => __( 'Parent Category', 'text_domain' ),
        'parent_item_colon' => __( 'Parent Category:', 'text_domain' ),
        'edit_item'         => __( 'Edit Category', 'text_domain' ),
        'update_item'       => __( 'Update Category', 'text_domain' ),
        'add_new_item'      => __( 'Add New Category', 'text_domain' ),
        'new_item_name'     => __( 'New Category Name', 'text_domain' ),
        'menu_name'         => __( 'Categories', 'text_domain' ),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'lut_image_category' ),
    );

    register_taxonomy( 'lut_image_category', array( 'lut_image' ), $args );
}
add_action( 'init', 'create_lut_image_post_type', 0 );





function create_lut_designs_post_type() {
    $labels = array(
        'name'               => _x( 'LUT Designs', 'post type general name', 'your-text-domain' ),
        'singular_name'      => _x( 'LUT Design', 'post type singular name', 'your-text-domain' ),
        'menu_name'          => _x( 'LUT Designs', 'admin menu', 'your-text-domain' ),
        'name_admin_bar'     => _x( 'LUT Design', 'add new on admin bar', 'your-text-domain' ),
        'add_new'            => _x( 'Add New', 'LUT Design', 'your-text-domain' ),
        'add_new_item'       => __( 'Add New LUT Design', 'your-text-domain' ),
        'new_item'           => __( 'New LUT Design', 'your-text-domain' ),
        'edit_item'          => __( 'Edit LUT Design', 'your-text-domain' ),
        'view_item'          => __( 'View LUT Design', 'your-text-domain' ),
        'all_items'          => __( 'All LUT Designs', 'your-text-domain' ),
        'search_items'       => __( 'Search LUT Designs', 'your-text-domain' ),
        'parent_item_colon'  => __( 'Parent LUT Designs:', 'your-text-domain' ),
        'not_found'          => __( 'No LUT Designs found.', 'your-text-domain' ),
        'not_found_in_trash' => __( 'No LUT Designs found in Trash.', 'your-text-domain' )
    );

    $args = array(
        'labels'             => $labels,
        'description'        => __( 'Description.', 'your-text-domain' ),
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'lut-design' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 6,
        'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
        'taxonomies'         => array( 'lut_design_category', 'post_tag' ), // Add categories and tags
    );

    register_post_type( 'lut-design', $args );

    // Associate the 'category' taxonomy with the 'lut-design' post type
    // register_taxonomy_for_object_type( 'category', 'lut-design' );

    // Create custom taxonomy for LUT Designs
    $labels = array(
        'name'              => _x( 'Categories', 'taxonomy general name', 'text_domain' ),
        'singular_name'     => _x( 'Category', 'taxonomy singular name', 'text_domain' ),
        'search_items'      => __( 'Search Categories', 'text_domain' ),
        'all_items'         => __( 'All Categories', 'text_domain' ),
        'parent_item'       => __( 'Parent Category', 'text_domain' ),
        'parent_item_colon' => __( 'Parent Category:', 'text_domain' ),
        'edit_item'         => __( 'Edit Category', 'text_domain' ),
        'update_item'       => __( 'Update Category', 'text_domain' ),
        'add_new_item'      => __( 'Add New Category', 'text_domain' ),
        'new_item_name'     => __( 'New Category Name', 'text_domain' ),
        'menu_name'         => __( 'Categories', 'text_domain' ),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'lut_design_category' ),
    );

    register_taxonomy( 'lut_design_category', array( 'lut-design' ), $args );
}
add_action( 'init', 'create_lut_designs_post_type' );


// Add custom fields for Product ID
function add_category_id_field() {
    ?>
    <div class="form-field">
        <label for="category-id"><?php _e( 'Product ID', 'text_domain' ); ?></label>
        <input type="text" name="category-id" id="category-id" value="">
        <p class="description"><?php _e( 'Enter the Product ID.', 'text_domain' ); ?></p>
    </div>
    <?php
}
add_action( 'lut_design_category_add_form_fields', 'add_category_id_field' );

// Save custom field value
function save_category_id_field( $term_id ) {
    if ( isset( $_POST['category-id'] ) ) {
        $category_id = sanitize_text_field( $_POST['category-id'] );
        update_term_meta( $term_id, 'category_id', $category_id );
    }
}
add_action( 'created_lut_design_category', 'save_category_id_field' );

// Edit custom fields for Product ID
function edit_category_id_field( $term ) {
    $category_id = get_term_meta( $term->term_id, 'category_id', true );
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="category-id"><?php _e( 'Product ID', 'text_domain' ); ?></label></th>
        <td>
            <input type="text" name="category-id" id="category-id" value="<?php echo esc_attr( $category_id ); ?>">
            <p class="description"><?php _e( 'Enter the Product ID.', 'text_domain' ); ?></p>
        </td>
    </tr>
    <?php
}
add_action( 'lut_design_category_edit_form_fields', 'edit_category_id_field' );

// Update custom field value
function update_category_id_field( $term_id ) {
    if ( isset( $_POST['category-id'] ) ) {
        $category_id = sanitize_text_field( $_POST['category-id'] );
        update_term_meta( $term_id, 'category_id', $category_id );
    }
}
add_action( 'edited_lut_design_category', 'update_category_id_field' );



/*

// // Add meta box for uploading .cube files
// function add_lut_cube_file_meta_box() {
//     add_meta_box(
//         'lut_cube_file_meta_box',
//         'Upload .cube File',
//         'render_lut_cube_file_meta_box',
//         'lut-design',
//         'normal',
//         'high'
//     );
// }
// add_action('add_meta_boxes', 'add_lut_cube_file_meta_box');

// // Render HTML for the meta box
// function render_lut_cube_file_meta_box($post) {
//     // Add a nonce field so we can check for it later.
//     wp_nonce_field('save_lut_cube_file_data', 'lut_cube_file_nonce');

//     // Display the current value of the meta box
//     $lut_cube_file = get_post_meta($post->ID, '_lut_cube_file', true);

//     $post_id = get_the_ID(); // Get the current post ID

//     $lut_cube_file = get_post_meta($post_id, '_lut_cube_file', true);

//     echo 'Post ID: ' . $post_id . '<br>';
//     echo 'LUT Cube File: ' . $lut_cube_file . '<br>';

//     ?>
//     <p>
//         <label for="lut_cube_file">Upload .cube File:</label><br />
//         <input type="file" id="lut_cube_file" name="lut_cube_file" accept=".cube" />

//         <?php if (!empty($lut_cube_file)) : ?>
//             <br />
//             <span>Uploaded File: <a href="<?php echo esc_url($lut_cube_file); ?>" target="_blank"><?php echo esc_html(basename($lut_cube_file)); ?></a></span>
//         <?php endif; ?>
//     </p>
//     <script type="text/javascript">
//         jQuery(document).ready(function($) {
//             $('#post').attr('enctype', 'multipart/form-data');
//             $('#post').attr('encoding', 'multipart/form-data'); // For older browsers
//         });
//     </script>
//     <?php
// }

// // Save the uploaded file
// function save_lut_cube_file_meta_data($post_id) {
//     // Check if nonce is set.
//     if (!isset($_POST['lut_cube_file_nonce'])) {
//         return;
//     }

//     // Verify that the nonce is valid.
//     if (!wp_verify_nonce($_POST['lut_cube_file_nonce'], 'save_lut_cube_file_data')) {
//         return;
//     }

//     // Check if this is an autosave.
//     if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
//         return;
//     }

//     // Check the user's permissions.
//     if (isset($_POST['post_type']) && 'lut-design' == $_POST['post_type']) {
//         if (!current_user_can('edit_post', $post_id)) {
//             return;
//         }
//     }
//     // print_r($_FILES['lut_cube_file']);
//     // echo "done";
//     // echo "<br>";
//     // die("l;fklr");

//     // Save the file
//     if (!empty($_FILES['lut_cube_file']['name'])) {
//         $file = $_FILES['lut_cube_file'];

//         // Upload the file
//         $upload_overrides = array('test_form' => false);
//         $uploaded_file = wp_handle_upload($file, $upload_overrides);

//         // print_r($uploaded_file);

//         // die("here");

//         if (!isset($uploaded_file['error'])) {
//             // File uploaded successfully, save its path to the post meta
//             update_post_meta($post_id, '_lut_cube_file', $uploaded_file['url']);
//         } else {
//             // Error uploading the file, display an error message
//             $upload_error_message = $uploaded_file['error'];
//             // Handle the error as you see fit
//         }
//     }
// }
// add_action('save_post', 'save_lut_cube_file_meta_data');


// function custom_upload_mimes($existing_mimes) {
//     $existing_mimes['cube'] = 'application/octet-stream'; // Adjust MIME type as needed
//     return $existing_mimes;
// }
// add_filter('upload_mimes', 'custom_upload_mimes');


*/


add_action('wp_ajax_my_custom_ajax_action', 'my_custom_ajax_function');
// Add action for non-logged in users
add_action('wp_ajax_nopriv_my_custom_ajax_action', 'my_custom_ajax_function');


function my_custom_ajax_function() {
    // Extract the data directly from the AJAX request
    $custom_image_paths = isset($_POST['custom_image_paths']) ? $_POST['custom_image_paths'] : [];
    $custom_cube_files = isset($_POST['custom_cube_files']) ? $_POST['custom_cube_files'] : [];

    $custom_cube_files = get_post_meta($custom_cube_files, '_lut_cube_file_url', true);

    $custom_cube_files = [$custom_cube_files];

    // print_r($lut_cube_file_url);
    // echo json_encode(array('image_url' => $lut_cube_file_url));
    // die;


    $images = isset($_POST['custom_image_paths']) ? $_POST['custom_image_paths'] : [];

    // Assuming you're downloading and sending the first image as a blob
    $image_data = file_get_contents($images[0]);

    if ($image_data !== false) {
        // Convert the image data to base64
        $base64_image = base64_encode($image_data);

        // Construct the response array
        $response = array(
            'base64_image' => $base64_image
        );

        // Set appropriate headers and send the JSON response
        // header('Content-Type: application/json');
        // echo json_encode($response);
    } else {
        // Handle errors if the image couldn't be fetched
        echo json_encode(array('error' => 'Failed to fetch image.'));
    }

    // Prepare the data as an array
    $data = array(
        "image_url" => $custom_image_paths,
        "lut_url" => $custom_cube_files,
        'image' => $response, // Adjust based on your API
    );

    // Encode the data as JSON
    $jsonData = json_encode($data);

    // Initialize cURL session
    $curl = curl_init();

    // Set cURL options
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://16.170.65.20:8000/process-image',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));

    // Execute cURL request and capture the response
    // Execute the cURL request
    $response = curl_exec($curl);

    // Close cURL session
    curl_close($curl);

    // Convert the JSON response into a PHP associative array
    // $response_array = json_decode($response, true);

    // // Check if decoding was successful
    // if ($response_array === null && json_last_error() !== JSON_ERROR_NONE) {
    //     // Handle JSON decoding error
    //     $error_message = 'Error decoding JSON response.';
    //     $response_array = array('error' => $error_message);
    // }

    // // Encode the response array into base64
    // $base64_encoded_response = base64_encode(json_encode($response_array));

    // // Set appropriate headers to indicate JSON content
    // header('Content-Type: application/json');

    // // Encode the base64 encoded response as JSON
    // echo json_encode(array('base64_response' => $base64_encoded_response));
    // echo json_encode($response);
    $response_data = json_decode($response, true);

    if (isset($response_data['base64_image'])) {
        $upload_dir = wp_upload_dir(); // Get WordPress upload directory paths
        $image_data = base64_decode($response_data['base64_image']);

        // Create a temporary file in the WordPress uploads directory
        $tmp_file = tempnam($upload_dir['path'], 'upload_');
        file_put_contents($tmp_file, $image_data);

        // Mimic the $_FILES array format
        $file_array = array(
            'name' => 'image'.rand().'.jpg', // Generate a unique file name
            'type' => 'image/jpeg',
            'tmp_name' => $tmp_file,
            'error' => 0,
            'size' => filesize($tmp_file),
        );
        
        // Set an array for the upload to prevent deleting the temporary file after import
        $overrides = array('test_form' => false, 'test_size' => true, 'test_upload' => true, 'test_type' => false);

        // Upload the file to the Media Library
        $uploaded_file = media_handle_sideload($file_array, 0, null, $overrides);

        if (is_wp_error($uploaded_file)) {
            // Handle error
            error_log($uploaded_file->get_error_message());
        } else {
            // Get the attachment URL. This is the URL you can use to display the image.
            $image_url = wp_get_attachment_url($uploaded_file);
            
            // You might want to delete the temporary file at this point.
            @unlink($tmp_file);
            
            echo json_encode(array('image_url' => $image_url));

            $current_date = date("Y-m-d H:i:s");

            $data = array(
                'image_url' => $image_url,
                'date' => $current_date,
            );

            $json_data = json_encode($data);

            $current_directory = plugin_dir_path(__FILE__);
            $file_path = $current_directory . 'image_data.txt';

            if (!file_exists($file_path)) {
                $file_handle = fopen($file_path, 'w');
                fclose($file_handle);
            }

            if (is_writable($file_path)) {
                // Read existing data from the file
                $existing_data = file_get_contents($file_path);
                
                // Decode existing JSON data or initialize an empty array
                $image_data = json_decode($existing_data, true) ?? array();

                // Append the new data to the array
                $image_data[] = $data;

                // Encode the updated data back to JSON format
                $updated_json_data = json_encode($image_data);

                // Write the updated JSON data back to the file
                file_put_contents($file_path, $updated_json_data);
            } 
        }
    }
    
    wp_die();
}




function add_lut_cube_file_meta_box() {
    add_meta_box(
        'lut_cube_file_meta_box',
        'Upload .cube File',
        'render_lut_cube_file_meta_box',
        'lut-design',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_lut_cube_file_meta_box');


function render_lut_cube_file_meta_box($post) {
    wp_nonce_field('ajax_lut_cube_file_nonce', 'lut_cube_file_nonce');

    // Retrieve the current file URL if it exists
    $lut_cube_file_url = get_post_meta($post->ID, '_lut_cube_file_url', true);

    ?>
    <input type="file" id="lut_cube_file" name="lut_cube_file" accept=".cube">
    <button id="upload_lut_cube_file_btn">Upload File</button>
    <?php if (!empty($lut_cube_file_url)): ?>
        <p id="uploaded_file_link">Uploaded File: <a href="<?php echo esc_url($lut_cube_file_url); ?>" target="_blank">Click here to view the file</a></p>
    <?php endif; ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#upload_lut_cube_file_btn').click(function(e) {
                e.preventDefault();
                var file_data = $('#lut_cube_file').prop('files')[0];
                var form_data = new FormData();
                form_data.append('lut_cube_file', file_data);
                form_data.append('action', 'upload_lut_cube_file');
                form_data.append('post_id', '<?php echo $post->ID; ?>');
                form_data.append('lut_cube_file_nonce', '<?php echo wp_create_nonce('ajax_lut_cube_file_nonce'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    contentType: false,
                    processData: false,
                    data: form_data,
                    success: function(response) {
                        if(response.success) {
                            // Display the uploaded file link
                            $('#uploaded_file_link').remove(); // Remove previous link if exists
                            $('<p id="uploaded_file_link">Uploaded File: <a href="' + response.data.file_url + '" target="_blank">Click here to view the file</a></p>').insertAfter('#upload_lut_cube_file_btn');
                            alert(response.data.message);
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
        });
    </script>
    <?php
}


function handle_lut_cube_file_ajax_upload() {
    // Check nonce for security
    check_ajax_referer('ajax_lut_cube_file_nonce', 'lut_cube_file_nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (isset($_FILES['lut_cube_file'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $file = $_FILES['lut_cube_file'];
        $upload_overrides = ['test_form' => false];
        $uploaded_file = wp_handle_upload($file, $upload_overrides);

        if (!isset($uploaded_file['error']) && $post_id) {
            update_post_meta($post_id, '_lut_cube_file_url', $uploaded_file['url']);
            wp_send_json_success(['message' => 'File uploaded successfully.', 'file_url' => $uploaded_file['url']]);
        } else {
            wp_send_json_error($uploaded_file['error']);
        }
    } else {
        wp_send_json_error('No file uploaded.');
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}

// Hook for authenticated users
add_action('wp_ajax_upload_lut_cube_file', 'handle_lut_cube_file_ajax_upload');


function my_custom_mime_types($mimes) {
    // Add custom mime types here
    $mimes['cube'] = 'text/plain'; // This MIME type can be adjusted if 'text/plain' is not appropriate

    return $mimes;
}
add_filter('upload_mimes', 'my_custom_mime_types');




function check_and_update_images() {
    // Get the absolute path to the directory of the current file
    $current_directory = plugin_dir_path(__FILE__);

    // Construct the path to the text file within the plugin directory
    $file_path = $current_directory . 'image_data.txt';

    // Check if the file exists
    if (file_exists($file_path)) {
        $file_contents = file_get_contents($file_path);

        // Decode the JSON data
        $image_data = json_decode($file_contents, true);

        // Get the current time
        $current_time = time();

        foreach ($image_data as $key => $image) {
            // Get the timestamp of the image's date
            $image_time = strtotime($image['date']);

            $time_difference = $current_time - $image_time;
            if ($time_difference > 300) {
                // Get the attachment ID of the image
                $attachment_id = attachment_url_to_postid($image['image_url']);

                // Delete the image from the media library
                if ($attachment_id) {
                    wp_delete_attachment($attachment_id);
                    // Remove the entry from the image data array
                    unset($image_data[$key]);
                    // Encode the updated data back to JSON format
                    $updated_json_data = json_encode($image_data);
                    // Write the updated JSON data back to the file
                    file_put_contents($file_path, $updated_json_data);
                }
            }
        }
    }
}

// Hook the function to the 'init' action, so it runs when WordPress initializes
add_action('init', 'check_and_update_images');


add_action('wp_ajax_add_to_cart', 'add_to_cart_callback');
add_action('wp_ajax_nopriv_add_to_cart', 'add_to_cart_callback');

function add_to_cart_callback() {
    // Get product ID and quantity from AJAX request
    $product_id = $_POST['product_id'];
    $quantity = 1;

    // Check if the product is already in the cart
    $cart_item_key = WC()->cart->find_product_in_cart($product_id);

    if ($cart_item_key) {
        // If the product is already in the cart, update the quantity
        WC()->cart->set_quantity($cart_item_key, $quantity);
        $message = 'Quantity updated in cart successfully.';
    } else {
        // If the product is not in the cart, add it
        WC()->cart->add_to_cart($product_id, $quantity);
        $message = 'Product added to cart successfully.';
    }

    // Return success message
    $response = array(
        'success' => true,
        'message' => $message
    );
    wp_send_json_success($response);
}

