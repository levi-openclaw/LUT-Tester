// Comparision Slider 
function initComparisons() {
  var x, i;
  /*find all elements with an "overlay" class:*/
  x = document.getElementsByClassName("img-comp-overlay");
  for (i = 0; i < x.length; i++) {
    /*once for each "overlay" element:
    pass the "overlay" element as a parameter when executing the compareImages function:*/
    compareImages(x[i]);
  }
  function compareImages(img) {
    var slider, img, clicked = 0, w, h;
    /*get the width and height of the img element*/
    w = img.offsetWidth;
    h = img.offsetHeight;
    console.log(" slider offset height " + img.offsetHeight);
    /*set the width of the img element to 50%:*/
    img.style.width = (w / 2) + "px";
    /*create slider:*/
    slider = document.createElement("DIV");
    slider.setAttribute("class", "img-comp-slider");
    /*insert slider*/
    img.parentElement.insertBefore(slider, img);
    /*position the slider in the middle:*/
    slider.style.top = (h / 2) - (slider.offsetHeight / 2) + "px";
    console.log("Height of image " + slider.style.top);
    slider.style.left = ((w / 2) - (slider.offsetWidth / 2)) + "px";
    /*execute a function when the mouse button is pressed:*/
    slider.addEventListener("mousedown", slideReady);
    /*and another function when the mouse button is released:*/
    window.addEventListener("mouseup", slideFinish);
    /*or touched (for touch screens:*/
    slider.addEventListener("touchstart", slideReady);
    /*and released (for touch screens:*/
    window.addEventListener("touchend", slideFinish);
    function slideReady(e) {
      /*prevent any other actions that may occur when moving over the image:*/
      e.preventDefault();
      /*the slider is now clicked and ready to move:*/
      clicked = 1;
      /*execute a function when the slider is moved:*/
      window.addEventListener("mousemove", slideMove);
      window.addEventListener("touchmove", slideMove);
    }
    function slideFinish() {
      /*the slider is no longer clicked:*/
      clicked = 0;
    }
    function slideMove(e) {
      var pos;
      /*if the slider is no longer clicked, exit this function:*/
      if (clicked == 0) return false;
      /*get the cursor's x position:*/
      pos = getCursorPos(e)
      /*prevent the slider from being positioned outside the image:*/
      if (pos < 0) pos = 0;
      if (pos > w) pos = w;
      /*execute a function that will resize the overlay image according to the cursor:*/
      slide(pos);
    }
    function getCursorPos(e) {
      var a, x = 0;
      e = (e.changedTouches) ? e.changedTouches[0] : e;
      /*get the x positions of the image:*/
      a = img.getBoundingClientRect();
      /*calculate the cursor's x coordinate, relative to the image:*/
      x = e.pageX - a.left;
      /*consider any page scrolling:*/
      x = x - window.pageXOffset;
      return x;
    }
    function slide(x) {
      /*resize the image:*/
      img.style.width = x + "px";
      /*position the slider:*/
      slider.style.left = img.offsetWidth - (slider.offsetWidth / 2) + "px";
    }
  }
}

// initComparisons();  
initComparisons();

// Range slider
jQuery(document).ready(function($) {
    var slider = $(".range-slider__range");
    var output = $(".range-slider__value");

    output.text(slider.val());

    // Function to set slider background color based on checkbox state
    function setSliderBackground() {
        if ($("#firstLut_checkbox").is(':checked')) {
            $('.adjust_intensity').addClass('active');
            var initialOpacity = (slider.val() - slider.attr('min')) / (slider.attr('max') - slider.attr('min')) * 100;
            slider.css('background', 'linear-gradient(to right, #48421c 0%, #48421c ' + initialOpacity + '%, rgb(215, 220, 223) ' + initialOpacity + '%, rgb(215, 220, 223) 100%)');
        } else {
             $('.adjust_intensity').removeClass('active');
            var initialOpacity = (slider.val() - slider.attr('min')) / (slider.attr('max') - slider.attr('min')) * 100;
            slider.css('background', 'linear-gradient(to right, #A09555 0%, #A09555 ' + initialOpacity + '%, #d7dcdf ' + initialOpacity + '%, #d7dcdf 100%)');
           
        }
    }

    // Slider input event handler
    slider.on('input', function() {
        output.text(this.value);
        var value = (this.value - this.min) / (this.max - this.min) * 100;
        this.style.background = 'linear-gradient(to right, #A09555 0%, #A09555 ' + value + '%, #d7dcdf ' + value + '%, #d7dcdf 100%)';
        var opacity = value / 100;
        // jQuery('#main_img_hide').css('opacity', 1 - opacity);
        jQuery('#lut_main_img').css('opacity', opacity);
        setSliderBackground(); // Call the function to set background color based on checkbox state
    });

    // Checkbox change event handler
    $("#firstLut_checkbox").on("change", function() {
        setSliderBackground(); // Call the function to set background color based on checkbox state
    });

    // Call the function to set initial background color
    setSliderBackground();
});


// Filters 

jQuery(document).ready(function() {
    jQuery(document).on("click", ".img_media_grid .img_wrapper img", function() {
        console.log("one_test");
        var src = jQuery(this).attr('src');
        jQuery(".img_media_grid .img_wrapper").removeClass("active_img");
        jQuery(this).parent().addClass("active_img");
        jQuery('#lut_main_img').attr('src', src);
        jQuery('#main_img_hide').attr('src', src);
        jQuery("#comparison_slider").removeClass("active");
        jQuery("#lut_main_img").show();
        jQuery(".select_lut, .adjust_intensity").hide();
        jQuery(".select_lut select").val('');
        jQuery('#first_lutOption').val('');
    });
   
    jQuery(document).on('change', '#firstLut_checkbox', function() {
        if (jQuery(this).is(':checked')) {
            // Checkbox is checked
            jQuery('#lut_main_img').hide();
            jQuery('#comparison_slider').addClass('active');
            jQuery('.img-comp-container').show();

            // Disable the range slider
            jQuery('.range-slider__range').prop('disabled', true);
        } else {
            // Checkbox is unchecked
            jQuery('#lut_main_img').show();
            jQuery('#comparison_slider').removeClass('active');
            jQuery('.img-comp-container').hide();

            // Enable the range slider
            jQuery('.range-slider__range').prop('disabled', false);
        }
    });

    jQuery(document).on('change','#secondary_lutOption',function() {
        if (jQuery(this).val() !== '') {
            jQuery('.secondary_lut_wrap .select_lut').show();
        } else {
            jQuery('.secondary_lut_wrap .select_lut').hide();
            jQuery('.secondary_lut_wrap .adjust_intensity').hide();
        }
    });
    jQuery(document).on('change','#secondary_lut_items',function(){
        if (jQuery(this).val() !== '') {
            jQuery('.secondary_lut_wrap .adjust_intensity').show();
        }else{
            jQuery('.secondary_lut_wrap .adjust_intensity').hide();
        }
    });

    jQuery(document).on('change','#secondaryLut_checkbox',function() {
        if (jQuery(this).is(':checked')) {
            
            jQuery('#lut_main_img').hide();
            jQuery('#comparison_slider').addClass('active');
            jQuery('.img-comp-container').show();
        } else {
            jQuery('#lut_main_img').show();
            jQuery('#comparison_slider').removeClass('active');
            jQuery('.img-comp-container').hide();
        }
    });

    jQuery("#first_lut_items").on("change",function(e){
      e.preventDefault();
      jQuery('.first_lut_wrap .adjust_intensity').hide();
      // jQuery("#firstLut_checkbox").prop("checked", false);
      img_src = jQuery("#main_img_hide").attr("src");
      lut_design = jQuery("#first_lut_items").find(":selected").val();
      console.log(img_src);
      console.log(lut_design);
      jQuery("#cube_lut").show();


      jQuery.ajax({
          url: "/wp-admin/admin-ajax.php",
          type: 'POST',
          dataType: 'json', // Expect JSON response
          data: {
              action: 'my_custom_ajax_action', // The AJAX action hook name
              custom_image_paths: [img_src],
              custom_cube_files: lut_design
          },
          success: function(response) {
            console.log(response);
            response = response.image_url;
            main_img = jQuery("#main_img_hide").attr("src");
            // jQuery("#lut_main_img").attr("src", response);
            jQuery(".first_img img").attr("src", main_img);
            jQuery(".result_img img").attr("src", response);
            // jQuery("#lut_main_img").hide();
            // jQuery("#comparison_slider").addClass("active");
            jQuery("#lut_main_img").attr("src", response);
            setTimeout(function(){
              jQuery("#cube_lut").hide();
              jQuery('.first_lut_wrap .adjust_intensity').show();
            },800);
            // console.log('Response from AJAX request:', response);
          },
          error: function(xhr, status, error) {
              console.error('Error:', error);
          }
      });
    })
});


jQuery(window).scroll(function() {
      if (jQuery(window).width() > 890) {
        if ((jQuery(this).scrollTop() > 200)) {
          jQuery('.scroll-header').fadeIn(350);
      } else {
          jQuery('.scroll-header').fadeOut(350);
      }
    }
  });
jQuery(window).resize(function() {
  if (jQuery(window).width() > 890) {
    jQuery('.scroll-header').removeAttr('style');
    jQuery('html').removeClass('nav-active');
    jQuery('a.nav-toggle').removeClass('active');
  } else if (jQuery(window).width() > 768) {
    jQuery('a.cat-toggle').removeClass('active');
    jQuery('.blog-cats').removeAttr('style');
  }
});
jQuery("a.nav-toggle").click(function(){
    if(jQuery("a.nav-toggle").hasClass('active')) {
        jQuery("a.nav-toggle").removeClass('active');
        jQuery('html').addClass('nav-fade');
        setTimeout(function(){
            jQuery('html').removeClass('nav-active');
            jQuery('html').removeClass('nav-fade');
        }, 250);
    } else {
        jQuery('html').addClass('nav-active');
        jQuery("a.nav-toggle").addClass('active');
    }
});

jQuery(document).ready(function($) {
    $('#cart_btn').on('click', function() {
        // var product_id = 123; // Replace with the actual product ID
        var product_id = $('#first_lutOption option:selected').attr("cat_id");
        // console.log(product_id);

        if (product_id === "") {
            return;
        }
        jQuery("#add_cart_img").show();

        // AJAX request
        $.ajax({
            url: "/wp-admin/admin-ajax.php", // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'add_to_cart', // Action hook for the PHP function
                product_id: product_id,
            },
            success: function(response) {
              console.log(response);
                // Handle success response
                jQuery("#add_cart_img").hide();
                if (response.success) {
                    $('.inner_flex_option').append('<div class="success">Product Added</div>');
                } 
                setTimeout(function(){
                  jQuery(".inner_flex_option .success").remove();
                },1500);
                // else {
                //     $('#cart-message').html('<div class="error">Error: Unable to add product to cart.</div>');
                // }
            },
            error: function() {
                $('#cart-message').html('<div class="error">Error: AJAX request failed.</div>');
            }
        });
    });
});


jQuery("#firstLut_checkbox").on("change", function() {
    if (jQuery(window).width() < 991) {
        if (jQuery(this).is(':checked')) {
            var imgHeight = jQuery('#lut_main_img').height();
            console.log(" Lut imgHeight " + imgHeight);
            var sliderTop = imgHeight / 2;
            jQuery('.img-comp-slider').css("top", sliderTop + "px");
            jQuery('#comparison_slider').css("height", imgHeight);
            jQuery('.img-comp-container').css("height", imgHeight);
            jQuery(window).on('resize', function(){
                var imgHeight = jQuery('#lut_main_img').height();
                jQuery('#comparison_slider').css("height", imgHeight);
                jQuery('.img-comp-container').css("height", imgHeight);
            });
            
        } else {
            jQuery('#comparison_slider').css("height", "0");
            jQuery('.img-comp-container').css("height", "0");
        }
    }
});


