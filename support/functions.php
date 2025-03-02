<?php

declare(strict_types=1);

namespace Support;

use Random\RandomException;
use Stringable, ArrayAccess;
use DateTimeImmutable, DateTimeZone, DateTimeInterface;
use Exception, InvalidArgumentException, BadFunctionCallException, LengthException;
use voku\helper\ASCII;
use BadMethodCallException;

// <editor-fold desc="Constants">
/**
 * Log levels, following Monolog and [RFC 5424](https://datatracker.ietf.org/doc/html/rfc5424)
 */
const LOG_LEVEL = [
    'debug'     => 100,
    'info'      => 200,
    'notice'    => 250,
    'warning'   => 300,
    'error'     => 400,
    'critical'  => 500,
    'alert'     => 550,
    'emergency' => 600,
];

/** Indicates a `default` value will be used unless provided */
const AUTO = null;

const
    TAB          = "\t",
    EMPTY_STRING = '',
    WHITESPACE   = ' ',
    NEWLINE      = "\n";

/** Line Feed  */
const LF = "\n";
/** Carriage Return */
const CR = "\r";
/** Carriage Return and Line Feed */
const CRLF = "\r\n";

const PLACEHOLDER_ARGS   = [[]];
const PLACEHOLDER_ARG    = [];
const PLACEHOLDER_ARRAY  = [];
const PLACEHOLDER_STRING = '';
const PLACEHOLDER_NULL   = null;
const PLACEHOLDER_INT    = 0;

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

// @formatter:off
const TAG_HEADING      = ['hgroup', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
const TAG_INLINE       = ['a', 'b', 'i', 's', 'em', 'small', 'strong', 'span', 'mark', 'cite', 'code', 'kbd', 'wbr'];
const TAG_SELF_CLOSING = ['meta', 'link', 'img', 'input', 'wbr', 'hr', 'br', 'col', 'area', 'base', 'source', 'embed', 'track'];
// @formatter:on

// </editor-fold>

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
        return normalizePath( ...$rootSegments );
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
        $tempDir = \sys_get_temp_dir();
        $dirHash = \hash( 'xxh3', getProjectDirectory() );

        return normalizePath( $tempDir, $dirHash );
    } )();
}

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

// <editor-fold desc="Class Functions">
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
 * @param class-string|object|string $class
 * @param ?callable-string           $filter {@see \strtolower} by default
 *
 * @return string
 */
function class_basename( string|object $class, ?string $filter = 'strtolower' ) : string
{
    $namespaced = \explode( '\\', \is_object( $class ) ? $class::class : $class );
    $basename   = \end( $namespaced );

    if ( \is_callable( $filter ) ) {
        return $filter( $basename );
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
 * @template T of object
 *
 * @param class-string    $class     Check if this class implements a given Interface
 * @param class-string<T> $interface The Interface to check against
 *
 * @return bool
 */
function implements_interface( string $class, string $interface ) : bool
{
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

/**
 * @param float $number
 * @param float $min
 * @param float $max
 *
 * @return bool
 */
function num_within( float $number, float $min, float $max ) : bool
{
    return $number >= $min && $number <= $max;
}

/**
 * @param float $number
 * @param float $min
 * @param float $max
 *
 * @return float
 */
function num_clamp( float $number, float $min, float $max ) : float
{
    return \max( $min, \min( $number, $max ) );
}

/**
 * @see https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array stackoverflow
 *
 * @param int   $humber
 * @param int[] $in
 * @param bool  $returnKey
 *
 * @return null|int|string
 */
function num_closest( int $humber, array $in, bool $returnKey = false ) : string|int|null
{
    foreach ( $in as $key => $value ) {
        if ( $humber <= $value ) {
            return $returnKey ? $key : $value;
        }
    }

    return null;
}

/**
 * Ensures appropriate string encoding.
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
function str_encode( null|string|Stringable $string, ?string $encoding = AUTO ) : string
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

/**
 * - Ensures appropriate string encoding.
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
    // Ensure appropriate string encoding
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
                return ( $tabs > 0 ) ? \str_repeat( "\t", $tabs ) : '';
            },
            $string,
        );
    }

    // Trim repeated whitespace, normalize line breaks
    return (string) \preg_replace( ['# +#', '#\r\n#', '#\r#'], [' ', "\n"], \trim( $string ) );
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

function mb_str_starts_with( string $haystack, string $needle ) : bool
{
    return \mb_stripos( $haystack, $needle, 0, 'UTF-8' ) === 0;
}

function mb_str_ends_with( string $haystack, string $needle ) : bool
{
    return \mb_strripos( $haystack, $needle, 0, 'UTF-8' ) === \mb_strlen( $haystack ) - \mb_strlen( $needle );
}

function str_start( string $string, string $with ) : string
{
    if ( \str_starts_with( $string, $with ) ) {
        return $string;
    }

    return $with.$string;
}

function str_end( string $string, string $with ) : string
{
    if ( \str_ends_with( $string, $with ) ) {
        return $string;
    }

    return $string.$with;
}

function str_starts_with_any( null|string|Stringable $string, null|string|Stringable ...$needle ) : bool
{
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

function str_ends_with_any( null|string|Stringable $string, null|string|Stringable ...$needle ) : bool
{
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
 * @param null|string|Stringable $string
 * @param string                 $separator
 * @param ?callable-string       $filter    {@see \strtolower} by default
 * @param string                 $language  [en]
 *
 * @return string
 */
function slug(
    null|string|Stringable $string,
    string                 $separator = '-',
    ?string                $filter = 'strtolower',
    string                 $language = 'en',
) : string {
    if ( ! $string = \trim( (string) $string ) ) {
        return EMPTY_STRING;
    }

    if ( \class_exists( ASCII::class ) ) {
        /** @var ASCII::* $language */
        $string = ASCII::to_ascii( $string, $language );
    }

    // Replace non-alphanumeric characters with the separator
    $string = \trim(
        (string) \preg_replace( "#[^a-z0-9{$separator}]+#i", $separator, $string ),
        " \n\r\t\v\0{$separator}",
    );

    return \is_callable( $filter ) ? (string) $filter( $string ) : $string;
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
 * @param mixed                        $value
 * @param 'implode'|'json'|'serialize' $encoder
 *
 * @return string 16 character hash of the value
 */
function hashKey(
    mixed  $value,
    string $encoder = 'json',
) : string {
    if ( ! \is_string( $value ) ) {
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
    }

    // Hash the $value to a 16 character string
    return \hash( algo : 'xxh3', data : $value );
}

/**
 * @param mixed ...$value
 */
function cacheKey( mixed ...$value ) : string
{
    $key = [];

    foreach ( $value as $segment ) {
        if ( \is_null( $segment ) ) {
            continue;
        }

        $key[] = match ( \gettype( $segment ) ) {
            'string'  => $segment,
            'boolean' => $segment ? 'true' : 'false',
            'integer' => (string) $segment,
            default   => \hash(
                algo : 'xxh32',
                data : \json_encode( $value ) ?: \serialize( $value ),
            ),
        };
    }

    return \strtolower( \trim( \implode( ':', $key ) ) );
}

/**
 * Generate a random hashed string.
 *
 * - `xxh32` 8 characters
 * - `xxh64` 16 characters
 *
 * @param 'xxh32'|'xxh64' $algo    [xxh64]
 * @param int<2,12>       $entropy [7]
 *
 * @return string
 */
function randKey( string $algo = 'xxh64', int $entropy = 7 ) : string
{
    try {
        return \hash( $algo, data : \random_bytes( $entropy ) );
    }
    catch ( RandomException ) {
        return \hash( $algo, data : (string) \rand( 0, PHP_INT_MAX ) );
    }
}

function implodeKey( mixed $value = AUTO, string $separator = ':' ) : string
{
    $key = [];

    if ( ! \is_iterable( $value ) ) {
        $value = [$value];
    }

    foreach ( $value as $segment ) {
        if ( \is_null( $segment ) ) {
            continue;
        }

        $key[] = match ( \gettype( $segment ) ) {
            'string'  => $segment,
            'boolean' => $segment ? 'true' : 'false',
            'integer' => (string) $segment,
            'object'  => $segment::class.'#'.\spl_object_id( $segment ),
            default   => \hash(
                algo : 'xxh3',
                data : \json_encode( $value ) ?: \serialize( $value ),
            ),
        };
    }

    return \implode( $separator, $key );
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
 * @param null|string|Stringable $value
 * @param string                 ...$enforceDomain
 *
 * @return bool
 */
function isEmail( null|string|Stringable $value, string ...$enforceDomain ) : bool
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
 * @param null|string|Stringable $string
 *
 * @return string
 */
function normalizeNewline( string|Stringable|null $string ) : string
{
    return \str_replace( ["\r\n", "\r", "\n"], NEWLINE, (string) $string );
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
 * @param ?string ...$path
 */
function normalizePath( ?string ...$path ) : string
{
    // Normalize separators
    $normalized = \str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, \array_filter( $path ) );

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
 * @param array<int, ?string>|string $path                 the string to normalize
 * @param false|string               $substituteWhitespace [-]
 * @param bool                       $trailingSlash
 *
 * @return string
 */
function normalizeUrl(
    string|array $path,
    false|string $substituteWhitespace = '-',
    bool         $trailingSlash = false,
) : string {
    $string = \is_array( $path ) ? \implode( '/', $path ) : $path;

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

    // Remove duplicate separators, and lowercase the $path
    $path = \strtolower( \implode( '/', \array_filter( \explode( '/', $string ) ) ) );

    // Prepend trailing separator if needed
    if ( $trailingSlash ) {
        $path .= '/';
    }

    // Assemble the URL
    return $protocol.$path.$query.$fragment;
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

function isDelimiter( string $string ) : bool
{
    return (bool) \preg_match( '#^[,;]+$#', $string );
}

function isPunctuation( string $string, bool $endingOnly = false ) : bool
{
    return (bool) ( $endingOnly
            ? \preg_match( '#^[.!]+$#', $string )
            : \preg_match( '#^[[:punct:]]+$#', $string ) );
}

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
        \is_bool( $value ) => $value ? 'true' : 'false',
        \is_null( $value ) => $nullable ? null : EMPTY_STRING,
        \is_scalar( $value ), $value instanceof Stringable => (string) $value,
        default => $value,
    };

    if ( isIterable( $value ) ) {
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

// <editor-fold desc="Filters and Escapes">

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
