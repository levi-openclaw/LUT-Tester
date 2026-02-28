<?php
/**
 * Template for the [gamut_lut_preview] shortcode.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title             = get_option( 'gamut_lut_title', 'Preview Our LUTs' );
$description       = get_option( 'gamut_lut_description', '' );
$images_title      = get_option( 'gamut_lut_images_title', 'Select an Image' );
$images_description = get_option( 'gamut_lut_images_description', '' );
?>
<div id="gamut-lut-preview" class="gamut-lut">

    <!-- Header -->
    <div class="gamut-lut__header">
        <?php if ( $title ) : ?>
            <h2 class="gamut-lut__title"><?php echo esc_html( $title ); ?></h2>
        <?php endif; ?>
        <?php if ( $description ) : ?>
            <p class="gamut-lut__description"><?php echo wp_kses_post( $description ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Main: Preview + Controls -->
    <div class="gamut-lut__main">

        <!-- Preview Area -->
        <div class="gamut-lut__preview">

            <!-- Main Canvas (hidden until image selected) -->
            <div class="gamut-lut__canvas-wrap">
                <canvas id="gamut-lut-canvas"></canvas>
                <div class="gamut-lut__loading">
                    <div class="gamut-lut__spinner"></div>
                </div>
                <div class="gamut-lut__lut-toast" aria-live="polite"></div>
            </div>

            <!-- Comparison Slider (hidden until compare mode enabled) -->
            <div class="gamut-lut__comparison" aria-label="<?php esc_attr_e( 'Before and after comparison', 'gamut-lut-preview' ); ?>">
                <div class="gamut-lut__comparison-after">
                    <canvas></canvas>
                </div>
                <div class="gamut-lut__comparison-before">
                    <canvas></canvas>
                </div>
                <div class="gamut-lut__comparison-handle"
                     role="slider"
                     tabindex="0"
                     aria-label="<?php esc_attr_e( 'Comparison slider', 'gamut-lut-preview' ); ?>"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     aria-valuenow="50">
                </div>
                <span class="gamut-lut__comparison-label gamut-lut__comparison-label--before"><?php esc_html_e( 'Before', 'gamut-lut-preview' ); ?></span>
                <span class="gamut-lut__comparison-label gamut-lut__comparison-label--after"><?php esc_html_e( 'After', 'gamut-lut-preview' ); ?></span>
            </div>

            <!-- A/B LUT Comparison (hidden until A/B mode enabled) -->
            <div class="gamut-lut__ab-comparison" aria-label="<?php esc_attr_e( 'Compare two LUTs', 'gamut-lut-preview' ); ?>">
                <div class="gamut-lut__comparison-after">
                    <canvas></canvas>
                </div>
                <div class="gamut-lut__comparison-before">
                    <canvas></canvas>
                </div>
                <div class="gamut-lut__comparison-handle"
                     role="slider"
                     tabindex="0"
                     aria-label="<?php esc_attr_e( 'A/B comparison slider', 'gamut-lut-preview' ); ?>"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     aria-valuenow="50">
                </div>
                <span class="gamut-lut__comparison-label gamut-lut__comparison-label--before"></span>
                <span class="gamut-lut__comparison-label gamut-lut__comparison-label--after"></span>
            </div>

            <!-- Empty State -->
            <div class="gamut-lut__empty-state">
                <?php esc_html_e( 'Select an image below to preview LUTs', 'gamut-lut-preview' ); ?>
            </div>
        </div>

        <!-- Controls Sidebar -->
        <div class="gamut-lut__controls">

            <!-- LUT Collection Selector -->
            <div class="gamut-lut__control-group gamut-lut__control-group--collection">
                <label class="gamut-lut__label" for="gamut-lut-collection">
                    <?php esc_html_e( 'LUT Collection', 'gamut-lut-preview' ); ?>
                </label>
                <select id="gamut-lut-collection" class="gamut-lut__select" aria-label="<?php esc_attr_e( 'Select LUT collection', 'gamut-lut-preview' ); ?>">
                    <option value=""><?php esc_html_e( 'Loading...', 'gamut-lut-preview' ); ?></option>
                </select>
            </div>

            <!-- Individual LUT Selector (hidden until collection chosen) -->
            <div class="gamut-lut__control-group gamut-lut__control-group--lut">
                <label class="gamut-lut__label" for="gamut-lut-select">
                    <?php esc_html_e( 'Select LUT', 'gamut-lut-preview' ); ?>
                    <span class="gamut-lut__cube-loading">
                        <span class="gamut-lut__spinner gamut-lut__spinner--small"></span>
                    </span>
                </label>
                <select id="gamut-lut-select" class="gamut-lut__select" aria-label="<?php esc_attr_e( 'Select individual LUT', 'gamut-lut-preview' ); ?>">
                    <option value=""><?php esc_html_e( 'Select LUT', 'gamut-lut-preview' ); ?></option>
                </select>
            </div>

            <!-- Intensity Slider (hidden until LUT chosen) -->
            <div class="gamut-lut__control-group gamut-lut__control-group--intensity">
                <label class="gamut-lut__label" for="gamut-lut-intensity">
                    <?php esc_html_e( 'Adjust Intensity', 'gamut-lut-preview' ); ?>
                </label>
                <div class="gamut-lut__intensity-wrap">
                    <input type="range"
                           id="gamut-lut-intensity"
                           class="gamut-lut__range"
                           min="0"
                           max="100"
                           value="100"
                           aria-label="<?php esc_attr_e( 'LUT intensity', 'gamut-lut-preview' ); ?>">
                    <span class="gamut-lut__intensity-value">100%</span>
                </div>
            </div>

            <!-- Compare Mode Selector (hidden until LUT chosen) -->
            <div class="gamut-lut__control-group gamut-lut__control-group--compare">
                <label class="gamut-lut__label"><?php esc_html_e( 'Compare', 'gamut-lut-preview' ); ?></label>
                <div class="gamut-lut__segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Compare mode', 'gamut-lut-preview' ); ?>">
                    <button type="button" class="gamut-lut__segment gamut-lut__segment--active" data-mode="none" role="radio" aria-checked="true">
                        <?php esc_html_e( 'Off', 'gamut-lut-preview' ); ?>
                    </button>
                    <button type="button" class="gamut-lut__segment" data-mode="before-after" role="radio" aria-checked="false">
                        <?php esc_html_e( 'Before / After', 'gamut-lut-preview' ); ?>
                    </button>
                    <button type="button" class="gamut-lut__segment" data-mode="ab" role="radio" aria-checked="false">
                        <?php esc_html_e( 'A / B LUT', 'gamut-lut-preview' ); ?>
                    </button>
                </div>
            </div>

            <!-- Second LUT selector for A/B mode (hidden until A/B enabled) -->
            <div class="gamut-lut__control-group gamut-lut__control-group--lut-b">
                <label class="gamut-lut__label" for="gamut-lut-select-b">
                    <?php esc_html_e( 'Compare With', 'gamut-lut-preview' ); ?>
                </label>
                <select id="gamut-lut-select-b" class="gamut-lut__select" aria-label="<?php esc_attr_e( 'Select second LUT to compare', 'gamut-lut-preview' ); ?>">
                    <option value=""><?php esc_html_e( 'Select LUT', 'gamut-lut-preview' ); ?></option>
                </select>
            </div>

            <!-- Share a Look -->
            <div class="gamut-lut__control-group gamut-lut__control-group--share">
                <button type="button" id="gamut-lut-share-btn" class="gamut-lut__share-btn" aria-label="<?php esc_attr_e( 'Copy share link', 'gamut-lut-preview' ); ?>">
                    <?php esc_html_e( 'SHARE THIS LOOK', 'gamut-lut-preview' ); ?>
                </button>
                <div class="gamut-lut__share-message"></div>
            </div>

            <!-- Add to Cart (hidden until collection with product_id chosen) -->
            <div class="gamut-lut__cart">
                <button type="button" id="gamut-lut-cart-btn" class="gamut-lut__cart-btn">
                    <?php esc_html_e( 'ADD TO CART', 'gamut-lut-preview' ); ?>
                </button>
                <div class="gamut-lut__cart-message"></div>
            </div>

            <!-- Upsell / Related Collections -->
            <div class="gamut-lut__upsell">
                <p class="gamut-lut__upsell-heading"><?php esc_html_e( 'Complete Your Collection', 'gamut-lut-preview' ); ?></p>
                <div class="gamut-lut__upsell-list"></div>
            </div>

        </div><!-- .gamut-lut__controls -->

    </div><!-- .gamut-lut__main -->

    <!-- Images Section -->
    <div class="gamut-lut__images-section">
        <div class="gamut-lut__images-header">
            <div>
                <?php if ( $images_title ) : ?>
                    <h3 class="gamut-lut__images-title"><?php echo esc_html( $images_title ); ?></h3>
                <?php endif; ?>
                <?php if ( $images_description ) : ?>
                    <p class="gamut-lut__images-description"><?php echo wp_kses_post( $images_description ); ?></p>
                <?php endif; ?>
            </div>
            <div class="gamut-lut__images-filters">
                <button type="button" id="gamut-lut-favorites-toggle" class="gamut-lut__favorites-toggle" aria-label="<?php esc_attr_e( 'Show favorite images only', 'gamut-lut-preview' ); ?>">
                    <svg class="gamut-lut__heart-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    <span><?php esc_html_e( 'Favorites', 'gamut-lut-preview' ); ?></span>
                    <span class="gamut-lut__favorites-count">0</span>
                </button>
                <div class="gamut-lut__category-filter">
                    <select id="gamut-lut-category" class="gamut-lut__select" aria-label="<?php esc_attr_e( 'Filter images by category', 'gamut-lut-preview' ); ?>">
                        <option value=""><?php esc_html_e( 'All', 'gamut-lut-preview' ); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="gamut-lut__grid">
            <!-- Image grid populated by JS -->
        </div>
    </div><!-- .gamut-lut__images-section -->

</div><!-- #gamut-lut-preview -->
