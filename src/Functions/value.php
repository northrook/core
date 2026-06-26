<?php

declare(strict_types=1);

namespace Northrook\Core;

use Stringable;

use function Northrook\Contracts\str_is_digit;

/**
 * Returns true when `$value` is considered empty.
 *
 * Treats `null`, empty strings, and empty arrays as empty. Numeric values and `false` are never empty.
 *
 * @param mixed                                                               $value
 *
 * @return bool
 * @phpstan-assert-if-true bool|non-empty-string|array<array-key,mixed>|object $value
 */
function is_empty(
    mixed $value,
): bool {
    // If it is a boolean, it cannot be empty
    if (\is_bool($value)) {
        return false;
    }

    if (\is_numeric($value)) {
        return false;
    }

    return empty($value);
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
function is_path(mixed $value, string $contains = '..', string $illegal = '{}'): bool
{
    // Bail early on non-stringable values
    if (! ( \is_string($value) || $value instanceof Stringable )) {
        return false;
    }

    // Stringify
    $string = \trim((string) $value);

    // Must be at least two characters long to be a path string
    if (! $string || \strlen($string) < 2) {
        return false;
    }

    if (! str_excludes($string, $illegal)) {
        return false;
    }

    // One or more slashes indicate this could be a path string
    if (\str_contains($string, '/') || \str_contains($string, '\\')) {
        return true;
    }

    // Any periods that aren't in the first 3 characters indicate this could be a `path/file.ext`
    if (\strrpos($string, '.') > 2) {
        return true;
    }

    // Indicates this could be a `.hidden` path
    if ($string[0] === '.' && \str_contains(\CHARSET_ALPHA, $string[1])) {
        return true;
    }

    return \str_contains($string, $contains);
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
function is_url(
    mixed $value,
    null|string $requiredProtocol = null,
): bool {
    // Bail early on non-stringable values
    if (! ( \is_string($value) || $value instanceof Stringable )) {
        return false;
    }

    // Cannot be null or an empty string
    if (! ( $string = (string) $value )) {
        return false;
    }

    // Must not start with a number
    if (\is_numeric($string[0])) {
        return false;
    }

    /**
     * Does the string resemble a URL-like structure?
     *
     * Ensures the string starts with a schema-like substring and has a real-ish domain extension.
     *
     * - Will gladly accept bogus strings like `not-a-schema://d0m@!n.tld/`
     */
    if (! \preg_match('#^([\w\-+]*?://)(\S.+)\.[a-z0-9]{2,}#m', $string)) {
        return false;
    }

    // Check for the required protocol if requested
    return ! ( $requiredProtocol && ! \str_starts_with($string, \rtrim($requiredProtocol, ':/') . '://') );
}

/**
 * Checks if a value resembles an email address.
 *
 * ⚠️ Does **NOT** perform full RFC validation!
 *
 * @param mixed  $value
 * @param string ...$enforceDomain when provided, the email must end with one of these domains
 *
 * @return bool
 */
function is_email(
    mixed $value,
    string ...$enforceDomain,
): bool {
    // Bail early on non-stringable values
    if (! ( \is_string($value) || $value instanceof Stringable )) {
        return false;
    }

    $string = (string) $value;

    // Cannot be an empty string
    if ($string === '') {
        return false;
    }

    // Must contain an [at] and at least one non-repeating period
    if (\substr_count($string, '@') !== 1 || \str_contains($string, '.') !== true || \str_contains($string, '..')) {
        return false;
    }

    // Emails are case-insensitive, lowercase the $value for easier processing
    $string = \strtolower($string);

    // Must not start with a period
    if ($string[0] === '.') {
        return false;
    }

    // Must end with a letter
    if (! \str_contains(\CHARSET_ALPHA, $string[-1])) {
        return false;
    }

    [$user, $server] = \explode('@', $string);

    if ($user === '' || ! \str_contains($server, '.')) {
        return false;
    }

    // Fail on IP addresses
    if (str_is_digit(\strtr($server, ['.' => '']))) {
        return false;
    }

    // Must only contain valid characters
    if (\preg_match('/[^' . URL_SAFE_CHARACTERS_UNICODE . ']/u', $string)) {
        return false;
    }

    if (empty($enforceDomain)) {
        return true;
    }

    // Validate domains, if specified
    return array_any(
        $enforceDomain,
        static fn($domain) => \str_ends_with(
            $string,
            \strtolower($domain),
        ),
    );
}
