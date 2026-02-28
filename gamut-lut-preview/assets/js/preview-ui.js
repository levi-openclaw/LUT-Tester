/**
 * Gamut LUT Preview UI Orchestrator
 *
 * Fetches data from the REST API, manages state, and coordinates
 * the LUT engine, comparison slider, and cart integration.
 *
 * @package Gamut_LUT_Preview
 */
var GamutLutPreview = (function() {
    'use strict';

    // DOM references cached on init.
    var dom = {};

    // Application state.
    var state = {
        images: [],
        categories: [],
        collections: [],
        selectedImage: null,
        selectedCollection: null,
        selectedLut: null,
        activeCategory: '',
        intensity: 100,
        compareMode: false,
        isLoading: false,
        cubeCache: new Map()
    };

    // Engine and slider instances.
    var engine = null;
    var slider = null;

    // Config from wp_localize_script.
    var config = null;

    /**
     * Initialize the preview UI.
     */
    function init() {
        if (typeof gamutLutConfig === 'undefined') {
            return;
        }
        config = gamutLutConfig;

        cacheDom();

        if (!dom.root) {
            return;
        }

        // Initialize the WebGL engine on the main canvas.
        if (dom.canvas) {
            engine = new GamutLutEngine(dom.canvas);
        }

        bindEvents();
        fetchData();
    }

    /**
     * Cache DOM element references.
     */
    function cacheDom() {
        dom.root = document.getElementById('gamut-lut-preview');
        if (!dom.root) return;

        dom.canvas = document.getElementById('gamut-lut-canvas');
        dom.canvasWrap = dom.root.querySelector('.gamut-lut__canvas-wrap');
        dom.emptyState = dom.root.querySelector('.gamut-lut__empty-state');
        dom.loading = dom.root.querySelector('.gamut-lut__loading');

        // Controls.
        dom.collectionSelect = document.getElementById('gamut-lut-collection');
        dom.lutSelect = document.getElementById('gamut-lut-select');
        dom.lutSelectGroup = dom.root.querySelector('.gamut-lut__control-group--lut');
        dom.intensitySlider = document.getElementById('gamut-lut-intensity');
        dom.intensityValue = dom.root.querySelector('.gamut-lut__intensity-value');
        dom.intensityGroup = dom.root.querySelector('.gamut-lut__control-group--intensity');
        dom.compareCheckbox = document.getElementById('gamut-lut-compare');
        dom.compareGroup = dom.root.querySelector('.gamut-lut__control-group--compare');

        // Comparison slider.
        dom.comparison = dom.root.querySelector('.gamut-lut__comparison');

        // Image grid.
        dom.grid = dom.root.querySelector('.gamut-lut__grid');
        dom.categoryFilter = document.getElementById('gamut-lut-category');

        // Cart.
        dom.cartSection = dom.root.querySelector('.gamut-lut__cart');
        dom.cartBtn = document.getElementById('gamut-lut-cart-btn');
        dom.cartMessage = dom.root.querySelector('.gamut-lut__cart-message');

        // Cube loading indicator.
        dom.cubeLoading = dom.root.querySelector('.gamut-lut__cube-loading');
    }

    /**
     * Bind UI event listeners.
     */
    function bindEvents() {
        if (dom.collectionSelect) {
            dom.collectionSelect.addEventListener('change', onCollectionChange);
        }
        if (dom.lutSelect) {
            dom.lutSelect.addEventListener('change', onLutChange);
        }
        if (dom.intensitySlider) {
            dom.intensitySlider.addEventListener('input', onIntensityChange);
        }
        if (dom.compareCheckbox) {
            dom.compareCheckbox.addEventListener('change', onCompareToggle);
        }
        if (dom.categoryFilter) {
            dom.categoryFilter.addEventListener('change', onCategoryChange);
        }
        if (dom.cartBtn) {
            dom.cartBtn.addEventListener('click', onAddToCart);
        }
    }

    /**
     * Fetch images and collections from the REST API in parallel.
     */
    function fetchData() {
        setLoading(true);

        var headers = { 'X-WP-Nonce': config.nonce };

        Promise.all([
            fetch(config.restUrl + '/images', { credentials: 'same-origin', headers: headers })
                .then(function(r) { return r.json(); }),
            fetch(config.restUrl + '/collections', { credentials: 'same-origin', headers: headers })
                .then(function(r) { return r.json(); })
        ])
        .then(function(results) {
            var imagesData = results[0];
            var collectionsData = results[1];

            state.images = imagesData.images || [];
            state.categories = imagesData.categories || [];
            state.collections = collectionsData.collections || [];

            renderImageGrid();
            renderCategoryFilter();
            renderCollectionDropdown();
            setLoading(false);
        })
        .catch(function(err) {
            setLoading(false);
            console.error('GamutLutPreview: Failed to fetch data', err);
        });
    }

    /**
     * Render the image grid.
     */
    function renderImageGrid() {
        if (!dom.grid) return;

        var html = '';
        state.images.forEach(function(img) {
            var catAttr = img.categories.join(' ');
            html += '<div class="gamut-lut__grid-item" data-id="' + img.id + '" data-categories="' + catAttr + '">';
            html += '<img src="' + img.thumbnail + '" alt="' + escapeHtml(img.title) + '" loading="lazy" width="' + img.width + '" height="' + img.height + '">';
            html += '</div>';
        });

        dom.grid.innerHTML = html;

        // Bind click events.
        var items = dom.grid.querySelectorAll('.gamut-lut__grid-item');
        for (var i = 0; i < items.length; i++) {
            items[i].addEventListener('click', onImageClick);
        }
    }

    /**
     * Render the category filter dropdown.
     */
    function renderCategoryFilter() {
        if (!dom.categoryFilter) return;

        var html = '<option value="">All</option>';
        state.categories.forEach(function(cat) {
            html += '<option value="' + cat.slug + '">' + escapeHtml(cat.name) + '</option>';
        });

        dom.categoryFilter.innerHTML = html;
    }

    /**
     * Render the collection dropdown.
     */
    function renderCollectionDropdown() {
        if (!dom.collectionSelect) return;

        var html = '<option value="">Select Collection</option>';
        state.collections.forEach(function(col) {
            html += '<option value="' + col.slug + '">' + escapeHtml(col.name) + '</option>';
        });

        dom.collectionSelect.innerHTML = html;
    }

    /**
     * Handle image click in the grid.
     */
    function onImageClick(e) {
        var item = e.currentTarget;
        var id = parseInt(item.getAttribute('data-id'), 10);

        // Find the image data.
        var img = null;
        for (var i = 0; i < state.images.length; i++) {
            if (state.images[i].id === id) {
                img = state.images[i];
                break;
            }
        }
        if (!img) return;

        // Update active state in grid.
        var active = dom.grid.querySelector('.gamut-lut__grid-item--active');
        if (active) active.classList.remove('gamut-lut__grid-item--active');
        item.classList.add('gamut-lut__grid-item--active');

        state.selectedImage = img;

        // Show canvas, hide empty state.
        if (dom.emptyState) dom.emptyState.style.display = 'none';
        if (dom.canvasWrap) dom.canvasWrap.style.display = 'block';

        // Load image into engine.
        if (engine) {
            setLoading(true);
            engine.loadImage(img.url).then(function() {
                setLoading(false);
                if (state.compareMode) {
                    updateComparison();
                }
            }).catch(function() {
                setLoading(false);
            });
        }
    }

    /**
     * Handle collection dropdown change.
     */
    function onCollectionChange() {
        var slug = dom.collectionSelect.value;

        if (!slug) {
            state.selectedCollection = null;
            state.selectedLut = null;
            hideControlGroup(dom.lutSelectGroup);
            hideControlGroup(dom.intensityGroup);
            hideControlGroup(dom.compareGroup);
            hideCart();
            return;
        }

        // Find collection.
        var col = null;
        for (var i = 0; i < state.collections.length; i++) {
            if (state.collections[i].slug === slug) {
                col = state.collections[i];
                break;
            }
        }
        if (!col) return;

        state.selectedCollection = col;
        state.selectedLut = null;

        // Populate LUT dropdown.
        var html = '<option value="">Select LUT</option>';
        col.luts.forEach(function(lut) {
            html += '<option value="' + lut.id + '">' + escapeHtml(lut.title) + '</option>';
        });
        dom.lutSelect.innerHTML = html;

        // Show LUT select group.
        showControlGroup(dom.lutSelectGroup);
        hideControlGroup(dom.intensityGroup);
        hideControlGroup(dom.compareGroup);

        // Show/hide cart button based on product_id.
        if (col.product_id) {
            showCart();
        } else {
            hideCart();
        }

        // Preload all .cube files for this collection in the background.
        preloadCollection(col);
    }

    /**
     * Handle LUT dropdown change.
     */
    function onLutChange() {
        var lutId = parseInt(dom.lutSelect.value, 10);

        if (!lutId || !state.selectedCollection) {
            state.selectedLut = null;
            hideControlGroup(dom.intensityGroup);
            hideControlGroup(dom.compareGroup);
            return;
        }

        // Find LUT data.
        var lut = null;
        var luts = state.selectedCollection.luts;
        for (var i = 0; i < luts.length; i++) {
            if (luts[i].id === lutId) {
                lut = luts[i];
                break;
            }
        }
        if (!lut) return;

        state.selectedLut = lut;

        // Load and apply the LUT.
        loadLutForPreview(lut);

        // Show intensity and compare controls.
        showControlGroup(dom.intensityGroup);
        showControlGroup(dom.compareGroup);
    }

    /**
     * Load a LUT, using cache if available.
     *
     * @param {{ id: number, title: string, lut_size: number }} lutData
     */
    function loadLutForPreview(lutData) {
        var id = lutData.id;

        if (state.cubeCache.has(id)) {
            applyLut(state.cubeCache.get(id));
            return;
        }

        // Fetch from protected endpoint.
        showCubeLoading();
        var url = config.restUrl + '/cube/' + id;

        GamutCubeParser.fetchAndParse(url, config.nonce)
            .then(function(parsed) {
                state.cubeCache.set(id, parsed);
                applyLut(parsed);
            })
            .catch(function(err) {
                console.error('GamutLutPreview: Failed to load LUT', err);
            })
            .finally(function() {
                hideCubeLoading();
            });
    }

    /**
     * Apply parsed LUT data to the engine and render.
     *
     * @param {{ size: number, data: Float32Array }} parsed
     */
    function applyLut(parsed) {
        if (!engine) return;

        engine.loadLut(parsed);
        engine.setIntensity(state.intensity / 100);
        engine.render();

        if (state.compareMode) {
            updateComparison();
        }
    }

    /**
     * Preload all .cube files for a collection in the background.
     *
     * @param {{ luts: Array }} collection
     */
    function preloadCollection(collection) {
        collection.luts.forEach(function(lut) {
            if (!state.cubeCache.has(lut.id)) {
                var url = config.restUrl + '/cube/' + lut.id;
                GamutCubeParser.fetchAndParse(url, config.nonce)
                    .then(function(parsed) {
                        state.cubeCache.set(lut.id, parsed);
                    })
                    .catch(function() {
                        // Silently fail preloads.
                    });
            }
        });
    }

    /**
     * Handle intensity slider change.
     */
    function onIntensityChange() {
        var value = parseInt(dom.intensitySlider.value, 10);
        state.intensity = value;

        // Update value display.
        if (dom.intensityValue) {
            dom.intensityValue.textContent = value + '%';
        }

        // Update slider track gradient.
        var pct = value;
        dom.intensitySlider.style.background =
            'linear-gradient(to right, var(--gamut-accent) 0%, var(--gamut-accent) ' + pct + '%, var(--gamut-slider-track) ' + pct + '%, var(--gamut-slider-track) 100%)';

        if (engine) {
            engine.setIntensity(value / 100);
            engine.render();

            if (state.compareMode) {
                updateComparison();
            }
        }
    }

    /**
     * Handle compare checkbox toggle.
     */
    function onCompareToggle() {
        state.compareMode = dom.compareCheckbox.checked;

        if (state.compareMode) {
            showComparison();
        } else {
            hideComparison();
        }
    }

    /**
     * Show the comparison slider with before/after images.
     */
    function showComparison() {
        if (!dom.comparison || !engine) return;

        dom.comparison.style.display = 'block';
        if (dom.canvasWrap) dom.canvasWrap.style.display = 'none';

        // Initialize slider if not already done.
        if (!slider) {
            slider = new GamutComparisonSlider(dom.comparison);
        }

        updateComparison();
    }

    /**
     * Hide the comparison slider and show the main canvas.
     */
    function hideComparison() {
        if (dom.comparison) dom.comparison.style.display = 'none';
        if (dom.canvasWrap && state.selectedImage) dom.canvasWrap.style.display = 'block';
    }

    /**
     * Update comparison slider images.
     */
    function updateComparison() {
        if (!slider || !engine || !engine.imageLoaded) return;

        var beforeCanvas = engine.getOriginalCanvas();
        var afterCanvas = engine.getGradedCanvas();

        slider.updateImages(beforeCanvas, afterCanvas);
        slider.reset();
    }

    /**
     * Handle category filter change.
     */
    function onCategoryChange() {
        state.activeCategory = dom.categoryFilter.value;

        var items = dom.grid.querySelectorAll('.gamut-lut__grid-item');
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (!state.activeCategory) {
                item.style.display = '';
            } else {
                var cats = item.getAttribute('data-categories') || '';
                item.style.display = cats.indexOf(state.activeCategory) !== -1 ? '' : 'none';
            }
        }
    }

    /**
     * Handle add to cart button click.
     */
    function onAddToCart() {
        if (!state.selectedCollection || !state.selectedCollection.product_id) return;

        dom.cartBtn.disabled = true;
        hideCartMessage();

        var formData = new FormData();
        formData.append('action', 'gamut_add_to_cart');
        formData.append('product_id', state.selectedCollection.product_id);
        formData.append('nonce', config.cartNonce);

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            dom.cartBtn.disabled = false;

            if (response.success) {
                var data = response.data;
                if (data.in_cart) {
                    showCartMessage(data.message + ' <a href="' + data.cart_url + '">View Cart</a>', 'info');
                } else {
                    showCartMessage(data.message, 'success');
                }
            } else {
                showCartMessage(response.data.message || 'Error adding to cart.', 'error');
            }
        })
        .catch(function() {
            dom.cartBtn.disabled = false;
            showCartMessage('Network error. Please try again.', 'error');
        });
    }

    // ---- UI Helpers ----

    function setLoading(loading) {
        state.isLoading = loading;
        if (dom.loading) {
            dom.loading.style.display = loading ? 'flex' : 'none';
        }
    }

    function showCubeLoading() {
        if (dom.cubeLoading) dom.cubeLoading.style.display = 'inline-block';
    }

    function hideCubeLoading() {
        if (dom.cubeLoading) dom.cubeLoading.style.display = 'none';
    }

    function showControlGroup(el) {
        if (el) el.style.display = '';
    }

    function hideControlGroup(el) {
        if (el) el.style.display = 'none';
    }

    function showCart() {
        if (dom.cartSection) dom.cartSection.style.display = '';
    }

    function hideCart() {
        if (dom.cartSection) dom.cartSection.style.display = 'none';
        hideCartMessage();
    }

    function showCartMessage(html, type) {
        if (!dom.cartMessage) return;
        dom.cartMessage.innerHTML = html;
        dom.cartMessage.className = 'gamut-lut__cart-message gamut-lut__cart-message--' + type;
        dom.cartMessage.style.display = 'block';
    }

    function hideCartMessage() {
        if (dom.cartMessage) {
            dom.cartMessage.style.display = 'none';
            dom.cartMessage.innerHTML = '';
        }
    }

    /**
     * Escape HTML to prevent XSS.
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        getState: function() { return state; },
        getEngine: function() { return engine; }
    };
})();
