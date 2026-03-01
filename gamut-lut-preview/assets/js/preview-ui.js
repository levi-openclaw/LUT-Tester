/**
 * Gamut LUT Preview UI Orchestrator
 *
 * Fetches data from the REST API, manages state, and coordinates
 * the LUT engine, comparison slider, and cart integration.
 *
 * Features:
 * - Favorites / Shortlist (localStorage + user meta sync)
 * - A/B Split View (compare two LUTs)
 * - Collection embed mode
 * - Collection bundling / upsell
 * - Share a Look (deep-link URLs)
 * - Analytics tracking
 * - Mobile gesture support (pinch-zoom, swipe)
 *
 * @package Gamut_LUT_Preview
 */
var GamutLutPreview = (function() {
    'use strict';

    // Storage key for favorites.
    var FAVORITES_KEY = 'gamut_lut_favorites';

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
        selectedLutB: null,
        activeCategory: '',
        intensity: 100,
        compareMode: 'none', // 'none' | 'before-after' | 'ab'
        showFavoritesOnly: false,
        favorites: [],
        isLoading: false,
        cubeCache: new Map(),
        sessionId: ''
    };

    // Engine and slider instances.
    var engine = null;
    var slider = null;
    var abSlider = null;

    // LUT toast timeout.
    var toastTimeout = null;

    // Favorites save debounce timer.
    var favSaveTimeout = null;

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

        // Generate a session ID for analytics deduplication.
        state.sessionId = generateSessionId();

        // Load favorites from localStorage first (instant).
        loadFavoritesLocal();

        cacheDom();

        if (!dom.root) {
            return;
        }

        // Initialize the WebGL engine on the main canvas.
        if (dom.canvas) {
            engine = new GamutLutEngine(dom.canvas);
        }

        bindEvents();
        initMobileGestures();
        parseShareUrl();
        fetchData();

        // If logged in, sync favorites from server (merge with localStorage).
        if (config.isLoggedIn) {
            syncFavoritesFromServer();
        }
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

        // Segmented compare control.
        dom.compareGroup = dom.root.querySelector('.gamut-lut__control-group--compare');
        dom.segmented = dom.root.querySelector('.gamut-lut__segmented');

        // LUT B dropdowns (collection + LUT).
        dom.collectionSelectB = document.getElementById('gamut-lut-collection-b');
        dom.lutSelectB = document.getElementById('gamut-lut-select-b');
        dom.lutSelectBGroup = dom.root.querySelector('.gamut-lut__control-group--lut-b');

        // Randomize button.
        dom.randomizeBtn = document.getElementById('gamut-lut-randomize');

        // Comparison slider.
        dom.comparison = dom.root.querySelector('.gamut-lut__comparison');

        // A/B comparison container.
        dom.abComparison = dom.root.querySelector('.gamut-lut__ab-comparison');

        // LUT name toast.
        dom.lutToast = dom.root.querySelector('.gamut-lut__lut-toast');

        // Image grid.
        dom.grid = dom.root.querySelector('.gamut-lut__grid');
        dom.categoryFilter = document.getElementById('gamut-lut-category');

        // Grid empty state.
        dom.gridEmpty = dom.root.querySelector('.gamut-lut__grid-empty');

        // Favorites.
        dom.favoritesToggle = document.getElementById('gamut-lut-favorites-toggle');
        dom.favoritesCount = dom.root.querySelector('.gamut-lut__favorites-count');

        // Cart.
        dom.cartSection = dom.root.querySelector('.gamut-lut__cart');
        dom.cartBtn = document.getElementById('gamut-lut-cart-btn');
        dom.cartMessage = dom.root.querySelector('.gamut-lut__cart-message');

        // Share.
        dom.shareBtn = document.getElementById('gamut-lut-share-btn');
        dom.shareGroup = dom.root.querySelector('.gamut-lut__control-group--share');
        dom.shareMessage = dom.root.querySelector('.gamut-lut__share-message');

        // Upsell.
        dom.upsellSection = dom.root.querySelector('.gamut-lut__upsell');
        dom.upsellList = dom.root.querySelector('.gamut-lut__upsell-list');

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
        if (dom.categoryFilter) {
            dom.categoryFilter.addEventListener('change', onCategoryChange);
        }
        if (dom.cartBtn) {
            dom.cartBtn.addEventListener('click', onAddToCart);
        }
        if (dom.favoritesToggle) {
            dom.favoritesToggle.addEventListener('click', onFavoritesToggle);
        }
        if (dom.collectionSelectB) {
            dom.collectionSelectB.addEventListener('change', onCollectionBChange);
        }
        if (dom.lutSelectB) {
            dom.lutSelectB.addEventListener('change', onLutBChange);
        }
        if (dom.randomizeBtn) {
            dom.randomizeBtn.addEventListener('click', onRandomize);
        }
        if (dom.shareBtn) {
            dom.shareBtn.addEventListener('click', onShareClick);
        }

        // Segmented compare control.
        if (dom.segmented) {
            var segments = dom.segmented.querySelectorAll('.gamut-lut__segment');
            for (var i = 0; i < segments.length; i++) {
                segments[i].addEventListener('click', onSegmentClick);
            }
        }

        // Recalculate comparison slider on window resize.
        var resizeTimer = null;
        window.addEventListener('resize', function() {
            if (resizeTimer) clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (state.compareMode === 'before-after' && slider) {
                    updateComparison();
                } else if (state.compareMode === 'ab' && abSlider) {
                    updateAbComparison();
                }
            }, 150);
        });
    }

    // ======================================================
    //  DATA FETCHING
    // ======================================================

    /**
     * Fetch images and collections from the REST API independently
     * for progressive rendering (don't wait for both to finish).
     */
    function fetchData() {
        setLoading(true);

        var headers = { 'X-WP-Nonce': config.nonce };
        var imagesReady = false;
        var collectionsReady = false;

        // Fetch images — render grid as soon as available.
        fetch(config.restUrl + '/images', { credentials: 'same-origin', headers: headers })
            .then(function(r) { return r.json(); })
            .then(function(imagesData) {
                state.images = imagesData.images || [];
                state.categories = imagesData.categories || [];

                renderImageGrid();
                renderCategoryFilter();
                updateFavoritesCount();
                imagesReady = true;

                // Auto-load first image immediately (don't wait for collections).
                if (state.images.length > 0 && !state.selectedImage) {
                    selectImageById(state.images[0].id);
                }

                if (collectionsReady) onAllDataReady();
            })
            .catch(function(err) {
                console.error('GamutLutPreview: Failed to fetch images', err);
            });

        // Fetch collections — render dropdowns as soon as available.
        fetch(config.restUrl + '/collections', { credentials: 'same-origin', headers: headers })
            .then(function(r) { return r.json(); })
            .then(function(collectionsData) {
                state.collections = collectionsData.collections || [];

                renderCollectionDropdown();
                collectionsReady = true;

                if (imagesReady) onAllDataReady();
            })
            .catch(function(err) {
                console.error('GamutLutPreview: Failed to fetch collections', err);
                if (dom.collectionSelect) {
                    dom.collectionSelect.innerHTML = '<option value="">Unable to load</option>';
                }
            });

        // Called once both have finished — apply share state.
        function onAllDataReady() {
            setLoading(false);
            try {
                applyShareState();
            } catch (e) {
                console.error('GamutLutPreview: Error applying share state', e);
            }
        }
    }

    /**
     * Show a visible error message when data loading fails.
     */
    function showFetchError() {
        if (dom.emptyState) {
            dom.emptyState.textContent = 'Unable to load preview data. Please refresh the page.';
        }
        if (dom.collectionSelect) {
            dom.collectionSelect.innerHTML = '<option value="">Unable to load</option>';
        }
    }

    // ======================================================
    //  IMAGE GRID RENDERING
    // ======================================================

    /**
     * Render the image grid with favorite hearts and title tooltips.
     */
    function renderImageGrid() {
        if (!dom.grid) return;

        var html = '';
        state.images.forEach(function(img) {
            var catAttr = img.categories.join(' ');
            var isFav = state.favorites.indexOf(img.id) !== -1;
            html += '<div class="gamut-lut__grid-item' + (isFav ? ' gamut-lut__grid-item--favorited' : '') + '" data-id="' + img.id + '" data-categories="' + catAttr + '" title="' + escapeAttr(img.title) + '" tabindex="0" role="button" aria-label="' + escapeAttr(img.title) + '">';
            html += '<img src="' + img.thumbnail + '" alt="' + escapeHtml(img.title) + '" loading="lazy" width="' + img.width + '" height="' + img.height + '">';
            html += '<button type="button" class="gamut-lut__favorite-btn" aria-label="' + (isFav ? 'Remove from favorites' : 'Add to favorites') + '">';
            html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="' + (isFav ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
            html += '</button>';
            html += '</div>';
        });

        dom.grid.innerHTML = html;

        // Bind click and keyboard events.
        var items = dom.grid.querySelectorAll('.gamut-lut__grid-item');
        for (var i = 0; i < items.length; i++) {
            items[i].addEventListener('click', onImageClick);
            items[i].addEventListener('keydown', onImageKeyDown);
        }

        // Bind favorite button clicks.
        var favBtns = dom.grid.querySelectorAll('.gamut-lut__favorite-btn');
        for (var j = 0; j < favBtns.length; j++) {
            favBtns[j].addEventListener('click', onFavoriteClick);
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
     * Render the collection dropdown (and B collection dropdown).
     */
    function renderCollectionDropdown() {
        if (!dom.collectionSelect) return;

        var html = '<option value="">Select Collection</option>';
        state.collections.forEach(function(col) {
            html += '<option value="' + col.slug + '">' + escapeHtml(col.name) + '</option>';
        });

        dom.collectionSelect.innerHTML = html;

        // Also populate the B side collection dropdown.
        if (dom.collectionSelectB) {
            dom.collectionSelectB.innerHTML = html;
        }
    }

    // ======================================================
    //  IMAGE SELECTION
    // ======================================================

    /**
     * Handle image click in the grid.
     */
    function onImageClick(e) {
        // Don't trigger image select when clicking the favorite button.
        if (e.target.closest('.gamut-lut__favorite-btn')) return;

        var item = e.currentTarget;
        var id = parseInt(item.getAttribute('data-id'), 10);

        selectImageById(id);
    }

    /**
     * Handle keyboard activation on grid items (Enter/Space).
     */
    function onImageKeyDown(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            var id = parseInt(e.currentTarget.getAttribute('data-id'), 10);
            selectImageById(id);
        }
    }

    /**
     * Select an image by ID.
     */
    function selectImageById(id) {
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
        var item = dom.grid.querySelector('.gamut-lut__grid-item[data-id="' + id + '"]');
        if (item) item.classList.add('gamut-lut__grid-item--active');

        state.selectedImage = img;

        // Show canvas, hide empty state — respect current compare mode.
        if (dom.emptyState) dom.emptyState.style.display = 'none';
        if (state.compareMode === 'none') {
            if (dom.canvasWrap) dom.canvasWrap.style.display = 'block';
        }

        // Scroll preview into view smoothly.
        scrollToPreview();

        // Load image into engine.
        if (engine) {
            setLoading(true);
            engine.loadImage(img.url).then(function() {
                setLoading(false);
                if (state.compareMode === 'before-after') {
                    updateComparison();
                }
                if (state.compareMode === 'ab') {
                    updateAbComparison();
                }
            }).catch(function() {
                setLoading(false);
            });
        }

        // Track analytics.
        trackEvent('image_preview', img.id, img.title);
    }

    /**
     * Scroll the preview area into view.
     */
    function scrollToPreview() {
        var target = dom.canvasWrap || dom.root.querySelector('.gamut-lut__preview');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // ======================================================
    //  COLLECTION & LUT SELECTION
    // ======================================================

    /**
     * Handle collection dropdown change.
     */
    function onCollectionChange() {
        var slug = dom.collectionSelect.value;

        if (!slug) {
            state.selectedCollection = null;
            state.selectedLut = null;
            state.selectedLutB = null;
            revealControlGroup(dom.lutSelectGroup, false);
            revealControlGroup(dom.intensityGroup, false);
            revealControlGroup(dom.compareGroup, false);
            revealControlGroup(dom.lutSelectBGroup, false);
            revealControlGroup(dom.shareGroup, false);
            hideCart();
            hideUpsell();
            return;
        }

        selectCollectionBySlug(slug);
    }

    /**
     * Select a collection by slug.
     */
    function selectCollectionBySlug(slug) {
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
        state.selectedLutB = null;

        // Ensure dropdown reflects the selection.
        if (dom.collectionSelect) {
            dom.collectionSelect.value = slug;
        }

        // Populate LUT dropdown.
        var html = '<option value="">Select LUT</option>';
        col.luts.forEach(function(lut) {
            html += '<option value="' + lut.id + '">' + escapeHtml(lut.title) + '</option>';
        });
        dom.lutSelect.innerHTML = html;

        // Reset LUT B dropdown (user must pick from B collection selector).
        if (dom.lutSelectB) {
            dom.lutSelectB.innerHTML = '<option value="">Select Look</option>';
        }

        // Show LUT select group, hide the rest.
        revealControlGroup(dom.lutSelectGroup, true);
        revealControlGroup(dom.intensityGroup, false);
        revealControlGroup(dom.compareGroup, false);
        revealControlGroup(dom.lutSelectBGroup, false);
        revealControlGroup(dom.shareGroup, false);

        // Show/hide cart button based on product_id or product_url.
        if (col.product_id || col.product_url) {
            showCart();
        } else {
            hideCart();
        }

        // Render upsell (other collections).
        renderUpsell(col);

        // Preload all .cube files for this collection in the background.
        preloadCollection(col);

        // Auto-select first LUT in the collection.
        if (col.luts && col.luts.length > 0) {
            setTimeout(function() {
                selectLutById(col.luts[0].id);
            }, 50);
        }
    }

    /**
     * Randomize: pick a random Look from any collection.
     */
    function onRandomize() {
        if (!state.collections.length) return;

        // Build flat list of all LUTs with their collection.
        var allLuts = [];
        state.collections.forEach(function(col) {
            if (col.luts && col.luts.length) {
                col.luts.forEach(function(lut) {
                    allLuts.push({ lut: lut, collection: col });
                });
            }
        });

        if (!allLuts.length) return;

        // Pick a random one (avoid picking the same LUT again).
        var pick;
        if (allLuts.length === 1) {
            pick = allLuts[0];
        } else {
            do {
                pick = allLuts[Math.floor(Math.random() * allLuts.length)];
            } while (state.selectedLut && pick.lut.id === state.selectedLut.id && allLuts.length > 1);
        }

        // Reset compare mode so the user sees the full canvas with the new LUT.
        if (state.compareMode !== 'none') {
            setCompareMode('none');
        }

        // Select the collection, then the LUT.
        selectCollectionBySlug(pick.collection.slug);

        // Auto-load first image if none selected.
        if (!state.selectedImage && state.images.length > 0) {
            selectImageById(state.images[0].id);
        }

        // Select the LUT (slight delay for collection preload to start).
        setTimeout(function() {
            selectLutById(pick.lut.id);
        }, 50);
    }

    /**
     * Handle LUT dropdown change.
     */
    function onLutChange() {
        var lutId = parseInt(dom.lutSelect.value, 10);

        if (!lutId || !state.selectedCollection) {
            state.selectedLut = null;
            revealControlGroup(dom.intensityGroup, false);
            revealControlGroup(dom.compareGroup, false);
            revealControlGroup(dom.shareGroup, false);
            return;
        }

        selectLutById(lutId);
    }

    /**
     * Select a LUT by ID.
     */
    function selectLutById(lutId) {
        if (!state.selectedCollection) return;

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

        // Ensure dropdown reflects the selection.
        if (dom.lutSelect) {
            dom.lutSelect.value = lutId;
        }

        // Load and apply the LUT.
        loadLutForPreview(lut);

        // Show controls with smooth reveal.
        revealControlGroup(dom.intensityGroup, true);
        revealControlGroup(dom.compareGroup, true);
        revealControlGroup(dom.shareGroup, true);

        // Show LUT name toast on canvas.
        showLutToast(lut.title);

        // Track analytics.
        trackEvent('lut_preview', lut.id, lut.title, state.selectedCollection.slug);
    }

    /**
     * Load a LUT, using cache if available.
     */
    function loadLutForPreview(lutData) {
        var id = lutData.id;

        if (state.cubeCache.has(id)) {
            applyLut(state.cubeCache.get(id));
            return;
        }

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
     */
    function applyLut(parsed) {
        if (!engine) return;

        engine.loadLut(parsed);
        engine.setIntensity(state.intensity / 100);
        engine.render();

        if (state.compareMode === 'before-after') {
            updateComparison();
        }
        if (state.compareMode === 'ab') {
            updateAbComparison();
        }
    }

    /**
     * Preload all .cube files for a collection in the background.
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

    // ======================================================
    //  INTENSITY
    // ======================================================

    /**
     * Handle intensity slider change.
     */
    function onIntensityChange() {
        var value = parseInt(dom.intensitySlider.value, 10);
        state.intensity = value;

        if (dom.intensityValue) {
            dom.intensityValue.textContent = value + '%';
        }

        var pct = value;
        dom.intensitySlider.style.background =
            'linear-gradient(to right, var(--gamut-accent) 0%, var(--gamut-accent) ' + pct + '%, var(--gamut-border) ' + pct + '%, var(--gamut-border) 100%)';

        if (engine) {
            engine.setIntensity(value / 100);
            engine.render();

            if (state.compareMode === 'before-after') {
                updateComparison();
            }
            if (state.compareMode === 'ab') {
                updateAbComparison();
            }
        }
    }

    // ======================================================
    //  COMPARE MODE (segmented control)
    // ======================================================

    /**
     * Handle segmented control click.
     */
    function onSegmentClick(e) {
        var btn = e.currentTarget;
        var mode = btn.getAttribute('data-mode');

        setCompareMode(mode);
    }

    /**
     * Set the compare mode and update UI.
     */
    function setCompareMode(mode) {
        state.compareMode = mode;

        // Update segmented control active state.
        if (dom.segmented) {
            var segments = dom.segmented.querySelectorAll('.gamut-lut__segment');
            for (var i = 0; i < segments.length; i++) {
                var isActive = segments[i].getAttribute('data-mode') === mode;
                segments[i].classList.toggle('gamut-lut__segment--active', isActive);
                segments[i].setAttribute('aria-checked', isActive ? 'true' : 'false');
            }
        }

        // Hide both comparison views first.
        hideComparison();
        hideAbComparison();
        revealControlGroup(dom.lutSelectBGroup, false);

        if (mode === 'before-after') {
            showComparison();
        } else if (mode === 'ab') {
            revealControlGroup(dom.lutSelectBGroup, true);
            showAbComparison();
        } else {
            // 'none' — show the main canvas.
            if (dom.canvasWrap && state.selectedImage) {
                dom.canvasWrap.style.display = 'block';
            }
        }
    }

    // ======================================================
    //  BEFORE/AFTER COMPARISON
    // ======================================================

    function showComparison() {
        if (!dom.comparison || !engine) return;

        dom.comparison.style.display = 'block';
        if (dom.canvasWrap) dom.canvasWrap.style.display = 'none';

        if (!slider) {
            slider = new GamutComparisonSlider(dom.comparison);
        }

        updateComparison();
    }

    function hideComparison() {
        if (dom.comparison) dom.comparison.style.display = 'none';
    }

    function updateComparison() {
        if (!slider || !engine || !engine.imageLoaded) return;

        var beforeCanvas = engine.getOriginalCanvas();
        var afterCanvas = engine.captureCanvas();

        slider.updateImages(beforeCanvas, afterCanvas);
        slider.reset();
    }

    // ======================================================
    //  A/B LUT COMPARISON
    // ======================================================

    /**
     * Handle B-side collection dropdown change.
     */
    function onCollectionBChange() {
        var slug = dom.collectionSelectB.value;
        state.selectedLutB = null;

        if (!slug || !dom.lutSelectB) {
            dom.lutSelectB.innerHTML = '<option value="">Select Look</option>';
            return;
        }

        // Find the selected B collection.
        var col = null;
        for (var i = 0; i < state.collections.length; i++) {
            if (state.collections[i].slug === slug) {
                col = state.collections[i];
                break;
            }
        }
        if (!col) return;

        // Populate the B LUT dropdown with this collection's Looks.
        var html = '<option value="">Select Look</option>';
        col.luts.forEach(function(lut) {
            html += '<option value="' + lut.id + '">' + escapeHtml(lut.title) + '</option>';
        });
        dom.lutSelectB.innerHTML = html;

        // Preload this collection's LUTs in background.
        preloadCollection(col);
    }

    /**
     * Handle second LUT (B) dropdown change — cross-collection.
     */
    function onLutBChange() {
        var lutId = parseInt(dom.lutSelectB.value, 10);

        if (!lutId) {
            state.selectedLutB = null;
            return;
        }

        // Find the LUT across the selected B collection.
        var bSlug = dom.collectionSelectB ? dom.collectionSelectB.value : '';
        var lut = findLutById(lutId, bSlug);
        if (!lut) return;

        state.selectedLutB = lut;

        // Load and render B side.
        loadLutB(lut);
    }

    /**
     * Find a LUT by ID, optionally within a specific collection.
     */
    function findLutById(lutId, collectionSlug) {
        var collections = state.collections;
        for (var i = 0; i < collections.length; i++) {
            if (collectionSlug && collections[i].slug !== collectionSlug) continue;
            var luts = collections[i].luts;
            for (var j = 0; j < luts.length; j++) {
                if (luts[j].id === lutId) return luts[j];
            }
        }
        return null;
    }

    /**
     * Load LUT B data and update A/B comparison.
     */
    function loadLutB(lutData) {
        var id = lutData.id;

        if (state.cubeCache.has(id)) {
            updateAbComparison();
            return;
        }

        var url = config.restUrl + '/cube/' + id;
        GamutCubeParser.fetchAndParse(url, config.nonce)
            .then(function(parsed) {
                state.cubeCache.set(id, parsed);
                updateAbComparison();
            })
            .catch(function(err) {
                console.error('GamutLutPreview: Failed to load LUT B', err);
            });
    }

    function showAbComparison() {
        if (!dom.abComparison || !engine) return;

        dom.abComparison.style.display = 'block';
        if (dom.canvasWrap) dom.canvasWrap.style.display = 'none';

        if (!abSlider) {
            abSlider = new GamutComparisonSlider(dom.abComparison);
        }

        // Show placeholder labels if not both LUTs selected yet.
        updateAbLabels();

        if (state.selectedLut && state.selectedLutB) {
            updateAbComparison();
        } else if (state.selectedLut && engine.imageLoaded) {
            // No LUT B yet — show LUT A vs. Original as a useful default.
            var parsedA = state.cubeCache.get(state.selectedLut.id);
            if (parsedA) {
                engine.loadLut(parsedA);
                engine.setIntensity(state.intensity / 100);
                var canvasA = engine.captureCanvas();
                var canvasOriginal = engine.getOriginalCanvas();
                engine.render();
                abSlider.updateImages(canvasA, canvasOriginal);
                abSlider.reset();
            }
        }
    }

    function hideAbComparison() {
        if (dom.abComparison) dom.abComparison.style.display = 'none';
    }

    /**
     * Update the A/B comparison labels. Shows "Select a LUT" as placeholder.
     */
    function updateAbLabels() {
        if (!dom.abComparison) return;

        var labelBefore = dom.abComparison.querySelector('.gamut-lut__comparison-label--before');
        var labelAfter = dom.abComparison.querySelector('.gamut-lut__comparison-label--after');

        if (labelBefore) {
            labelBefore.textContent = state.selectedLut ? state.selectedLut.title : 'LUT A';
        }
        if (labelAfter) {
            labelAfter.textContent = state.selectedLutB ? state.selectedLutB.title : 'Original';
        }
    }

    /**
     * Render both LUT A and LUT B onto the A/B comparison canvases.
     */
    function updateAbComparison() {
        if (!abSlider || !engine || !engine.imageLoaded) return;
        if (!state.selectedLut) {
            updateAbLabels();
            return;
        }

        // No LUT B yet — show LUT A vs. Original as a useful default.
        if (!state.selectedLutB) {
            var parsedFallback = state.cubeCache.get(state.selectedLut.id);
            if (parsedFallback) {
                engine.loadLut(parsedFallback);
                engine.setIntensity(state.intensity / 100);
                var canvasLutA = engine.captureCanvas();
                var canvasOrig = engine.getOriginalCanvas();
                engine.render();
                abSlider.updateImages(canvasLutA, canvasOrig);
                abSlider.reset();
            }
            updateAbLabels();
            return;
        }

        var parsedA = state.cubeCache.get(state.selectedLut.id);
        var parsedB = state.cubeCache.get(state.selectedLutB.id);
        if (!parsedA || !parsedB) return;

        // Render LUT A at current intensity and capture.
        engine.loadLut(parsedA);
        engine.setIntensity(state.intensity / 100);
        var canvasA = engine.captureCanvas();

        // Render LUT B at current intensity and capture.
        engine.loadLut(parsedB);
        engine.setIntensity(state.intensity / 100);
        var canvasB = engine.captureCanvas();

        // Restore LUT A as the active LUT.
        engine.loadLut(parsedA);
        engine.render();

        // Update labels.
        updateAbLabels();

        abSlider.updateImages(canvasA, canvasB);
        abSlider.reset();
    }

    // ======================================================
    //  LUT NAME TOAST
    // ======================================================

    /**
     * Show the LUT name as a toast overlay on the canvas.
     */
    function showLutToast(name) {
        if (!dom.lutToast) return;

        dom.lutToast.textContent = name;
        dom.lutToast.classList.add('gamut-lut__lut-toast--visible');

        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function() {
            dom.lutToast.classList.remove('gamut-lut__lut-toast--visible');
        }, 1500);
    }

    // ======================================================
    //  FAVORITES
    // ======================================================

    /**
     * Load favorites from localStorage (instant, no network).
     */
    function loadFavoritesLocal() {
        try {
            var stored = localStorage.getItem(FAVORITES_KEY);
            state.favorites = stored ? JSON.parse(stored) : [];
        } catch (e) {
            state.favorites = [];
        }
    }

    /**
     * Save favorites to localStorage.
     */
    function saveFavoritesLocal() {
        try {
            localStorage.setItem(FAVORITES_KEY, JSON.stringify(state.favorites));
        } catch (e) {
            // Silently fail if localStorage is full or unavailable.
        }
    }

    /**
     * Sync favorites from the server for logged-in users.
     * Merges server-side favorites with any localStorage ones.
     */
    function syncFavoritesFromServer() {
        var formData = new FormData();
        formData.append('action', 'gamut_get_favorites');
        formData.append('nonce', config.cartNonce);

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success && response.data.logged_in) {
                var serverFavs = response.data.favorites || [];
                var localFavs = state.favorites;

                // Merge: union of server and local.
                var merged = serverFavs.slice();
                for (var i = 0; i < localFavs.length; i++) {
                    if (merged.indexOf(localFavs[i]) === -1) {
                        merged.push(localFavs[i]);
                    }
                }

                state.favorites = merged;
                saveFavoritesLocal();
                updateFavoritesCount();

                // Re-render grid to show correct hearts.
                renderImageGrid();

                // If local had extras not on server, push merge to server.
                if (merged.length !== serverFavs.length) {
                    saveFavoritesToServer();
                }
            }
        })
        .catch(function() {
            // Silently fall back to localStorage.
        });
    }

    /**
     * Save favorites to the server (debounced).
     */
    function saveFavoritesToServer() {
        if (!config.isLoggedIn) return;

        if (favSaveTimeout) clearTimeout(favSaveTimeout);
        favSaveTimeout = setTimeout(function() {
            var formData = new FormData();
            formData.append('action', 'gamut_save_favorites');
            formData.append('nonce', config.cartNonce);
            formData.append('favorites', JSON.stringify(state.favorites));

            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).catch(function() {
                // Silently fail.
            });
        }, 1000);
    }

    function onFavoriteClick(e) {
        e.stopPropagation();
        e.preventDefault();

        var gridItem = e.currentTarget.closest('.gamut-lut__grid-item');
        var id = parseInt(gridItem.getAttribute('data-id'), 10);
        var idx = state.favorites.indexOf(id);

        if (idx !== -1) {
            state.favorites.splice(idx, 1);
            gridItem.classList.remove('gamut-lut__grid-item--favorited');
        } else {
            state.favorites.push(id);
            gridItem.classList.add('gamut-lut__grid-item--favorited');
        }

        // Update the heart icon fill and aria-label.
        var isFav = state.favorites.indexOf(id) !== -1;
        var svg = e.currentTarget.querySelector('svg path');
        if (svg) {
            svg.setAttribute('fill', isFav ? 'currentColor' : 'none');
        }
        e.currentTarget.setAttribute('aria-label', isFav ? 'Remove from favorites' : 'Add to favorites');

        saveFavoritesLocal();
        saveFavoritesToServer();
        updateFavoritesCount();

        // Re-apply filter if favorites-only mode is active.
        if (state.showFavoritesOnly) {
            filterImages();
        }
    }

    function onFavoritesToggle() {
        state.showFavoritesOnly = !state.showFavoritesOnly;

        if (dom.favoritesToggle) {
            dom.favoritesToggle.classList.toggle('gamut-lut__favorites-toggle--active', state.showFavoritesOnly);
            dom.favoritesToggle.setAttribute('aria-label',
                state.showFavoritesOnly ? 'Show all images' : 'Show favorite images only'
            );
            dom.favoritesToggle.setAttribute('aria-pressed', state.showFavoritesOnly ? 'true' : 'false');
        }

        filterImages();
    }

    function updateFavoritesCount() {
        if (dom.favoritesCount) {
            dom.favoritesCount.textContent = state.favorites.length;
            dom.favoritesCount.style.display = state.favorites.length > 0 ? '' : 'none';
        }
    }

    // ======================================================
    //  CATEGORY FILTER (with favorites integration)
    // ======================================================

    function onCategoryChange() {
        state.activeCategory = dom.categoryFilter.value;
        filterImages();
    }

    function filterImages() {
        if (!dom.grid) return;

        var items = dom.grid.querySelectorAll('.gamut-lut__grid-item');
        var visibleCount = 0;
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var id = parseInt(item.getAttribute('data-id'), 10);
            var show = true;

            // Category filter.
            if (state.activeCategory) {
                var cats = item.getAttribute('data-categories') || '';
                if (cats.indexOf(state.activeCategory) === -1) {
                    show = false;
                }
            }

            // Favorites filter.
            if (state.showFavoritesOnly && state.favorites.indexOf(id) === -1) {
                show = false;
            }

            item.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        }

        // Show empty state when favorites filter yields no results.
        if (dom.gridEmpty) {
            dom.gridEmpty.style.display = (visibleCount === 0 && state.showFavoritesOnly) ? '' : 'none';
        }
    }

    // ======================================================
    //  SHARE A LOOK
    // ======================================================

    // Pending deep-link state parsed from URL before data is loaded.
    var pendingShareState = null;

    /**
     * Parse share parameters from the current URL.
     */
    function parseShareUrl() {
        var params = new URLSearchParams(window.location.search);
        var col = params.get('gamut_collection');
        var lut = params.get('gamut_lut');
        var img = params.get('gamut_image');
        var intensity = params.get('gamut_intensity');

        if (col && lut) {
            pendingShareState = {
                collection: col,
                lut: parseInt(lut, 10),
                image: img ? parseInt(img, 10) : null,
                intensity: intensity ? parseInt(intensity, 10) : 100
            };
        }
    }

    /**
     * Apply the pending share state after data is loaded.
     */
    function applyShareState() {
        if (!pendingShareState) return;

        var ss = pendingShareState;
        pendingShareState = null;

        // Select collection.
        selectCollectionBySlug(ss.collection);

        // Set intensity.
        if (ss.intensity !== null && dom.intensitySlider) {
            state.intensity = ss.intensity;
            dom.intensitySlider.value = ss.intensity;
            onIntensityChange();
        }

        // Select image.
        if (ss.image) {
            selectImageById(ss.image);
        }

        // Select LUT (with slight delay to allow preload).
        if (ss.lut) {
            setTimeout(function() {
                selectLutById(ss.lut);
            }, 100);
        }
    }

    /**
     * Handle share button click.
     */
    function onShareClick() {
        if (!state.selectedCollection || !state.selectedLut) return;

        var baseUrl = config.pageUrl || window.location.href.split('?')[0];
        var params = new URLSearchParams();
        params.set('gamut_collection', state.selectedCollection.slug);
        params.set('gamut_lut', state.selectedLut.id);
        if (state.selectedImage) {
            params.set('gamut_image', state.selectedImage.id);
        }
        if (state.intensity !== 100) {
            params.set('gamut_intensity', state.intensity);
        }

        var shareUrl = baseUrl + '?' + params.toString();

        // Copy to clipboard.
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shareUrl).then(function() {
                showShareMessage('Link copied to clipboard!');
            }).catch(function() {
                showShareFallback(shareUrl);
            });
        } else {
            showShareFallback(shareUrl);
        }

        // Track analytics.
        trackEvent('share_click', state.selectedLut.id, state.selectedLut.title, state.selectedCollection.slug);
    }

    function showShareFallback(url) {
        prompt('Copy this link:', url);
    }

    function showShareMessage(text) {
        if (!dom.shareMessage) return;
        dom.shareMessage.textContent = text;
        dom.shareMessage.classList.add('gamut-lut__share-message--visible');
        setTimeout(function() {
            dom.shareMessage.classList.remove('gamut-lut__share-message--visible');
        }, 3500);
    }

    // ======================================================
    //  UPSELL / BUNDLING
    // ======================================================

    /**
     * Render upsell section showing other purchasable collections.
     */
    function renderUpsell(currentCollection) {
        if (!dom.upsellList || !dom.upsellSection) return;

        var others = state.collections.filter(function(col) {
            return col.slug !== currentCollection.slug && col.product_id;
        });

        if (others.length === 0) {
            hideUpsell();
            return;
        }

        var html = '';
        others.forEach(function(col) {
            html += '<button type="button" class="gamut-lut__upsell-item" data-slug="' + col.slug + '">';
            html += '<span class="gamut-lut__upsell-name">' + escapeHtml(col.name) + '</span>';
            html += '<span class="gamut-lut__upsell-count">' + col.lut_count + ' LUTs</span>';
            html += '</button>';
        });

        dom.upsellList.innerHTML = html;
        dom.upsellSection.style.display = '';

        // Bind clicks.
        var items = dom.upsellList.querySelectorAll('.gamut-lut__upsell-item');
        for (var i = 0; i < items.length; i++) {
            items[i].addEventListener('click', function(e) {
                var slug = e.currentTarget.getAttribute('data-slug');
                if (dom.collectionSelect) {
                    dom.collectionSelect.value = slug;
                }
                selectCollectionBySlug(slug);
            });
        }
    }

    function hideUpsell() {
        if (dom.upsellSection) dom.upsellSection.style.display = 'none';
    }

    // ======================================================
    //  CART
    // ======================================================

    function onAddToCart() {
        if (!state.selectedCollection) return;

        var col = state.selectedCollection;
        var lutTitle = state.selectedLut ? state.selectedLut.title : col.name;

        // External product URL: open in a new tab.
        if (!col.product_id && col.product_url) {
            window.open(col.product_url, '_blank', 'noopener');
            trackEvent('cart_add', 0, lutTitle, col.slug);
            return;
        }

        if (!col.product_id) return;

        dom.cartBtn.disabled = true;
        hideCartMessage();

        var formData = new FormData();
        formData.append('action', 'gamut_add_to_cart');
        formData.append('product_id', col.product_id);
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

        // Track analytics — record both the product and which LUT was being previewed.
        trackEvent('cart_add', col.product_id, lutTitle, col.slug);
    }

    // ======================================================
    //  ANALYTICS
    // ======================================================

    /**
     * Track an analytics event via a lightweight AJAX ping.
     * Non-blocking, fire-and-forget.
     */
    function trackEvent(eventType, objectId, title, collection) {
        var formData = new FormData();
        formData.append('action', 'gamut_track_preview');
        formData.append('nonce', config.cartNonce);
        formData.append('event_type', eventType);
        formData.append('object_id', objectId);
        formData.append('title', title || '');
        formData.append('collection', collection || '');
        formData.append('session_id', state.sessionId);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(config.ajaxUrl, formData);
        } else {
            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                keepalive: true
            }).catch(function() {
                // Silently fail analytics.
            });
        }
    }

    function generateSessionId() {
        try {
            var stored = sessionStorage.getItem('gamut_session_id');
            if (stored) return stored;
            var id = 'gs_' + Math.random().toString(36).substr(2, 12) + Date.now().toString(36);
            sessionStorage.setItem('gamut_session_id', id);
            return id;
        } catch (e) {
            return 'gs_' + Math.random().toString(36).substr(2, 12);
        }
    }

    // ======================================================
    //  MOBILE GESTURES
    // ======================================================

    /**
     * Initialize mobile gesture support on the preview area.
     * - Pinch-to-zoom on the canvas
     * - Swipe left/right to cycle LUTs
     */
    function initMobileGestures() {
        var target = dom.canvasWrap;
        if (!target) return;

        var touchState = {
            startX: 0,
            startY: 0,
            startDist: 0,
            startScale: 1,
            currentScale: 1,
            translateX: 0,
            translateY: 0,
            isPinching: false,
            isSwiping: false,
            startTime: 0
        };

        target.addEventListener('touchstart', function(e) {
            if (e.touches.length === 2) {
                // Pinch start.
                e.preventDefault();
                touchState.isPinching = true;
                touchState.isSwiping = false;
                touchState.startDist = getTouchDistance(e.touches);
                touchState.startScale = touchState.currentScale;
            } else if (e.touches.length === 1) {
                // Potential swipe.
                touchState.startX = e.touches[0].clientX;
                touchState.startY = e.touches[0].clientY;
                touchState.startTime = Date.now();
                touchState.isSwiping = true;
                touchState.isPinching = false;
            }
        }, { passive: false });

        target.addEventListener('touchmove', function(e) {
            if (touchState.isPinching && e.touches.length === 2) {
                e.preventDefault();
                var dist = getTouchDistance(e.touches);
                var scale = touchState.startScale * (dist / touchState.startDist);
                touchState.currentScale = Math.max(1, Math.min(4, scale));

                applyCanvasTransform(touchState);
            } else if (touchState.isSwiping && touchState.currentScale <= 1) {
                // Allow default scroll when not zoomed and not swiping horizontally.
                var deltaX = Math.abs(e.touches[0].clientX - touchState.startX);
                var deltaY = Math.abs(e.touches[0].clientY - touchState.startY);
                if (deltaY > deltaX) {
                    // Vertical — let the page scroll through.
                    touchState.isSwiping = false;
                }
            }
        }, { passive: false });

        target.addEventListener('touchend', function(e) {
            if (touchState.isPinching) {
                touchState.isPinching = false;

                // Snap back to 1x if close.
                if (touchState.currentScale < 1.1) {
                    touchState.currentScale = 1;
                    touchState.translateX = 0;
                    touchState.translateY = 0;
                    applyCanvasTransform(touchState);
                }
                return;
            }

            if (touchState.isSwiping && e.changedTouches.length === 1) {
                touchState.isSwiping = false;
                var deltaX = e.changedTouches[0].clientX - touchState.startX;
                var deltaY = e.changedTouches[0].clientY - touchState.startY;
                var elapsed = Date.now() - touchState.startTime;

                // If zoomed in, pan instead of swiping LUTs.
                if (touchState.currentScale > 1) {
                    touchState.translateX += deltaX;
                    touchState.translateY += deltaY;
                    applyCanvasTransform(touchState);
                    return;
                }

                // Detect horizontal swipe: fast enough, horizontal enough.
                if (elapsed < 400 && Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
                    if (deltaX < 0) {
                        cycleLut(1); // Swipe left = next.
                    } else {
                        cycleLut(-1); // Swipe right = previous.
                    }
                }
            }
        }, { passive: true });

        // Double-tap to reset zoom.
        var lastTap = 0;
        target.addEventListener('touchend', function(e) {
            if (e.touches.length > 0) return;
            var now = Date.now();
            if (now - lastTap < 300) {
                touchState.currentScale = 1;
                touchState.translateX = 0;
                touchState.translateY = 0;
                applyCanvasTransform(touchState);
            }
            lastTap = now;
        }, { passive: true });
    }

    function getTouchDistance(touches) {
        var dx = touches[0].clientX - touches[1].clientX;
        var dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function applyCanvasTransform(ts) {
        var canvas = dom.canvas;
        if (!canvas) return;
        canvas.style.transform = 'scale(' + ts.currentScale + ') translate(' + (ts.translateX / ts.currentScale) + 'px, ' + (ts.translateY / ts.currentScale) + 'px)';
        canvas.style.transformOrigin = 'center center';
    }

    /**
     * Cycle through LUTs in the current collection.
     *
     * @param {number} direction - +1 for next, -1 for previous.
     */
    function cycleLut(direction) {
        if (!state.selectedCollection || !state.selectedCollection.luts.length) return;

        var luts = state.selectedCollection.luts;
        var currentIdx = -1;

        if (state.selectedLut) {
            for (var i = 0; i < luts.length; i++) {
                if (luts[i].id === state.selectedLut.id) {
                    currentIdx = i;
                    break;
                }
            }
        }

        var nextIdx = currentIdx + direction;
        if (nextIdx < 0) nextIdx = luts.length - 1;
        if (nextIdx >= luts.length) nextIdx = 0;

        dom.lutSelect.value = luts[nextIdx].id;
        selectLutById(luts[nextIdx].id);
    }

    // ======================================================
    //  UI HELPERS
    // ======================================================

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

    /**
     * Reveal or hide a control group with CSS transition.
     */
    function revealControlGroup(el, show) {
        if (!el) return;

        if (show) {
            el.classList.add('gamut-lut__control-group--visible');
        } else {
            el.classList.remove('gamut-lut__control-group--visible');
        }
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

    /**
     * Escape string for use in HTML attributes.
     */
    function escapeAttr(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ======================================================
    //  COLLECTION EMBED INSTANCES
    // ======================================================

    /**
     * Initialize any [gamut_collection] embed instances on the page.
     */
    function initEmbeds() {
        var embeds = document.querySelectorAll('.gamut-lut--embed');
        for (var i = 0; i < embeds.length; i++) {
            initSingleEmbed(embeds[i]);
        }
    }

    /**
     * Initialize a single collection embed instance.
     */
    function initSingleEmbed(container) {
        var collectionSlug = container.getAttribute('data-collection');
        if (!collectionSlug || typeof gamutLutConfig === 'undefined') return;

        var cfg = gamutLutConfig;
        var headers = { 'X-WP-Nonce': cfg.nonce };
        var embedState = {
            collection: null,
            selectedLut: null,
            selectedImage: null,
            intensity: 100,
            compareMode: false,
            cubeCache: new Map()
        };

        var canvas = container.querySelector('.gamut-lut__embed-canvas');
        var lutSelect = container.querySelector('.gamut-lut__embed-lut-select');
        var intensitySlider = container.querySelector('.gamut-lut__embed-intensity');
        var intensityValue = container.querySelector('.gamut-lut__intensity-value');
        var intensityGroup = container.querySelector('.gamut-lut__control-group--intensity');
        var compareCheckbox = container.querySelector('.gamut-lut__embed-compare');
        var cartBtn = container.querySelector('.gamut-lut__embed-cart-btn');
        var cartSection = container.querySelector('.gamut-lut__cart');
        var cartMessage = container.querySelector('.gamut-lut__cart-message');
        var comparison = container.querySelector('.gamut-lut__comparison');
        var canvasWrap = container.querySelector('.gamut-lut__canvas-wrap');
        var emptyState = container.querySelector('.gamut-lut__empty-state');
        var grid = container.querySelector('.gamut-lut__grid');
        var cubeLoading = container.querySelector('.gamut-lut__cube-loading');

        var embedEngine = canvas ? new GamutLutEngine(canvas) : null;
        var embedSlider = null;

        // Fetch collection and images data.
        Promise.all([
            fetch(cfg.restUrl + '/collection/' + encodeURIComponent(collectionSlug), { credentials: 'same-origin', headers: headers })
                .then(function(r) { return r.json(); }),
            fetch(cfg.restUrl + '/images', { credentials: 'same-origin', headers: headers })
                .then(function(r) { return r.json(); })
        ]).then(function(results) {
            embedState.collection = results[0];
            var imagesData = results[1];
            var images = imagesData.images || [];

            // Populate LUT dropdown.
            var html = '<option value="">Select LUT</option>';
            if (embedState.collection && embedState.collection.luts) {
                embedState.collection.luts.forEach(function(lut) {
                    html += '<option value="' + lut.id + '">' + escapeHtml(lut.title) + '</option>';
                });
            }
            if (lutSelect) lutSelect.innerHTML = html;

            // Show cart if product_id or product_url exists.
            if (embedState.collection && (embedState.collection.product_id || embedState.collection.product_url) && cartSection) {
                cartSection.style.display = '';
            }

            // Render image grid.
            if (grid && images.length) {
                var gridHtml = '';
                images.forEach(function(img) {
                    gridHtml += '<div class="gamut-lut__grid-item" data-id="' + img.id + '" title="' + escapeAttr(img.title) + '" tabindex="0" role="button" aria-label="' + escapeAttr(img.title) + '">';
                    gridHtml += '<img src="' + img.thumbnail + '" alt="' + escapeHtml(img.title) + '" loading="lazy" width="' + img.width + '" height="' + img.height + '">';
                    gridHtml += '</div>';
                });
                grid.innerHTML = gridHtml;

                // Bind image clicks and keyboard activation.
                var items = grid.querySelectorAll('.gamut-lut__grid-item');
                for (var j = 0; j < items.length; j++) {
                    items[j].addEventListener('click', function(e) {
                        var id = parseInt(e.currentTarget.getAttribute('data-id'), 10);
                        selectEmbedImage(id, e.currentTarget);
                    });
                    items[j].addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            var id = parseInt(e.currentTarget.getAttribute('data-id'), 10);
                            selectEmbedImage(id, e.currentTarget);
                        }
                    });
                }

                function selectEmbedImage(id, el) {
                    var img = null;
                    for (var k = 0; k < images.length; k++) {
                        if (images[k].id === id) { img = images[k]; break; }
                    }
                    if (!img || !embedEngine) return;

                    var prev = grid.querySelector('.gamut-lut__grid-item--active');
                    if (prev) prev.classList.remove('gamut-lut__grid-item--active');
                    el.classList.add('gamut-lut__grid-item--active');

                    embedState.selectedImage = img;
                    if (emptyState) emptyState.style.display = 'none';
                    if (canvasWrap) canvasWrap.style.display = 'block';

                    embedEngine.loadImage(img.url).then(function() {
                        if (embedState.compareMode && embedSlider) {
                            var before = embedEngine.getOriginalCanvas();
                            var after = embedEngine.captureCanvas();
                            embedSlider.updateImages(before, after);
                            embedSlider.reset();
                        }
                    });
                }
            }

            // Preload LUTs.
            if (embedState.collection && embedState.collection.luts) {
                embedState.collection.luts.forEach(function(lut) {
                    var url = cfg.restUrl + '/cube/' + lut.id;
                    GamutCubeParser.fetchAndParse(url, cfg.nonce)
                        .then(function(parsed) { embedState.cubeCache.set(lut.id, parsed); })
                        .catch(function() {});
                });
            }
        }).catch(function(err) {
            console.error('GamutLutPreview: Embed fetch failed', err);
        });

        // LUT select handler.
        if (lutSelect) {
            lutSelect.addEventListener('change', function() {
                var lutId = parseInt(lutSelect.value, 10);
                if (!lutId || !embedState.collection) return;

                var lut = null;
                embedState.collection.luts.forEach(function(l) {
                    if (l.id === lutId) lut = l;
                });
                if (!lut) return;

                embedState.selectedLut = lut;

                // Show intensity and compare controls with smooth reveal.
                revealControlGroup(intensityGroup, true);
                var compareParent = compareCheckbox ? compareCheckbox.closest('.gamut-lut__control-group') : null;
                revealControlGroup(compareParent, true);

                var applyEmbedLut = function(parsed) {
                    if (!embedEngine) return;
                    embedEngine.loadLut(parsed);
                    embedEngine.setIntensity(embedState.intensity / 100);
                    embedEngine.render();
                };

                if (embedState.cubeCache.has(lutId)) {
                    applyEmbedLut(embedState.cubeCache.get(lutId));
                } else {
                    if (cubeLoading) cubeLoading.style.display = 'inline-block';
                    var url = cfg.restUrl + '/cube/' + lutId;
                    GamutCubeParser.fetchAndParse(url, cfg.nonce)
                        .then(function(parsed) {
                            embedState.cubeCache.set(lutId, parsed);
                            applyEmbedLut(parsed);
                        })
                        .finally(function() {
                            if (cubeLoading) cubeLoading.style.display = 'none';
                        });
                }
            });
        }

        // Intensity handler.
        if (intensitySlider) {
            intensitySlider.addEventListener('input', function() {
                var val = parseInt(intensitySlider.value, 10);
                embedState.intensity = val;
                if (intensityValue) intensityValue.textContent = val + '%';
                intensitySlider.style.background =
                    'linear-gradient(to right, var(--gamut-accent) 0%, var(--gamut-accent) ' + val + '%, var(--gamut-border) ' + val + '%, var(--gamut-border) 100%)';
                if (embedEngine) {
                    embedEngine.setIntensity(val / 100);
                    embedEngine.render();
                }
            });
        }

        // Compare handler.
        if (compareCheckbox) {
            compareCheckbox.addEventListener('change', function() {
                embedState.compareMode = compareCheckbox.checked;
                if (embedState.compareMode && comparison && embedEngine) {
                    comparison.style.display = 'block';
                    if (canvasWrap) canvasWrap.style.display = 'none';
                    if (!embedSlider) {
                        embedSlider = new GamutComparisonSlider(comparison);
                    }
                    var before = embedEngine.getOriginalCanvas();
                    var after = embedEngine.captureCanvas();
                    embedSlider.updateImages(before, after);
                    embedSlider.reset();
                } else {
                    if (comparison) comparison.style.display = 'none';
                    if (canvasWrap && embedState.selectedImage) canvasWrap.style.display = 'block';
                }
            });
        }

        // Cart handler.
        if (cartBtn) {
            cartBtn.addEventListener('click', function() {
                if (!embedState.collection) return;

                var col = embedState.collection;

                // External product URL: open in a new tab.
                if (!col.product_id && col.product_url) {
                    window.open(col.product_url, '_blank', 'noopener');
                    return;
                }

                if (!col.product_id) return;
                cartBtn.disabled = true;

                var formData = new FormData();
                formData.append('action', 'gamut_add_to_cart');
                formData.append('product_id', col.product_id);
                formData.append('nonce', cfg.cartNonce);

                fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(response) {
                        cartBtn.disabled = false;
                        if (cartMessage) {
                            if (response.success) {
                                cartMessage.innerHTML = response.data.in_cart
                                    ? response.data.message + ' <a href="' + response.data.cart_url + '">View Cart</a>'
                                    : response.data.message;
                                cartMessage.className = 'gamut-lut__cart-message gamut-lut__cart-message--' + (response.data.in_cart ? 'info' : 'success');
                            } else {
                                cartMessage.textContent = response.data.message || 'Error adding to cart.';
                                cartMessage.className = 'gamut-lut__cart-message gamut-lut__cart-message--error';
                            }
                            cartMessage.style.display = 'block';
                        }
                    })
                    .catch(function() {
                        cartBtn.disabled = false;
                    });
            });
        }
    }

    // ======================================================
    //  INITIALIZATION
    // ======================================================

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            initEmbeds();
        });
    } else {
        init();
        initEmbeds();
    }

    return {
        getState: function() { return state; },
        getEngine: function() { return engine; },
        cycleLut: cycleLut
    };
})();
