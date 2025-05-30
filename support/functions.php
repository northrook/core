<?php

declare(strict_types=1);

namespace Support;

use Core\Exception\MissingPropertyException;
use JetBrains\PhpStorm\Deprecated;
use Stringable;
use InvalidArgumentException, BadMethodCallException, BadFunctionCallException;
use Random\RandomException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

// <editor-fold desc="Constants">

const URL_SAFE_CHARACTERS_UNICODE = "\w.,_~:;@!$&*?#=%()+\-\[\]\'\/";
const URL_SAFE_CHARACTERS         = "A-Za-z0-9.,_~:;@!$&*?#=%()+\-\[\]\'\/";

const ENCODE_ESCAPE_JSON            = JSON_UNESCAPED_UNICODE       | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
const ENCODE_PARTIAL_UNESCAPED_JSON = JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE;

const FILTER_STRING_COMMENTS = [
    '{* '   => '<!-- ', // Latte
    ' *}'   => ' -->',
    '{# '   => '<!-- ', // Twig
    ' #}'   => ' -->',
    '{{-- ' => '<!-- ', // Blade
    ' --}}' => ' -->',
];

// </editor-fold>

// <editor-fold desc="Get">

/**
 * Retrieves the project root directory.
 *
 * - This function assumes the Composer directory is present in the project root.
 *
 * @return string
 */
function getProjectDirectory() : string
{
    static $projectDirectory = null;

    return $projectDirectory ??= ( static function() : string {
        // Split the current directory into an array of directory segments
        $segments = \explode( DIRECTORY_SEPARATOR, __DIR__ );

        // Ensure the directory array has at least 5 segments and a valid vendor value
        if ( ( \count( $segments ) >= 5 && $segments[\count( $segments ) - 4] === 'vendor' ) ) {
            // Remove the last 4 segments (vendor, package name, and Composer structure)
            $rootSegments = \array_slice( $segments, 0, -4 );
        }
        else {
            $message = __FUNCTION__.' was unable to determine a valid root. Current path: '.__DIR__;
            throw new BadFunctionCallException( $message );
        }

        // Normalize and return the project path
        return normalize_path( $rootSegments );
    } )();
}

/**
 * Retrieves the system temp directory for this project.
 *
 * - The directory is named using a hash based on the getProjectDirectory.
 *
 * @return string
 */
function getSystemCacheDirectory() : string
{
    static $cacheDirectory = null;
    return $cacheDirectory ??= ( static function() : string {
        return normalize_path( [\sys_get_temp_dir(), \hash( 'xxh32', getProjectDirectory() )] );
    } )();
}

// </editor-fold>

// <editor-fold desc="Class Functions">

/**
 * @wip
 *
 * @param class-string|object $class
 * @param string              $property
 * @param ?string             $type
 * @param null|mixed          $from
 *
 * @return bool
 */
function match_property_type(
    object|string $class,
    string        $property,
    ?string       $type = AUTO,
    mixed         $from = null,
) : bool {
    $type ??= \gettype( $from );

    \assert( \class_exists( \is_object( $class ) ? $class::class : $class ) );

    if ( ! \property_exists( $class, $property ) ) {
        throw new MissingPropertyException( $property, $type, $class );
    }

    $classProperty = new ReflectionProperty( $class, $property );
    $propertyType  = $classProperty->getType();

    $allowedTypes = [];

    if ( $propertyType?->allowsNull() ) {
        $allowedTypes['null'] = 'NULL';
    }

    if ( $propertyType instanceof ReflectionNamedType ) {
        $typeName = $propertyType->getName();

        $allowedTypes[$typeName] ??= $typeName;
    }
    elseif ( $propertyType instanceof ReflectionUnionType ) {
        foreach ( $propertyType->getTypes() as $unionType ) {
            if ( $unionType instanceof ReflectionNamedType ) {
                $allowedTypes[$unionType->getName()] ??= $unionType->getName();
            }
            else {
                dump( $unionType );
            }
        }
    }

    foreach ( $allowedTypes as $match ) {
        if ( $type === match ( $match ) {
            'int'   => 'integer',
            'null'  => 'NULL',
            default => $match,
        } ) {
            return true;
        }
    }

    return false;
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
 * # Get the name of a provided class.
 *```
 * $class = new \Northrook\Core\Env();
 * classBasename( $class ) => 'Env'
 * ```
 *
 * @param class-string|object|string                               $class
 * @param null|'strtolower'|'strtoupper'|'ucfirst'|callable-string $callable
 *
 * @return string
 */
function class_basename( string|object $class, ?string $callable = null ) : string
{
    $namespaced = \explode( '\\', \is_object( $class ) ? $class::class : $class );
    $basename   = \end( $namespaced );

    if ( $callable && \is_callable( $callable ) ) {
        return $callable( $basename );
    }

    return $basename;
}

/**
 * Returns the name of an object or callable.
 *
 * @param callable|callable-string|class-string|string $from
 * @param bool                                         $validate [optional] ensure the `class_exists`
 *
 * @return ($validate is true ? class-string : ?string)
 */
function class_name( mixed $from, bool $validate = false ) : ?string
{
    // array callables [new SomeClass, 'method']
    if ( \is_array( $from ) && isset( $from[0] ) && \is_object( $from[0] ) ) {
        $from = $from[0]::class;
    }

    // Handle direct objects
    if ( \is_object( $from ) ) {
        $from = $from::class;
    }

    // The [callable] type should have been handled by the two previous checks
    if ( ! \is_string( $from ) ) {
        if ( $validate ) {
            $message = __METHOD__.' was passed an unresolvable class of type '.\gettype( $from ).'.';
            throw new InvalidArgumentException( $message );
        }
        return null;
    }

    // Handle class strings
    $class = \str_contains( $from, '::' ) ? \explode( '::', $from, 2 )[0] : $from;

    // Check existence if $validate is true
    if ( $validate && ! \class_exists( $class ) ) {
        throw new InvalidArgumentException( message : 'Class '.$class.' does not exists.' );
    }

    return $class;
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
function class_id( object $class, bool $normalize = false ) : string
{
    if ( $normalize ) {
        return \strtolower( \trim( \str_replace( '\\', '.', $class::class ), '.' ).'.'.\spl_object_id( $class ) );
    }

    return $class::class.'::'.\spl_object_id( $class );
}

/**
 * Returns the name of an object or callable.
 *
 * @param mixed $callable
 * @param bool  $validate [optional] ensure the `class_exists`
 *
 * @return ($validate is true ? array{0: class-string, 1: string} : array{0: string, 1: string})
 */
function explode_class_callable( mixed $callable, bool $validate = false ) : array
{
    if ( \is_array( $callable ) && \count( $callable ) === 2 ) {
        $class  = $callable[0];
        $method = $callable[1];
    }
    elseif ( \is_string( $callable ) && \str_contains( $callable, '::' ) ) {
        [$class, $method] = \explode( '::', $callable );
    }
    else {
        throw new InvalidArgumentException( 'The provided callable must be a string or an array.' );
    }

    \assert( \is_string( $class ) && \is_string( $method ) );

    // Check existence if $validate is true
    if ( $validate && ! \class_exists( $class ) ) {
        throw new InvalidArgumentException( message : 'Class '.$class.' does not exists.' );
    }

    return [
        $class,
        $method,
    ];
}

/**
 * @param object|string $class
 * @param bool          $autoload
 *
 * @return array<class-string, class-string>
 */
function class_composition( object|string $class, bool $autoload = true ) : array
{
    $composition = \class_parents( $class, $autoload ) ?: [];

    foreach ( $composition as $parent ) {
        $composition += \class_uses( $parent, $autoload );
    }

    $composition += \class_implements( $class, $autoload ) ?: [];
    $composition += \class_uses( $class, $autoload ) ?: [];

    return $composition;
}

/**
 * @param object|string $class
 * @param object|string ...$adopts
 *
 * @return bool
 */
function class_adopts_any( object|string $class, object|string ...$adopts ) : bool
{
    $composition = class_composition( $class );

    foreach ( $adopts as $has ) {
        if ( \array_key_exists( \is_object( $has ) ? $has::class : $has, $composition ) ) {
            return true;
        }
    }

    return false;
}

/**
 * @param object|string $class
 * @param object|string ...$adopts
 *
 * @return bool
 */
function class_adopts_all( object|string $class, object|string ...$adopts ) : bool
{
    $composition = class_composition( $class );

    foreach ( $adopts as $has ) {
        if ( ! \array_key_exists( \is_object( $has ) ? $has::class : $has, $composition ) ) {
            return false;
        }
    }

    return true;
}

/**
 * @template T of object
 *
 * @param class-string    $class     Check if this class implements a given Interface
 * @param class-string<T> $interface The Interface to check against
 *
 * @return bool
 */
function implements_interface( object|string $class, string $interface ) : bool
{
    $class = \is_object( $class ) ? $class::class : $class;

    if ( ! \class_exists( $class, false ) || ! \interface_exists( $interface ) ) {
        return false;
    }

    $interfaces = \class_implements( $class );

    if ( ! $interfaces || ! \in_array( $interface, $interfaces, true ) ) {
        return false;
    }

    return \interface_exists( $interface );
}

/**
 * @param class-string|object|string $class     Check if this class uses a given Trait
 * @param class-string|object|string $trait     The Trait to check against
 * @param bool                       $recursive [false] Also check for Traits using Traits
 *
 * @return bool
 */
function uses_trait( string|object $class, string|object $trait, bool $recursive = false ) : bool
{
    if ( \is_object( $trait ) ) {
        $trait = $trait::class;
    }

    $traits = get_traits( $class );

    if ( $recursive ) {
        foreach ( $traits as $traitClass ) {
            $traits += get_traits( $traitClass );
        }
    }

    return \in_array( $trait, $traits, true );
}

/**
 * @param class-string|object|string $class
 *
 * @return array<string, class-string>
 */
function get_traits( string|object $class ) : array
{
    if ( \is_object( $class ) ) {
        $class = $class::class;
    }

    $traits = \class_uses( $class );

    foreach ( \class_parents( $class ) ?: [] as $parent ) {
        $traits += \class_uses( $parent );
    }

    return $traits;
}

/**
 * # Get all the classes, traits, and interfaces used by a class.
 *
 * @param class-string|object|string $class
 * @param bool                       $includeSelf
 * @param bool                       $includeInterface
 * @param bool                       $includeTrait
 * @param bool                       $namespace
 * @param bool                       $details
 *
 * @return array<array-key, string>
 */
function class_extends(
    string|object $class,
    bool          $includeSelf = true,
    bool          $includeInterface = true,
    bool          $includeTrait = true,
    bool          $namespace = true,
    bool          $details = false,
) : array {
    $class = \is_object( $class ) ? $class::class : $class;

    $classes = $includeSelf ? [$class => 'self'] : [];

    $parent = \class_parents( $class ) ?: [];
    $classes += \array_fill_keys( $parent, 'parent' );

    if ( $includeInterface ) {
        $interfaces = \class_implements( $class ) ?: [];
        $classes += \array_fill_keys( $interfaces, 'interface' );
    }

    if ( $includeTrait ) {
        $traits = \class_uses( $class ) ?: [];
        $classes += \array_fill_keys( $traits, 'trait' );
    }

    if ( $details ) {
        return $classes;
    }

    $classes = \array_keys( $classes );

    if ( $namespace ) {
        foreach ( $classes as $key => $class ) {
            $classes[$key] = class_basename( $class );
        }
    }

    return $classes;
}

// </editor-fold>

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

// <editor-fold desc="Strings">

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

/**
 * Split the provided `$string` in two, at the first or last `$substring`.
 *
 * - Always returns an array of `[string: before, null|string: after]`.
 * - The matched part of the `$substring` belongs to `after` by default.
 * - If no `$substring` is found, the `after` value will be `null`
 *
 *  ```
 * // default, match first
 *  str_bisect(
 *      string: 'this example [has] example [substring].',
 *      substring: '[',
 *  ) [
 *      'this example ',
 *      '[has] example [substring].',
 *  ]
 * // match last
 *  str_bisect(
 *      string: 'this example [has] example [substring].',
 *      substring: '[',
 *      first: false,
 *  ) [
 *      'this example [has] example ',
 *      '[substring].',
 *  ]
 * // string .= substring
 *  str_bisect(
 *      string: 'this example [has] example [substring].',
 *      substring: '[',
 *      includeSubstring: true,
 *  ) [
 *      'this example [',
 *      'has] example [substring].',
 *  ]
 * ```
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable $needle
 * @param bool                   $last
 * @param bool                   $needleLast
 *
 * @return array{string, string}
 */
function str_bisect(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool                   $last = false,
    bool                   $needleLast = false,
) : array {
    $string = (string) $string;
    $needle = (string) $needle;

    if ( ! $string || ! $needle ) {
        return [$string, ''];
    }

    $offset = $last
            ? \mb_strripos( $string, $needle )
            : \mb_stripos( $string, $needle );

    if ( $offset === false ) {
        return [$string, ''];
    }

    if ( $last ) {
        $offset = $needleLast ? $offset + \mb_strlen( $needle ) : $offset;
    }
    else {
        $offset = $needleLast ? $offset : $offset + \mb_strlen( $needle );
    }

    $before = \mb_substr( $string, 0, $offset );
    $after  = \mb_substr( $string, $offset );

    return [
        $before,
        $after,
    ];
}

function mb_str_starts_with(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
) : bool {
    return \mb_stripos( (string) $haystack, (string) $needle, 0, 'UTF-8' ) === 0;
}

function mb_str_ends_with(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
) : bool {
    $haystack = (string) $haystack;
    $needle   = (string) $needle;
    return \mb_strripos( $haystack, $needle, 0, 'UTF-8' ) === \mb_strlen( $haystack ) - \mb_strlen( $needle );
}

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

// </editor-fold>

// <editor-fold desc="Filters and Escapes">

/**
 * @param null|string|Stringable $string
 * @param bool                   $comments
 * @param string                 $encoding
 * @param int                    $flags
 *
 * @return string
 */
function escape_html(
    null|string|Stringable $string,
    bool                   $comments = false,
    string                 $encoding = 'UTF-8',
    int                    $flags = ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
) : string {
    if ( ! $string = (string) $string ) {
        return $string;
    }

    $string = \htmlspecialchars( $string, $flags, $encoding );

    if ( $comments ) {
        $string = str_replace_each( FILTER_STRING_COMMENTS, $string );
    }

    return $string;
}

/**
 * Filter a string assuming it a URL.
 *
 * - Preserves Unicode characters.
 * - Removes tags by default.
 *
 * @param null|string|Stringable $string       $string
 * @param bool                   $preserveTags [false]
 *
 * @return string
 */
function escape_url(
    null|string|Stringable $string,
    bool                   $preserveTags = false,
) : string {
    if ( ! $string = (string) $string ) {
        return $string;
    }

    $safeCharacters = URL_SAFE_CHARACTERS_UNICODE;

    if ( $preserveTags ) {
        $safeCharacters .= '{}|^`"><@';
    }

    $filtered = (string) ( \preg_replace(
        pattern     : "/[^{$safeCharacters}]/u",
        replacement : EMPTY_STRING,
        subject     : $string,
    ) ?? EMPTY_STRING );

    // Escape special characters including tags
    return \htmlspecialchars( $filtered, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param null|string|Stringable $string       $string
 * @param bool                   $preserveTags
 *
 * @return string
 * @deprecated `\Support\Escape::url( .., .., )`
 *
 * Filter a string assuming it a URL.
 *
 * - Preserves Unicode characters.
 * - Removes tags by default.
 */
function filterUrl( null|string|Stringable $string, bool $preserveTags = false ) : string
{
    throw new BadMethodCallException( __FUNCTION__.' no longer supported.' );
    // Can not be null or an empty string
    // if ( ! $string = (string) $string ) {
    //     return EMPTY_STRING;
    // }
    // trigger_deprecation( 'Northrook\\Functions', 'dev', __METHOD__ );
    // static $cache = [];
    //
    // return $cache[\json_encode( [$string, $preserveTags], 832 )] ??= (
    //     static function() use ( $string, $preserveTags ) : string {
    //         $safeCharacters = URL_SAFE_CHARACTERS_UNICODE;
    //
    //         if ( $preserveTags ) {
    //             $safeCharacters .= '{}|^`"><@';
    //         }
    //
    //         return \preg_replace(
    //             pattern     : "/[^{$safeCharacters}]/u",
    //             replacement : EMPTY_STRING,
    //             subject     : $string,
    //         ) ?? EMPTY_STRING;
    //     }
    // )();
}

function stripTags(
    null|string|Stringable $string,
    string                 $replacement = ' ',
    ?string             ...$allowed_tags,
) : string {
    throw new BadMethodCallException( __FUNCTION__.' no longer supported.' );
    // return \str_replace(
    //     '  ',
    //     ' ',
    //     \strip_tags(
    //         \str_replace( '<', "{$replacement}<", (string) $string ),
    //     ),
    // );
}

/**
 * Escapes string for use inside iCal template.
 *
 * @param null|string|Stringable $value
 *
 * @return string
 */
function escapeICal( null|string|Stringable $value ) : string
{
    // Cannot be null or an empty string
    if ( ! ( $string = (string) $value ) ) {
        return EMPTY_STRING;
    }

    trigger_deprecation( 'Northrook\\Functions', 'probing', __METHOD__ );
    // https://www.ietf.org/rfc/rfc5545.txt
    $string = \str_replace( "\r", '', $string );
    $string = \preg_replace( '#[\x00-\x08\x0B-\x1F]#', "\u{FFFD}", (string) $string );

    return \addcslashes( (string) $string, "\";\\,:\n" );
}

// </editor-fold>

// <editor-fold desc="Case Converters">

function snakeToCamelCase( string $input ) : string
{
    return \lcfirst( \str_replace( '_', '', \ucwords( $input, '_' ) ) );
}

function kebabToCamelCase( string $input ) : string
{
    return \lcfirst( \str_replace( '-', '', \ucwords( $input, '-' ) ) );
}

// </editor-fold>
