<?php

/* Core Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook\Core;

function filterHtml( $s ) : string {
    return \htmlspecialchars( (string) $s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Escapes string for use inside HTML text.
 */
function filterHtmlText( $s ) : string {
    return \htmlspecialchars( (string) $s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Escapes string for use inside CSS template.
 */
function filterCssString( $s ) : string {
    // http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6
    return \addcslashes( (string) $s, "\x00..\x1F!\"#$%&'()*+,./:;<=>?@[\\]^`{|}~" );
}

/**
 * Escapes variables for use inside <script>.
 */
function filterJsString( mixed $variable ) : string {
    $json = \json_encode( $variable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
    if ( \json_last_error() ) {
        throw new \RuntimeException( \json_last_error_msg() );
    }

    return \str_replace( [ ']]>', '<!', '</' ], [ ']]\u003E', '\u003C!', '<\/' ], $json );
}

/**
 * Retrieves the project root directory.
 *
 * - This function assumes the Composer directory is present in the project root.
 * - The return is cached for this process.
 *
 * @return string
 */
function getProjectRootDirectory() : string {
    static $projectRoot;
    return $projectRoot ??= (
    static function () : string {
        // Get an array of each directory leading to this file
        $explodeCurrentDirectory = \explode( DIRECTORY_SEPARATOR, __DIR__ );
        // Slice off three levels, in this case /core/northrook/composer-dir, commonly /vendor
        $vendorDirectory = \array_slice( $explodeCurrentDirectory, 0, -3 );
        // Implode and return the $projectRoot path
        return \implode( DIRECTORY_SEPARATOR, $vendorDirectory );
    }
    )();
}

function memoize( mixed $key, callable $callback ) : mixed {
    static $cache = [];
    return $cache[ encodeKey( $key ) ] ??= $callback();
}

function escChar( string $string ) : string {

    return \implode( '', \array_map( static fn ( $char ) => '\\' . $char, \str_split( $string ) ) );
}


function timestamp(
    string | \DateTimeInterface $dateTime = 'now',
    string | \DateTimeZone      $timezone = 'UTC',
) : \DateTimeImmutable {
    try {
        return new \DateTimeImmutable( $dateTime, \timezone_open( $timezone ) ?: null );
    }
    catch ( \Exception $exception ) {
        throw new \InvalidArgumentException(
            message  : "Unable to create a new DateTimeImmutable object for $timezone.",
            code     : 500,
            previous : $exception,
        );
    }
}

/** Replace each key from `$map` with its value, when found in `$content`.
 *
 * @param array         $map  search:replace
 * @param string|array  $content
 * @param bool          $caseSensitive
 *
 * @return array|string|string[] The processed `$content`, or null if `$content` is empty
 */
function replaceEach(
    array          $map,
    string | array $content,
    bool           $caseSensitive = true,
) : string | array {

    if ( !$content ) {
        return $content;
    }

    $keys = \array_keys( $map );

    return $caseSensitive
        ? \str_replace( $keys, $map, $content )
        : \str_ireplace( $keys, $map, $content );
}

/**
 * # Ensure a number is within a range.
 *
 * @param int|float  $number
 * @param int|float  $ceil
 * @param int|float  $floor
 *
 * @return int|float
 */
function numberWithin( int | float $number, int | float $ceil, int | float $floor ) : int | float {
    return match ( true ) {
        $number >= $ceil => $ceil,
        $number < $floor => $floor,
        default          => $number
    };
}

function isUrl( mixed $url, ?string $requiredProtocol = null ) : bool {

    if ( !$url || !isScalar( $url ) ) {
        return false;
    }

    if ( \is_int( $url[ 0 ] ) ) {
        return false;
    }

    if ( !\preg_match( '/([\w\-+:\\/]*?).+\.[a-z0-9]{2,}/', $url ) ) {
        return false;
    }

    if ( $requiredProtocol && !\str_starts_with( $url, "$requiredProtocol://" ) ) {
        return false;
    }

    return true;
}

/**
 * # Determine if a value is a scalar.
 *
 * @param mixed  $value
 *
 * @return bool
 */
function isScalar( mixed $value ) : bool {
    return \is_scalar( $value ) || $value instanceof \Stringable || \is_null( $value );
}

/**
 * # Get the class name of a provided class, or the calling class.
 *
 * - Will use the `debug_backtrace()` to get the calling class if no `$class` is provided.
 *
 * ```
 * $class = new \Northrook\Core\Env();
 * classBasename( $class );
 * // => 'Env'
 * ```
 *
 *
 * @param class-string|object|null  $class
 *
 * @return string
 */
function classBasename( string | object | null $class = null ) : string {
    $class     ??= \debug_backtrace()[ 1 ] [ 'class' ];
    $class     = \is_object( $class ) ? $class::class : $class;
    $namespace = \strrpos( $class, '\\' );
    return $namespace ? \substr( $class, ++$namespace ) : $class;
}

/**
 * # Get all the classes, traits, and interfaces used by a class.
 *
 *
 * @param null|string|object  $class
 * @param bool                $includeSelf
 * @param bool                $includeInterface
 * @param bool                $includeTrait
 * @param bool                $namespace
 * @param bool                $details
 *
 * @return array
 */
function extendingClasses(
    string | object | null $class = null,
    bool                   $includeSelf = true,
    bool                   $includeInterface = true,
    bool                   $includeTrait = true,
    bool                   $namespace = true,
    bool                   $details = false,
) : array {

    $class ??= \debug_backtrace()[ 1 ] [ 'class' ];
    $class = \is_object( $class ) ? $class::class : $class;

    $classes = $includeSelf ? [ $class => 'self' ] : [];

    $parent  = \class_parents( $class );
    $classes += \array_fill_keys( $parent, 'parent' );

    if ( $includeInterface ) {
        $interfaces = \class_implements( $class );
        $classes    += \array_fill_keys( $interfaces, 'interface' );
    }

    if ( $includeTrait ) {
        $traits  = \class_uses( $class );
        $classes += \array_fill_keys( $traits, 'trait' );
    }

    if ( $details ) {
        return $classes;
    }

    $classes = \array_keys( $classes );

    return $namespace ? $classes : \array_map( 'Northrook\Core\Function\classBasename', $classes );

}

/**
 * Get a boolean option from an array of options.
 *
 * ⚠️ Be careful if passing other nullable values, as they will be converted to booleans.
 *
 * - Pass an array of options, `get_defined_vars()` is recommended.
 * - All 'nullable' values will be converted to booleans.
 * - `true` options set all others to false.
 * - `false` options set all others to true.
 * - Use the `$default` parameter to set value for all if none are set.
 *
 * @param array  $array    Array of options, `get_defined_vars()` is recommended
 * @param bool   $default  Default value for all options
 *
 * @return array<string, bool>
 */
function booleanValues( array $array, bool $default = true ) : array {

    // Isolate the options
    $array = \array_filter( $array, static fn ( $value ) => \is_bool( $value ) || \is_null( $value ) );

    // If any option is true, set all others to false
    if ( \in_array( true, $array, true ) ) {
        return \array_map( static fn ( $option ) => $option === true, $array );
    }

    // If any option is false, set all others to true
    if ( \in_array( false, $array, true ) ) {
        return \array_map( static fn ( $option ) => $option !== false, $array );
    }

    // If none are true or false, set all to the default
    return \array_map( static fn ( $option ) => $default, $array );
}

/**
 * # Generate a deterministic key from a value.
 *
 *  - `$value` will be stringified using `json_encode()`.
 *
 * @param mixed  ...$value
 *
 * @return string
 */
function encodeKey( mixed ...$value ) : string {
    return \json_encode( $value, 64 | 256 | 512 );
}

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
        $value = \serialize( $value );
    }
    // Implode if defined and $value is an array
    elseif ( $encoder === 'implode' && \is_array( $value ) ) {
        $value = \implode( ':', $value );
    }
    // JSON as default, or as fallback
    else {
        $value = \json_encode( $value ) ?: \serialize( $value );
    }

    // Hash the $value to a 16 character string
    return \hash( algo : 'xxh3', data : $value );
}

/**
 * # Normalise a `string`, assuming returning it as a `key`.
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
 * @param string[]  $string
 * @param string    $separator
 *
 * @return string
 */
function normalizeKey( string | array $string, string $separator = '-' ) : string {
    // Convert to lowercase
    $string = \strtolower( \is_string( $string ) ? $string : \implode( $separator, $string ) );

    // Replace non-alphanumeric characters with the separator
    $string = \preg_replace( '/[^a-z0-9]+/i', $separator, $string );

    // Remove leading and trailing separators
    return \trim( $string, $separator );
}

/**
 * # Normalise a `string` or `string[]`, assuming it is a `path`.
 *
 * - If an array of strings is passed, they will be joined using the directory separator.
 * - Normalises slashes to system separator.
 * - Removes repeated separators.
 * - Valid paths will be added to the realpath cache.
 * - The resulting string will be cached for this process.
 * - Will throw a {@see \LengthException} if the resulting string exceeds {@see PHP_MAXPATHLEN}.
 *
 * ```
 * normalizePath( './assets\\\/scripts///example.js' );
 * // => '.\assets\scripts\example.js'
 * ```
 *
 * @param string[]  $string         The string to normalize.
 * @param bool      $trailingSlash  Append a trailing slash.
 *
 * @return string
 */
function normalizePath(
    string | array $string,
    bool           $trailingSlash = false,
) : string {
    static $cache = [];
    return $cache[ \json_encode( [ $string, $trailingSlash ], 832 ) ] ??= (
    static function () use ( $string, $trailingSlash ) : string {

        // Normalize separators
        $normalize = \str_replace( [ '\\', '/' ], DIRECTORY_SEPARATOR, $string );

        // Explode strings for separator deduplication
        $exploded = \is_string( $normalize ) ? \explode( DIRECTORY_SEPARATOR, $normalize ) : $normalize;

        // Filter the exploded path, and implode using the directory separator
        $path = \implode( DIRECTORY_SEPARATOR, \array_filter( $exploded ) );


        // Ensure the resulting path does not exceed the system limitations
        validateCharacterLimit( $path, \PHP_MAXPATHLEN - 2, __NAMESPACE__ . '\normalizePath' );

        // Add to realpath cache if valid
        $path = \realpath( $path ) ?: $path;

        // Return with or without a $trailingSlash
        return $trailingSlash ? $path . DIRECTORY_SEPARATOR : $path;
    } )();
}


/**
 * @param string  $string  $string
 * @param bool    $trailingSlash
 *
 * @return string
 */
function normalizeUrl(
    string $string,
    bool   $trailingSlash = false,
) : string {
    static $cache = [];
    return $cache[ \json_encode( [ $string, $trailingSlash ], 832 ) ] ??= (
    static function () use ( $string, $trailingSlash ) : string {
        $protocol = '';
        $fragment = '';
        $query    = '';

        // Extract and lowercase the $protocol
        if ( \str_contains( $string, '://' ) ) {
            [ $protocol, $string ] = \explode( '://', $string, 2 );
            $protocol = \strtolower( $protocol ) . '://';
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
                [ $string, $query ] = \explode( '?', $string, 2 );
                [ $query, $fragment ] = \explode( '#', $query, 2 );
            }
            // $matchFragment is first
            else {
                [ $string, $fragment ] = \explode( '#', $string, 2 );
                [ $fragment, $query ] = \explode( '?', $fragment, 2 );
            }

            // After splitting, prepend the relevant identifiers.
            $query    = "?$query";
            $fragment = "#$fragment";
        }
        // If the $string only contains $query
        elseif ( $matchQuery ) {
            [ $string, $query ] = \explode( '?', $string, 2 );
            $query = "?$query";
        }
        // If the $string only contains $fragment
        elseif ( $matchFragment ) {
            [ $string, $fragment ] = \explode( '#', $string, 2 );
            $fragment = "#$fragment";
        }

        // Remove duplicate separators, and lowercase the $path
        $path = \strtolower( \implode( '/', \array_filter( \explode( '/', $string ) ) ) );

        // Prepend trailing separator if needed
        if ( $trailingSlash ) {
            $path .= '/';
        }

        // Assemble the URL
        return $protocol . $path . $query . $fragment;
    }
    )();
}

/**
 * Throws a {@see \LengthException} when the length of `$string` exceeds the provided `$limit`.
 *
 * @param string       $string
 * @param int          $limit
 * @param null|string  $caller  Class, method, or function name
 *
 * @return void
 */
function validateCharacterLimit(
    string  $string,
    int     $limit,
    ?string $caller = null,
) : void {
    $limit  = \PHP_MAXPATHLEN - 2;
    $length = \strlen( $string );
    if ( $length > $limit ) {
        throw new \LengthException (
            $caller
                ? $caller . " resulted in a $length character string, exceeding the $limit limit."
                : "The provided string is $length characters long, exceeding the $limit limit.",
        );
    }
}