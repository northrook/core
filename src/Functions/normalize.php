<?php

declare(strict_types=1);

namespace Northrook\Core;

use InvalidArgumentException;
use LengthException;
use LogicException;
use Northrook\Contracts\Exceptions\ErrorException;
use Stringable;

/**
 * Ensures the appropriate string encoding.
 *
 * ⚠️ This function can be expensive.
 *
 * 💡 Cache the result when possible.
 *
 * @param null|string|Stringable $string
 * @param false|int<2,4>         $tabSize  [4]
 * @param null|non-empty-string  $encoding [CHARSET]
 *
 * @return string
 */
function normalize_string(
    string|Stringable|null $string,
    false|int $tabSize = 4,
    null|string $encoding = CHARSET,
): string {
    // Ensure string encoding
    $string = str_encode(
        $string,
        $encoding,
    );

    // Convert leading spaces to tabs
    if ($tabSize) {
        $string = (string) \preg_replace_callback(
            '#^ *#m',
            static function($matches) use ($tabSize) {
                // Group each $tabSize
                $tabs = \intdiv(\strlen($matches[0]), $tabSize);

                // Replace $tabs with "\t", excess spaces discarded
                // Otherwise leading whitespace is trimmed
                return $tabs > 0 ? \str_repeat("\t", $tabs) : '';
            },
            $string,
        );
    }

    // Trim repeated whitespace, normalize line breaks
    return (string) \preg_replace(['# +#', '#\r\n#', '#\r#'], [' ', NEWLINE], \trim($string));
}

/**
 * Normalize repeated whitespace, newlines, and indentation, to a single white space.
 */
function normalize_whitespace(
    null|string|Stringable $string,
): string {
    return (string) \preg_replace('#\s+#', ' ', \trim((string) $string));
}

/**
 * Normalize all newlines in a string to `NEWLINE`.
 *
 * @param null|string|Stringable $string
 */
function normalize_newline(
    null|string|Stringable $string,
): string {
    return \str_replace(["\r\n", "\r"], NEWLINE, (string) $string);
}

/**
 * Normalize all slashes in a string to `SLASH`.
 */
function normalize_slashes(
    string|Stringable $string,
): string {
    return \strtr((string) $string, '\\', SLASH);
}

/**
 * # Normalize a `string` or `string[]`, assuming it is a `path`.
 *
 * - If an array of strings is passed, they will be joined using the directory separator.
 * - Normalizes slashes to `DIR_SEP`.
 * - Removes repeated separators.
 * - Will throw a {@see \ValueError} if the resulting string exceeds {@see \PHP_MAXPATHLEN}.
 *
 * ```
 * normalizePath( './assets\\\/scripts///example.js' );
 * // => './assets/scripts/example.js'
 * ```
 *
 * @param null|array<array-key,null|string|Stringable>|string|Stringable $path
 * @param bool                                                           $traversal
 * @param bool                                                           $throwOnFault
 *
 * @return string
 */
function normalize_path(
    null|string|Stringable|array $path,
    bool $traversal = false,
    bool $throwOnFault = false,
): string {
    // Return early on an empty $path
    if (! $path) {
        return $throwOnFault
            ? throw new \InvalidArgumentException(
                message: 'The provided path is empty: ' . \var_export($path, true),
                previous: ErrorException::getLast(),
            )
            : EMPTY_STRING;
    }

    // Resolve provided $path
    $path = \is_array($path) ? \implode(DIR_SEP, \array_filter($path)) : (string) $path;

    // Normalize separators (both `\` and `/` to the system separator)
    $path = \strtr($path, '\\', \DIR_SEP);

    // Check for starting separator
    $relative = match (true) {
        $path[0] === DIR_SEP => DIR_SEP,
        $path[0] === '.' && $path[1] === DIR_SEP => '.' . DIR_SEP,
        default => null,
    };

    // Relative paths cannot be traversed: throw when requested, otherwise traversal is silently disabled.
    if ($traversal && $relative) {
        if ($throwOnFault) {
            throw new LogicException(
                'Cannot traverse relative path: ' . \var_export($path, true),
            );
        }
        $traversal = false;
    }

    $fragments = [];

    if (empty($path)) {
        return EMPTY_STRING;
    }

    // Deduplicate separators and handle traversal
    foreach (\explode(DIR_SEP, $path) as $fragment) {
        // Ensure each part does not start or end with illegal characters
        $fragment = \trim($fragment, " \n\r\t\v\0\\/");

        if (! $fragment) {
            continue;
        }

        if (
            $traversal // if we are allowed to traverse
            && $fragment === '..' // and this fragment traverses
            && $fragments // and we have at least one parent
            && \end($fragments) !== '..' // and the parent isn't traversing
        ) {
            \array_pop($fragments);
        } elseif ($fragment !== '.') {
            $fragments[] = $fragment;
        }
    }

    // Implode, preserving intended relative paths
    $path = $relative . \implode(DIR_SEP, $fragments);

    if (( $length = \strlen($path) ) > ( $maxLength = PHP_MAXPATHLEN - 2 )) {
        $method    = __METHOD__;
        $length    = (string) $length;
        $maxLength = (string) $maxLength;
        $message   = "{$method} resulted in a string of {$length}, exceeding the {$maxLength} byte length.";
        $result    = 'Operation was halted to prevent overflow.';
        throw new LengthException($message . PHP_EOL . $result);
    }

    if (! $path) {
        return $throwOnFault
            ? throw new InvalidArgumentException(
                'The provided path is empty: ' . \var_export($path, true),
            )
            : EMPTY_STRING;
    }

    return $path;
}

/**
 * Normalize a URL path segment: slashes, whitespace, protocol casing, and duplicate separators.
 *
 * @param array<int, ?string>|string $path                 the string to normalize
 * @param false|string               $substituteWhitespace [-]
 * @param bool                       $trailingSlash
 *
 * @return string
 */
function normalize_url(
    null|string|Stringable|array $path,
    false|string $substituteWhitespace = '-',
    bool $trailingSlash = false,
): string {
    // Return early on an empty $path
    if (! $path) {
        return EMPTY_STRING;
    }

    $string = \is_array($path) ? \implode('/', $path) : (string) $path;

    // Normalize slashes
    $string = \str_replace('\\', '/', $string);

    // Handle whitespace
    if ($substituteWhitespace !== false) {
        $string = (string) \preg_replace('#\s+#', $substituteWhitespace, $string);
    }

    $protocol = '/';
    $fragment = '';
    $query    = '';

    // Extract and lowercase the $protocol
    if (\str_contains($string, '://')) {
        [$protocol, $string] = \explode('://', $string, 2);
        $protocol = \strtolower($protocol) . '://';
    }

    // Check if the $string contains $query and $fragment
    $matchQuery    = \strpos($string, '?');
    $matchFragment = \strpos($string, '#');

    // If the $string contains both
    if ($matchQuery !== false && $matchFragment !== false) {
        // To parse both regardless of order, we check which one appears first in the $string.
        // Split the $string by the first $match, which will then contain the other.

        // $matchQuery is first
        if ($matchQuery < $matchFragment) {
            [$string, $query] = \explode('?', $string, 2);
            [$query, $fragment] = \explode('#', $query, 2);
        }
        // $matchFragment is first
        else {
            [$string, $fragment] = \explode('#', $string, 2);
            [$fragment, $query] = \explode('?', $fragment, 2);
        }

        // After splitting, prepend the relevant identifiers.
        $query    = "?{$query}";
        $fragment = "#{$fragment}";
    }
    // If the $string only contains $query
    elseif ($matchQuery !== false) {
        [$string, $query] = \explode('?', $string, 2);
        $query = "?{$query}";
    }
    // If the $string only contains $fragment
    elseif ($matchFragment !== false) {
        [$string, $fragment] = \explode('#', $string, 2);
        $fragment = "#{$fragment}";
    }

    // Remove duplicate separators and lowercase the $path
    $path = \strtolower(\implode('/', \array_filter(\explode('/', $string))));

    // Prepend trailing separator if needed
    if ($trailingSlash) {
        $path .= '/';
    }

    // Assemble the URL
    return $protocol . $path . $query . $fragment;
}
