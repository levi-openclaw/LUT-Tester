/**
 * Gamut .cube File Parser
 *
 * Parses the binary LUT format served by the protected REST endpoint
 * into Float32Array data for WebGL.
 *
 * @package Gamut_LUT_Preview
 */
var GamutCubeParser = (function() {
    'use strict';

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
        parseBinary: parseBinary,
        fetchAndParse: fetchAndParse
    };
})();
