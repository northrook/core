<?php

/**
 * Normalise a `string`, assuming it is a `path`.
 *
 * - Removes repeated slashes.
 * - Normalises slashes to system separator.
 * - No validation is performed.
 *
 * @param string  $string         The string to normalize.
 * @param bool    $trailingSlash  Append a trailing slash.
 *
 * @return string
 */
function normalizeRealPath(
    string $string,
    bool   $trailingSlash = false,
) : string {
    $normalize = str_replace( [ '\\', '/' ], DIRECTORY_SEPARATOR, $string );
    $exploded  = explode( DIRECTORY_SEPARATOR, $normalize );
    $path      = implode( DIRECTORY_SEPARATOR, array_filter( $exploded ) );

    $path = ( realpath( $path ) ?: $path );
    return $trailingSlash ? $path . DIRECTORY_SEPARATOR : $path;
}