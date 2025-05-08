<?php

namespace Support;

use BackedEnum;
use Core\Interface\Printable;
use Stringable;
use UnitEnum;

/**
 * Check if the `$value` is a multidimensional iterable.
 *
 * @param mixed $value
 *
 * @return bool
 */
function is_multidimensional( mixed $value ) : bool
{
    if ( ! \is_iterable( $value ) ) {
        return false;
    }

    $array = \iterator_to_array( $value, false );

    return (bool) \count( \array_filter( $array, '\is_iterable' ) );
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
