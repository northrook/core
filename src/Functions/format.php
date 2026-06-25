<?php

declare(strict_types=1);

namespace Northrook\Core;

use Northrook\Core;
use Northrook\Core\DateTime\DateFormat;

/**
 * Format a high-resolution timestamp as a human-readable millisecond string.
 *
 * @param float             $hrtime
 * @param int               $decimals
 * @param non-empty-string  $decimal
 * @param string            $thousands
 * @param string            $append
 * @param string            $pad
 *
 * @return string
 */
function hrtime_format(
    float $hrtime,
    int $decimals = 4,
    string $decimal = '.',
    string $thousands = ',',
    string $append = 'ms',
    string $pad = '0',
): string {
    $milliseconds = $hrtime / 1_000_000;
    $formatted    = \number_format($milliseconds, $decimals, $decimal, $thousands);

    // Pad the fractional portion to `$decimals` digits (validate: intended display width for sub-ms precision).
    if ($decimals > 0 && \str_contains($formatted, $decimal)) {
        [$whole, $fraction] = \explode($decimal, $formatted, 2);
        $fraction           = \str_pad($fraction, $decimals, $pad, STR_PAD_RIGHT);
        $formatted          = $whole . $decimal . $fraction;
    }

    return $formatted . $append;
}

/**
 * Format a date using a Core default, raw format string, or {@see DateFormat} enum.
 *
 * When `$segment` is enabled, date parts are wrapped in HTML spans via {@see date_format_highlight()}.
 */
function date_format(
    \DateTimeInterface $date,
    null|string|DateFormat $format = null,
    bool|string $segment = false,
): string {
    $string = match (true) {
        $format === null => Core::get()->dateFormat->value,
        is_string($format) => $format,
        default            => $format->value,
    };

    return $date->format(
        $segment
            ? date_format_highlight(
                $string,
                $segment === true ? null : $segment,
            )
            : $string,
    );
}

/**
 * Wrap day, month, year, weekday, and time segments of a date format string in HTML spans.
 *
 * @param string      $string      PHP date format string
 * @param null|string $classPrefix optional prefix for generated CSS classes
 */
function date_format_highlight(
    string $string,
    null|string $classPrefix,
): string {
    $each = [];

    $escape = static fn(string $string): string => \implode(
        '',
        \array_map(
            static fn($char) => '\\' . $char,
            \str_split($string),
        ),
    );

    $string = (string) \preg_replace_callback_array(
        [
            // Day
            '#[dD]#' => static function($match) use (&$each) {
                $each[] = [
                    'type' => 'day',
                    'flag' => $match[0],
                ];
                return '[' . ( \count($each) - 1 ) . ']';
            },
            // Month
            '#[mM]#' => static function($match) use (&$each) {
                $each[] = [
                    'type' => 'month',
                    'flag' => $match[0],
                ];
                return '[' . ( \count($each) - 1 ) . ']';
            },
            // Year
            '#[yY]#' => static function($match) use (&$each) {
                $each[] = [
                    'type' => 'year',
                    'flag' => $match[0],
                ];
                return '[' . ( \count($each) - 1 ) . ']';
            },
            // Day
            '#[jS]#' => static function($match) use (&$each) {
                $each[] = [
                    'type' => 'day',
                    'flag' => $match[0],
                ];
                return '[' . ( \count($each) - 1 ) . ']';
            },
            // Weekday
            '#W#' => static function($match) use (&$each) {
                $each[] = [
                    'type' => 'weekday',
                    'flag' => $match[0],
                ];
                return '[' . ( \count($each) - 1 ) . ']';
            },
            // Time
            '#[aABgGhHisu].*#' => static function($match) use (&$each) {
                $each[] = [
                    'type' => 'time',
                    'flag' => $match[0],
                ];
                return '[' . ( \count($each) - 1 ) . ']';
            },
        ],
        $string,
    );

    foreach ($each as $key => $value) {
        $class  = empty($classPrefix) ? $value['type'] : $classPrefix . '-' . $value['type'];
        $flag   = $value['flag'];
        $string = \str_replace(
            "[{$key}]",
            $escape('<span class="' . $class . '">') . $flag . $escape('</span>'),
            $string,
        );
    }

    return $string;
}
