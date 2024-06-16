<?php // globally available functions

declare( strict_types = 1 );


/**
 * # Generate a deterministic hash key from a value.
 *
 *  - `$value` will be stringified using `json_encode()` by default.
 *  - The value is hashed using `xxh3`.
 *  - The hash is not reversible.
 *
 * The $value can be stringified in one of the following ways:
 *
 * ## `json`
 * Often the fastest option when passing a large object.
 * Will fall back to `serialize` if `json_encode()` fails.
 *
 * ## `serialize`
 * Can sometimes be faster for arrays of strings.
 *
 * ## `implode`
 * Very fast for simple arrays of strings.
 * Requires the `$value` to be an `array` of `string|int|float|bool|Stringable`.
 * Nested arrays are not supported.
 *
 * ```
 * hashKey( [ 'example', new stdClass(), true ] );
 * // => a0a42b9a3a72e14c
 * ```
 *
 * @param mixed                         $value
 * @param 'json'|'serialize'|'implode'  $encoder
 *
 * @return string 16 character hash of the value
 */
function hashKey(
    mixed  $value,
    string $encoder = 'json',
) : string {

    // Use serialize if defined
    if ( $encoder === 'serialize' ) {
        $value = serialize( $value );
    }
    // Implode if defined and $value is an array
    elseif ( $encoder === 'implode' && is_array( $value ) ) {
        $value = implode( ':', $value );
    }
    // JSON as default, or as fallback
    else {
        $value = json_encode( $value ) ?: serialize( $value );
    }

    // Hash the $value to a 16 character string
    return hash( algo : 'xxh3', data : $value );
}

/**
 * Normalise a `string`, assuming returning it as a `key`.
 *
 * - Removes non-alphanumeric characters.
 * - Removes leading and trailing separators.
 * - Converts to lowercase.
 *
 * ```
 * normalizeKey( './assets/scripts/example.js' );
 * // => 'assets-scripts-example-js'
 * ```
 *
 * @param string  $string
 * @param string  $separator
 *
 * @return string
 */
function normalizeKey( string $string, string $separator = '-' ) : string {
    // Convert to lowercase
    $string = strtolower( $string );

    // Replace non-alphanumeric characters with the separator
    $string = preg_replace( '/[^a-z0-9]+/i', $separator, $string );

    // Remove leading and trailing separators
    return trim( $string, $separator );
}

/**
 * Normalise a `string`, assuming it is a `path`.
 *
 * - Removes repeated slashes.
 * - Normalises slashes to system separator.
 * - No validation is performed.
 *
 * ```
 * normalizePath( './assets\\\/scripts///example.js' );
 * // => '.\assets\scripts\example.js'
 * ```
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