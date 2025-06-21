<?php

declare(strict_types=1);

namespace Support;

use Stringable;
use InvalidArgumentException;
use OverflowException;
use LengthException;

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
function str_encode(
    null|string|Stringable $string,
    ?string                $encoding = CHARSET,
) : string {
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
 * Converts a given `$string` to an `ASCII-safe` string by transliterating
 * Unicode characters to their closest ASCII equivalent.
 *
 * If transliteration fails, an `E_USER_WARNING` is raised,
 * and the original `$string` returned.
 *
 * @param null|string|Stringable $string
 *
 * @return string
 */
function str_ascii(
    null|string|Stringable $string,
) : string {
    if ( ! $string = (string) $string ) {
        return EMPTY_STRING;
    }

    $ascii = false;

    if ( \function_exists( 'transliterator_transliterate' ) ) {
        $ascii = \transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove',
            $string,
        );
    }

    if ( $ascii === false && \function_exists( 'iconv' ) ) {
        $ascii = \iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $string );
    }

    if ( $ascii !== false ) {
        return $ascii;
    }

    $error = \error_get_last();

    if ( $error !== null ) {
        $error['ext-intl']  = \function_exists( 'transliterator_transliterate' ) ? 'Installed' : 'Not Installed';
        $error['ext-iconv'] = \function_exists( 'iconv' ) ? 'Installed' : 'Not Installed';

        foreach ( $error as $key => $value ) {
            $error[$key] = "{$key}: {$value}";
        }

        $error = "\n".\implode( ",\n", $error );
    }

    \trigger_error(
        "Error parsing string `{$string}` to ASCII-safe string.".$error,
        E_USER_WARNING,
    );

    return $string;
}

/**
 * Determine if a given `$string` contains only `$characters` in any number and order.
 *
 * @param null|string|Stringable $string     input string to check
 * @param non-empty-string       $characters set of allowed characters
 *
 * @return bool true if the string contains only the specified characters, false otherwise
 */
function str_contains_only(
    string|Stringable|null $string,
    string                 $characters,
) : bool {
    if ( ! $string = (string) $string ) {
        return false;
    }
    if ( ! $characters ) {
        throw new LengthException( __FUNCTION__.' requires at least one character to look for.' );
    }

    return \strspn( $string, $characters ) === \strlen( $string );
}

/**
 * Determine if a given `$string` contains all `$characters` in any number and order.
 *
 * - Starting at an optional offset and considering an optional length.
 *
 * @param null|string|Stringable $string     string to search within
 * @param non-empty-string       $characters set of characters to check for inclusion
 * @param int                    $offset     position in the string to start the search. Defaults to 0.
 * @param ?int                   $length     length of the substring to consider. If null, the entire string is used from the offset.
 *
 * @return bool true if all characters from the set are found in the string, false otherwise
 */
function str_includes_all(
    null|string|Stringable $string,
    string                 $characters,
    int                    $offset = 0,
    ?int                   $length = null,
) : bool {
    if ( ! $string = (string) $string ) {
        return false;
    }

    if ( ! $characters ) {
        throw new LengthException( __FUNCTION__.' requires at least one character to look for.' );
    }

    return \strlen( $characters ) === \strspn( $characters, $string, $offset, $length );
}

/**
 * Determine if a given `$string` contains at least one of many `$characters` in any number and order.
 *
 *  - Starting at an optional offset and considering an optional length.
 *
 * @param null|string|Stringable $string     string to search within
 * @param non-empty-string       $characters set of characters to check for inclusion
 * @param int                    $offset     position in the string to start the search. Defaults to 0.
 * @param ?int                   $length     length of the substring to consider. If null, the entire string is used from the offset.
 *
 * @return bool true if at least one character from the set is found in the string, false otherwise
 */
function str_includes_any(
    null|string|Stringable $string,
    string                 $characters,
    int                    $offset = 0,
    ?int                   $length = null,
) : bool {
    if ( ! $string = (string) $string ) {
        return false;
    }

    if ( ! $characters ) {
        throw new LengthException( __FUNCTION__.' requires at least one character to look for.' );
    }

    return \strpbrk( $string, $characters ) !== false;
}

/**
 * Checks if the given string excludes specific characters within an optional range.
 *
 * @param null|string|Stringable $string     the input string to evaluate
 * @param string                 $characters a list of characters to check for exclusion
 * @param int                    $offset     the starting position for the check (default is 0)
 * @param ?int                   $length     the length of the substring to check (default is null, meaning until the end of the string)
 *
 * @return bool returns true if the string excludes all specified characters, false otherwise
 */
function str_excludes(
    null|string|Stringable $string,
    string                 $characters,
    int                    $offset = 0,
    ?int                   $length = null,
) : bool {
    if ( ! $string = (string) $string ) {
        return true;
    }
    return \strlen( $string ) !== \strcspn( $string, $characters, $offset, $length );
}

/**
 * Replace each key from `$map` with its value when found in `$content`.
 *
 * @param array<string,null|string|Stringable> $map
 * @param string[]                             $content
 * @param bool                                 $caseSensitive
 *
 * @return ($content is string ? string : string[])
 */
function str_replace_each(
    array        $map,
    string|array $content,
    bool         $caseSensitive = true,
) : string|array {
    // Bail early on empty content
    if ( ! $content ) {
        return $content;
    }

    // Validate and normalize the $map
    foreach ( $map as $match => $replace ) {
        \assert( \is_string( $match ), __METHOD__.' does not accept empty match keys' );
        $map[$match] = (string) $replace;
    }

    $search  = \array_keys( $map );
    $replace = \array_values( $map );

    /**
     * @var string[] $search
     * @var string[] $replace
     * */
    return $caseSensitive
            ? \str_replace( $search, $replace, $content )
            : \str_ireplace( $search, $replace, $content );
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

/**
 * Bisect a string into two parts at a specified position or around a given substring.
 *
 * - Modifies the `$string` to contain the remainder after bisection
 * - `false` needles cause an early empty return
 * - `$includeNeedle' includes the `$needle` string in the return
 * - `$nullable` casts empty returns to `null`
 *
 * @param string           &$string
 * @param false|int|string $needle
 * @param bool             $includeNeedle
 * @param bool             $nullable
 *
 * @return null|string string before the `$needle`
 */
function str_bisect(
    string &           $string,
    string|false|int $needle,
    bool             $includeNeedle = false,
    bool             $nullable = false,
) : ?string {
    if ( \is_string( $needle ) ) {
        $needlePosition = \mb_strpos( $string, $needle );
        if ( $needlePosition === false ) {
            return $nullable ? null : '';
        }
        $needle = $includeNeedle
                ? $needlePosition + \mb_strlen( $needle )
                : $needlePosition;
    }
    if ( ! \is_int( $needle ) ) {
        return $nullable ? null : '';
    }

    $before = \mb_substr( $string, 0, $needle );
    $string = \mb_substr( $string, $needle );

    return $nullable ? ( $before === '' ? null : $before ) : $before;
}

/**
 * @param null|string|Stringable       $string
 * @param int                          $start
 * @param int                          $end
 * @param null|false|string|Stringable $replace
 * @param string                       $encoding
 *
 * @return string
 */
function str_extract(
    null|string|Stringable       $string,
    int                          $start,
    int                          $end,
    false|null|string|Stringable $replace = false,
    string                       $encoding = 'UTF-8',
) : string {
    if ( ! $string = (string) $string ) {
        return EMPTY_STRING;
    }

    $end -= $start;

    if ( $replace === false ) {
        return \mb_substr( $string, $start, $end );
    }

    $replace = (string) $replace;

    $before = \mb_substr( $string, 0, $start, $encoding );

    $length = \mb_strlen( $before, $encoding ) + $end;

    $after = \mb_substr( $string, $length, AUTO, $encoding );

    return $before.$replace.$after;
}

function str_before(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool                   $last = false,
    bool                   $includeNeedle = false,
) : string {
    $string = (string) $string;
    $needle = (string) $needle;

    if ( $string === '' || $needle === '' ) {
        return $string;
    }

    $pos = $last ? \strrpos( $string, $needle ) : \strpos( $string, $needle );

    if ( $pos === false ) {
        return $string;
    }

    return $includeNeedle
            ? \substr( $string, 0, $pos + \strlen( $needle ) )
            : \substr( $string, 0, $pos );
}

function str_after(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool                   $last = false,
    bool                   $includeNeedle = false,
) : string {
    $string = (string) $string;
    $needle = (string) $needle;

    if ( $string === '' || $needle === '' ) {
        return $string;
    }

    $pos = $last ? \strrpos( $string, $needle ) : \strpos( $string, $needle );

    if ( $pos === false ) {
        return $string;
    }

    return $includeNeedle
            ? \substr( $string, $pos )
            : \substr( $string, $pos + \strlen( $needle ) );
}

/**
 * Ensures that a string starts with a specified substring.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable $with
 *
 * @return string prepended with `$with` if not already present
 */
function str_start(
    null|string|Stringable $string,
    null|string|Stringable $with,
) : string {
    $string = (string) $string;
    $with   = (string) $with;

    if ( \str_starts_with( $string, $with ) ) {
        return $string;
    }

    return $with.$string;
}

/**
 * Ensures that a string ends with a specified substring.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable $with
 *
 * @return string appended with `$with` if not already present
 */
function str_end(
    null|string|Stringable $string,
    null|string|Stringable $with,
) : string {
    $string = (string) $string;
    $with   = (string) $with;

    if ( \str_ends_with( $string, $with ) ) {
        return $string;
    }

    return $string.$with;
}

/**
 * Checks if a multibyte string starts with a given substring.
 *
 * @param null|string|Stringable $haystack
 * @param null|string|Stringable $needle
 *
 * @return bool
 */
function mb_str_starts_with(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
) : bool {
    return \mb_stripos( (string) $haystack, (string) $needle, 0, 'UTF-8' ) === 0;
}

/**
 * Checks if a multibyte string ends with a given substring.
 *
 * @param null|string|Stringable $haystack
 * @param null|string|Stringable $needle
 *
 * @return bool
 */
function mb_str_ends_with(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
) : bool {
    $haystack = (string) $haystack;
    $needle   = (string) $needle;
    return \mb_strripos( $haystack, $needle, 0, 'UTF-8' ) === \mb_strlen( $haystack ) - \mb_strlen( $needle );
}

/**
 * Checks if a `$string` starts with any of the provided `$needle` substrings.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable ...$needle
 *
 * @return bool
 */
function str_starts_with_any(
    null|string|Stringable    $string,
    null|string|Stringable ...$needle,
) : bool {
    if ( ! $string = (string) $string ) {
        return false;
    }

    foreach ( $needle as $substring ) {
        if ( \str_starts_with( $string, (string) $substring ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Checks if a `$string` ends with any of the provided `$needle` substrings.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable ...$needle
 *
 * @return bool
 */
function str_ends_with_any(
    null|string|Stringable    $string,
    null|string|Stringable ...$needle,
) : bool {
    if ( ! $string = (string) $string ) {
        return false;
    }

    foreach ( $needle as $substring ) {
        if ( \str_ends_with( $string, (string) $substring ) ) {
            return true;
        }
    }

    return false;
}

/**
 * A {@see \strrchr()} implementation with full needle support
 *
 * @param string $haystack
 * @param string $needle
 * @param bool   $before
 *
 * @return false|string
 */
function str_last(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
    bool                   $before = false,
) : string|false {
    $haystack = (string) $haystack;
    $needle   = (string) $needle;

    if ( $haystack === '' || $needle === '' ) {
        return $haystack;
    }

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
