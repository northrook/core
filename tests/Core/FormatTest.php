<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use DateTimeImmutable;
use Northrook\Core\DateTime\DateFormat;
use PHPUnit\Framework\TestCase;

use function Northrook\Core\date_format;
use function Northrook\Core\date_format_highlight;
use function Northrook\Core\hrtime_format;

final class FormatTest extends TestCase
{
    public function testHrtimeFormat(): void
    {
        self::assertSame('1.5000ms', hrtime_format(1_500_000.0));
        self::assertSame('1,5000ms', hrtime_format(1_500_000.0, decimal: ','));
        self::assertSame('2ms', hrtime_format(2_000_000.0, decimals: 0, append: 'ms'));
    }

    public function testDateFormatHighlightEscapesMarkupAroundFlags(): void
    {
        $highlighted = date_format_highlight('Y-m-d', null);

        self::assertStringContainsString('\\y\\e\\a\\r', $highlighted);
        self::assertStringContainsString('\\m\\o\\n\\t\\h', $highlighted);
        self::assertStringContainsString('\\d\\a\\y', $highlighted);
        self::assertStringContainsString('\\<', $highlighted);
        self::assertStringContainsString('\\>', $highlighted);
    }

    public function testDateFormatHighlightUsesClassPrefix(): void
    {
        $highlighted = date_format_highlight('Y', 'date');

        self::assertStringContainsString('\\d\\a\\t\\e\\-\\y\\e\\a\\r', $highlighted);
    }

    public function testDateFormatUsesEnumAndSegments(): void
    {
        $date = new DateTimeImmutable('2024-01-02 03:04:05', new \DateTimeZone('UTC'));

        self::assertSame('2024-01-02', date_format($date, DateFormat::DATE));
        self::assertSame(
            '<span class="year">2024</span>',
            date_format($date, 'Y', segment: true),
        );
    }
}
