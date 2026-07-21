<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Northrook\Core\DateTime;
use Northrook\Core\DateTime\DateFormat;
use Northrook\Core\DateTime\TimeZone;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
    public function testConstructFromStringAndFormat(): void
    {
        $date = new DateTime('2024-01-02 03:04:05', TimeZone::UTC);

        self::assertSame('2024-01-02', $date->format(DateFormat::DATE));
        self::assertSame(1_704_164_645, $date->timestamp);
    }

    public function testConstructFromTimestamp(): void
    {
        $date = new DateTime(1_704_164_645, TimeZone::UTC);

        self::assertSame('2024-01-02T03:04:05+00:00', (string) $date);
    }

    public function testConstructFromDateTimeInterfacePreservesTimestamp(): void
    {
        $source = new DateTimeImmutable('2024-06-01 12:00:00', new DateTimeZone('Europe/Oslo'));
        $date   = new DateTime($source);

        self::assertSame($source->getTimestamp(), $date->timestamp);
    }

    public function testGetImmutableRejectsInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateTime::getImmutable('not a date', TimeZone::UTC);
    }

    public function testDiff(): void
    {
        $a = new DateTime('2024-01-01', TimeZone::UTC);
        $b = new DateTimeImmutable('2024-01-03', new DateTimeZone('UTC'));

        self::assertSame(2, (int) $a->diff($b)->format('%a'));
    }
}
