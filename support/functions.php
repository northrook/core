<?php

declare(strict_types=1);

namespace Support;

use Core\Exception\MissingPropertyException;
use Core\Interface\Printable;
use SplFileInfo, Stringable, ArrayAccess;
use DateTimeImmutable, DateTimeZone, DateTimeInterface;
use Throwable, Exception, InvalidArgumentException,
BadMethodCallException, BadFunctionCallException,
RuntimeException, OverflowException;
use Random\RandomException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use BackedEnum;
use UnitEnum;

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

// <editor-fold desc="System">
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

// </editor-fold>

// <editor-fold desc="Filesystem">

/**
 * @param string $filename
 * @param mixed  $data
 * @param bool   $overwrite
 * @param bool   $append
 *
 * @return void
 */
function file_save(
    null|string|Stringable $filename,
    mixed                  $data,
    bool                   $overwrite = true,
    bool                   $append = false,
) : void {
    if ( ! $filename ) {
        throw new RuntimeException( 'No filename specified.' );
    }

    $path = new SplFileInfo( (string) $filename );

    if ( ! $overwrite && $path->isReadable() ) {
        return;
    }

    if ( ! \file_exists( $path->getPath() ) ) {
        \mkdir( $path->getPath(), 0777, true );
    }

    if ( ! \is_writable( $path->getPath() ) ) {
        throw new RuntimeException( message : 'The file '.$path->getPathname().' is not writable.' );
    }

    $mode = $append ? FILE_APPEND : LOCK_EX;

    $status = \file_put_contents( $path->getPathname(), $data, $mode );

    if ( $status === false ) {
        throw new RuntimeException( message : 'Unable to write to file '.$path->getPathname() );
    }
}

/**
 * Removes a file or directory, including nested files.
 *
 * @param string $path
 *
 * @return bool
 */
function file_purge( string $path ) : bool
{
    return \is_file( $path )
            ? @\unlink( $path )
            : \array_map( __FUNCTION__, \glob( $path.'/*' ) ?: [] ) == @\rmdir( $path );
}

// </editor-fold>

// <editor-fold desc="Get">

/**
 * @param DateTimeInterface|int|string $when
 * @param null|DateTimeZone|string     $timezone [UTC]
 *
 * @return DateTimeImmutable
 */
function datetime(
    int|string|DateTimeInterface $when = 'now',
    string|DateTimeZone|null     $timezone = AUTO,
) : DateTimeImmutable {
    $fromDateTime = $when instanceof DateTimeInterface;
    $datetime     = $fromDateTime ? $when->getTimestamp() : $when;

    if ( \is_int( $datetime ) ) {
        $datetime = "@{$datetime}";
    }

    $timezone = match ( true ) {
        \is_null( $timezone )   => $fromDateTime ? $when->getTimezone() : \timezone_open( 'UTC' ),
        \is_string( $timezone ) => \timezone_open( $timezone ),
        default                 => $timezone,
    } ?: null;

    try {
        return new DateTimeImmutable( $datetime, $timezone );
    }
    catch ( Exception $exception ) {
        $message = 'Unable to create a new DateTimeImmutable object: '.$exception->getMessage();
        throw new InvalidArgumentException( $message, 500, $exception );
    }
}

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
        return normalize_path( ...$rootSegments );
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
        return normalize_path(
            \sys_get_temp_dir(),
            \hash( 'xxh32', getProjectDirectory() ),
        );
    } )();
}

/**
 * Capture the output buffer from a provided `callback`.
 *
 * - Will throw a {@see RuntimeException} if the `callback` throws any exceptions.
 *
 * @param callable $callback
 * @param mixed    ...$args
 *
 * @return string
 */
function ob_get( callable $callback, mixed ...$args ) : string
{
    \ob_start();
    try {
        $callback( ...$args );
    }
    catch ( Throwable $exception ) {
        \ob_end_clean();
        throw new RuntimeException(
            message  : 'An error occurred while capturinb the callback.',
            code     : 500,
            previous : $exception,
        );
    }
    return \ob_get_clean() ?: '';
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
 * False if passed value is considered `null` and `empty` type values, retains `0` and `false`.
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
 * `is_iterable` implementation that also checks for {@see ArrayAccess}.
 *
 * @phpstan-assert-if-true iterable|\Traversable $value
 *
 * @param mixed $value
 *
 * @return bool
 */
function is_iterable( mixed $value ) : bool
{
    return \is_iterable( $value ) || $value instanceof ArrayAccess;
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
function is_path( string|Stringable $string, string $contains = '..', string $illegal = '{}' ) : bool
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
function is_url( string|Stringable $string, ?string $requiredProtocol = null ) : bool
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
function is_relative_path( string|Stringable $path ) : bool
{
    return \str_starts_with( \str_replace( '\\', '/', (string) $path ), '/' );
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
 * @param null|string|Stringable $value
 * @param string                 ...$enforceDomain
 *
 * @return bool
 */
function is_email( null|string|Stringable $value, string ...$enforceDomain ) : bool
{
    // Can not be null or an empty string
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

// <editor-fold desc="Hashes and Keys">

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

// </editor-fold>

// <editor-fold desc="Strings">

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
            throw new OverflowException( 'Resulting string would be too long' );
        }

        $string .= \str_repeat( $character, $padding );
    }

    $length = \mb_strlen( $string, $encoding );

    return $string;
}

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
 * Ensures the appropriate string encoding.
 *
 *⚠️ This function can be expensive.
 *
 * @param null|string|Stringable $string
 * @param false|int<2,4>         $tabSize  [4]
 * @param null|non-empty-string  $encoding [UTF-8]
 *
 * @return string
 */
function str_normalize(
    string|Stringable|null $string,
    false|int              $tabSize = 4,
    ?string                $encoding = AUTO,
) : string {
    return normalize_string( $string, $tabSize, $encoding );
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

function str_contains_only( string|Stringable|null $string, string $characters ) : bool
{
    $string = (string) $string;

    if ( ! $characters ) {
        $message = __FUNCTION__.' requires at least one character to look for.';
        throw new InvalidArgumentException( $message );
    }

    return \strspn( $string, $characters ) === \strlen( $string );
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
function str_includes_any(
    null|string|Stringable $string,
    string                 $characters,
    int                    $offset = 0,
    ?int                   $length = null,
) : bool {
    if ( ! $string = (string) $string ) {
        return false;
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

function str_before(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool                   $last = false,
) : string {
    if ( ! $string = (string) $string ) {
        return EMPTY_STRING;
    }

    $before = $last
            ? \strrchr( $string, (string) $needle, true )
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
 * Replace each key from `$map` with its value, when found in `$content`.
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

// <editor-fold desc="Arrays">
/**
 * Ensures the provided array contains all keys.
 *
 * @param array<array-key, mixed> $array
 * @param array-key               ...$keys
 *
 * @return bool
 */
function arr_has_keys(
    array         $array,
    int|string ...$keys,
) : bool {
    foreach ( $keys as $key ) {
        if ( ! \array_key_exists( $key, $array ) ) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<array-key, mixed> $array
 * @param array-key               $key
 * @param array-key               $replacement
 *
 * @return array<array-key, mixed>
 */
function arr_replace_key(
    array      $array,
    int|string $key,
    int|string $replacement,
) : array {
    $keys  = \array_keys( $array );
    $index = \array_search( $key, $keys, true );

    if ( $index !== false ) {
        $keys[$index] = $replacement;
        $array        = \array_combine( $keys, $array );
    }

    return $array;
}

/**
 * @param array<array-key, mixed> $array
 * @param bool                    $preserveKeys
 * @param bool                    $filter
 * @param int<0,2>                $filterMode   ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 *
 * @return array<array-key, mixed>
 */
function arr_flatten(
    array         $array,
    bool          $preserveKeys = false,
    bool|callable $filter = false,
    int           $filterMode = ARRAY_FILTER_USE_VALUE,
) : array {
    $result = [];

    \array_walk_recursive(
        $array,
        match ( $preserveKeys ) {
            true => function( $v, $k ) use ( &$result ) : void {
                $result[$k] = $v;
            },
            false => function( $v ) use ( &$result ) : void {
                $result[] = $v;
            },
        },
    );

    if ( $filter === false ) {
        return $result;
    }

    $callback = $filter === true ? static fn( $v ) => ! is_empty( $v ) : $filter;

    return \array_filter( $result, $callback, $filterMode );
}

/**
 * @template TKey of array-key
 * @template TValue of mixed
 * Default:
 * - Removes `null` and `empty` type values, retains `0` and `false`.
 *
 * @param array<TKey, TValue> $array
 * @param ?callable           $callback
 * @param int-mask<0,2>       $mode     ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 *
 * @return array<TKey, TValue>
 */
function arr_filter(
    array     $array,
    ?callable $callback = null,
    int       $mode = ARRAY_FILTER_USE_VALUE,
) : array {
    $callback ??= static fn( $v ) => ! is_empty( $v );
    return \array_filter( $array, $callback, $mode );
}

/**
 * Default:
 * - Removes `null` and `empty` type values, retains `0` and `false`.
 *
 * @param array<array-key, mixed> $array
 * @param ?callable               $callback
 * @param int-mask<0,2>           $mode     ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 *
 * @return array<array-key, mixed>
 */
function arr_filter_recursive(
    array     $array,
    ?callable $callback = null,
    int       $mode = ARRAY_FILTER_USE_VALUE,
) : array {
    trigger_deprecation(
        'Support\Arr',
        '_dev',
        __METHOD__.' deprecated',
    );

    foreach ( $array as $key => $value ) {
        if ( \is_array( $value ) ) {
            $array[$key] = ! $value
                    ? arr_filter_recursive( $value, $callback, $mode )
                    : arr_filter( $value, $callback, $mode );
        }
        else {
            $array[$key] = $value;
        }
    }

    return arr_filter( $array );
}

/**
 * @param array<array-key, mixed> $array
 * @param mixed                   $match
 * @param int<0,2>                $mode  ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 *
 * @return null|int|string
 */
function arr_search(
    array $array,
    mixed $match,
    int   $mode = ARRAY_FILTER_USE_VALUE,
) : string|int|null {
    trigger_deprecation(
        'Support\Arr',
        '_dev',
        __METHOD__.' deprecated',
    );

    foreach ( $array as $key => $value ) {
        if ( \is_callable( $match ) && match ( $mode ) {
            ARRAY_FILTER_USE_VALUE => $match( $value ),
            ARRAY_FILTER_USE_KEY   => $match( $key ),
            ARRAY_FILTER_USE_BOTH  => $match( $value, $key ),
        } ) {
            return $key;
        }

        if ( $value === $match ) {
            return $key;
        }

        if ( \is_array( $value ) && arr_search( $value, $match, $mode ) ) {
            return $key;
        }
    }

    return null;
}

/**
 * Return the closest key or value that `$match` in the provided `$array`.
 *
 * @wip
 * @link https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array
 *
 * @param int|string              $match
 * @param array<array-key, mixed> $array
 *
 * @return null|int|string
 */
function arr_closest( int|string $match, array $array ) : null|int|string
{
    // TODO : Match key/value toggle
    // TODO : closest int/float round up/down
    // TODO : closest string match - str_starts_with / other algo?
    // TODO : option to return key/value of match
    // TODO : return FALSE on no match

    /** @var ?string $closest */
    $closest = null;

    foreach ( $array as $item ) {
        if ( ! \is_numeric( $item ) ) {
            throw new InvalidArgumentException( 'Array item must be numeric.' );
        }
        if ( $closest === null
             || \abs( (int) $match - (int) $closest )
                > \abs( (int) $item - (int) $match )
        ) {
            $closest = (int) $item;
        }
    }
    return $closest;
}

// </editor-fold>

// <editor-fold desc="Numbers and Math">

/**
 * Calculate the greatest common divisor between `$a` and `$b`.
 *
 * @param int $a
 * @param int $b
 *
 * @return int
 */
function num_gcd( int $a, int $b ) : int
{
    while ( $b !== 0 ) {
        [$a, $b] = [$b, $a % $b];
    }

    return $a;
}

/**
 * @param float|int $num
 * @param float|int $min
 * @param float|int $max
 *
 * @return bool
 */
function num_within( float|int $num, float|int $min, float|int $max ) : bool
{
    return $num >= $min && $num <= $max;
}

/**
 * @param float|int $num
 * @param float|int $min
 * @param float|int $max
 *
 * @return float|int
 */
function num_clamp(
    float|int $num,
    float|int $min,
    float|int $max,
) : float|int {
    return \max( $min, \min( $num, $max ) );
}

/**
 * @see https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array stackoverflow
 *
 * @param int   $num
 * @param int[] $in
 * @param bool  $returnKey
 *
 * @return null|int|string
 */
function num_closest( int $num, array $in, bool $returnKey = false ) : string|int|null
{
    foreach ( $in as $key => $value ) {
        if ( $num <= $value ) {
            return $returnKey ? $key : $value;
        }
    }

    return null;
}

/**
 * Calculate the difference in percentage `$from` `$to` given numbers.
 *
 * @param float $from
 * @param float $to
 *
 * @return float
 */
function num_percent( float $from, float $to ) : float
{
    if ( ! $from || $from === $to ) {
        return 0;
    }
    return (float) \number_format( ( $from - $to ) / $from * 100, 2 );
}

function num_byte_size( string|int|float $bytes ) : string
{
    $bytes = (float) ( \is_string( $bytes ) ? \mb_strlen( $bytes, '8bit' ) : $bytes );

    $unitDecimalsByFactor = [
        ['B', 0],  //     byte
        ['KiB', 0], // kibibyte
        ['MiB', 2], // mebibyte
        ['GiB', 2], // gigabyte
        ['TiB', 3], // mebibyte
        ['PiB', 3], // mebibyte
    ];

    $factor = $bytes ? \floor( \log( (int) $bytes, 1_024 ) ) : 0;
    $factor = (float) \min( $factor, \count( $unitDecimalsByFactor ) - 1 );

    $value = \round( $bytes / ( 1_024 ** $factor ), (int) $unitDecimalsByFactor[$factor][1] );
    $units = (string) $unitDecimalsByFactor[$factor][0];

    return $value.$units;
}

// </editor-fold>

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

    if ( is_iterable( $value ) ) {
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
    // Can not be null or an empty string
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

// <editor-fold desc="Normalizers">

require './functions/normalize.php';

// </editor-fold>

// <editor-fold desc="Path">

/**
 * @param string                        $path
 * @param bool                          $throw
 * @param null|InvalidArgumentException $exception
 *
 * @return bool
 */
function path_valid(
    string                   $path,
    bool                     $throw = false,
    InvalidArgumentException & $exception = null,
) : bool {
    // Ensure we are not receiving any previously set exceptions
    $exception = null;

    // Check if path exists and is readable
    $isReadable = \is_readable( $path );
    $exists     = \file_exists( $path ) && $isReadable;

    // Return early
    if ( $exists ) {
        return true;
    }

    // Determine path type
    $type = \is_dir( $path ) ? 'dir' : ( \is_file( $path ) ? 'file' : false );

    // Handle non-existent paths
    if ( ! $type ) {
        $exception = new InvalidArgumentException( "The '{$path}' does not exist." );
        if ( $throw ) {
            throw $exception;
        }
        return false;
    }

    $isWritable = \is_writable( $path );

    $error = ( ! $isWritable && ! $isReadable ) ? ' is not readable nor writable.' : null;
    $error ??= ( ! $isReadable ) ? ' not writable.' : null;
    $error ??= ( ! $isReadable ) ? ' not unreadable.' : null;
    $error ??= ' encountered a filesystem error. The cause could not be determined.';

    // Create exception message
    $exception = new InvalidArgumentException( "The path '{$path}' {$error}" );

    if ( $throw ) {
        throw $exception;
    }

    return false;
}

/**
 * @param string                        $path
 * @param bool                          $throw     [false]
 * @param null|InvalidArgumentException $exception
 *
 * @return bool
 */
function path_readable(
    string                   $path,
    bool                     $throw = false,
    InvalidArgumentException & $exception = null,
) : bool {
    $exception = null;

    if ( ! \file_exists( $path ) ) {
        $exception = new InvalidArgumentException(
            'The file at "'.$path.'" does not exist.',
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    if ( ! \is_readable( $path ) ) {
        $exception = new InvalidArgumentException(
            \sprintf( 'The "%s" "%s" is not readable.', \is_dir( $path ) ? 'directory' : 'file', $path ),
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    return ! $exception;
}

/**
 * @param string                        $path
 * @param bool                          $throw     [false]
 * @param null|InvalidArgumentException $exception
 *
 * @return bool
 */
function path_writable(
    string                   $path,
    bool                     $throw = false,
    InvalidArgumentException & $exception = null,
) : bool {
    $exception = null;

    if ( ! \file_exists( $path ) ) {
        $exception = new InvalidArgumentException(
            'The file at "'.$path.'" does not exist.',
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    if ( ! \is_writable( $path ) ) {
        $exception = new InvalidArgumentException(
            \sprintf( 'The "%s" "%s" is not writable.', \is_dir( $path ) ? 'directory' : 'file', $path ),
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    return ! $exception;
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

// <editor-fold desc="Utility">
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
// </editor-fold>
