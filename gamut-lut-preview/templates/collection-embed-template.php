<?php
/**
 * Template for the [gamut_collection] shortcode.
 *
 * Embeds a single LUT collection (up to 6 looks) into a blog post or page.
 * Shows a compact previewer scoped to one collection with add-to-cart.
 *
 * @package Gamut_LUT_Preview
 *
 * @var string $collection_slug The collection slug from shortcode attribute.
 * @var string $instance_id     Unique ID for this embed instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="gamut-lut gamut-lut--embed" data-collection="<?php echo esc_attr( $collection_slug ); ?>">

    <!-- Preview Area -->
    <div class="gamut-lut__embed-preview">
        <div class="gamut-lut__canvas-wrap">
            <canvas class="gamut-lut__embed-canvas"></canvas>
            <div class="gamut-lut__loading">
                <div class="gamut-lut__spinner"></div>
            </div>
        </div>

        <!-- Comparison Slider -->
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

        <!-- Empty State -->
        <div class="gamut-lut__empty-state">
            <?php esc_html_e( 'Select an image below to preview', 'gamut-lut-preview' ); ?>
        </div>
    </div>

    <!-- Controls (inline below preview for embed) -->
    <div class="gamut-lut__embed-controls">
        <div class="gamut-lut__embed-controls-row">
            <!-- LUT Selector -->
            <div class="gamut-lut__control-group">
                <label class="gamut-lut__label">
                    <?php esc_html_e( 'Select LUT', 'gamut-lut-preview' ); ?>
                    <span class="gamut-lut__cube-loading">
                        <span class="gamut-lut__spinner gamut-lut__spinner--small"></span>
                    </span>
                </label>
                <select class="gamut-lut__select gamut-lut__embed-lut-select" aria-label="<?php esc_attr_e( 'Select LUT', 'gamut-lut-preview' ); ?>">
                    <option value=""><?php esc_html_e( 'Loading...', 'gamut-lut-preview' ); ?></option>
                </select>
            </div>

            <!-- Intensity -->
            <div class="gamut-lut__control-group gamut-lut__control-group--intensity">
                <label class="gamut-lut__label"><?php esc_html_e( 'Intensity', 'gamut-lut-preview' ); ?></label>
                <div class="gamut-lut__intensity-wrap">
                    <input type="range" class="gamut-lut__range gamut-lut__embed-intensity" min="0" max="100" value="100" aria-label="<?php esc_attr_e( 'LUT intensity', 'gamut-lut-preview' ); ?>">
                    <span class="gamut-lut__intensity-value">100%</span>
                </div>
            </div>

            <!-- Compare -->
            <div class="gamut-lut__control-group gamut-lut__control-group--compare">
                <label class="gamut-lut__checkbox-wrap">
                    <input type="checkbox" class="gamut-lut__checkbox gamut-lut__embed-compare" aria-label="<?php esc_attr_e( 'Show before and after', 'gamut-lut-preview' ); ?>">
                    <span class="gamut-lut__checkbox-label"><?php esc_html_e( 'Before & After', 'gamut-lut-preview' ); ?></span>
                </label>
            </div>
        </div>

        <!-- Cart -->
        <div class="gamut-lut__cart" style="display: none;">
            <button type="button" class="gamut-lut__cart-btn gamut-lut__embed-cart-btn">
                <?php esc_html_e( 'ADD TO CART', 'gamut-lut-preview' ); ?>
            </button>
            <div class="gamut-lut__cart-message"></div>
        </div>
    </div>

    <!-- Sample Images (compact grid) -->
    <div class="gamut-lut__embed-images">
        <div class="gamut-lut__grid gamut-lut__grid--embed">
            <!-- Populated by JS -->
        </div>
    </div>

</div>
