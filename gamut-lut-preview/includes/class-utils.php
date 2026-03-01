<?php
/**
 * Shared utility helpers for Gamut LUT Preview.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Utils {

    /**
     * Parse LUT_3D_SIZE from a .cube file header.
     *
     * @param string $file_path Absolute path to the .cube file.
     * @return int|false LUT grid size or false if not found.
     */
    public static function parse_lut_size( $file_path ) {
        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return false;
        }

        $lines_read = 0;
        $lut_size   = false;

        while ( ( $line = fgets( $handle ) ) !== false && $lines_read < 50 ) {
            $line = trim( $line );
            if ( preg_match( '/^LUT_3D_SIZE\s+(\d+)/i', $line, $matches ) ) {
                $lut_size = absint( $matches[1] );
                break;
            }
            $lines_read++;
        }

        fclose( $handle );
        return $lut_size;
    }
}
