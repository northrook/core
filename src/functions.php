<?php

declare(strict_types=1);

namespace Support;

use ArrayAccess;

use Stringable, LengthException;

const PLACEHOLDER_ARGS   = [[]];
const PLACEHOLDER_ARG    = [];
const PLACEHOLDER_ARRAY  = [];
const PLACEHOLDER_STRING = '';
const PLACEHOLDER_NULL   = null;
const PLACEHOLDER_INT    = 0;

/**
 * Check whether the script is being executed from a command line.
 */
function isCLI() : bool
{
    return PHP_SAPI === 'cli' || \defined( 'STDIN' );
}

/**
 * Checks whether OPcache is installed and enabled for the given environment.
 */
function isOPcacheEnabled() : bool
{
    // Ensure OPcache is installed and not disabled
    if (
        ! \function_exists( 'opcache_invalidate' )
        || ! \ini_get( 'opcache.enable' )
    ) {
        return false;
    }

    // If called from CLI, check accordingly, otherwise true
    return ! isCLI() || \ini_get( 'opcache.enable_cli' );
}

/**
 * @param class-string|object|string $class
 *
 * @return string
 */
function class_string( object|string $class ) : string
{
    return \is_object( $class ) ? $class::class : $class;
}

/**
 * Returns a string of the `$class`, appended by the object_jid.
 *
 * ```
 * \Namespace\ClassName::42
 * ```
 *
 * @param object $class
 * @param bool   $normalize
 *
 * @return string `FQCN::#` or `f.q.cn.#` when normalized
 */
function get_class_id( object $class, bool $normalize = false ) : string
{
    if ( $normalize ) {
        return \strtolower( \trim( \str_replace( '\\', '.', $class::class ), '.' ).'.'.\spl_object_id( $class ) );
    }

    return $class::class.'::'.\spl_object_id( $class );
}

/**
 * Determines if a given set of characters is fully included in a string.
 *
 * Checks whether all characters from the specified character set exist in the string,
 * starting at an optional offset and considering an optional length.
 *
 * @param null|string|Stringable $string     the string to search within
 * @param string                 $characters the set of characters to check for inclusion
 * @param int                    $offset     The position in the string to start the search. Defaults to 0.
 * @param ?int                   $length     The length of the substring to consider. If null, the entire string is used from the offset.
 *
 * @return bool returns true if all characters from the set are found in the string, false otherwise
 */
function str_includes(
    null|string|Stringable $string,
    string                 $characters,
    int                    $offset = 0,
    ?int                   $length = null,
) : bool {
    if ( ! $string = (string) $string ) {
        return false;
    }
    return \strlen( $characters ) === \strspn( $characters, $string, $offset, $length );
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
 * False if passed value is considered `null` and `empty` type values, retains `0` and `false`.
 *
 * @phpstan-assert-if-true scalar $value
 *
 * @param mixed $value
 *
 * @return bool
 */
function isEmpty( mixed $value ) : bool
{
    // If it is a boolean, it cannot be empty
    if ( \is_bool( $value ) ) {
        return false;
    }

    if ( \is_numeric( $value ) ) {
        return false;
    }

    return empty( $value );
}

/**
 * # Determine if a value is a scalar.
 *
 * @phpstan-assert-if-true scalar|\Stringable|null $value
 *
 * @param mixed $value
 *
 * @return bool
 */
function isScalar( mixed $value ) : bool
{
    return \is_scalar( $value ) || $value instanceof Stringable || \is_null( $value );
}

/**
 * `is_iterable` implementation that also checks for {@see ArrayAccess}.
 *
 * @phpstan-assert-if-true iterable|\Traversable $value
 *
 * @param mixed $value
 *
 * @return bool
 */
function isIterable( mixed $value ) : bool
{
    return \is_iterable( $value ) || $value instanceof ArrayAccess;
}

/**
 * Normalize all slashes in a string to `/`.
 *
 * @param string|Stringable $path
 *
 * @return string
 */
function normalizeSlashes( string|Stringable $path ) : string
{
    return \str_replace( '\\', '/', (string) $path );
}

/**
 * Normalize repeated whitespace, newlines and indentation, to a single white space.
 *
 * @param null|string|Stringable $string
 *
 * @return string
 */
function normalizeWhitespace( string|Stringable|null $string ) : string
{
    return (string) \preg_replace( '#\s+#', ' ', \trim( (string) $string ) );
}

/**
 * # Normalise a `string` or `string[]`, assuming it is a `path`.
 *
 * - If an array of strings is passed, they will be joined using the directory separator.
 * - Normalises slashes to system separator.
 * - Removes repeated separators.
 * - Will throw a {@see ValueError} if the resulting string exceeds {@see PHP_MAXPATHLEN}.
 *
 * ```
 * normalizePath( './assets\\\/scripts///example.js' );
 * // => '.\assets\scripts\example.js'
 * ```
 *
 * @param string ...$path
 */
function normalizePath( string ...$path ) : string
{
    // Normalize separators
    $normalized = \str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, $path );

    $isRelative = $normalized[0][0] === DIRECTORY_SEPARATOR;

    // Implode->Explode for separator deduplication
    $exploded = \explode( DIRECTORY_SEPARATOR, \implode( DIRECTORY_SEPARATOR, $normalized ) );

    // Ensure each part does not start or end with illegal characters
    $exploded = \array_map( static fn( $item ) => \trim( $item, " \n\r\t\v\0\\/" ), $exploded );

    // Filter the exploded path, and implode using the directory separator
    $path = \implode( DIRECTORY_SEPARATOR, \array_filter( $exploded ) );

    if ( ( $length = \mb_strlen( $path ) ) > ( $limit = PHP_MAXPATHLEN - 2 ) ) {
        $method  = __METHOD__;
        $length  = (string) $length;
        $limit   = (string) $limit;
        $message = "{$method} resulted in a string of {$length}, exceeding the {$limit} character limit.";
        $result  = 'Operation was halted to prevent overflow.';
        throw new LengthException( $message.PHP_EOL.$result );
    }

    // Preserve intended relative paths
    if ( $isRelative ) {
        $path = DIRECTORY_SEPARATOR.$path;
    }

    return $path;
}

/**
 * Checks if a given value has a `path` structure.
 *
 * ⚠️ Does **NOT** validate the `path` in any capacity!
 *
 * @param string|Stringable $string
 * @param string            $contains [..] optional `str_contains` check
 * @param string            $illegal
 *
 * @return bool
 */
function isPath( string|Stringable $string, string $contains = '..', string $illegal = '{}' ) : bool
{
    // Stringify scalars and Stringable objects
    // Stringify
    $string = \trim( (string) $string );

    // Must be at least two characters long to be a path string
    if ( ! $string || \strlen( $string ) < 2 ) {
        return false;
    }

    if ( str_excludes( $string, '{}' ) ) {
        return false;
    }

    // One or more slashes indicate this could be a path string
    if ( \str_contains( $string, '/' ) || \str_contains( $string, '\\' ) ) {
        return true;
    }

    // Any periods that aren't in the first 3 characters indicate this could be a `path/file.ext`
    if ( \strrpos( $string, '.' ) > 2 ) {
        return true;
    }

    // Indicates this could be a `.hidden` path
    if ( $string[0] === '.' && \ctype_alpha( $string[1] ) ) {
        return true;
    }

    return \str_contains( $string, $contains );
}

/**
 * Checks if a given value has a `URL` structure.
 *
 * ⚠️ Does **NOT** validate the URL in any capacity!
 *
 * @param string|Stringable $string
 * @param ?string           $requiredProtocol
 *
 * @return bool
 */
function isUrl( string|Stringable $string, ?string $requiredProtocol = null ) : bool
{
    // Stringify
    $string = \trim( (string) $string );

    // Can not be an empty string
    if ( ! $string ) {
        return false;
    }

    // Must not start with a number
    if ( \is_numeric( $string[0] ) ) {
        return false;
    }

    /**
     * Does the string resemble a URL-like structure?
     *
     * Ensures the string starts with a schema-like substring, and has a real-ish domain extension.
     *
     * - Will gladly accept bogus strings like `not-a-schema://d0m@!n.tld/`
     */
    if ( ! \preg_match( '#^([\w\-+]*?[:/]{2}).+\.[a-z0-9]{2,}#m', $string ) ) {
        return false;
    }

    // Check for required protocol if requested
    return ! ( $requiredProtocol && ! \str_starts_with( $string, \rtrim( $requiredProtocol, ':/' ).'://' ) );
}

/**
 * Check if the provided `$path` starts with a `/`.
 *
 * @param string|Stringable $path
 *
 * @return bool
 */
function isRelativePath( string|Stringable $path ) : bool
{
    return \str_starts_with( \str_replace( '\\', '/', (string) $path ), '/' );
}
