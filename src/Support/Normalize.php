<?php

namespace Northrook\Core\Support;

final class Normalize
{
    /**
     * Normalise a `string`, assuming it is a `path`.
     *
     * - Removes repeated slashes.
     * - Normalises slashes to system separator.
     * - Prevents backtracking.
     * - Optional trailing slash for directories.
     * - No validation is performed.
     *
     * @param string   $string         The string to normalize.
     * @param ?string  $append         Optional appended string to append.
     * @param bool     $trailingSlash  Whether to append a trailing slash to the path.
     *
     * @return string  The normalized path.
     */
    public static function path( string $string, ?string $append = null, bool $trailingSlash = true ) : string {

        if ( $append ) {
            $string .= "/$append";
        }

        $string = mb_strtolower( strtr( $string, "\\", "/" ) );

        if ( str_contains( $string, '/' ) ) {


            $path = [];

            foreach ( array_filter( explode( '/', $string ) ) as $part ) {
                if ( $part === '..' && $path && end( $path ) !== '..' ) {
                    array_pop( $path );
                }
                elseif ( $part !== '.' ) {
                    $path[] = trim( $part );
                }
            }

            $path = implode(
                separator : DIRECTORY_SEPARATOR,
                array     : $path,
            );
        }
        else {
            $path = $string;
        }

        // If the string contains a valid extension, return it as-is
        if ( isset( pathinfo( $path )[ 'extension' ] ) && !str_contains( pathinfo( $path )[ 'extension' ], '%' ) ) {
            return $path;
        }

        return $trailingSlash ? $path . DIRECTORY_SEPARATOR : $path;
    }

    /**
     * @param string   $string
     * @param ?string  $requireScheme  = ['http', 'https', 'ftp', 'ftps', 'mailto','file','data']
     * @param bool     $trailingSlash
     *
     * @return ?string
     *
     * @link https://github.com/glenscott/url-normalizer/blob/master/src/URL/Normalizer.php Good starting point
     */
    public static function url(
        string  $string,
        ?string $requireScheme = null,
        bool    $trailingSlash = true,
    ) : ?string {

        [ $url, $query ] = explode( '?', $string, 2 );

        return $trailingSlash ? rtrim( $string, '/' ) : $string;
    }

    public static function key() {}

}