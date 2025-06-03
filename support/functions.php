<?php

declare(strict_types=1);

namespace Support;

use Core\Exception\MissingPropertyException;
use JetBrains\PhpStorm\Deprecated;
use Stringable;
use InvalidArgumentException, BadFunctionCallException;
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
#[Deprecated( replacement : '\Support\get_project_directory()' )]
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
#[Deprecated( replacement : '\Support\get_system_cache_directory()' )]
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

// <editor-fold desc="Filters and Escapes">

/**
 * @param null|string|Stringable $string
 * @param bool                   $comments
 * @param string                 $encoding
 * @param int                    $flags
 *
 * @return string
 */
#[Deprecated( 'probing' )]
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
#[Deprecated( 'probing' )]
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

// /**
//  * @param null|string|Stringable $string       $string
//  * @param bool                   $preserveTags
//  *
//  * @return string
//  * @deprecated `\Support\Escape::url( .., .., )`
//  *
//  * Filter a string assuming it a URL.
//  *
//  * - Preserves Unicode characters.
//  * - Removes tags by default.
//  */
// function filterUrl( null|string|Stringable $string, bool $preserveTags = false ) : string
// {
//     throw new BadMethodCallException( __FUNCTION__.' no longer supported.' );
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
// }

// function stripTags(
//     null|string|Stringable $string,
//     string                 $replacement = ' ',
//     ?string             ...$allowed_tags,
// ) : string {
//     throw new BadMethodCallException( __FUNCTION__.' no longer supported.' );
//     // return \str_replace(
//     //     '  ',
//     //     ' ',
//     //     \strip_tags(
//     //         \str_replace( '<', "{$replacement}<", (string) $string ),
//     //     ),
//     // );
// }

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

// /**
//  * Escapes string for use inside iCal template.
//  *
//  * @param null|string|Stringable $value
//  *
//  * @return string
//  */
// function escapeICal( null|string|Stringable $value ) : string
// {
//     // Cannot be null or an empty string
//     if ( ! ( $string = (string) $value ) ) {
//         return EMPTY_STRING;
//     }
//
//     trigger_deprecation( 'Northrook\\Functions', 'probing', __METHOD__ );
//     // https://www.ietf.org/rfc/rfc5545.txt
//     $string = \str_replace( "\r", '', $string );
//     $string = \preg_replace( '#[\x00-\x08\x0B-\x1F]#', "\u{FFFD}", (string) $string );
//
//     return \addcslashes( (string) $string, "\";\\,:\n" );
// }

// /**
//  * Split the provided `$string` in two, at the first or last `$substring`.
//  *
//  * - Always returns an array of `[string: before, null|string: after]`.
//  * - The matched part of the `$substring` belongs to `after` by default.
//  * - If no `$substring` is found, the `after` value will be `null`
//  *
//  *  ```
//  * // default, match first
//  *  str_bisect(
//  *      string: 'this example [has] example [substring].',
//  *      substring: '[',
//  *  ) [
//  *      'this example ',
//  *      '[has] example [substring].',
//  *  ]
//  * // match last
//  *  str_bisect(
//  *      string: 'this example [has] example [substring].',
//  *      substring: '[',
//  *      first: false,
//  *  ) [
//  *      'this example [has] example ',
//  *      '[substring].',
//  *  ]
//  * // string .= substring
//  *  str_bisect(
//  *      string: 'this example [has] example [substring].',
//  *      substring: '[',
//  *      includeSubstring: true,
//  *  ) [
//  *      'this example [',
//  *      'has] example [substring].',
//  *  ]
//  * ```
//  *
//  * @param null|string|Stringable $string
//  * @param null|string|Stringable $needle
//  * @param bool                   $last
//  * @param bool                   $needleLast
//  *
//  * @return array{string, string}
//  */
// function str_bisect(
//     null|string|Stringable $string,
//     null|string|Stringable $needle,
//     bool                   $last = false,
//     bool                   $needleLast = false,
// ) : array {
//     $string = (string) $string;
//     $needle = (string) $needle;
//
//     if ( ! $string || ! $needle ) {
//         return [$string, ''];
//     }
//
//     $offset = $last
//             ? \mb_strripos( $string, $needle )
//             : \mb_stripos( $string, $needle );
//
//     if ( $offset === false ) {
//         return [$string, ''];
//     }
//
//     if ( $last ) {
//         $offset = $needleLast ? $offset + \mb_strlen( $needle ) : $offset;
//     }
//     else {
//         $offset = $needleLast ? $offset : $offset + \mb_strlen( $needle );
//     }
//
//     $before = \mb_substr( $string, 0, $offset );
//     $after  = \mb_substr( $string, $offset );
//
//     return [
//         $before,
//         $after,
//     ];
// }
