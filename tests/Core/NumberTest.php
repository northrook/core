<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Core\num_byte_size;
use function Northrook\Core\num_clamp;
use function Northrook\Core\num_closest;
use function Northrook\Core\num_gcd;
use function Northrook\Core\num_percent;
use function Northrook\Core\num_within;
use function Northrook\Core\num_xor;

final class NumberTest extends TestCase
{
    #[DataProvider('percentProvider')]
    public function testNumPercent(
        float $from,
        float $to,
        float $expected,
    ): void {
        self::assertSame($expected, num_percent($from, $to));
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: float}>
     */
    public static function percentProvider(): iterable
    {
        yield 'zero from' => [0.0, 10.0, 0.0];
        yield 'equal' => [10.0, 10.0, 0.0];
        yield 'decrease' => [100.0, 80.0, 20.0];
        yield 'increase' => [50.0, 75.0, -50.0];
    }

    #[DataProvider('byteSizeProvider')]
    public function testNumByteSize(
        string|int|float $bytes,
        string $expected,
    ): void {
        self::assertSame($expected, num_byte_size($bytes));
    }

    /**
     * @return iterable<string, array{0: string|int|float, 1: string}>
     */
    public static function byteSizeProvider(): iterable
    {
        yield 'zero' => [0, '0B'];
        yield 'bytes' => [512, '512B'];
        yield 'kib' => [1_024, '1KiB'];
        yield 'string length' => ['abcd', '4B'];
    }

    public function testNumXor(): void
    {
        $seed = 0;
        self::assertSame(3, num_xor([1, 2], $seed));
        self::assertSame(3, $seed);
        self::assertSame(0, num_xor([3], $seed));
    }

    public function testNumGcd(): void
    {
        self::assertSame(6, num_gcd(54, 24));
        self::assertSame(1, num_gcd(17, 13));
    }

    public function testNumWithinAndClamp(): void
    {
        self::assertTrue(num_within(5, 1, 10));
        self::assertTrue(num_within(1, 1, 10));
        self::assertFalse(num_within(0, 1, 10));

        self::assertSame(1, num_clamp(0, 1, 10));
        self::assertSame(10, num_clamp(20, 1, 10));
        self::assertSame(5, num_clamp(5, 1, 10));
    }

    public function testNumClosest(): void
    {
        self::assertNull(num_closest(5, []));
        self::assertSame(10, num_closest(9, [1, 10, 20]));
        self::assertSame(1, num_closest(9, [1, 10, 20], returnKey: true));
    }
}
