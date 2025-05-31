<?php

declare(strict_types=1);

namespace Support;

use Random\RandomException;

/**
 *  # Generate a deterministic hash key from a value.
 *   ```
 *   key_hash( 'xxh64', 'example', new stdClass(), true );
 *   // => a0a42b9a3a72e14c
 *   ```
 *
 * Recommended algorithms:
 *
 * - `xxh3` - `16` character
 * - `xxh32` - `8` character `fastest`
 * - `xxh64` - `16` characters `fastest`
 * - `xxh128` - `32` character `fastest`
 *
 * @link https://github.com/Kovah/php-hashes?tab=readme-ov-file#sorted-by-execution-time
 *
 * @param 'xxh128'|'xxh32'|'xxh64'|string $algo
 * @param mixed                           ...$value
 *
 * @return string
 */
function key_hash( string $algo, mixed ...$value ) : string
{
    foreach ( $value as $index => $segment ) {
        if ( \is_null( $segment ) ) {
            continue;
        }

        $value[$index] = match ( \gettype( $segment ) ) {
            'string'  => $segment,
            'boolean' => $segment ? 'true' : 'false',
            'integer' => (string) $segment,
            default   => \hash(
                algo : 'xxh32',
                data : \json_encode( $value ) ?: \serialize( $value ),
            ),
        };
    }

    return \hash( $algo, \implode( '', $value ) );
}

/**
 * Create a string key from provided values.
 *
 * The default separator is `:`, set a trailing `separator: $sep` argument to override.
 *
 * ```
 * key_hash( 'xxh64', 'example', new stdClass(), true, null );
 * // => example:stdClass#42:true:NULL
 * ```
 *
 * @param mixed ...$value
 */
function key_from( mixed ...$value ) : string
{
    $key = [];
    $sep = ':';
    if ( isset( $value['separator'] ) && ( ! $value['separator'] || \ctype_punct( $value['separator'] ) )
    ) {
        $sep = $value['separator'] ?: '';
        unset( $value['separator'] );
        \assert( \is_string( $sep ) );
    }

    foreach ( $value as $segment ) {
        $key[] = match ( \gettype( $segment ) ) {
            'NULL'    => 'NULL',
            'string'  => $segment,
            'boolean' => $segment ? 'true' : 'false',
            'integer' => (string) $segment,
            'array'   => '['.key_hash( 'xxh32', $segment ).']',
            'object'  => $segment::class.'#'.\spl_object_id( $segment ),
            default   => key_hash( 'xxh32', $value ),
        };
    }

    return \trim( \implode( $sep, $key ), " \n\r\t\v\0{$sep}" );
}

/**
 * Generate a random hashed string.
 *
 * - `xxh32` 8 characters
 * - `xxh64` 16 characters
 *
 * @param 'xxh32'|'xxh64'|false $hash    [xxh64] raw bytes returned on `false`
 * @param int<2,12>             $entropy [7]
 *
 * @return string
 */
function key_rand( false|string $hash = 'xxh64', int $entropy = 7 ) : string
{
    try {
        $key = \random_bytes( $entropy );
    }
    catch ( RandomException ) {
        $key = (string) \rand( 0, PHP_INT_MAX );
    }

    return $hash ? \hash( $hash, $key ) : $key;
}
