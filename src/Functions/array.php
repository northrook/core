<?php

declare(strict_types=1);

namespace Northrook\Core;

use InvalidArgumentException;

/**
 * Checks if the provided `$array` only contains string keys.
 *
 * @param array<array-key,mixed> $array
 *
 * @phpstan-assert-if-true array<string, mixed> $array
 * @return bool
 */
function array_is_associative(
    array $array,
): bool {
    if ($array === []) {
        return false;
    }

    return array_all(
        \array_keys($array),
        static fn(string|int $key): bool => \is_string($key),
    );
}

/**
 * Check if the `$value` is a multidimensional iterable.
 *
 * @param mixed $value
 *
 * @return bool
 */
function array_is_multidimensional(
    mixed $value,
): bool {
    if (! \is_iterable($value)) {
        return false;
    }

    $array = \iterator_to_array($value, false);

    return (bool) \count(\array_filter($array, '\is_iterable'));
}

/**
 * Ensures the provided array contains all keys.
 *
 * @param array<array-key, mixed> $array
 * @param array-key               ...$keys
 *
 * @return bool
 */
function array_has_keys(
    array $array,
    int|string ...$keys,
): bool {
    return array_all(
        $keys,
        static fn($key) => \array_key_exists($key, $array),
    );
}

/**
 * Recursively filter an array, applying the callback at every depth.
 *
 * Default callback removes `null` and empty-type values; retains `0` and `false`.
 *
 * @template TKey of array-key
 * @template TValue of mixed
 *
 * @param array<TKey, TValue> $array
 * @param ?callable           $callback
 * @param int-mask<0,1,2>     $mode      ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 *
 * @return array<TKey, TValue>
 */
function array_filter_recursive(
    array $array,
    null|callable $callback = null,
    int $mode = ARRAY_FILTER_USE_VALUE,
): array {
    $callback ??= static fn($v) => ! is_empty($v);

    foreach ($array as $key => $value) {
        if (\is_array($value) && ! empty($value)) {
            $array[$key] = array_filter_recursive(
                $value,
                $callback,
                $mode,
            );
        } else {
            $array[$key] = $value;
        }
    }

    /** @var array<TKey, TValue> $array */

    return \array_filter(
        $array,
        $callback,
        $mode,
    );
}

/**
 * Flatten a nested array to a single level using {@see \array_walk_recursive()}.
 *
 * @param array<array-key, mixed> $array
 * @param bool                    $preserveKeys when true, leaf keys overwrite earlier values on collision
 * @param bool|callable           $filter       when true, drops empty-type values after flattening
 * @param int-mask<0,1,2>         $filterMode   ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 *
 * @return array<array-key, mixed>
 */
function arr_flatten(
    array $array,
    bool $preserveKeys = false,
    bool|callable $filter = false,
    int $filterMode = ARRAY_FILTER_USE_VALUE,
): array {
    $result = [];

    \array_walk_recursive(
        $array,
        match ($preserveKeys) {
            true => function($v, $k) use (&$result): void {
                $result[$k] = $v;
            },
            false => function($v) use (&$result): void {
                $result[] = $v;
            },
        },
    );

    if ($filter === false) {
        return $result;
    }

    $callback = $filter === true
        ? static fn($v) => ! is_empty($v)
        : $filter;

    return \array_filter(
        $result,
        $callback,
        $filterMode,
    );
}

/**
 * Rename a single key while preserving value order.
 *
 * @param array<array-key, mixed> $array
 * @param array-key               $key
 * @param array-key               $replacement
 *
 * @return array<array-key, mixed>
 */
function arr_replace_key(
    array $array,
    int|string $key,
    int|string $replacement,
): array {
    $keys  = \array_keys($array);
    $index = \array_search($key, $keys, true);

    if ($index !== false) {
        $keys[$index] = $replacement;
        $array        = \array_combine($keys, $array);
    }

    return $array;
}

/**
 * Find the first matching key in a nested array.
 *
 * When a nested match is found, returns the key at the current depth, not the nested key.
 *
 * @param array<array-key, mixed> $array
 * @param mixed                   $match   value for strict equality, or a callback when callable
 * @param int<0,2>                $mode    ARRAY_FILTER_USE_VALUE|ARRAY_FILTER_USE_KEY|ARRAY_FILTER_USE_BOTH
 *
 * @return null|int|string
 */
function arr_search(
    array $array,
    mixed $match,
    int $mode = ARRAY_FILTER_USE_VALUE,
): string|int|null {
    foreach ($array as $key => $value) {
        if (\is_callable($match)) {
            if (match ($mode) {
                ARRAY_FILTER_USE_VALUE => $match($value),
                ARRAY_FILTER_USE_KEY   => $match($key),
                ARRAY_FILTER_USE_BOTH  => $match($value, $key),
            }) {
                return $key;
            }
        } elseif ($value === $match) {
            return $key;
        }

        if (\is_array($value)) {
            $found = arr_search($value, $match, $mode);

            if ($found !== null) {
                return $key;
            }
        }
    }

    return null;
}

/**
 * Return the closest numeric value in `$array` to `$match`.
 *
 * @param int|string              $match
 * @param array<array-key, mixed> $array
 *
 * @return null|float
 */
function arr_closest(
    int|string $match,
    array $array,
): null|float {
    if ($array === []) {
        return null;
    }

    $matchNumeric = (float) $match;
    $closest      = null;
    $closestDiff  = null;

    foreach ($array as $item) {
        if (! \is_numeric($item)) {
            throw new InvalidArgumentException('Array item must be numeric.');
        }

        $itemNumeric = (float) $item;
        $diff        = \abs($matchNumeric - $itemNumeric);

        if ($closestDiff === null || $diff < $closestDiff) {
            $closestDiff = $diff;
            $closest     = $itemNumeric;
        }
    }

    // TODO: option to return key/value of match, string matching, round up/down
    return $closest;
}
