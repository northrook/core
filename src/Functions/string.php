<?php

declare(strict_types=1);

namespace Northrook\Core;

use InvalidArgumentException;
use LengthException;
use OverflowException;
use Stringable;

/**
 * First occurrence of `$needle` in `$haystack` (multibyte).
 *
 * Defaults to case-insensitive search; pass `$caseSensitive = true` for exact matching.
 */
function str_first_pos(
    string $haystack,
    string $needle,
    int $offset = 0,
    null|string $encoding = null,
    bool $caseSensitive = false,
): false|int {
    $encoding ??= CHARSET;

    return $caseSensitive
        ? \mb_strpos($haystack, $needle, $offset, $encoding)
        : \mb_stripos($haystack, $needle, $offset, $encoding);
}

/**
 * Last occurrence of `$needle` in `$haystack` (multibyte).
 *
 * Defaults to case-insensitive search; pass `$caseSensitive = true` for exact matching.
 */
function str_last_pos(
    string $haystack,
    string $needle,
    int $offset = 0,
    null|string $encoding = null,
    bool $caseSensitive = false,
): false|int {
    $encoding ??= CHARSET;

    return $caseSensitive
        ? \mb_strrpos($haystack, $needle, $offset, $encoding)
        : \mb_strripos($haystack, $needle, $offset, $encoding);
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
function str_encode(
    null|string|Stringable $string,
    null|string $encoding = CHARSET,
): string {
    if (! ( $string = (string) $string )) {
        return EMPTY_STRING;
    }

    $encoding ??= 'UTF-8';

    $entities = \htmlentities($string, ENT_NOQUOTES, $encoding, false);
    $decoded  = \htmlspecialchars_decode($entities, ENT_NOQUOTES);
    $map      = [0x80, 0x10_FF_FF, 0, ~0];

    return \mb_encode_numericentity($decoded, $map, $encoding);
}

/**
 * Compress a string by replacing consecutive whitespace characters with a single one.
 *
 * @param null|string|Stringable $string         $string
 * @param bool                   $whitespaceOnly if true, only spaces are squished, leaving tabs and new lines intact
 *
 * @return string the squished string with consecutive whitespace replaced by the defined whitespace character
 */
function str_squish(
    string|Stringable|null $string,
    bool $whitespaceOnly = false,
): string {
    return (string) (
        $whitespaceOnly
            ? \preg_replace('# +#', WHITESPACE, \trim((string) $string))
            : \preg_replace("#\s+#", WHITESPACE, \trim((string) $string))
    );
}

/**
 * Determine if a given `$string` contains only `$characters` in any number and order.
 *
 * @param null|string|Stringable $string     input string to check
 * @param non-empty-string       $characters set of allowed characters
 *
 * @return bool true if the string contains only the specified characters, false otherwise
 */
function str_contains_only(
    string|Stringable|null $string,
    string $characters,
): bool {
    if (! ( $string = (string) $string )) {
        return false;
    }
    if (! $characters) {
        throw new LengthException(__FUNCTION__ . ' requires at least one character to look for.');
    }

    return \strspn($string, $characters) === \strlen($string);
}
/**
 * Determine if a given `$string` contains all `$characters` in any number and order.
 *
 * - Starting at an optional offset and considering an optional length.
 *
 * @param null|string|Stringable $string     string to search within
 * @param non-empty-string       $characters set of characters to check for inclusion
 * @param int                    $offset     position in the string to start the search. Defaults to 0.
 * @param ?int                   $length     length of the substring to consider. If null, the entire string is used from the offset.
 *
 * @return bool true if all characters from the set are found in the string, false otherwise
 */
function str_includes_all(
    null|string|Stringable $string,
    string $characters,
    int $offset = 0,
    null|int $length = null,
): bool {
    if (! ( $string = (string) $string )) {
        return false;
    }

    if (! $characters) {
        throw new LengthException(__FUNCTION__ . ' requires at least one character to look for.');
    }

    return \strlen($characters) === \strspn($characters, $string, $offset, $length);
}

/**
 * Determine if a given `$string` contains at least one of many `$characters` in any number and order.
 *
 *  - Starting at an optional offset and considering an optional length.
 *
 * @param null|string|Stringable $string     string to search within
 * @param non-empty-string       $characters set of characters to check for inclusion
 * @param int                    $offset     position in the string to start the search. Defaults to 0.
 * @param ?int                   $length     length of the substring to consider. If null, the entire string is used from the offset.
 *
 * @return bool true if at least one character from the set is found in the string, false otherwise
 */
function str_includes_any(
    null|string|Stringable $string,
    string $characters,
    int $offset = 0,
    null|int $length = null,
): bool {
    if (! ( $string = (string) $string )) {
        return false;
    }

    if (! $characters) {
        throw new LengthException(__FUNCTION__ . ' requires at least one character to look for.');
    }

    if ($offset !== 0 || $length !== null) {
        $string = \substr($string, $offset, $length);
    }

    return \strpbrk($string, $characters) !== false;
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
    string $characters,
    int $offset = 0,
    null|int $length = null,
): bool {
    if (! ( $string = (string) $string )) {
        return true;
    }

    $segmentLength = $length ?? ( \strlen($string) - $offset );

    return \strcspn($string, $characters, $offset, $length) === $segmentLength;
}

/**
 * Replace each key from `$map` with its value when found in `$content`.
 *
 * @param array<string,null|string|Stringable> $map
 * @param string|array<string>                 $content
 * @param bool                                 $caseSensitive
 *
 * @return ($content is string ? string : string[])
 */
function str_replace_each(
    array $map,
    string|array $content,
    bool $caseSensitive = true,
): string|array {
    // Bail early on empty content
    if (! $content) {
        return $content;
    }

    // Validate and normalize the $map
    foreach ($map as $match => $replace) {
        \assert(\is_string($match), __METHOD__ . ' does not accept empty match keys');
        $map[$match] = (string) $replace;
    }

    $search  = \array_keys($map);
    $replace = \array_values($map);

    /**
     * @var string[] $search
     * @var string[] $replace
     * */
    return $caseSensitive ? \str_replace($search, $replace, $content) : \str_ireplace($search, $replace, $content);
}

/**
 * Bisect a string into two parts at a specified position or around a given substring.
 *
 * - Modifies the `$string` to contain the remainder after bisection
 * - `false` needles cause an early empty return
 * - `$includeNeedle` includes the `$needle` string in the return
 * - `$nullable` casts empty returns to `null`
 *
 * @param string           &$string
 * @param false|int|string $needle
 * @param bool             $includeNeedle
 * @param bool             $nullable
 * @param bool             $caseSensitive
 * @param null|string      $encoding
 *
 * @return null|string string before the `$needle`
 */
function str_bisect(
    string &$string,
    string|false|int $needle,
    bool $includeNeedle = false,
    bool $nullable = false,
    bool $caseSensitive = false,
    null|string $encoding = null,
): null|string {
    $encoding ??= CHARSET;

    if (\is_string($needle)) {
        $needlePosition = str_first_pos($string, $needle, 0, $encoding, $caseSensitive);
        if ($needlePosition === false) {
            return $nullable ? null : '';
        }
        $needle = $includeNeedle
            ? $needlePosition + \mb_strlen($needle, $encoding)
            : $needlePosition;
    }
    if (! \is_int($needle)) {
        return $nullable ? null : '';
    }

    $before = \mb_substr($string, 0, $needle, $encoding);
    $string = \mb_substr($string, $needle, null, $encoding);

    return $nullable ? ( $before === '' ? null : $before ) : $before;
}

/**
 * Replace the substring between `$start` and `$end`, or extract it when `$replace` is false.
 *
 * @param null|string|Stringable       $string
 * @param int                          $start
 * @param int                          $end
 * @param null|false|string|Stringable $replace
 * @param string                       $encoding
 *
 * @return string
 */
function str_extract(
    null|string|Stringable $string,
    int $start,
    int $end,
    false|null|string|Stringable $replace = false,
    string $encoding = 'UTF-8',
): string {
    if (! ( $string = (string) $string )) {
        return EMPTY_STRING;
    }

    $end -= $start;

    if ($replace === false) {
        return \mb_substr($string, $start, $end);
    }

    $replace = (string) $replace;

    $before = \mb_substr($string, 0, $start, $encoding);

    $length = \mb_strlen($before, $encoding) + $end;

    $after = \mb_substr($string, $length, null, $encoding);

    return $before . $replace . $after;
}

/**
 * Return the substring before the first or last occurrence of `$needle` (multibyte).
 *
 * When `$needle` is not found, returns the original string unchanged.
 */
function str_before(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool $last = false,
    bool $includeNeedle = false,
    bool $caseSensitive = false,
    null|string $encoding = null,
): string {
    $string   = (string) $string;
    $needle   = (string) $needle;
    $encoding ??= CHARSET;

    if ($string === '' || $needle === '') {
        return $string;
    }

    $pos = $last
        ? str_last_pos($string, $needle, 0, $encoding, $caseSensitive)
        : str_first_pos($string, $needle, 0, $encoding, $caseSensitive);

    if ($pos === false) {
        return $string;
    }

    $end = $includeNeedle
        ? $pos + \mb_strlen($needle, $encoding)
        : $pos;

    return \mb_substr($string, 0, $end, $encoding);
}

/**
 * Return the substring after the first or last occurrence of `$needle` (multibyte).
 *
 * When `$needle` is not found, returns the original string unchanged.
 */
function str_after(
    null|string|Stringable $string,
    null|string|Stringable $needle,
    bool $last = false,
    bool $includeNeedle = false,
    bool $caseSensitive = false,
    null|string $encoding = null,
): string {
    $string   = (string) $string;
    $needle   = (string) $needle;
    $encoding ??= CHARSET;

    if ($string === '' || $needle === '') {
        return $string;
    }

    $pos = $last
        ? str_last_pos($string, $needle, 0, $encoding, $caseSensitive)
        : str_first_pos($string, $needle, 0, $encoding, $caseSensitive);

    if ($pos === false) {
        return $string;
    }

    $start = $includeNeedle
        ? $pos
        : $pos + \mb_strlen($needle, $encoding);

    return \mb_substr($string, $start, null, $encoding);
}

/**
 * Ensures that a string starts with a specified substring.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable $with
 * @param bool                   $caseSensitive
 * @param null|string            $encoding
 *
 * @return string prepended with `$with` if not already present
 */
function str_start(
    null|string|Stringable $string,
    null|string|Stringable $with,
    bool $caseSensitive = false,
    null|string $encoding = null,
): string {
    $string = (string) $string;
    $with   = (string) $with;

    if (mb_str_starts_with($string, $with, $caseSensitive, $encoding)) {
        return $string;
    }

    return $with . $string;
}

/**
 * Ensures that a string ends with a specified substring.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable $with
 * @param bool                   $caseSensitive
 * @param null|string            $encoding
 *
 * @return string appended with `$with` if not already present
 */
function str_end(
    null|string|Stringable $string,
    null|string|Stringable $with,
    bool $caseSensitive = false,
    null|string $encoding = null,
): string {
    $string = (string) $string;
    $with   = (string) $with;

    if (mb_str_ends_with($string, $with, $caseSensitive, $encoding)) {
        return $string;
    }

    return $string . $with;
}

/**
 * Checks if a multibyte string starts with a given substring.
 *
 * @param null|string|Stringable $haystack
 * @param null|string|Stringable $needle
 * @param bool                   $caseSensitive
 * @param null|string            $encoding
 *
 * @return bool
 */
function mb_str_starts_with(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
    bool $caseSensitive = false,
    null|string $encoding = null,
): bool {
    $haystack = (string) $haystack;
    $needle   = (string) $needle;
    $encoding ??= CHARSET;

    if ($needle === '') {
        return true;
    }

    return str_first_pos($haystack, $needle, 0, $encoding, $caseSensitive) === 0;
}

/**
 * Checks if a multibyte string ends with a given substring.
 *
 * @param null|string|Stringable $haystack
 * @param null|string|Stringable $needle
 * @param bool                   $caseSensitive
 * @param null|string            $encoding
 *
 * @return bool
 */
function mb_str_ends_with(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
    bool $caseSensitive = false,
    null|string $encoding = null,
): bool {
    $haystack = (string) $haystack;
    $needle   = (string) $needle;
    $encoding ??= CHARSET;

    if ($needle === '') {
        return true;
    }

    $pos = str_last_pos($haystack, $needle, 0, $encoding, $caseSensitive);

    if ($pos === false) {
        return false;
    }

    return $pos === ( \mb_strlen($haystack, $encoding) - \mb_strlen($needle, $encoding) );
}

/**
 * Checks if a `$string` starts with any of the provided `$needle` substrings.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable ...$needle
 *
 * @return bool
 */
function str_starts_with_any(
    null|string|Stringable $string,
    null|string|Stringable ...$needle,
): bool {
    if (! ( $string = (string) $string )) {
        return false;
    }

    return array_any(
        $needle,
        static fn($substring) => mb_str_starts_with($string, (string) $substring),
    );
}

/**
 * Checks if a `$string` ends with any of the provided `$needle` substrings.
 *
 * @param null|string|Stringable $string
 * @param null|string|Stringable ...$needle
 *
 * @return bool
 */
function str_ends_with_any(
    null|string|Stringable $string,
    null|string|Stringable ...$needle,
): bool {
    if (! ( $string = (string) $string )) {
        return false;
    }

    return array_any(
        $needle,
        static fn($substring) => mb_str_ends_with($string, (string) $substring),
    );
}

/**
 * A {@see \strrchr()} implementation with full needle support
 *
 * @param null|string|\Stringable  $haystack
 * @param null|string|\Stringable  $needle
 * @param bool                     $before
 * @param bool                     $caseSensitive
 * @param null|string              $encoding
 *
 * @return false|string
 */
function str_last(
    null|string|Stringable $haystack,
    null|string|Stringable $needle,
    bool $before = false,
    bool $caseSensitive = false,
    null|string $encoding = null,
): string|false {
    $haystack = (string) $haystack;
    $needle   = (string) $needle;
    $encoding ??= CHARSET;

    if ($haystack === '' || $needle === '') {
        return $needle === '' ? false : $haystack;
    }

    $pos = str_last_pos($haystack, $needle, 0, $encoding, $caseSensitive);

    if ($pos === false) {
        return false;
    }

    return $before
        ? \mb_substr($haystack, 0, $pos, $encoding)
        : \mb_substr($haystack, $pos + \mb_strlen($needle, $encoding), null, $encoding);
}

/**
 * Converts a given `$string` to an `ASCII-safe` string by transliterating
 * Unicode characters to their closest ASCII equivalent.
 *
 * If transliteration fails, an `E_USER_WARNING` is raised,
 * and the original `$string` returned.
 *
 * @param null|string|Stringable $string
 *
 * @return string
 */
function str_to_ascii(
    null|string|Stringable $string,
): string {
    if (! ( $string = (string) $string )) {
        return EMPTY_STRING;
    }

    $ascii = false;

    if (\function_exists('transliterator_transliterate')) {
        $ascii = \transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove',
            $string,
        );
    }

    if ($ascii === false && \function_exists('iconv')) {
        $ascii = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
    }

    if ($ascii !== false) {
        return $ascii;
    }

    $error = \error_get_last();

    if ($error !== null) {
        $error['ext-intl']  = \function_exists('transliterator_transliterate') ? 'Installed' : 'Not Installed';
        $error['ext-iconv'] = \function_exists('iconv') ? 'Installed' : 'Not Installed';

        foreach ($error as $key => $value) {
            $error[$key] = "{$key}: {$value}";
        }

        $error = "\n" . \implode(",\n", $error);
    }

    // TODO: Use DependencyException
    \trigger_error(
        "Error parsing string `{$string}` to ASCII-safe string." . $error,
        E_USER_WARNING,
    );

    return $string;
}

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
function buffer_str_align(
    null|string|Stringable $string,
    null|int $size = null,
    string $encoding = 'UTF-8',
    string $character = ' ',
    null|int &$length = null,
): string {
    if (! ( $string = (string) $string )) {
        return '';
    }

    if (! $character || \mb_strlen($character, $encoding) !== 1) {
        throw new InvalidArgumentException('Padding character must be exactly one character long');
    }

    $length = \mb_strlen($string, $encoding);

    // Set the buffer
    $buffer = $size ?? (int) \ini_get('output_buffering') ?: 4_096;

    // Ensure the buffer is within reasonable bounds
    \assert(
        num_within(
            $buffer,
            512,
            131_072,
        ),
        'Buffer size must be between 512 and 131072 bytes. It is currently ' . $buffer . ' bytes.',
    );

    if ($align = $length % $buffer) {
        $padding = $buffer - $align;

        // Guard against overflows
        if (( $length + $padding ) > PHP_INT_MAX) {
            throw new OverflowException('Resulting string would cause buffer overflow.');
        }

        $string .= \str_repeat($character, $padding);
    }

    $length = \mb_strlen($string, $encoding);

    return $string;
}
