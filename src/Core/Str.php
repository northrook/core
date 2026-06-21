<?php

namespace Northrook\Core;

use InvalidArgumentException;
use OverflowException;
use Stringable;

final class Str
{
    private function __construct() {}

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
    public static function encode(
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
    public static function squish(
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
    public static function ascii(
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
    public static function bufferAlign(
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
            Num::within(
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
}
