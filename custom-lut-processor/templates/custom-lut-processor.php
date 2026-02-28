<?php
/*
Template Name: Custom Lut Processor
*/
 get_header(); // Include the header

?>


<?php include(get_template_directory().'/site-top.php'); ?>

<?php
	$lut_title         			= get_option('lut_title');
	$lut_description   			= get_option('lut_description');
	$apply_luts_title  			= get_option('apply_luts_title');
	$apply_luts_description 	= get_option('apply_luts_description');
	$select_image_title 		= get_option('select_image_title');
	$select_image_description	= get_option('select_image_description');
?>
<style type="text/css">
	body{
		background: #171915;
	}
	section{
		margin: 100px 0px;
	}
</style>
<section class="custom-section">
    <div class="container">
        <div class="flex_100">
        	<div class="flex_70">
        		<div class="luts_main">

        			<div class="text_dec desktop_show">
                        <h2><?php echo esc_html($lut_title); ?></h2>
                        <p><?php echo wpautop(esc_html($lut_description)); ?></p>         
                    </div>
        			
    				<?php
        				$args = array(
        				    'post_type' => 'lut_image', // Replace 'lut_image' with your custom post type slug
        				    'posts_per_page' => 1, // Set the limit to 14 items
        				);
        				$query = new WP_Query( $args );

        				if ( $query->have_posts() ) :
        				    while ( $query->have_posts() ) : $query->the_post();
        				        $post_id = get_the_ID();
        				        $featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( $post_id ) );
        				        ?>	
        				        	<div class="lut_img_wrap">
    						            <img id="lut_main_img" src="<?php echo $featured_image_url; ?>" alt="<?php the_title_attribute(); ?>">
    		                            <img id="main_img_hide" src="<?php echo $featured_image_url; ?>" alt="<?php the_title_attribute(); ?>">
        				        	</div>
        				            
        				        <?php
        				    endwhile;
        				    wp_reset_postdata();
        				else :
        				    echo 'No posts found';
        				endif;
    				?>
        			<div>
        				<div id="comparison_slider">
	        				<div class="img-comp-container">
	        				  <div class="img-comp-img first_img img-comp-overlay">
                                <img src="https://gamut.io/wp-content/uploads/2024/02/Apricity3Log.jpg">
                              </div>
                              <div class="img-comp-img result_img">
	        				    <img src="https://gamut.io/wp-content/uploads/2024/02/Apricity3.jpg">
	        				  </div>
	        				</div>
        				</div>
        			</div>
        		</div>
        		<div class="img_select_box">
        			<div class="wd_100">
        				<div class="wd_70">
        					<h3><?php echo esc_html($select_image_title); ?></h3>
        					<p><?php echo wpautop(esc_html($select_image_description)); ?></p>
        				</div>
        				<div class="wd_30">
        					<h4>IMAGE CATEGORY</h4>
        					<?php
	        					$args = array(
	        					    'taxonomy' => 'lut_image_category', 
	        					    'hide_empty' => false, 
	        					    'post_type' => 'lut_image',
	        					);
	        					$categories = get_categories( $args );
        					?>
        					<select id="lutImage_category">
        					    <option value="all_images">All images</option>
        					    <?php foreach ( $categories as $category ) : ?>
        					        <option value="<?php echo esc_attr( $category->slug ); ?>"><?php echo esc_html( $category->name ); ?></option>
        					    <?php endforeach; ?>
        					</select>
        				</div>
        			</div>
        			<div class="luts_media_wrap">
	        			<div class="img_media_grid">
	        				<?php
		        				$args = array(
		        				    'post_type' => 'lut_image', // Replace 'lut_image' with your custom post type slug
		        				    'posts_per_page' => -1, // Set the limit to 14 items
		        				);
		        				$query = new WP_Query( $args );

		        				if ( $query->have_posts() ) :
		        				    while ( $query->have_posts() ) : $query->the_post();
		        				        $post_id = get_the_ID();
		        				        $featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( $post_id ) );
		        				        ?>
		        				        <div class="img_wrapper">
		        				            <img src="<?php echo $featured_image_url; ?>" alt="<?php the_title_attribute(); ?>">
		        				        </div>
		        				        <?php
		        				    endwhile;
		        				    wp_reset_postdata();
		        				else :
		        				    echo 'No posts found';
		        				endif;
	        				?>
	        				
	        			</div>
	        			<div id="img_media_loader" style="display: none;">
	        				<div>
	        					<img src="https://gamut.io/wp-content/uploads/2024/02/loading.gif">
	        				</div>
	        			</div>
        			</div>
        			

        			<script>
        				// jQuery(document).ready(function() {
        				//     // Function to load images based on category
        				//     function loadImages(category) {
        				//     	jQuery('#loader').show();
        				//         jQuery.ajax({
        				//             url: "/wp-admin/admin-ajax.php",
        				//             type: 'post',
        				//             dataType: 'json',
        				//             data: {
        				//                 action: 'get_filtered_posts',
        				//                 category: category,
        				//             },
        				//             success: function(response) {
            //                             console.log(response);
        				//                 jQuery('.img_media_grid').empty();

        				//                 if (response.length > 0) {
            //                                 jQuery('.img_media_grid').removeClass("error_find");
            //                                 if (response.length > 14) {
            //                                     var loadMoreButton = '<button class="load_more_btn">Load More</button>';
            //                                     jQuery('.img_media_grid').after(loadMoreButton);
            //                                     jQuery('.load_more_btn').click(function() {
            //                                         jQuery('.img_media_grid .img_wrapper:hidden').slice(0, 10).slideDown();
            //                                         if (jQuery('.img_media_grid .img_wrapper:hidden').length == 0) {
            //                                             jQuery('.load_more_btn').fadeOut('slow');
            //                                         }
            //                                     });
            //                                 }
            //                                 jQuery.each(response, function(index, post) {
            //                                     var html = '<div class="img_wrapper"' + (index >= 14 ? ' style="display:none;"' : '') + '>';
            //                                     html += '<img src="' + post.image + '" alt="' + post.title + '">';
            //                                     html += '</div>';
            //                                     jQuery('.img_media_grid').append(html);
            //                                 });
            //                             } else {
            //                                 jQuery('.img_media_grid').addClass("error_find");
            //                                 jQuery('.img_media_grid').html('<div class="error_message"><p>No Lut found</p></div>');
            //                                 jQuery('#loader').hide();
            //                             }


        				//             },
        				//         });
        				//     }

        				//     // Initial load of images
        				//     // loadImages('');

        				//     // Change event handler for category select
        				//     jQuery('#lutImage_category').change(function() {
        				//         var selectedCategory = jQuery(this).val();
        				//         if (selectedCategory == 'all_images') {
        				//             loadImages('');
        				//         } else {
        				//             loadImages(selectedCategory);
        				//         }
        				//     });
        				// });
        				jQuery(document).ready(function() {
        				    // Function to load images based on category
        				    function loadImages(category) {
        				        jQuery('#img_media_loader').show();
                                jQuery(".load_more_btn").remove();
        				        jQuery.ajax({
        				            url: "/wp-admin/admin-ajax.php",
        				            type: 'post',
        				            dataType: 'json',
        				            data: {
        				                action: 'get_filtered_posts',
        				                category: category,
        				            },
        				            success: function(response) {
        				                jQuery('.img_media_grid').empty();

                                        console.log(response);

        				                if (response.length > 0) {
                                            jQuery('.img_media_grid').removeClass("error_find");
                                            if (response.length > 14) {
                                                var loadMoreButton = '<button class="load_more_btn">Load More</button>';
                                                jQuery('.img_media_grid').after(loadMoreButton);
                                                jQuery('.load_more_btn').click(function() {
                                                    jQuery('.img_media_grid .img_wrapper:hidden').slice(0, 10).slideDown();
                                                    if (jQuery('.img_media_grid .img_wrapper:hidden').length == 0) {
                                                        jQuery('.load_more_btn').fadeOut('slow');
                                                    }
                                                });
                                            }
                                            jQuery.each(response, function(index, post) {
                                                var html = '<div class="img_wrapper"' + (index >= 14 ? ' style="display:none;"' : '') + '>';
                                                html += '<img src="' + post.image + '" alt="' + post.title + '">';
                                                html += '</div>';
                                                jQuery('.img_media_grid').append(html);
                                            });
                                        } else {
                                            jQuery('.img_media_grid').addClass("error_find");
                                            jQuery('.img_media_grid').html('<div class="error_message"><p>No Lut found</p></div>');
                                            jQuery('#loader').hide();
                                        }
        				            },
        				            complete: function() {
        				                // Hide img_media_loader after request is completed
        				                jQuery('#img_media_loader').hide();
        				            }
        				        });
        				    }

        				    // Initial load of images
        				    loadImages('');

        				    // Change event handler for category select
        				    jQuery('#lutImage_category').change(function() {
        				        var selectedCategory = jQuery(this).val();
        				        if (selectedCategory == 'all_images') {
        				            loadImages('');
        				        } else {
        				            loadImages(selectedCategory);
        				        }
        				    });
        				});

        			</script>
        		</div>	
        	</div>
        	
        	<!-- side bar start-->
        	<div class="flex_30">
        		<div class="luts_filter">
        			<div class="apply_luts">
                        <div class="text_dec mobile_show">
                            <h2><?php echo esc_html($lut_title); ?></h2>
                            <p><?php echo wpautop(esc_html($lut_description)); ?></p>         
                        </div>
        				<h3><?php echo esc_html($apply_luts_title); ?></h3>
        				<p><?php echo wpautop(esc_html($apply_luts_description)); ?></p>	
        			</div>
        			<!-- first lut start from here -->
        			<div class="first_lut_wrap">
	        			<div class="luts_collection">
	        				<h4>LUT COLLECTION</h4>
	        				<!-- show there lut design cats -->
	        				<div class="flex_options_wrap">
	        					<div class="inner_flex_option">
	        						<select id="first_lutOption">
	        						    <option value="">Choose LUT Collection</option>
	        						    <?php
	        						    $terms = get_terms(array(
                                            'taxonomy'   => 'lut_design_category', 
                                            'hide_empty' => false, 
                                            'post_type'  => 'lut-design',
                                            'number'     => 14,
                                            'orderby'    => 'name', // Sort terms alphabetically by name
                                        ));

                                        foreach ($terms as $term) {
                                            $category_id = get_term_meta($term->term_id, 'category_id', true);
                                            echo '<option value="' . esc_attr($term->slug) . '" cat_id="' . esc_attr($category_id) . '">' . esc_html($term->name) . '</option>';
                                        }
	        						    ?>
	        						</select>
	        						<button id="cart_btn">ADD TO CART</button>
                                    <div id="add_cart_img" style="display: none;">
                                        <div>
                                            <img src="https://gamut.io/wp-content/uploads/2024/02/loading.gif">
                                        </div>
                                    </div> 
	        					</div>	
	        					<div id="lutOptions_loader" style="display: none;">
	        						<div>
	        							<img src="https://gamut.io/wp-content/uploads/2024/02/loading.gif">
	        						</div>
	        					</div>	
	        				</div>
	        					
	        			</div>
	        			<div class="select_lut">
							<h4>SELECT LUT</h4>
							<!-- items from selected lut design cat -->
							<select id="first_lut_items">
                                <option value="">Select a LUT</option>
                            </select>


                            <div id="cube_lut" style="display: none;">
                                <div>
                                    <img src="https://gamut.io/wp-content/uploads/2024/02/loading.gif">
                                </div>
                            </div>  
                            

	        			</div>
	        			<script>
	        				jQuery(document).ready(function($) {
	        				    // Add the empty option when the page loads
	        				    $('#first_lut_items').html('<option value="">Select a LUT</option>');

	        				    $('#first_lutOption').on("change",function() {
	        				        var selectedOption = $(this).find(":selected");
                                    var selectedCategory = selectedOption.val();
                                    console.log(selectedCategory);

                                    // Get the value of the 'cat_id' attribute of the selected option
                                    var cat_id = selectedOption.attr("cat_id");
                                    console.log(cat_id);

                                    jQuery('#cart_btn').hide();
                                    jQuery('.first_lut_wrap .select_lut').hide();
                                    // console.log(selectedCategory);
	        				        $.ajax({
	        				            url: '<?php echo admin_url('admin-ajax.php'); ?>',
	        				            type: 'post',
	        				            data: {
	        				                action: 'get_lut_posts',
	        				                category: selectedCategory
	        				            },
	        				            beforeSend: function() {
	        				                // Show the loader before the AJAX request is sent
	        				                $('#lutOptions_loader').show();
	        				            },
	        				            success: function(response) {
                                            // console.log(response);
	        				                $('#first_lut_items').html(response);
                                            jQuery('.first_lut_wrap .select_lut').show();
                                            if (cat_id !== '') {
                                                jQuery('#cart_btn').show();
                                                console.log("show");
                                            }
	        				            },
	        				            complete: function() {
	        				                // Hide the loader after the AJAX request is completed
	        				                $('#lutOptions_loader').hide();
	        				            }
	        				        });
	        				    });
	        				});
	        			</script>
	        			<div class="adjust_intensity">
							<h4>ADJUST INTENSITY</h4>
	        				<input class="range-slider__range" type="range" value="100" min="0" max="100">
	        				<span class="range-slider__value">0</span>
	        				<div class="checbox">
	        					<input type="checkbox" id="firstLut_checkbox">
	        					<label for="firstLut_checkbox">Show Before and After</label>
	        				</div>
	        			</div>
        			</div>
        			<!-- secondary lut start from here -->
        			<div class="secondary_lut_wrap">
        				<h5 class="secondary_lut_title">Secondary LUT</h5>
	        			<div class="luts_collection">
	        				<h4>LUT COLLECTION</h4>
	        				<div class="flex_options_wrap">
		        				<div class="inner_flex_option">
		        					<select id="secondary_lutOption">
		        						<option value="">Choose LUT Collection</option>
		        						<?php
			        					    $terms = get_terms(array(
			        					        'taxonomy' => 'lut_design_category', 
			        					        'hide_empty' => false, 
			        					        'post_type' => 'lut-design',
			        					        'number' => 14,
			        					    ));
			        					    foreach ($terms as $term) {
			        					        echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
			        					    }
			        					?>
		        					</select>
		        					<!-- <button>ADD TO CART</button>	 -->
		        				</div>
		        				<div id="lutOptions_loader2" style="display: none;">
		        					<div>
		        						<img src="https://gamut.io/wp-content/uploads/2024/02/loading.gif">
		        					</div>
		        				</div>		
	        				</div>
	        					
	        			</div>
	        			<div class="select_lut">
							<h4>SELECT LUT</h4>
							<select id="secondary_lut_items">
								<option>Select a LUT</option>
							</select>		        				
	        			</div>
	        			<script>
	        				// $('#secondary_lutOption').change(function() {
	        				//     var selectedCategory = $(this).val();
	        				//     $.ajax({
	        				//         url: '<?php echo admin_url('admin-ajax.php'); ?>',
	        				//         type: 'post',
	        				//         data: {
	        				//             action: 'get_lut_posts',
	        				//             category: selectedCategory,
	        				//             dropdownId: '#secondary_lut_items'
	        				//         },
	        				//         success: function(response) {
	        				//             $('#secondary_lut_items').html(response);
	        				//         }
	        				//     });
	        				// });
	        				jQuery(document).ready(function($) {
	        				    // Add the empty option when the page loads
	        				    $('#secondary_lut_itemss').html('<option value="">Select a LUT</option>');

	        				    $('#secondary_lutOption').change(function() {
	        				        var selectedCategory = $(this).val();
	        				        $.ajax({
	        				            url: '<?php echo admin_url('admin-ajax.php'); ?>',
	        				            type: 'post',
	        				            data: {
	        				                action: 'get_lut_posts',
	        				                category: selectedCategory
	        				            },
	        				            beforeSend: function() {
	        				                // Show the loader before the AJAX request is sent
	        				                $('#lutOptions_loader2').show();
	        				            },
	        				            success: function(response) {
	        				                $('#secondary_lut_items').html(response);
	        				            },
	        				            complete: function() {
	        				                // Hide the loader after the AJAX request is completed
	        				                $('#lutOptions_loader2').hide();
	        				            }
	        				        });
	        				    });
	        				});

	        			</script>
	        			<div class="adjust_intensity">
							<h4>ADJUST INTENSITY</h4>
	        				<input class="range-slider__range" type="range" value="100" min="0" max="100">
	        				<span class="range-slider__value">0</span>
	        				<div class="checbox">
	        					<input type="checkbox" id="secondaryLut_checkbox">
	        					<label for="secondaryLut_checkbox">Show Before and After</label>
	        				</div>
	        			</div>
        			</div>
	
        		</div>
        	</div>
        	<!-- side bar end -->
        </div>
    </div>	
</section>
<script type="text/javascript">

</script>

<script>

</script>
<?php

get_footer(); // Include the footer

include(get_template_directory().'/site-bottom.php'); 