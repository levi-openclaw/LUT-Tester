/**
 * Gamut .cube File Parser
 *
 * Parses .cube LUT text files into Float32Array data for WebGL,
 * and parses the binary format served by the protected REST endpoint.
 *
 * @package Gamut_LUT_Preview
 */
var GamutCubeParser = (function() {
    'use strict';

    /**
     * Parse a .cube text file into structured LUT data.
     *
     * @param {string} textContent - Raw .cube file text.
     * @returns {{ size: number, data: Float32Array, domainMin: number[], domainMax: number[] }}
     */
    function parse(textContent) {
        var lines = textContent.split('\n');
        var size = 0;
        var domainMin = [0.0, 0.0, 0.0];
        var domainMax = [1.0, 1.0, 1.0];
        var rgbData = [];
        var i, line, parts;

        for (i = 0; i < lines.length; i++) {
            line = lines[i].trim();

            // Skip empty lines and comments.
            if (line === '' || line.charAt(0) === '#') {
                continue;
            }

            // Parse LUT_3D_SIZE header.
            if (line.indexOf('LUT_3D_SIZE') === 0) {
                parts = line.split(/\s+/);
                if (parts.length >= 2) {
                    size = parseInt(parts[1], 10);
                }
                continue;
            }

            // Parse DOMAIN_MIN header.
            if (line.indexOf('DOMAIN_MIN') === 0) {
                parts = line.split(/\s+/);
                if (parts.length >= 4) {
                    domainMin = [parseFloat(parts[1]), parseFloat(parts[2]), parseFloat(parts[3])];
                }
                continue;
            }

            // Parse DOMAIN_MAX header.
            if (line.indexOf('DOMAIN_MAX') === 0) {
                parts = line.split(/\s+/);
                if (parts.length >= 4) {
                    domainMax = [parseFloat(parts[1]), parseFloat(parts[2]), parseFloat(parts[3])];
                }
                continue;
            }

            // Skip other header lines (TITLE, etc.).
            if (/^[A-Z_]/.test(line)) {
                continue;
            }

            // Parse data line: "R G B" floats.
            parts = line.split(/\s+/);
            if (parts.length >= 3) {
                rgbData.push(parseFloat(parts[0]));
                rgbData.push(parseFloat(parts[1]));
                rgbData.push(parseFloat(parts[2]));
            }
        }

        if (!size) {
            throw new Error('GamutCubeParser: LUT_3D_SIZE not found in .cube file');
        }

        var expected = size * size * size;
        var actual = rgbData.length / 3;
        if (actual !== expected) {
            throw new Error(
                'GamutCubeParser: Data count mismatch. Expected ' + expected +
                ' points, got ' + actual
            );
        }

        // Convert RGB triplets to RGBA Float32Array (WebGL requires 4 channels).
        var rgbaData = new Float32Array(expected * 4);
        for (i = 0; i < expected; i++) {
            rgbaData[i * 4]     = rgbData[i * 3];     // R
            rgbaData[i * 4 + 1] = rgbData[i * 3 + 1]; // G
            rgbaData[i * 4 + 2] = rgbData[i * 3 + 2]; // B
            rgbaData[i * 4 + 3] = 1.0;                 // A
        }

        return {
            size: size,
            data: rgbaData,
            domainMin: domainMin,
            domainMax: domainMax
        };
    }

    /**
     * Parse the binary format from the protected REST endpoint.
     *
     * Binary format:
     * - 4 bytes: uint32 grid size (little-endian)
     * - Remaining: Float32 RGBA values
     *
     * @param {ArrayBuffer} buffer - Binary data from the server.
     * @returns {{ size: number, data: Float32Array }}
     */
    function parseBinary(buffer) {
        var headerView = new DataView(buffer);
        var size = headerView.getUint32(0, true); // Little-endian.

        var expected = size * size * size * 4; // RGBA floats.
        var dataBuffer = buffer.slice(4);
        var data = new Float32Array(dataBuffer);

        if (data.length !== expected) {
            throw new Error(
                'GamutCubeParser: Binary data mismatch. Expected ' + expected +
                ' floats, got ' + data.length
            );
        }

        return {
            size: size,
            data: data
        };
    }

    /**
     * Fetch a .cube file from the protected REST endpoint and parse it.
     *
     * @param {string} url - Full URL to the /gamut/v1/cube/{id} endpoint.
     * @param {string} nonce - WordPress REST nonce.
     * @returns {Promise<{ size: number, data: Float32Array }>}
     */
    function fetchAndParse(url, nonce) {
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': nonce
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('GamutCubeParser: Failed to fetch cube file (HTTP ' + response.status + ')');
            }
            return response.arrayBuffer();
        })
        .then(function(buffer) {
            return parseBinary(buffer);
        });
    }

    // Public API.
    return {
        parse: parse,
        parseBinary: parseBinary,
        fetchAndParse: fetchAndParse
    };
})();
