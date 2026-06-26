<?php

declare(strict_types=1);

namespace Northrook\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Formats strings with ANSI SGR codes for terminal output from lightweight markup tags.
 *
 * Only recognised tag and attribute names are parsed; all other `<` sequences are left literal.
 *
 * ```
 * $format = new AnsiFormatter();
 * echo $format->colorizeString('<blue b>Hello</blue> <b>World</b>!');
 * ```
 */
final class AnsiFormatter
{
    private const string RESET = "\033[0m";

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
     * Unsupported tags and attributes are stripped. When output is not a TTY, supported tags are removed.
     */
    public function colorizeString(
        string $string,
    ): string {
        if (! $this->isTty()) {
            return $this->stripTags($string);
        }

        $this->unsupportedQueue = [];

        try {
            return $this->parseSegment($string, 0, [])[0];
        } finally {
            if ($this->stderrOnUnsupported) {
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

    private function stripTags(
        string $string,
    ): string {
        $stripped = \preg_replace(
            '#</?(?:' . $this->tagPattern . ')(?:\s[^>]*)?>#',
            '',
            $string,
        );

        return $stripped ?? $string;
    }

    /**
     * @param list<int> $stack
     *
     * @return array{0: string, 1: int}
     */
    private function parseSegment(
        string $string,
        int $offset,
        array $stack,
        null|string $untilCloseTag = null,
    ): array {
        $output = '';
        $length = \strlen($string);

        while ($offset < $length) {
            if ($untilCloseTag !== null && $this->tryConsumeSupportedClose($string, $offset, $untilCloseTag)) {
                return [$output, $offset];
            }

            $next = \strpos($string, '<', $offset);

            if ($next === false) {
                return [$output . \substr($string, $offset), $length];
            }

            $output .= \substr($string, $offset, $next - $offset);
            $offset = $next;

            if ($untilCloseTag !== null && $this->tryConsumeSupportedClose($string, $offset, $untilCloseTag)) {
                return [$output, $offset];
            }

            if ($this->isCloseTagAt($string, $offset)) {
                if ($parsed = $this->tryParseSupportedElement($string, $offset, $stack)) {
                    [$segment, $offset] = $parsed;
                    $output            .= $segment;

                    continue;
                }

                if ($this->tryConsumeUnsupportedTag($string, $offset)) {
                    continue;
                }
            } elseif ($this->isOpenTagAt($string, $offset)) {
                if ($parsed = $this->tryParseSupportedElement($string, $offset, $stack)) {
                    [$segment, $offset] = $parsed;
                    $output            .= $segment;

                    continue;
                }

                if ($this->tryConsumeUnsupportedTag($string, $offset)) {
                    continue;
                }
            }

            $output .= '<';
            ++$offset;
        }

        return [$output, $offset];
    }

    private function isOpenTagAt(
        string $string,
        int $offset,
    ): bool {
        if ($string[$offset] !== '<' || ( isset($string[$offset + 1]) && $string[$offset + 1] === '/' )) {
            return false;
        }

        return $offset === 0 || ! \str_contains(\CHARSET_ALNUM, $string[$offset - 1]);
    }

    private function isCloseTagAt(
        string $string,
        int $offset,
    ): bool {
        return isset($string[$offset + 1]) && $string[$offset] === '<' && $string[$offset + 1] === '/';
    }

    /**
     * @param list<int> $stack
     *
     * @return null|array{0: string, 1: int}
     */
    private function tryParseSupportedElement(
        string $string,
        int &$offset,
        array $stack,
    ): null|array {
        $tail = \substr($string, $offset);

        if (! \preg_match(
            '#^<(' . $this->tagPattern . ')(\s+[^>]*)?>#',
            $tail,
            $matches,
        )) {
            return null;
        }

        $tag          = \strtolower($matches[1]);
        $elementCodes = $this->resolveCodes($tag, $matches[2] ?? '');
        $newStack     = $this->mergeStack($stack, $elementCodes);
        $offset      += \strlen($matches[0]);

        [$content, $offset] = $this->parseSegment($string, $offset, $newStack, $tag);

        return [
            $this->transition($stack, $newStack) . $content . $this->transition($newStack, $stack),
            $offset,
        ];
    }

    private function tryConsumeSupportedClose(
        string $string,
        int &$offset,
        string $tag,
    ): bool {
        $tail = \substr($string, $offset);

        if (! \preg_match(
            '#^</(' . $this->tagPattern . ')>#',
            $tail,
            $matches,
        )) {
            return false;
        }

        if (\strtolower($matches[1]) !== $tag) {
            return false;
        }

        $offset += \strlen($matches[0]);

        return true;
    }

    private function tryConsumeUnsupportedTag(
        string $string,
        int &$offset,
    ): bool {
        $tail = \substr($string, $offset);

        if (! \preg_match(
            '#^</?([a-z][a-z0-9-]*)(\s+[^>]*)?>#',
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
                        $this->recordUnsupported("Unsupported format attribute: {$name} on <{$tag}>");
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

        $this->recordUnsupported("Unsupported format tag: <{$tag}>");

        if ($attrString !== '') {
            foreach (\preg_split('/\s+/', $attrString, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $attribute) {
                $name = \strtolower($attribute);

                if (! isset(self::ANSI_CODES[$name])) {
                    $this->recordUnsupported("Unsupported format attribute: {$name} on <{$tag}>");
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

            $this->recordUnsupported("Unsupported format attribute: {$name} on <{$tag}>");
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
    ): string {
        if ($from === $to) {
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
    ): void {
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
