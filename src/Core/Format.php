<?php

declare(strict_types=1);

namespace Northrook\Core;

use Northrook\Core;
use Northrook\Core\DateTime\DateFormat;
use Stringable;

final class Format
{
    /** @var array<string, int> */
    private const array ANSI_CODES = [
        'b'          => 1,
        'dim'        => 2,
        'i'          => 3,
        'u'          => 4,
        'blink'      => 5,
        'reverse'    => 7,
        'conceal'    => 8,
        'black'      => 30,
        'red'        => 31,
        'green'      => 32,
        'yellow'     => 33,
        'blue'       => 34,
        'magenta'    => 35,
        'cyan'       => 36,
        'white'      => 37,
        'bg-black'   => 40,
        'bg-red'     => 41,
        'bg-green'   => 42,
        'bg-yellow'  => 43,
        'bg-blue'    => 44,
        'bg-magenta' => 45,
        'bg-cyan'    => 46,
        'bg-white'   => 47,
    ];

    private function __construct() {}

    public static function date(
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
                ? Format::dateFormat(
                    $string,
                    $segment === true ? null : $segment,
                )
                : $string,
        );
    }

    /**
     * @param null|string|Stringable $string
     * @param string                 $separator
     * @param ?callable-string       $filter    {@see \strtolower} by default
     * @param string                 $language  [en]
     *
     * @return null|non-empty-string
     */
    public static function slug(
        null|string|Stringable $string,
        string $separator = '-',
        null|string $filter = 'strtolower',
        string $language = 'en',
    ): null|string {
        if (! ( $string = \trim((string) $string) )) {
            return null;
        }

        static $cache = [];

        if (\array_key_exists($string, $cache)) {
            return $cache[$string];
        }

        $parse  = \strtolower(Str::ascii($string));
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

        $slug = \is_callable($filter) ? (string) $filter($slug) : $slug;

        return $cache[$string] = $slug ?: null;
    }

    private static function dateFormat(
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

    /**
     * Formats a string with ANSI colour codes for terminal output based on embedded tags.
     *
     * ```
     * $string = Format::colorizeString(
     *  '<blue b>Hello</blue> <b>World</b>!'
     * );
     * echo $string; // Outputs: \033[34mHello\033[0m \033[1mWorld\033[0m!
     * ```
     */
    public static function colorizeString(
        string $string,
    ): string {
        if (\defined('STDOUT') && ! \stream_isatty(STDOUT)) {
            return \preg_replace('#</?[^>]+>#', '', $string) ?? $string;
        }

        // TODO: Look into using HTML AST for nested tags

        /**
         * @param string $resolve
         * @return array<int, string>
         */
        $modifiers = function(string $resolve): array {
            $split = $resolve !== ''
                ? \preg_split('/ +/', \trim($resolve))
                : false;

            return $split === false ? [] : $split;
        };

        return \preg_replace_callback(
            '#<([a-z]+)((?: +[a-z\-]+)*?)>(.*?)</\1>#si',
            static function(array $m) use ($modifiers): string {
                $tag       = $m[1];
                $text      = $m[3];
                $fragments = [];

                foreach ($modifiers($m[2]) as $modifier) {
                    if (isset(Format::ANSI_CODES[$modifier])) {
                        $fragments[] = Format::ANSI_CODES[$modifier];
                    }
                }
                if (isset(Format::ANSI_CODES[$tag])) {
                    $fragments[] = Format::ANSI_CODES[$tag];
                }

                if (empty($fragments)) {
                    return $text;
                }

                return "\033[" . \implode(';', $fragments) . 'm' . $text . "\033[0m";
            },
            $string,
        ) ?? $string;
    }
}
