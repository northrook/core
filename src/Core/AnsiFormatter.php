<?php

declare(strict_types=1);

namespace Northrook\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;

/**
 * Formats strings with ANSI SGR codes for terminal output from lightweight markup tags.
 *
 * Tag-shaped markup is consumed; non-tag `<` sequences (generics, comparisons) stay literal.
 * Pass dynamic content via variadic `%s` placeholders to keep it raw and unparsed.
 *
 * ```
 * $format = new AnsiFormatter();
 * echo $format->colorizeString('<blue b>Hello</blue> <b>World</b>!');
 * echo $format->colorizeString('<red>Error:</red> %s', $userMessage);
 * ```
 */
final class AnsiFormatter
{
    private const string RESET = "\033[0m";

    private const string LITERAL_GUARD_CHARSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

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
        'gray'       => 90,
        'bg-black'   => 40,
        'bg-red'     => 41,
        'bg-green'   => 42,
        'bg-yellow'  => 43,
        'bg-blue'    => 44,
        'bg-magenta' => 45,
        'bg-cyan'    => 46,
        'bg-white'   => 47,
    ];

    private readonly LoggerInterface $logger;

    private readonly string $tagPattern;

    /** @var array<string, true> */
    private array $unsupportedQueue = [];

    /**
     * @param bool                   $stderrOnUnsupported When true, queued unsupported markup is written to STDERR before returning
     * @param null|LoggerInterface   $logger              Unsupported markup is always logged when a logger is provided
     * @param null|bool              $assumeTty           When set, overrides automatic TTY detection on STDOUT
     */
    public function __construct(
        private readonly bool $stderrOnUnsupported = false,
        null|LoggerInterface $logger = null,
        private readonly null|bool $assumeTty = null,
    ) {
        $this->logger     = $logger ?? new NullLogger();
        $this->tagPattern = self::buildTagPattern();
    }

    /**
     * Formats a string with ANSI colour codes for terminal output based on embedded tags.
     *
     * Unsupported tags and attributes are stripped. When output is not a TTY, all tag-shaped
     * markup is removed without emitting ANSI codes.
     *
     * When variadic arguments are passed, `%s` inserts each argument as raw literal text
     * (never parsed or stripped) and `%%` emits a literal percent sign.
     */
    public function string(
        string $string,
        Stringable|string|int|float ...$args,
    ): string {
        $emitAnsi = $this->isTty();

        if ($emitAnsi) {
            $this->unsupportedQueue = [];
        }

        $argIndex = 0;
        /** @var list<Stringable|string|int|float> $argValues */
        $argValues = \array_values($args);

        try {
            return $this->parseSegment($string, 0, [], null, $emitAnsi, $argValues, $argIndex)[0];
        } finally {
            if ($emitAnsi && $this->stderrOnUnsupported) {
                $this->writeUnsupportedToStderr();
            }
        }
    }

    private function isTty(): bool
    {
        if ($this->assumeTty !== null) {
            return $this->assumeTty;
        }

        return \defined('STDOUT') && \stream_isatty(STDOUT);
    }

    /**
     * @param list<int>                                    $stack
     * @param list<Stringable|string|int|float>            $args
     *
     * @return array{0: string, 1: int}
     */
    private function parseSegment(
        string $string,
        int $offset,
        array $stack,
        null|string $untilCloseTag,
        bool $emitAnsi,
        array $args,
        int &$argIndex,
    ): array {
        $output = '';
        $length = \strlen($string);
        $scanPlaceholders = $args !== [];

        while ($offset < $length) {
            if ($untilCloseTag !== null && $this->tryConsumeSupportedClose($string, $offset, $untilCloseTag, $emitAnsi)) {
                return [$output, $offset];
            }

            $next = $this->findNextSpecial($string, $offset, $scanPlaceholders);

            if ($next === false) {
                return [$output . \substr($string, $offset), $length];
            }

            $output .= \substr($string, $offset, $next - $offset);
            $offset = $next;

            if ($scanPlaceholders && $this->tryConsumePlaceholder($string, $offset, $args, $argIndex, $output)) {
                continue;
            }

            if ($untilCloseTag !== null && $this->tryConsumeSupportedClose($string, $offset, $untilCloseTag, $emitAnsi)) {
                return [$output, $offset];
            }

            if ($this->isCloseTagAt($string, $offset)) {
                if ($untilCloseTag !== null) {
                    $this->tryWarnUnexpectedClose($string, $offset, $untilCloseTag, $emitAnsi);
                }

                if ($this->tryConsumeUnsupportedTag($string, $offset, $emitAnsi)) {
                    continue;
                }
            } elseif ($this->isOpenTagAt($string, $offset)) {
                if ($parsed = $this->tryParseSupportedElement($string, $offset, $stack, $emitAnsi, $args, $argIndex)) {
                    [$segment, $offset] = $parsed;
                    $output            .= $segment;

                    continue;
                }

                if ($this->tryConsumeUnsupportedTag($string, $offset, $emitAnsi)) {
                    continue;
                }
            }

            $output .= $string[$offset];
            ++$offset;
        }

        return [$output, $offset];
    }

    private function findNextSpecial(
        string $string,
        int $offset,
        bool $scanPlaceholders,
    ): false|int {
        $nextLt = \strpos($string, '<', $offset);

        if (! $scanPlaceholders) {
            return $nextLt;
        }

        $nextPct = \strpos($string, '%', $offset);

        if ($nextLt === false) {
            return $nextPct;
        }

        if ($nextPct === false) {
            return $nextLt;
        }

        return \min($nextLt, $nextPct);
    }

    /**
     * @param list<Stringable|string|int|float> $args
     */
    private function tryConsumePlaceholder(
        string $string,
        int &$offset,
        array $args,
        int &$argIndex,
        string &$output,
    ): bool {
        if ($string[$offset] !== '%') {
            return false;
        }

        $next = $string[$offset + 1] ?? '';

        if ($next === '%') {
            $output .= '%';
            $offset += 2;

            return true;
        }

        if ($next === 's' && $argIndex < \count($args)) {
            $output .= (string) $args[$argIndex];
            ++$argIndex;
            $offset += 2;

            return true;
        }

        return false;
    }

    private function isOpenTagAt(
        string $string,
        int $offset,
    ): bool {
        if ($string[$offset] !== '<' || ( isset($string[$offset + 1]) && $string[$offset + 1] === '/' )) {
            return false;
        }

        return $offset === 0 || ! \str_contains(self::LITERAL_GUARD_CHARSET, $string[$offset - 1]);
    }

    private function isCloseTagAt(
        string $string,
        int $offset,
    ): bool {
        return isset($string[$offset + 1]) && $string[$offset] === '<' && $string[$offset + 1] === '/';
    }

    /**
     * @param list<int>                         $stack
     * @param list<Stringable|string|int|float> $args
     *
     * @return null|array{0: string, 1: int}
     */
    private function tryParseSupportedElement(
        string $string,
        int &$offset,
        array $stack,
        bool $emitAnsi,
        array $args,
        int &$argIndex,
    ): null|array {
        $tail = \substr($string, $offset);

        if (! \preg_match(
            '#^<(' . $this->tagPattern . ')(\s+[^>]*)?>#i',
            $tail,
            $matches,
        )) {
            return null;
        }

        $tag          = \strtolower($matches[1]);
        $elementCodes = $this->resolveCodes($tag, $matches[2] ?? '', $emitAnsi);
        $newStack     = $this->mergeStack($stack, $elementCodes);
        $offset      += \strlen($matches[0]);

        [$content, $offset] = $this->parseSegment($string, $offset, $newStack, $tag, $emitAnsi, $args, $argIndex);

        return [
            $this->transition($stack, $newStack, $emitAnsi) . $content . $this->transition($newStack, $stack, $emitAnsi),
            $offset,
        ];
    }

    private function tryConsumeSupportedClose(
        string $string,
        int &$offset,
        string $tag,
        bool $emitAnsi,
    ): bool {
        $tail = \substr($string, $offset);

        if (! \preg_match(
            '#^</(' . $this->tagPattern . ')>#i',
            $tail,
            $matches,
        )) {
            return false;
        }

        $found = \strtolower($matches[1]);

        if ($found !== $tag) {
            $this->recordUnsupported("Mismatched close tag: </{$found}> does not match <{$tag}>", $emitAnsi);

            return false;
        }

        $offset += \strlen($matches[0]);

        return true;
    }

    private function tryWarnUnexpectedClose(
        string $string,
        int $offset,
        string $expectedTag,
        bool $emitAnsi,
    ): void {
        $tail = \substr($string, $offset);

        if (! \preg_match(
            '#^</([a-z][a-z0-9-]*)>#i',
            $tail,
            $matches,
        )) {
            return;
        }

        $found = \strtolower($matches[1]);

        if (isset(self::ANSI_CODES[$found])) {
            return;
        }

        $this->recordUnsupported("Unexpected close tag: </{$found}> while open <{$expectedTag}>", $emitAnsi);
    }

    private function tryConsumeUnsupportedTag(
        string $string,
        int &$offset,
        bool $emitAnsi,
    ): bool {
        $tail = \substr($string, $offset);

        if (! \preg_match(
            '#^</?([a-z][a-z0-9-]*)(\s+[^>]*)?>#i',
            $tail,
            $matches,
        )) {
            return false;
        }

        $tag         = \strtolower($matches[1]);
        $isClose     = $tail[1] === '/';
        $attrString  = isset($matches[2]) ? \trim($matches[2]) : '';
        $consumedLen = \strlen($matches[0]);

        if (isset(self::ANSI_CODES[$tag])) {
            if (! $isClose && $attrString !== '') {
                foreach (\preg_split('/\s+/', $attrString, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $attribute) {
                    $name = \strtolower($attribute);

                    if (! isset(self::ANSI_CODES[$name])) {
                        $this->recordUnsupported("Unsupported format attribute: {$name} on <{$tag}>", $emitAnsi);
                    }
                }
            }

            $offset += $consumedLen;

            return true;
        }

        if ($isClose) {
            $offset += $consumedLen;

            return true;
        }

        $this->recordUnsupported("Unsupported format tag: <{$tag}>", $emitAnsi);

        if ($attrString !== '') {
            foreach (\preg_split('/\s+/', $attrString, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $attribute) {
                $name = \strtolower($attribute);

                if (! isset(self::ANSI_CODES[$name])) {
                    $this->recordUnsupported("Unsupported format attribute: {$name} on <{$tag}>", $emitAnsi);
                }
            }
        }

        $offset += $consumedLen;

        return true;
    }

    /**
     * @return list<int>
     */
    private function resolveCodes(
        string $tag,
        string $attributeString,
        bool $emitAnsi,
    ): array {
        $codes = [self::ANSI_CODES[$tag]];

        $attributeString = \trim($attributeString);

        if ($attributeString === '') {
            return $codes;
        }

        foreach (\preg_split('/\s+/', $attributeString, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $attribute) {
            $name = \strtolower($attribute);

            if (isset(self::ANSI_CODES[$name])) {
                $codes[] = self::ANSI_CODES[$name];

                continue;
            }

            $this->recordUnsupported("Unsupported format attribute: {$name} on <{$tag}>", $emitAnsi);
        }

        return $codes;
    }

    /**
     * @param list<int> $stack
     * @param list<int> $codes
     *
     * @return list<int>
     */
    private function mergeStack(
        array $stack,
        array $codes,
    ): array {
        if ($codes === []) {
            return $stack;
        }

        return \array_values(\array_unique([...$stack, ...$codes]));
    }

    /**
     * @param list<int> $from
     * @param list<int> $to
     */
    private function transition(
        array $from,
        array $to,
        bool $emitAnsi,
    ): string {
        if (! $emitAnsi || $from === $to) {
            return '';
        }

        if ($to === []) {
            return self::RESET;
        }

        if ($from === []) {
            return "\033[" . \implode(';', $to) . 'm';
        }

        return self::RESET . "\033[" . \implode(';', $to) . 'm';
    }

    private static function buildTagPattern(): string
    {
        $tags = \array_keys(self::ANSI_CODES);

        \usort(
            $tags,
            static fn(string $left, string $right): int => \strlen($right) <=> \strlen($left),
        );

        return \implode(
            '|',
            \array_map(static fn(string $tag): string => \preg_quote($tag, '#'), $tags),
        );
    }

    /**
     * @phpstan-impure
     */
    private function recordUnsupported(
        string $message,
        bool $emitAnsi,
    ): void {
        if (! $emitAnsi) {
            return;
        }

        $this->logger->warning($message);

        if ($this->stderrOnUnsupported) {
            $this->unsupportedQueue[$message] = true;
        }
    }

    private function writeUnsupportedToStderr(): void
    {
        if ($this->unsupportedQueue === [] || ! \defined('STDERR')) {
            return;
        }

        \fwrite(STDERR, \implode("\n", \array_keys($this->unsupportedQueue)) . "\n");
    }
}
