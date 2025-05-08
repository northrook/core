<?php

namespace Support;

use InvalidArgumentException;

/**
 * Checks if the provided `$array` only contains string keys.
 *
 * @param array<array-key,mixed> $array
 *
 * @return bool
 */
function array_is_associative( array $array ) : bool
{
    return (bool) \count( \array_filter( \array_keys( $array ), '\is_string' ) );
}

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
 * @template TKey of array-key
 * @template TValue of mixed
 * Default:
 * - Removes `null` and `empty` type values, retains `0` and `false`.
 *
 * @param array<TKey, TValue> $array
 * @param ?callable           $callback
 * @param int-mask<0,2>       $mode      ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 * @param bool                $recursive
 *
 * @return array<TKey, TValue>
 */
function arr_filter(
    array     $array,
    ?callable $callback = null,
    int       $mode = ARRAY_FILTER_USE_VALUE,
    bool      $recursive = false,
) : array {
    $callback ??= static fn( $v ) => ! is_empty( $v );

    if ( $recursive ) {
        foreach ( $array as $key => $value ) {
            if ( \is_array( $value ) && ! empty( $value ) ) {
                $array[$key] = arr_filter( $value, $callback, $mode, true );
            }
            else {
                $array[$key] = $value;
            }
        }
    }

    /** @var array<TKey, TValue> $array */

    return \array_filter( $array, $callback, $mode );
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
