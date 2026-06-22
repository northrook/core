<?php

declare(strict_types=1);

namespace Northrook\Core;

use JetBrains\PhpStorm\Language;
use Northrook\Core;
use Northrook\Exceptions\RegexpException;
use Stringable;
use Throwable;

const URL_SAFE_CHARACTERS_UNICODE = "\w.,_~:;@!$&*?#=%()+\-\[\]\'\/";
const URL_SAFE_CHARACTERS         = "A-Za-z0-9.,_~:;@!$&*?#=%()+\-\[\]\'\/";

require_once __DIR__ . '/Functions/array.php';
require_once __DIR__ . '/Functions/filesystem.php';
require_once __DIR__ . '/Functions/format.php';
require_once __DIR__ . '/Functions/hashes.php';
require_once __DIR__ . '/Functions/normalize.php';
require_once __DIR__ . '/Functions/number.php';
require_once __DIR__ . '/Functions/string.php';
require_once __DIR__ . '/Functions/value.php';

/**
 * @template Value
 *
 * @param callable(): Value     $callback
 * @param Value                 $fallback
 *
 * @return Value
 */
function get(
    callable $callback,
    mixed $fallback,
): mixed {
    try {
        return $callback();
    } catch (Throwable $exception) {
        Core::log()->error(
            $exception->getMessage(),
            ['exception' => $exception],
        );
    }
    return $fallback;
}

/**
 * @param null|string|Stringable $string
 * @param string                 $separator
 * @param ?callable-string       $filter    {@see \strtolower} by default
 * @param string                 $language  [en]
 *
 * @return null|non-empty-string
 */
function slug(
    null|string|Stringable $string,
    string $separator = '-',
    null|string $filter = 'strtolower',
    string $language = 'en',
): null|string {
    if (! ( $string = \trim((string) $string) )) {
        return null;
    }

    static $cache = [];

    $cacheKey = $string . "\0" . $separator . "\0" . ( $filter ?? '' );

    if (\array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    // TODO: `$language` reserved for locale-aware transliteration via str_to_ascii
    $parse  = \strtolower(str_to_ascii($string));
    $length = \strlen($parse);

    $slug      = '';
    $separated = true;

    for ($i = 0; $i < $length; $i++) {
        $c = $parse[$i];
        // If the $character is [a-z0-9], add
        if ($c >= 'a' && $c <= 'z' || $c >= '0' && $c <= '9') {
            $slug      .= $c;
            $separated = false;
        }
        // Add separator as needed
        elseif (! $separated) {
            $slug      .= $separator;
            $separated = true;
        }
    }

    $slug = \rtrim($slug, $separator);

    $slug = \is_callable($filter)
        ? (string) $filter($slug)
        : $slug;

    return $cache[$cacheKey] = $slug ?: null;
}

/**
 * @param string $pattern
 * @param string $subject
 * @param int    $offset
 *
 * @return string[][]
 */
function regex_match_all(
    #[Language('PhpRegExp')]
    string $pattern,
    string $subject,
    int $offset = 0,
): array {
    \preg_match_all(
        $pattern,
        $subject,
        $matches,
        PREG_SET_ORDER,
        $offset,
    );
    RegexpException::check();
    return $matches;
}
