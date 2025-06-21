<?php

declare(strict_types=1);

namespace Support;

use Core\Exception\ErrorException;
use LengthException;
use Stringable;
use InvalidArgumentException;
use LogicException;

/**
 * @param null|string|Stringable $string
 * @param string                 $separator
 * @param ?callable-string       $filter    {@see \strtolower} by default
 * @param string                 $language  [en]
 *
 * @return null|non-empty-string
 */
function slug(
    null|string|Stringable $string,
    string                 $separator = '-',
    ?string                $filter = 'strtolower',
    string                 $language = 'en',
) : ?string {
    if ( ! $string = \trim( (string) $string ) ) {
        return null;
    }

    static $cache = [];

    if ( \array_key_exists( $string, $cache ) ) {
        return $cache[$string];
    }

    $parse  = \strtolower( str_ascii( $string ) );
    $length = \strlen( $parse );

    $slug      = '';
    $separated = true;

    for ( $i = 0; $i < $length; $i++ ) {
        $c = $parse[$i];
        // If the $character is [a-z0-9], add
        if ( $c >= 'a' && $c <= 'z' || $c >= '0' && $c <= '9' ) {
            $slug .= $c;
            $separated = false;
        }
        // Add separator as needed
        elseif ( ! $separated ) {
            $slug .= $separator;
            $separated = true;
        }
    }

    $slug = \rtrim( $slug, $separator );

    $slug = \is_callable( $filter ) ? (string) $filter( $slug ) : $slug;

    return $cache[$string] = $slug ?: null;
}

/**
 * Ensures the appropriate string encoding.
 *
 * ⚠️ This function can be expensive.
 *
 * 💡 Cache the result when possible.
 *
 * @param null|string|Stringable $string
 * @param false|int<2,4>         $tabSize  [4]
 * @param null|non-empty-string  $encoding [CHARSET]
 *
 * @return string
 */
function normalize_string(
    string|Stringable|null $string,
    false|int              $tabSize = 4,
    ?string                $encoding = CHARSET,
) : string {
    // Ensure string encoding
    $string = str_encode( $string, $encoding );

    // Convert leading spaces to tabs
    if ( $tabSize ) {
        $string = (string) \preg_replace_callback(
            '#^ *#m',
            static function( $matches ) use ( $tabSize ) {
                // Group each $tabSize
                $tabs = \intdiv( \strlen( $matches[0] ), $tabSize );

                // Replace $tabs with "\t", excess spaces discarded
                // Otherwise leading whitespace is trimmed
                return ( $tabs > 0 ) ? \str_repeat( TAB, $tabs ) : '';
            },
            $string,
        );
    }

    // Trim repeated whitespace, normalize line breaks
    return (string) \preg_replace( ['# +#', '#\r\n#', '#\r#'], [' ', NEWLINE], \trim( $string ) );
}

/**
 * Normalize repeated whitespace, newlines and indentation, to a single white space.
 *
 * @param null|string|Stringable $string
 *
 * @return string
 */
function normalize_whitespace( string|Stringable|null $string ) : string
{
    return (string) \preg_replace( '#\s+#', ' ', \trim( (string) $string ) );
}

/**
 * @param null|string|Stringable $string
 *
 * @return string
 */
function normalize_newline( string|Stringable|null $string ) : string
{
    return \str_replace( ["\r\n", "\r", "\n"], NEWLINE, (string) $string );
}

/**
 * Normalize all slashes in a string to `/`.
 *
 * @param string|Stringable $string
 *
 * @return string
 */
function normalize_slashes( string|Stringable $string ) : string
{
    return \strtr( (string) $string, '\\', '/' );
}

/**
 * # Normalise a `string` or `string[]`, assuming it is a `path`.
 *
 * - If an array of strings is passed, they will be joined using the directory separator.
 * - Normalises slashes to system separator.
 * - Removes repeated separators.
 * - Will throw a {@see \ValueError} if the resulting string exceeds {@see \PHP_MAXPATHLEN}.
 *
 * ```
 * normalizePath( './assets\\\/scripts///example.js' );
 * // => './assets/scripts/example.js'
 * ```
 *
 * @param null|array<array-key,null|string|Stringable>|string|Stringable $path
 * @param bool                                                           $traversal
 * @param bool                                                           $throwOnFault
 * @param non-empty-string                                               $separator
 *
 * @return string
 */
function normalize_path(
    null|string|Stringable|array $path,
    bool                         $traversal = false,
    bool                         $throwOnFault = false,
    string                       $separator = DIR_SEP,
) : string {
    // Return early on an empty $path
    if ( ! $path ) {
        return $throwOnFault
                ? throw new InvalidArgumentException(
                    message  : 'The provided path is empty: '.\var_export( $path, true ),
                    previous : ErrorException::getLast(),
                )
                : EMPTY_STRING;
    }

    // Resolve provided $path
    $path = \is_array( $path ) ? \implode( $separator, \array_filter( $path ) ) : (string) $path;

    // Normalize separators
    $path = \strtr( $path, '\\', $separator );

    // Check for starting separator
    $relative = match ( true ) {
        $path[0] === $separator                     => $separator,
        $path[0] === '.' && $path[1] === $separator => '.'.$separator,
        default                                     => null,
    };

    if ( $traversal && $relative ) {
        $traversal = $throwOnFault && throw new LogicException(
            'Cannot traverse relative path: '.\var_export( $path, true ),
        );
    }

    $fragments = [];

    if ( empty( $path ) ) {
        return EMPTY_STRING;
    }

    \assert(
        ! empty( $separator ),
        __METHOD__.'( $separator ) must be a non-empty string',
    );

    // Deduplicate separators and handle traversal
    foreach ( \explode( $separator, $path ) as $fragment ) {
        // Ensure each part does not start or end with illegal characters
        $fragment = \trim( $fragment, " \n\r\t\v\0\\/" );

        if ( ! $fragment ) {
            continue;
        }

        if ( $traversal // if we are allowed to traverse
             && $fragment === '..' // and this fragment traverses
             && $fragments // and we have at least one parent
             && \end( $fragments ) !== '..' // and the parent isn't traversing
        ) {
            \array_pop( $fragments );
        }
        elseif ( $fragment !== '.' ) {
            $fragments[] = $fragment;
        }
    }

    // Implode, preserving intended relative paths
    $path = $relative.\implode( $separator, $fragments );

    if ( ( $length = \mb_strlen( $path ) ) > ( $limit = PHP_MAXPATHLEN ) ) {
        $method  = __METHOD__;
        $length  = (string) $length;
        $limit   = (string) $limit;
        $message = "{$method} resulted in a string of {$length}, exceeding the {$limit} character limit.";
        $result  = 'Operation was halted to prevent overflow.';
        throw new LengthException( $message.PHP_EOL.$result );
    }

    if ( ! $path ) {
        return $throwOnFault
                ? throw new InvalidArgumentException(
                    'The provided path is empty: '.\var_export( $path, true ),
                )
                : EMPTY_STRING;
    }

    return $path;
}

/**
 * @param array<int, ?string>|string $path                 the string to normalize
 * @param false|string               $substituteWhitespace [-]
 * @param bool                       $trailingSlash
 *
 * @return string
 */
function normalize_url(
    null|string|Stringable|array $path,
    false|string                 $substituteWhitespace = '-',
    bool                         $trailingSlash = false,
) : string {
    // Return early on an empty $path
    if ( ! $path ) {
        return EMPTY_STRING;
    }

    $string = \is_array( $path ) ? \implode( '/', $path ) : (string) $path;

    // Normalize slashes
    $string = \str_replace( '\\', '/', $string );

    // Handle whitespace
    if ( $substituteWhitespace !== false ) {
        $string = (string) \preg_replace( '#\s+#', $substituteWhitespace, $string );
    }

    $protocol = '/';
    $fragment = '';
    $query    = '';

    // Extract and lowercase the $protocol
    if ( \str_contains( $string, '://' ) ) {
        [$protocol, $string] = \explode( '://', $string, 2 );
        $protocol            = \strtolower( $protocol ).'://';
    }

    // Check if the $string contains $query and $fragment
    $matchQuery    = \strpos( $string, '?' );
    $matchFragment = \strpos( $string, '#' );

    // If the $string contains both
    if ( $matchQuery && $matchFragment ) {
        // To parse both regardless of order, we check which one appears first in the $string.
        // Split the $string by the first $match, which will then contain the other.

        // $matchQuery is first
        if ( $matchQuery < $matchFragment ) {
            [$string, $query]   = \explode( '?', $string, 2 );
            [$query, $fragment] = \explode( '#', $query, 2 );
        }
        // $matchFragment is first
        else {
            [$string, $fragment] = \explode( '#', $string, 2 );
            [$fragment, $query]  = \explode( '?', $fragment, 2 );
        }

        // After splitting, prepend the relevant identifiers.
        $query    = "?{$query}";
        $fragment = "#{$fragment}";
    }
    // If the $string only contains $query
    elseif ( $matchQuery ) {
        [$string, $query] = \explode( '?', $string, 2 );
        $query            = "?{$query}";
    }
    // If the $string only contains $fragment
    elseif ( $matchFragment ) {
        [$string, $fragment] = \explode( '#', $string, 2 );
        $fragment            = "#{$fragment}";
    }

    // Remove duplicate separators and lowercase the $path
    $path = \strtolower( \implode( '/', \array_filter( \explode( '/', $string ) ) ) );

    // Prepend trailing separator if needed
    if ( $trailingSlash ) {
        $path .= '/';
    }

    // Assemble the URL
    return $protocol.$path.$query.$fragment;
}
