/**
 * Gamut Before/After Comparison Slider
 *
 * Accessible, responsive slider using clip-path for smooth performance.
 * Supports mouse, touch, and keyboard input.
 *
 * @package Gamut_LUT_Preview
 */
var GamutComparisonSlider = (function() {
    'use strict';

    /**
     * @constructor
     * @param {HTMLElement} container - The .gamut-lut__comparison container element.
     */
    function Slider(container) {
        this.container = container;
        this.beforeWrap = container.querySelector('.gamut-lut__comparison-before');
        this.afterWrap = container.querySelector('.gamut-lut__comparison-after');
        this.handle = container.querySelector('.gamut-lut__comparison-handle');
        this.beforeCanvas = container.querySelector('.gamut-lut__comparison-before canvas');
        this.afterCanvas = container.querySelector('.gamut-lut__comparison-after canvas');

        this.position = 0.5; // 0.0 = full before, 1.0 = full after.
        this.isDragging = false;
        this._rafId = null;
        this._pendingPosition = null;

        // Bind event handlers so they can be removed later.
        this._onMouseDown = this._onMouseDown.bind(this);
        this._onMouseMove = this._onMouseMove.bind(this);
        this._onMouseUp = this._onMouseUp.bind(this);
        this._onTouchStart = this._onTouchStart.bind(this);
        this._onTouchMove = this._onTouchMove.bind(this);
        this._onTouchEnd = this._onTouchEnd.bind(this);
        this._onKeyDown = this._onKeyDown.bind(this);

        this._bindEvents();
        this._applyPosition(this.position);
    }

    /**
     * Bind all event listeners.
     */
    Slider.prototype._bindEvents = function() {
        // Mouse events on handle.
        this.handle.addEventListener('mousedown', this._onMouseDown);

        // Touch events on handle.
        this.handle.addEventListener('touchstart', this._onTouchStart, { passive: false });

        // Keyboard events on handle (it has tabindex).
        this.handle.addEventListener('keydown', this._onKeyDown);

        // Also allow clicking anywhere on the container to move the slider.
        this.container.addEventListener('mousedown', this._onMouseDown);
        this.container.addEventListener('touchstart', this._onTouchStart, { passive: false });
    };

    /**
     * Mouse down handler.
     */
    Slider.prototype._onMouseDown = function(e) {
        e.preventDefault();
        this.isDragging = true;
        this._updatePositionFromEvent(e);

        document.addEventListener('mousemove', this._onMouseMove);
        document.addEventListener('mouseup', this._onMouseUp);
    };

    /**
     * Mouse move handler.
     */
    Slider.prototype._onMouseMove = function(e) {
        if (!this.isDragging) return;
        e.preventDefault();
        this._scheduleUpdate(e);
    };

    /**
     * Mouse up handler.
     */
    Slider.prototype._onMouseUp = function() {
        this.isDragging = false;
        document.removeEventListener('mousemove', this._onMouseMove);
        document.removeEventListener('mouseup', this._onMouseUp);
    };

    /**
     * Touch start handler.
     */
    Slider.prototype._onTouchStart = function(e) {
        e.preventDefault();
        this.isDragging = true;
        this._updatePositionFromEvent(e.touches[0]);

        document.addEventListener('touchmove', this._onTouchMove, { passive: false });
        document.addEventListener('touchend', this._onTouchEnd);
    };

    /**
     * Touch move handler.
     */
    Slider.prototype._onTouchMove = function(e) {
        if (!this.isDragging) return;
        e.preventDefault();
        this._scheduleUpdate(e.touches[0]);
    };

    /**
     * Touch end handler.
     */
    Slider.prototype._onTouchEnd = function() {
        this.isDragging = false;
        document.removeEventListener('touchmove', this._onTouchMove);
        document.removeEventListener('touchend', this._onTouchEnd);
    };

    /**
     * Keyboard handler for accessibility.
     */
    Slider.prototype._onKeyDown = function(e) {
        var step = 0.02; // 2% per keystroke.
        var newPos = this.position;

        switch (e.key) {
            case 'ArrowLeft':
            case 'ArrowDown':
                newPos = Math.max(0, this.position - step);
                break;
            case 'ArrowRight':
            case 'ArrowUp':
                newPos = Math.min(1, this.position + step);
                break;
            case 'Home':
                newPos = 0;
                break;
            case 'End':
                newPos = 1;
                break;
            default:
                return;
        }

        e.preventDefault();
        this.setPosition(newPos);
    };

    /**
     * Schedule a position update via requestAnimationFrame for 60fps.
     */
    Slider.prototype._scheduleUpdate = function(event) {
        var self = this;
        var rect = this.container.getBoundingClientRect();
        var x = event.clientX - rect.left;
        var pos = Math.max(0, Math.min(1, x / rect.width));

        this._pendingPosition = pos;

        if (this._rafId === null) {
            this._rafId = requestAnimationFrame(function() {
                self._rafId = null;
                if (self._pendingPosition !== null) {
                    self.setPosition(self._pendingPosition);
                    self._pendingPosition = null;
                }
            });
        }
    };

    /**
     * Update position from a mouse/touch event immediately.
     */
    Slider.prototype._updatePositionFromEvent = function(event) {
        var rect = this.container.getBoundingClientRect();
        var x = event.clientX - rect.left;
        var pos = Math.max(0, Math.min(1, x / rect.width));
        this.setPosition(pos);
    };

    /**
     * Set the slider position programmatically.
     *
     * @param {number} pos - Position from 0.0 to 1.0.
     */
    Slider.prototype.setPosition = function(pos) {
        this.position = Math.max(0, Math.min(1, pos));
        this._applyPosition(this.position);
    };

    /**
     * Apply position to DOM elements.
     */
    Slider.prototype._applyPosition = function(pos) {
        var rightClip = ((1 - pos) * 100).toFixed(2);
        this.beforeWrap.style.clipPath = 'inset(0 ' + rightClip + '% 0 0)';
        this.handle.style.left = (pos * 100).toFixed(2) + '%';

        // Update ARIA attributes.
        this.handle.setAttribute('aria-valuenow', Math.round(pos * 100));
    };

    /**
     * Update the before and after images.
     *
     * @param {HTMLCanvasElement} beforeSource - Canvas with the original image.
     * @param {HTMLCanvasElement} afterSource - Canvas with the graded image.
     */
    Slider.prototype.updateImages = function(beforeSource, afterSource) {
        // Copy before source to the before canvas.
        if (this.beforeCanvas && beforeSource) {
            this.beforeCanvas.width = beforeSource.width;
            this.beforeCanvas.height = beforeSource.height;
            var ctx = this.beforeCanvas.getContext('2d');
            ctx.drawImage(beforeSource, 0, 0);
        }

        // Copy after source to the after canvas.
        if (this.afterCanvas && afterSource) {
            this.afterCanvas.width = afterSource.width;
            this.afterCanvas.height = afterSource.height;
            var ctx2 = this.afterCanvas.getContext('2d');
            ctx2.drawImage(afterSource, 0, 0);
        }
    };

    /**
     * Recalculate dimensions on image swap or resize.
     */
    Slider.prototype.reset = function() {
        this.setPosition(0.5);
    };

    /**
     * Remove all event listeners and clean up.
     */
    Slider.prototype.destroy = function() {
        this.handle.removeEventListener('mousedown', this._onMouseDown);
        this.handle.removeEventListener('touchstart', this._onTouchStart);
        this.handle.removeEventListener('keydown', this._onKeyDown);
        this.container.removeEventListener('mousedown', this._onMouseDown);
        this.container.removeEventListener('touchstart', this._onTouchStart);
        document.removeEventListener('mousemove', this._onMouseMove);
        document.removeEventListener('mouseup', this._onMouseUp);
        document.removeEventListener('touchmove', this._onTouchMove);
        document.removeEventListener('touchend', this._onTouchEnd);

        if (this._rafId !== null) {
            cancelAnimationFrame(this._rafId);
        }
    };

    return Slider;
})();
