<?php

namespace Support;

use Stringable;
use InvalidArgumentException;
use OverflowException;

/**
 * Ensures the appropriate string encoding.
 *
 * Replacement for the deprecated {@see \mb_convert_encoding()}, see [PHP.watch](https://php.watch/versions/8.2/mbstring-qprint-base64-uuencode-html-entities-deprecated) for details.
 *
 * Directly inspired by [aleblanc](https://github.com/aleblanc)'s comment on [this GitHub issue](https://github.com/symfony/symfony/issues/44281#issuecomment-1647665965).
 *
 * @param null|string|Stringable $string
 * @param null|non-empty-string  $encoding [UTF-8]
 *
 * @return string
 */
function str_encode( null|string|Stringable $string, ?string $encoding = CHARSET ) : string
{
    if ( ! $string = (string) $string ) {
        return EMPTY_STRING;
    }

    $encoding ??= 'UTF-8';

    $entities = \htmlentities( $string, ENT_NOQUOTES, $encoding, false );
    $decoded  = \htmlspecialchars_decode( $entities, ENT_NOQUOTES );
    $map      = [0x80, 0x10_FF_FF, 0, ~0];

    return \mb_encode_numericentity( $decoded, $map, $encoding );
}

/**
 * Compress a string by replacing consecutive whitespace characters with a single one.
 *
 * @param null|string|Stringable $string         $string
 * @param bool                   $whitespaceOnly if true, only spaces are squished, leaving tabs and new lines intact
 *
 * @return string the squished string with consecutive whitespace replaced by the defined whitespace character
 */
function str_squish( string|Stringable|null $string, bool $whitespaceOnly = false ) : string
{
    return (string) ( $whitespaceOnly
            ? \preg_replace( '# +#', WHITESPACE, \trim( (string) $string ) )
            : \preg_replace( "#\s+#", WHITESPACE, \trim( (string) $string ) ) );
}

function str_before(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool                   $last = false,
) : string {
    if ( ! $string = (string) $string ) {
        return EMPTY_STRING;
    }

    $before = $last
            ? str_last( $string, (string) $needle, true )
            : \strstr( $string, (string) $needle, true );

    return $before ?: $string;
}

function str_after(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool                   $last = false,
) : string {
    if ( ! $string = (string) $string ) {
        return EMPTY_STRING;
    }

    $before = $last
            ? \strrchr( $string, (string) $needle )
            : \strstr( $string, (string) $needle );

    return $before ?: $string;
}

/**
 * A {@see \strrchr()} implementation with full-needle support
 *
 * @param string $haystack
 * @param string $needle
 * @param bool   $before
 *
 * @return false|string
 */
function str_last(
    string $haystack,
    string $needle,
    bool   $before = false,
) : string|false {
    $pos = \strrpos( $haystack, $needle );

    if ( $pos === false ) {
        return false;
    }

    return $before
            ? \substr( $haystack, 0, $pos )
            : \substr( $haystack, $pos + \strlen( $needle ) );
}

/**
 * Align a `$string` to the output buffer size by padding the final chunk if necessary.
 *
 * @param null|string|Stringable $string
 * @param null|int<512,131072>   $size      `output_buffering` or `4096` if not set
 * @param string                 $encoding  `UTF-8` used when processing the string
 * @param non-empty-string       $character ` ` The single padding character
 * @param null|int               $length    Final `$length` by reference
 *
 * @return string
 *
 * @throws InvalidArgumentException on invalid `$character` string
 * @throws OverflowException        if the resulting string exceeds `PHP_INT_MAX`
 */
function str_buffer_align(
    null|string|Stringable $string,
    ?int                   $size = null,
    string                 $encoding = 'UTF-8',
    string                 $character = ' ',
    ?int &                   $length = null,
) : string {
    if ( ! $string = (string) $string ) {
        return '';
    }

    if ( ! $character || \mb_strlen( $character, $encoding ) !== 1 ) {
        throw new InvalidArgumentException( 'Padding character must be exactly one character long' );
    }

    $length = \mb_strlen( $string, $encoding );

    // Set the buffer
    $buffer = ( $size ?? (int) \ini_get( 'output_buffering' ) ) ?: 4_096;

    // Ensure the buffer is within reasonable bounds
    \assert(
        num_within( $buffer, 512, 131_072 ),
        'Buffer size must be between 512 and 131072 bytes. It is currently '.$buffer.' bytes.',
    );

    if ( $align = $length % $buffer ) {
        $padding = $buffer - $align;

        // Guard against overflows
        if ( $length + $padding > PHP_INT_MAX ) {
            throw new OverflowException( 'Resulting string would cause buffer overflow.' );
        }

        $string .= \str_repeat( $character, $padding );
    }

    $length = \mb_strlen( $string, $encoding );

    return $string;
}
