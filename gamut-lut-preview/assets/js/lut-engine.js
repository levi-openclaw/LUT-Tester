/**
 * Gamut WebGL2 LUT Engine
 *
 * GPU-accelerated 3D color lookup table processing.
 * Falls back to Canvas 2D pixel processing when WebGL2 is unavailable.
 *
 * @package Gamut_LUT_Preview
 */
var GamutLutEngine = (function() {
    'use strict';

    // Vertex shader: fullscreen quad.
    var VERTEX_SHADER_SRC = [
        '#version 300 es',
        'in vec2 a_position;',
        'in vec2 a_texCoord;',
        'out vec2 v_texCoord;',
        'void main() {',
        '    gl_Position = vec4(a_position, 0.0, 1.0);',
        '    v_texCoord = a_texCoord;',
        '}'
    ].join('\n');

    // Fragment shader: 3D LUT lookup with intensity blending.
    var FRAGMENT_SHADER_SRC = [
        '#version 300 es',
        'precision highp float;',
        'precision highp sampler3D;',
        '',
        'in vec2 v_texCoord;',
        'out vec4 fragColor;',
        '',
        'uniform sampler2D u_image;',
        'uniform sampler3D u_lut;',
        'uniform float u_intensity;',
        'uniform float u_lutSize;',
        '',
        'void main() {',
        '    vec4 originalColor = texture(u_image, v_texCoord);',
        '',
        '    // Map pixel RGB to LUT texture coordinates.',
        '    // Half-texel offset ensures sampling at grid cell centers.',
        '    float scale = (u_lutSize - 1.0) / u_lutSize;',
        '    float offset = 0.5 / u_lutSize;',
        '    vec3 lutCoord = originalColor.rgb * scale + offset;',
        '',
        '    // GPU trilinear interpolation between 8 neighboring LUT entries.',
        '    vec4 gradedColor = texture(u_lut, lutCoord);',
        '',
        '    // Blend based on intensity.',
        '    vec3 result = mix(originalColor.rgb, gradedColor.rgb, u_intensity);',
        '',
        '    fragColor = vec4(result, originalColor.a);',
        '}'
    ].join('\n');

    /**
     * @constructor
     * @param {HTMLCanvasElement} canvas - The canvas element to render into.
     */
    function Engine(canvas) {
        this.canvas = canvas;
        this.gl = null;
        this.useWebGL2 = false;
        this.imageLoaded = false;
        this.lutLoaded = false;
        this.intensity = 1.0;

        // WebGL resources.
        this._program = null;
        this._vao = null;
        this._imageTexture = null;
        this._lutTexture = null;
        this._uniforms = {};
        this._imageWidth = 0;
        this._imageHeight = 0;

        // Canvas 2D fallback resources.
        this._ctx2d = null;
        this._originalImageData = null;
        this._lutData = null;
        this._lutSize = 0;

        // Offscreen image element for loading.
        this._imgElement = null;

        this._initContext();
    }

    /**
     * Initialize WebGL2 context or fall back to Canvas 2D.
     */
    Engine.prototype._initContext = function() {
        var gl = this.canvas.getContext('webgl2', {
            premultipliedAlpha: false,
            preserveDrawingBuffer: true
        });

        if (gl) {
            this.gl = gl;
            this.useWebGL2 = true;
            // Enable float texture support (needed for RGBA32F 3D textures).
            gl.getExtension('EXT_color_buffer_float');
            gl.getExtension('OES_texture_float_linear');
            this._initWebGL();
        } else {
            this._ctx2d = this.canvas.getContext('2d');
            this.useWebGL2 = false;
        }
    };

    /**
     * Set up WebGL2 program, shaders, and geometry.
     */
    Engine.prototype._initWebGL = function() {
        var gl = this.gl;

        // Compile shaders.
        var vs = this._compileShader(gl.VERTEX_SHADER, VERTEX_SHADER_SRC);
        var fs = this._compileShader(gl.FRAGMENT_SHADER, FRAGMENT_SHADER_SRC);

        // Link program.
        var program = gl.createProgram();
        gl.attachShader(program, vs);
        gl.attachShader(program, fs);
        gl.linkProgram(program);

        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            throw new Error('GamutLutEngine: Shader program link failed: ' + gl.getProgramInfoLog(program));
        }

        this._program = program;
        gl.useProgram(program);

        // Get uniform locations.
        this._uniforms.image = gl.getUniformLocation(program, 'u_image');
        this._uniforms.lut = gl.getUniformLocation(program, 'u_lut');
        this._uniforms.intensity = gl.getUniformLocation(program, 'u_intensity');
        this._uniforms.lutSize = gl.getUniformLocation(program, 'u_lutSize');

        // Set texture unit bindings.
        gl.uniform1i(this._uniforms.image, 0);
        gl.uniform1i(this._uniforms.lut, 1);
        gl.uniform1f(this._uniforms.intensity, this.intensity);

        // Create fullscreen quad geometry.
        // Positions: clip space (-1 to 1), TexCoords: (0 to 1, flipped Y for image).
        var vertices = new Float32Array([
            // pos x, pos y, tex u, tex v
            -1, -1,  0, 0,
             1, -1,  1, 0,
            -1,  1,  0, 1,
             1,  1,  1, 1
        ]);

        var vao = gl.createVertexArray();
        gl.bindVertexArray(vao);

        var buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, vertices, gl.STATIC_DRAW);

        var posLoc = gl.getAttribLocation(program, 'a_position');
        gl.enableVertexAttribArray(posLoc);
        gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 16, 0);

        var texLoc = gl.getAttribLocation(program, 'a_texCoord');
        gl.enableVertexAttribArray(texLoc);
        gl.vertexAttribPointer(texLoc, 2, gl.FLOAT, false, 16, 8);

        this._vao = vao;

        // Create texture objects.
        this._imageTexture = gl.createTexture();
        this._lutTexture = gl.createTexture();
    };

    /**
     * Compile a WebGL shader.
     *
     * @param {number} type - gl.VERTEX_SHADER or gl.FRAGMENT_SHADER.
     * @param {string} source - GLSL source code.
     * @returns {WebGLShader}
     */
    Engine.prototype._compileShader = function(type, source) {
        var gl = this.gl;
        var shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);

        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            var info = gl.getShaderInfoLog(shader);
            gl.deleteShader(shader);
            throw new Error('GamutLutEngine: Shader compile error: ' + info);
        }

        return shader;
    };

    /**
     * Load an image from a URL. Resizes the canvas to match the image.
     *
     * @param {string} url - Image URL.
     * @returns {Promise<void>}
     */
    Engine.prototype.loadImage = function(url) {
        var self = this;

        return new Promise(function(resolve, reject) {
            var img = new Image();
            img.crossOrigin = 'anonymous';

            img.onload = function() {
                self._imageWidth = img.naturalWidth;
                self._imageHeight = img.naturalHeight;
                self.canvas.width = img.naturalWidth;
                self.canvas.height = img.naturalHeight;
                self._imgElement = img;

                if (self.useWebGL2) {
                    self._uploadImageTexture(img);
                } else {
                    self._storeOriginalImageData(img);
                }

                self.imageLoaded = true;

                // If a LUT is already loaded, re-render.
                if (self.lutLoaded) {
                    self.render();
                }

                resolve();
            };

            img.onerror = function() {
                reject(new Error('GamutLutEngine: Failed to load image: ' + url));
            };

            img.src = url;
        });
    };

    /**
     * Upload image data to the WebGL 2D texture.
     *
     * @param {HTMLImageElement} img
     */
    Engine.prototype._uploadImageTexture = function(img) {
        var gl = this.gl;

        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, this._imageTexture);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);

        gl.viewport(0, 0, img.naturalWidth, img.naturalHeight);
    };

    /**
     * Store original image pixel data for Canvas 2D fallback.
     *
     * @param {HTMLImageElement} img
     */
    Engine.prototype._storeOriginalImageData = function(img) {
        this._ctx2d.drawImage(img, 0, 0);
        this._originalImageData = this._ctx2d.getImageData(0, 0, img.naturalWidth, img.naturalHeight);
    };

    /**
     * Load parsed LUT data as a 3D texture.
     *
     * @param {{ size: number, data: Float32Array }} parsed - Output from GamutCubeParser.
     */
    Engine.prototype.loadLut = function(parsed) {
        this._lutSize = parsed.size;

        if (this.useWebGL2) {
            this._uploadLutTexture(parsed);
        } else {
            this._lutData = parsed.data;
        }

        this.lutLoaded = true;
    };

    /**
     * Upload LUT data to WebGL 3D texture.
     *
     * @param {{ size: number, data: Float32Array }} parsed
     */
    Engine.prototype._uploadLutTexture = function(parsed) {
        var gl = this.gl;
        var size = parsed.size;

        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_3D, this._lutTexture);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_R, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);

        gl.texImage3D(
            gl.TEXTURE_3D, 0, gl.RGBA32F,
            size, size, size,
            0, gl.RGBA, gl.FLOAT,
            parsed.data
        );

        gl.useProgram(this._program);
        gl.uniform1f(this._uniforms.lutSize, size);
    };

    /**
     * Set the LUT intensity (blend factor).
     *
     * @param {number} value - 0.0 (original) to 1.0 (full LUT).
     */
    Engine.prototype.setIntensity = function(value) {
        this.intensity = Math.max(0, Math.min(1, value));

        if (this.useWebGL2 && this._program) {
            this.gl.useProgram(this._program);
            this.gl.uniform1f(this._uniforms.intensity, this.intensity);
        }
    };

    /**
     * Render the current image with the loaded LUT and intensity.
     */
    Engine.prototype.render = function() {
        if (!this.imageLoaded) {
            return;
        }

        if (this.useWebGL2) {
            this._renderWebGL();
        } else {
            this._renderCanvas2D();
        }
    };

    /**
     * WebGL2 render pass.
     */
    Engine.prototype._renderWebGL = function() {
        var gl = this.gl;

        gl.useProgram(this._program);
        gl.viewport(0, 0, this.canvas.width, this.canvas.height);

        // Bind textures.
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, this._imageTexture);

        if (this.lutLoaded) {
            gl.activeTexture(gl.TEXTURE1);
            gl.bindTexture(gl.TEXTURE_3D, this._lutTexture);
            gl.uniform1f(this._uniforms.intensity, this.intensity);
        } else {
            // No LUT loaded — render original by setting intensity to 0.
            gl.uniform1f(this._uniforms.intensity, 0.0);
        }

        // Draw fullscreen quad.
        gl.bindVertexArray(this._vao);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    };

    /**
     * Canvas 2D fallback render with manual trilinear interpolation.
     */
    Engine.prototype._renderCanvas2D = function() {
        if (!this._originalImageData) {
            return;
        }

        var src = this._originalImageData.data;
        var output = this._ctx2d.createImageData(this._originalImageData.width, this._originalImageData.height);
        var dst = output.data;
        var intensity = this.intensity;

        if (!this.lutLoaded || !this._lutData) {
            // No LUT loaded — draw original.
            dst.set(src);
            this._ctx2d.putImageData(output, 0, 0);
            return;
        }

        var lut = this._lutData;
        var size = this._lutSize;
        var sizeM1 = size - 1;

        for (var i = 0; i < src.length; i += 4) {
            var r = src[i] / 255.0;
            var g = src[i + 1] / 255.0;
            var b = src[i + 2] / 255.0;
            var a = src[i + 3];

            // Map to LUT coordinates.
            var lr = r * sizeM1;
            var lg = g * sizeM1;
            var lb = b * sizeM1;

            // Floor and ceil indices.
            var r0 = Math.floor(lr);
            var g0 = Math.floor(lg);
            var b0 = Math.floor(lb);
            var r1 = Math.min(r0 + 1, sizeM1);
            var g1 = Math.min(g0 + 1, sizeM1);
            var b1 = Math.min(b0 + 1, sizeM1);

            // Fractional parts.
            var fr = lr - r0;
            var fg = lg - g0;
            var fb = lb - b0;

            // Trilinear interpolation: sample 8 corners of the cube.
            var out = trilinear(lut, size, r0, r1, g0, g1, b0, b1, fr, fg, fb);

            // Mix with original based on intensity.
            dst[i]     = Math.round((r + (out[0] - r) * intensity) * 255);
            dst[i + 1] = Math.round((g + (out[1] - g) * intensity) * 255);
            dst[i + 2] = Math.round((b + (out[2] - b) * intensity) * 255);
            dst[i + 3] = a;
        }

        this._ctx2d.putImageData(output, 0, 0);
    };

    /**
     * Trilinear interpolation in the LUT data array.
     * Data ordering: R varies fastest, then G, then B (matching .cube spec).
     * RGBA format: 4 floats per entry.
     */
    function trilinear(lut, size, r0, r1, g0, g1, b0, b1, fr, fg, fb) {
        var s2 = size * size;

        // 8 corner sample indices (b * size² + g * size + r) * 4 channels.
        var i000 = (b0 * s2 + g0 * size + r0) * 4;
        var i100 = (b0 * s2 + g0 * size + r1) * 4;
        var i010 = (b0 * s2 + g1 * size + r0) * 4;
        var i110 = (b0 * s2 + g1 * size + r1) * 4;
        var i001 = (b1 * s2 + g0 * size + r0) * 4;
        var i101 = (b1 * s2 + g0 * size + r1) * 4;
        var i011 = (b1 * s2 + g1 * size + r0) * 4;
        var i111 = (b1 * s2 + g1 * size + r1) * 4;

        var result = [0, 0, 0];
        for (var c = 0; c < 3; c++) {
            var c000 = lut[i000 + c];
            var c100 = lut[i100 + c];
            var c010 = lut[i010 + c];
            var c110 = lut[i110 + c];
            var c001 = lut[i001 + c];
            var c101 = lut[i101 + c];
            var c011 = lut[i011 + c];
            var c111 = lut[i111 + c];

            // Interpolate along R.
            var c00 = c000 + (c100 - c000) * fr;
            var c10 = c010 + (c110 - c010) * fr;
            var c01 = c001 + (c101 - c001) * fr;
            var c11 = c011 + (c111 - c011) * fr;

            // Interpolate along G.
            var c0 = c00 + (c10 - c00) * fg;
            var c1 = c01 + (c11 - c01) * fg;

            // Interpolate along B.
            result[c] = c0 + (c1 - c0) * fb;
        }

        return result;
    }

    /**
     * Get a canvas with the original (ungraded) image.
     *
     * @returns {HTMLCanvasElement}
     */
    Engine.prototype.getOriginalCanvas = function() {
        var offscreen = document.createElement('canvas');
        offscreen.width = this.canvas.width;
        offscreen.height = this.canvas.height;

        if (this._imgElement) {
            var ctx = offscreen.getContext('2d');
            ctx.drawImage(this._imgElement, 0, 0, offscreen.width, offscreen.height);
        }

        return offscreen;
    };

    /**
     * Get a canvas snapshot at the current intensity setting.
     *
     * @returns {HTMLCanvasElement}
     */
    Engine.prototype.captureCanvas = function() {
        var offscreen = document.createElement('canvas');
        offscreen.width = this.canvas.width;
        offscreen.height = this.canvas.height;

        if (!this.imageLoaded) {
            return offscreen;
        }

        this.render();
        var ctx = offscreen.getContext('2d');
        ctx.drawImage(this.canvas, 0, 0);

        return offscreen;
    };

    /**
     * Check if WebGL2 is being used.
     *
     * @returns {boolean}
     */
    Engine.prototype.isWebGL2 = function() {
        return this.useWebGL2;
    };

    /**
     * Clean up GPU resources.
     */
    Engine.prototype.destroy = function() {
        if (this.gl) {
            var gl = this.gl;
            if (this._imageTexture) gl.deleteTexture(this._imageTexture);
            if (this._lutTexture) gl.deleteTexture(this._lutTexture);
            if (this._program) gl.deleteProgram(this._program);
            if (this._vao) gl.deleteVertexArray(this._vao);
        }

        this.gl = null;
        this._ctx2d = null;
        this._originalImageData = null;
        this._lutData = null;
        this._imgElement = null;
        this.imageLoaded = false;
        this.lutLoaded = false;
    };

    return Engine;
})();
