<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Core\AnsiFormatter;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class AnsiFormatterTest extends TestCase
{
    public function testStripTagsWhenNotTty(): void
    {
        $format = new AnsiFormatter(assumeTty: false);

        self::assertSame(
            'Hello World!',
            $format->colorizeString('<blue b>Hello</blue> <b>World</b>!'),
        );
    }

    public function testLogsUnsupportedMarkup(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $format = new AnsiFormatter(logger: $logger, assumeTty: true);
        $format->colorizeString('<foo bar>text</foo>');

        self::assertContains('Unsupported format tag: <foo>', $logger->messages);
        self::assertContains('Unsupported format attribute: bar on <foo>', $logger->messages);
    }

    public function testNestedTags(): void
    {
        $format = new AnsiFormatter(assumeTty: true);
        $result = $format->colorizeString('<blue><b>hi</b> there</blue>');

        self::assertSame(
            "\033[34m\033[0m\033[34;1mhi\033[0m\033[34m there\033[0m",
            $result,
        );
    }

    public function testFlatTags(): void
    {
        $format = new AnsiFormatter(assumeTty: true);
        $result = $format->colorizeString('<blue b>Hello</blue> <b>World</b>!');

        self::assertSame(
            "\033[34;1mHello\033[0m \033[1mWorld\033[0m!",
            $result,
        );
    }

    public function testUnsupportedTagIsStrippedButContentRemains(): void
    {
        $format = new AnsiFormatter(assumeTty: true);

        self::assertSame(
            "\033[34mtext\033[0m",
            $format->colorizeString('<blue><foo>text</foo></blue>'),
        );
    }

    public function testDuplicateColorCodesCollapseInStack(): void
    {
        $format = new AnsiFormatter(assumeTty: true);

        self::assertSame(
            "\033[34mnested same\033[0m",
            $format->colorizeString('<blue><blue>nested same</blue></blue>'),
        );
    }

    public function testCombinesForegroundAndBackground(): void
    {
        $format = new AnsiFormatter(assumeTty: true);

        self::assertSame(
            "\033[31;44mtest\033[0m",
            $format->colorizeString('<red bg-blue>test</red>'),
        );
    }

    public function testWritesUnsupportedMessagesToStderr(): void
    {
        $script = <<<'PHP'
            require %s;
            $format = new Northrook\Core\AnsiFormatter(stderrOnUnsupported: true, assumeTty: true);
            $format->colorizeString('<foo bar>text</foo>');
            PHP;

        $script = \sprintf($script, \var_export(__DIR__ . '/../../vendor/autoload.php', true));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open(['php', '-r', $script], $descriptors, $pipes);
        self::assertIsResource($process);

        \fclose($pipes[0]);

        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        self::assertStringContainsString('Unsupported format tag: <foo>', $stderr);
        self::assertStringContainsString('Unsupported format attribute: bar on <foo>', $stderr);
    }

    public function testLeavesUnrecognisedAngleBracketsLiteral(): void
    {
        $format = new AnsiFormatter(assumeTty: true);

        self::assertSame(
            'array<int>',
            $format->colorizeString('array<int>'),
        );
        self::assertSame(
            'Generic<Foo>',
            $format->colorizeString('Generic<Foo>'),
        );
        self::assertSame(
            '2 < 3',
            $format->colorizeString('2 < 3'),
        );
    }

    public function testStripTagsOnlyRemovesSupportedMarkupWhenNotTty(): void
    {
        $format = new AnsiFormatter(assumeTty: false);

        self::assertSame(
            'array<int> hello',
            $format->colorizeString('array<int> <blue>hello</blue>'),
        );
    }
}
