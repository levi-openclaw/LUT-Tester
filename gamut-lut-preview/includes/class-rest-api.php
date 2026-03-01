<?php
/**
 * REST API endpoints for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_REST_API {

    const NAMESPACE = 'gamut/v1';
    const CACHE_TTL = HOUR_IN_SECONDS;

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );

        // Cache invalidation hooks.
        add_action( 'save_post_gamut_sample_image', array( $this, 'invalidate_images_cache' ) );
        add_action( 'save_post_gamut_lut_design', array( $this, 'invalidate_collections_cache' ) );
        add_action( 'edited_gamut_lut_collection', array( $this, 'invalidate_collections_cache' ) );
        add_action( 'edited_gamut_image_category', array( $this, 'invalidate_images_cache' ) );
        add_action( 'delete_post', array( $this, 'invalidate_all_caches' ) );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route( self::NAMESPACE, '/images', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_images' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NAMESPACE, '/collections', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_collections' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NAMESPACE, '/collection/(?P<slug>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_collection' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'slug' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_title',
                ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/cube/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cube_binary' ),
            'permission_callback' => array( $this, 'verify_cube_access' ),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );
    }

    /**
     * GET /images — All sample images with categories.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_images( $request ) {
        $cached = get_transient( 'gamut_lut_images' );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $images = array();
        $query  = new WP_Query( array(
            'post_type'      => 'gamut_sample_image',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ) );

        foreach ( $query->posts as $post ) {
            $thumb_id = get_post_thumbnail_id( $post->ID );
            if ( ! $thumb_id ) {
                continue;
            }

            $full_src  = wp_get_attachment_image_src( $thumb_id, 'full' );
            $thumb_src = wp_get_attachment_image_src( $thumb_id, 'medium_large' );

            if ( ! $full_src ) {
                continue;
            }

            $terms      = wp_get_post_terms( $post->ID, 'gamut_image_category', array( 'fields' => 'slugs' ) );
            $categories = is_wp_error( $terms ) ? array() : $terms;

            $images[] = array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'url'        => $full_src[0],
                'thumbnail'  => $thumb_src ? $thumb_src[0] : $full_src[0],
                'width'      => $full_src[1],
                'height'     => $full_src[2],
                'categories' => $categories,
            );
        }

        // Get all categories.
        $cat_terms  = get_terms( array(
            'taxonomy'   => 'gamut_image_category',
            'hide_empty' => true,
        ) );
        $categories = array();
        if ( ! is_wp_error( $cat_terms ) ) {
            foreach ( $cat_terms as $term ) {
                $categories[] = array(
                    'slug' => $term->slug,
                    'name' => $term->name,
                );
            }
        }

        $response = array(
            'images'     => $images,
            'categories' => $categories,
        );

        set_transient( 'gamut_lut_images', $response, self::CACHE_TTL );

        return rest_ensure_response( $response );
    }

    /**
     * GET /collections — All LUT collections with their LUTs.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_collections( $request ) {
        $cached = get_transient( 'gamut_lut_collections' );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $collections = $this->build_collections_data();

        $response = array( 'collections' => $collections );
        set_transient( 'gamut_lut_collections', $response, self::CACHE_TTL );

        return rest_ensure_response( $response );
    }

    /**
     * GET /collection/{slug} — Single collection.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_collection( $request ) {
        $slug      = $request->get_param( 'slug' );
        $cache_key = 'gamut_lut_collection_' . $slug;

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $term = get_term_by( 'slug', $slug, 'gamut_lut_collection' );
        if ( ! $term ) {
            return new WP_Error( 'not_found', __( 'Collection not found.', 'gamut-lut-preview' ), array( 'status' => 404 ) );
        }

        $collection = $this->build_single_collection( $term );

        set_transient( $cache_key, $collection, self::CACHE_TTL );

        return rest_ensure_response( $collection );
    }

    /**
     * GET /cube/{id} — Protected binary .cube file proxy.
     *
     * Converts the .cube text file to a binary format:
     * - 4 bytes: uint32 grid size (little-endian)
     * - Remaining: Float32Array of RGBA values (size³ × 4 floats)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_cube_binary( $request ) {
        $lut_id = $request->get_param( 'id' );

        $post = get_post( $lut_id );
        if ( ! $post || 'gamut_lut_design' !== $post->post_type || 'publish' !== $post->post_status ) {
            return new WP_Error( 'not_found', __( 'LUT not found.', 'gamut-lut-preview' ), array( 'status' => 404 ) );
        }

        $file_id = get_post_meta( $lut_id, '_gamut_cube_file_id', true );
        if ( ! $file_id ) {
            return new WP_Error( 'no_file', __( 'No .cube file attached.', 'gamut-lut-preview' ), array( 'status' => 404 ) );
        }

        $file_path = get_attached_file( $file_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_missing', __( 'Cube file not found on disk.', 'gamut-lut-preview' ), array( 'status' => 404 ) );
        }

        $content = file_get_contents( $file_path );
        if ( false === $content ) {
            return new WP_Error( 'read_error', __( 'Could not read cube file.', 'gamut-lut-preview' ), array( 'status' => 500 ) );
        }

        $parsed = $this->parse_cube_to_binary( $content );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        // Send binary response with no-cache headers.
        header( 'Content-Type: application/octet-stream' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Content-Length: ' . strlen( $parsed ) );
        header( 'X-Content-Type-Options: nosniff' );

        echo $parsed;
        exit;
    }

    /**
     * Permission callback for the /cube endpoint.
     * Requires a valid WordPress REST nonce.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function verify_cube_access( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Access denied.', 'gamut-lut-preview' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Parse a .cube text file into binary format.
     *
     * Binary format:
     * - 4 bytes: uint32 grid size (little-endian)
     * - N bytes: Float32 RGBA data (size³ × 4 floats × 4 bytes each)
     *
     * @param string $content Raw .cube file text.
     * @return string|WP_Error Binary data or error.
     */
    private function parse_cube_to_binary( $content ) {
        $lines    = explode( "\n", $content );
        $lut_size = 0;
        $data     = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // Skip empty lines and comments.
            if ( '' === $line || '#' === $line[0] ) {
                continue;
            }

            // Parse header.
            if ( preg_match( '/^LUT_3D_SIZE\s+(\d+)/i', $line, $matches ) ) {
                $lut_size = (int) $matches[1];
                continue;
            }

            // Skip other header directives.
            if ( preg_match( '/^[A-Z_]/', $line ) ) {
                continue;
            }

            // Parse data line: "R G B" floats.
            $parts = preg_split( '/\s+/', $line );
            if ( count( $parts ) >= 3 ) {
                $r = (float) $parts[0];
                $g = (float) $parts[1];
                $b = (float) $parts[2];
                $data[] = $r;
                $data[] = $g;
                $data[] = $b;
                $data[] = 1.0; // Alpha channel for WebGL RGBA.
            }
        }

        if ( ! $lut_size ) {
            return new WP_Error( 'parse_error', __( 'Could not find LUT_3D_SIZE in .cube file.', 'gamut-lut-preview' ), array( 'status' => 422 ) );
        }

        $expected = $lut_size * $lut_size * $lut_size;
        $actual   = count( $data ) / 4;
        if ( $actual !== $expected ) {
            return new WP_Error(
                'parse_error',
                sprintf(
                    __( 'LUT data count mismatch: expected %d points, got %d.', 'gamut-lut-preview' ),
                    $expected,
                    $actual
                ),
                array( 'status' => 422 )
            );
        }

        // Build binary: 4-byte uint32 header + float32 RGBA array.
        $binary = pack( 'V', $lut_size ); // Little-endian uint32.
        foreach ( $data as $val ) {
            $binary .= pack( 'f', $val ); // Little-endian float32.
        }

        return $binary;
    }

    /**
     * Build collections data array.
     *
     * @return array
     */
    private function build_collections_data() {
        $collections = array();
        $terms       = get_terms( array(
            'taxonomy'   => 'gamut_lut_collection',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $terms ) ) {
            return $collections;
        }

        foreach ( $terms as $term ) {
            $collections[] = $this->build_single_collection( $term );
        }

        return $collections;
    }

    /**
     * Build data for a single collection term.
     *
     * @param WP_Term $term The taxonomy term.
     * @return array
     */
    private function build_single_collection( $term ) {
        $product_id = get_term_meta( $term->term_id, 'gamut_product_id', true );

        $luts  = array();
        $query = new WP_Query( array(
            'post_type'      => 'gamut_lut_design',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'gamut_lut_collection',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ),
            ),
        ) );

        foreach ( $query->posts as $post ) {
            $lut_size = get_post_meta( $post->ID, '_gamut_lut_size', true );

            $luts[] = array(
                'id'       => $post->ID,
                'title'    => self::clean_lut_title( $post->post_title ),
                'lut_size' => $lut_size ? (int) $lut_size : null,
            );
        }

        return array(
            'slug'       => $term->slug,
            'name'       => $term->name,
            'product_id' => $product_id ? (int) $product_id : null,
            'lut_count'  => count( $luts ),
            'luts'       => $luts,
        );
    }

    /**
     * Clean a LUT title by removing monitoring-size suffixes (ML, ml, Ml).
     *
     * Strips trailing " ML", " ml", " Ml", etc. from titles like "Dolce 02 Ml".
     *
     * @param string $title Raw post title.
     * @return string Cleaned title.
     */
    public static function clean_lut_title( $title ) {
        return trim( preg_replace( '/\s+[Mm][Ll]$/u', '', $title ) );
    }

    /**
     * Delete the images transient cache.
     */
    public function invalidate_images_cache() {
        delete_transient( 'gamut_lut_images' );
    }

    /**
     * Delete the collections transient cache.
     */
    public function invalidate_collections_cache() {
        delete_transient( 'gamut_lut_collections' );

        // Also clear individual collection caches.
        $terms = get_terms( array(
            'taxonomy'   => 'gamut_lut_collection',
            'hide_empty' => false,
            'fields'     => 'slugs',
        ) );

        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $slug ) {
                delete_transient( 'gamut_lut_collection_' . $slug );
            }
        }
    }

    /**
     * Invalidate all caches on post deletion.
     */
    public function invalidate_all_caches() {
        $this->invalidate_images_cache();
        $this->invalidate_collections_cache();
    }
}
