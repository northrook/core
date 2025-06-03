<?php

declare(strict_types=1);

namespace Support;

use BackedEnum;
use Core\Interface\Printable;
use Stringable;
use UnitEnum;

/**
 * This function tries very hard to return a string from any given `$value`.
 *
 * @param mixed $value
 * @param bool  $nullable
 * @param bool  $serialize
 *
 * @return ($nullable is true ? null|string : string)
 */
function as_string(
    mixed $value,
    bool  $nullable = false,
    bool  $serialize = true,
) : ?string {
    $value = match ( true ) {
        \is_bool( $value )           => $value ? 'true' : 'false',
        \is_null( $value )           => $nullable ? null : EMPTY_STRING,
        $value instanceof BackedEnum => $value->value,
        $value instanceof UnitEnum   => $value->name,
        $value instanceof Printable  => $value->toString(),
        \is_scalar( $value ),
        $value instanceof Stringable => (string) $value,
        default                      => $value,
    };

    if ( \is_iterable( $value ) ) {
        $value = \iterator_to_array( $value );
    }

    if ( \is_array( $value ) ) {
        $value = \json_encode( $value, ENCODE_ESCAPE_JSON );
    }

    if ( \is_object( $value ) && $serialize ) {
        $value = \serialize( $value );
    }

    \assert( \is_string( $value ) || ( $nullable && \is_null( $value ) ) );

    return $value;
}

/**
 * @param mixed $value
 * @param bool  $is_list
 *
 * @return ($is_list is true ? array<int, mixed> : array<array-key, mixed>)
 */
function as_array( mixed $value, bool $is_list = false ) : array
{
    $value = match ( true ) {
        \is_array( $value )    => $value,
        \is_iterable( $value ) => \iterator_to_array( $value ),
        default                => [$value],
    };

    if ( $is_list ) {
        \assert( \array_is_list( $value ) );
    }
    return $value;
}

/**
 * @param array<array-key, mixed> $get_defined_vars
 *
 * @return array<array-key, mixed>
 */
function variadic_argument( array $get_defined_vars ) : array
{
    // @phpstan-ignore-next-line
    return [...\array_pop( $get_defined_vars ), ...$get_defined_vars];
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
 * @param array<string, ?bool> $array   Array of options, `get_defined_vars()` is recommended
 * @param bool                 $default Default value for all options
 *
 * @return array<string, bool>
 */
function booleanValues( array $array, bool $default = true ) : array
{
    // Isolate the options
    $array = \array_filter( $array, static fn( $value ) => \is_bool( $value ) );

    // If any option is true, set all others to false
    if ( \in_array( true, $array, true ) ) {
        return \array_map( static fn( $option ) => $option === true, $array );
    }

    // If any option is false, set all others to true
    if ( \in_array( false, $array, true ) ) {
        return \array_map(
            static fn( ?bool $option ) => $option !== false,
            $array,
        );
    }

    // If none are true or false, set all to the default
    return \array_map( static fn( $option ) => $default, $array );
}

// <editor-fold desc="Checks">

/**
 * False if the passed value is considered `null` and `empty` type values, retains `0` and `false`.
 *
 * @phpstan-assert-if-true scalar $value
 *
 * @param mixed $value
 *
 * @return bool
 */
function is_empty( mixed $value ) : bool
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
 * Determine if a `$value` be cast as `(string)`.
 *
 * @phpstan-assert-if-true scalar|\Stringable|null $value
 *
 * @param mixed $value
 *
 * @return bool
 */
function is_stringable( mixed $value ) : bool
{
    return \is_scalar( $value ) || $value instanceof Stringable || \is_null( $value );
}

/**
 * Checks if a given value has a `path` structure.
 *
 * ⚠️ Does **NOT** validate the `path` in any capacity!
 *
 * @param mixed  $value
 * @param string $contains [..] optional `str_contains` check
 * @param string $illegal
 *
 * @return bool
 */
function is_path( mixed $value, string $contains = '..', string $illegal = '{}' ) : bool
{
    // Bail early on non-stringable values
    if ( ! ( \is_string( $value ) || $value instanceof Stringable ) ) {
        return false;
    }

    // Stringify
    $string = \trim( (string) $value );

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
 * @param mixed   $value
 * @param ?string $requiredProtocol
 *
 * @return bool
 */
function is_url( mixed $value, ?string $requiredProtocol = null ) : bool
{
    // Bail early on non-stringable values
    if ( ! ( \is_string( $value ) || $value instanceof Stringable ) ) {
        return false;
    }

    // Cannot be null or an empty string
    if ( ! $string = (string) $value ) {
        return false;
    }

    // Must not start with a number
    if ( \is_numeric( $string[0] ) ) {
        return false;
    }

    /**
     * Does the string resemble a URL-like structure?
     *
     * Ensures the string starts with a schema-like substring and has a real-ish domain extension.
     *
     * - Will gladly accept bogus strings like `not-a-schema://d0m@!n.tld/`
     */
    if ( ! \preg_match( '#^([\w\-+]*?://)(\S.+)\.[a-z0-9]{2,}#m', $string ) ) {
        return false;
    }

    // Check for the required protocol if requested
    return ! ( $requiredProtocol && ! \str_starts_with( $string, \rtrim( $requiredProtocol, ':/' ).'://' ) );
}

/**
 * Check if the provided `$path` starts with a `/`.
 *
 * @param string|Stringable $path
 *
 * @return bool
 */
function is_relative_path( string|Stringable $path ) : bool
{
    return \str_starts_with( \strtr( (string) $path, '\\', '/' ), '/' );
}

function is_delimiter( string $string ) : bool
{
    return (bool) \preg_match( '#^[,;]+$#', $string );
}

function is_punctuation( string $string, bool $endingOnly = false ) : bool
{
    return (bool) ( $endingOnly
            ? \preg_match( '#^[.!]+$#', $string )
            : \preg_match( '#^[[:punct:]]+$#', $string ) );
}

/**
 * @param mixed  $value
 * @param string ...$enforceDomain
 *
 * @return bool
 */
function is_email( mixed $value, string ...$enforceDomain ) : bool
{
    // Bail early on non-stringable values
    if ( ! ( \is_string( $value ) || $value instanceof Stringable ) ) {
        return false;
    }

    // Cannot be null or an empty string
    if ( ! $string = (string) $value ) {
        return false;
    }

    // Emails are case-insensitive, lowercase the $value for processing
    $string = \strtolower( $string );

    // Must contain an [at] and at least one period
    if ( ! \str_contains( $string, '@' ) || ! \str_contains( $string, '.' ) ) {
        return false;
    }

    // Must end with a letter
    if ( ! \preg_match( '/[a-z]/', $string[-1] ) ) {
        return false;
    }

    // Must only contain valid characters
    if ( \preg_match( '/[^'.URL_SAFE_CHARACTERS_UNICODE.']/u', $string ) ) {
        return false;
    }

    // Validate domains, if specified
    foreach ( $enforceDomain as $domain ) {
        if ( \str_ends_with( $string, \strtolower( $domain ) ) ) {
            return true;
        }
    }

    return true;
}

// </editor-fold>
