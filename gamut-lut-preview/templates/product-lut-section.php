<?php
/**
 * Template for the LUT preview section on WooCommerce product pages.
 *
 * Auto-rendered when a product has a linked LUT collection.
 * Design: dark background, serif headings, thin dividers, LUT item list.
 *
 * @package Gamut_LUT_Preview
 *
 * @var string $collection_slug The collection slug.
 * @var string $collection_name The collection display name.
 * @var int    $product_id      The WooCommerce product ID.
 * @var string $product_name    The WooCommerce product name.
 * @var string $product_price   The product price HTML.
 * @var int    $lut_count       Number of LUTs in the collection.
 * @var array  $luts            Array of LUT data (id, title).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$instance_id = 'gamut-product-lut-' . sanitize_title( $collection_slug );
?>

<div class="gamut-product-lut" id="<?php echo esc_attr( $instance_id ); ?>">

    <!-- Two-column layout: Preview left, LUT list right -->
    <div class="gamut-product-lut__layout">

        <!-- Left: Embedded LUT Previewer -->
        <div class="gamut-product-lut__preview-col">
            <div class="gamut-lut gamut-lut--embed gamut-lut--product" data-collection="<?php echo esc_attr( $collection_slug ); ?>">
                <div class="gamut-lut__embed-preview">
                    <div class="gamut-lut__canvas-wrap">
                        <canvas class="gamut-lut__embed-canvas"></canvas>
                        <div class="gamut-lut__loading">
                            <div class="gamut-lut__spinner"></div>
                        </div>
                    </div>

                    <div class="gamut-lut__comparison" aria-label="<?php esc_attr_e( 'Before and after comparison', 'gamut-lut-preview' ); ?>">
                        <div class="gamut-lut__comparison-after"><canvas></canvas></div>
                        <div class="gamut-lut__comparison-before"><canvas></canvas></div>
                        <div class="gamut-lut__comparison-handle" role="slider" tabindex="0" aria-label="<?php esc_attr_e( 'Comparison slider', 'gamut-lut-preview' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"></div>
                        <span class="gamut-lut__comparison-label gamut-lut__comparison-label--before"><?php esc_html_e( 'Before', 'gamut-lut-preview' ); ?></span>
                        <span class="gamut-lut__comparison-label gamut-lut__comparison-label--after"><?php esc_html_e( 'After', 'gamut-lut-preview' ); ?></span>
                    </div>

                    <div class="gamut-lut__empty-state">
                        <?php esc_html_e( 'Select an image below to preview', 'gamut-lut-preview' ); ?>
                    </div>
                </div>

                <!-- Compact controls row -->
                <div class="gamut-lut__embed-controls">
                    <div class="gamut-lut__embed-controls-row">
                        <div class="gamut-lut__control-group">
                            <label class="gamut-lut__label">
                                <?php esc_html_e( 'Select LUT', 'gamut-lut-preview' ); ?>
                                <span class="gamut-lut__cube-loading"><span class="gamut-lut__spinner gamut-lut__spinner--small"></span></span>
                            </label>
                            <select class="gamut-lut__select gamut-lut__embed-lut-select" aria-label="<?php esc_attr_e( 'Select LUT', 'gamut-lut-preview' ); ?>">
                                <option value=""><?php esc_html_e( 'Loading...', 'gamut-lut-preview' ); ?></option>
                            </select>
                        </div>
                        <div class="gamut-lut__control-group gamut-lut__control-group--intensity">
                            <label class="gamut-lut__label"><?php esc_html_e( 'Intensity', 'gamut-lut-preview' ); ?></label>
                            <div class="gamut-lut__intensity-wrap">
                                <input type="range" class="gamut-lut__range gamut-lut__embed-intensity" min="0" max="100" value="100" aria-label="<?php esc_attr_e( 'LUT intensity', 'gamut-lut-preview' ); ?>">
                                <span class="gamut-lut__intensity-value">100%</span>
                            </div>
                        </div>
                        <div class="gamut-lut__control-group gamut-lut__control-group--compare">
                            <label class="gamut-lut__checkbox-wrap">
                                <input type="checkbox" class="gamut-lut__checkbox gamut-lut__embed-compare" aria-label="<?php esc_attr_e( 'Show before and after', 'gamut-lut-preview' ); ?>">
                                <span class="gamut-lut__checkbox-label"><?php esc_html_e( 'Before & After', 'gamut-lut-preview' ); ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Sample Images -->
                <div class="gamut-lut__embed-images">
                    <div class="gamut-lut__grid gamut-lut__grid--embed"></div>
                </div>
            </div>
        </div>

        <!-- Right: Collection info + LUT list -->
        <div class="gamut-product-lut__info-col">

            <div class="gamut-product-lut__header">
                <h2 class="gamut-product-lut__title"><?php echo esc_html( $collection_name ); ?></h2>
                <p class="gamut-product-lut__subtitle">
                    <?php
                    printf(
                        /* translators: %d: number of LUTs */
                        esc_html( _n( '%d Look included', '%d Looks included', $lut_count, 'gamut-lut-preview' ) ),
                        $lut_count
                    );
                    ?>
                </p>
            </div>

            <div class="gamut-product-lut__divider"></div>

            <!-- LUT Item List -->
            <div class="gamut-product-lut__lut-list">
                <?php foreach ( $luts as $index => $lut ) : ?>
                    <div class="gamut-product-lut__lut-item" data-lut-id="<?php echo esc_attr( $lut['id'] ); ?>">
                        <span class="gamut-product-lut__lut-number"><?php echo esc_html( str_pad( $index + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
                        <span class="gamut-product-lut__lut-name"><?php echo esc_html( $lut['title'] ); ?></span>
                    </div>
                    <?php if ( $index < count( $luts ) - 1 ) : ?>
                        <div class="gamut-product-lut__divider"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="gamut-product-lut__divider"></div>

            <!-- Price + CTA -->
            <div class="gamut-product-lut__cta">
                <div class="gamut-product-lut__price">
                    <?php echo $product_price; // Already escaped by WooCommerce. ?>
                </div>
                <button type="button" class="gamut-product-lut__cart-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                    <?php esc_html_e( 'Add to Cart', 'gamut-lut-preview' ); ?>
                </button>
                <div class="gamut-product-lut__cart-message"></div>
            </div>

        </div>

    </div>

</div>

<script>
(function() {
    var wrapper = document.getElementById('<?php echo esc_js( $instance_id ); ?>');
    if (!wrapper) return;

    var btn = wrapper.querySelector('.gamut-product-lut__cart-btn');
    var msg = wrapper.querySelector('.gamut-product-lut__cart-message');
    var embed = wrapper.querySelector('.gamut-lut--embed');
    if (!btn) return;

    // Get the currently selected LUT name from the embed dropdown.
    function getSelectedLutTitle() {
        if (!embed) return '';
        var sel = embed.querySelector('.gamut-lut__embed-lut-select');
        if (!sel || !sel.value) return '';
        var opt = sel.options[sel.selectedIndex];
        return opt ? opt.textContent : '';
    }

    // Track analytics event (fire-and-forget).
    function trackCartAdd(productId, lutTitle) {
        var cfg = typeof gamutLutConfig !== 'undefined' ? gamutLutConfig : null;
        if (!cfg) return;

        var data = new FormData();
        data.append('action', 'gamut_track_preview');
        data.append('nonce', cfg.cartNonce);
        data.append('event_type', 'cart_add');
        data.append('object_id', productId);
        data.append('title', lutTitle || '<?php echo esc_js( $collection_name ); ?>');
        data.append('collection', '<?php echo esc_js( $collection_slug ); ?>');
        data.append('session_id', '');

        if (navigator.sendBeacon) {
            navigator.sendBeacon(cfg.ajaxUrl, data);
        } else {
            fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data, keepalive: true }).catch(function() {});
        }
    }

    btn.addEventListener('click', function() {
        var productId = btn.getAttribute('data-product-id');
        if (!productId) return;

        btn.disabled = true;
        btn.textContent = '<?php echo esc_js( __( 'Adding...', 'gamut-lut-preview' ) ); ?>';
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; msg.className = 'gamut-product-lut__cart-message'; }

        var cfg = typeof gamutLutConfig !== 'undefined' ? gamutLutConfig : null;
        if (!cfg) { btn.disabled = false; btn.textContent = '<?php echo esc_js( __( 'Add to Cart', 'gamut-lut-preview' ) ); ?>'; return; }

        var formData = new FormData();
        formData.append('action', 'gamut_add_to_cart');
        formData.append('product_id', productId);
        formData.append('nonce', cfg.cartNonce);

        // Track which LUT was selected at time of add-to-cart.
        var lutTitle = getSelectedLutTitle();
        trackCartAdd(productId, lutTitle);

        fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js( __( 'Add to Cart', 'gamut-lut-preview' ) ); ?>';
                if (msg) {
                    if (response.success) {
                        var data = response.data;
                        if (data.in_cart) {
                            msg.innerHTML = data.message + ' <a href="' + data.cart_url + '">View Cart</a>';
                            msg.className = 'gamut-product-lut__cart-message gamut-product-lut__cart-message--info';
                        } else {
                            msg.textContent = data.message;
                            msg.className = 'gamut-product-lut__cart-message gamut-product-lut__cart-message--success';
                        }
                    } else {
                        msg.textContent = (response.data && response.data.message) || 'Error adding to cart.';
                        msg.className = 'gamut-product-lut__cart-message gamut-product-lut__cart-message--error';
                    }
                    msg.style.display = 'block';
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js( __( 'Add to Cart', 'gamut-lut-preview' ) ); ?>';
                if (msg) {
                    msg.textContent = 'Network error. Please try again.';
                    msg.className = 'gamut-product-lut__cart-message gamut-product-lut__cart-message--error';
                    msg.style.display = 'block';
                }
            });
    });
})();
</script>
