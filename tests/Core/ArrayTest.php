<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Core\array_is_associative;

final class ArrayTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $array
     */
    #[DataProvider('associativeProvider')]
    public function testArrayIsAssociative(array $array, bool $expected): void
    {
        self::assertSame($expected, array_is_associative($array));
    }

    /**
     * Associative means every key is a string — not merely "not a list".
     *
     * @return iterable<string, array{0: array<array-key, mixed>, 1: bool}>
     */
    public static function associativeProvider(): iterable
    {
        yield 'empty' => [[], false];

        yield 'sequential list' => [['a', 'b'], false];
        yield 'zero-based int keys' => [[0 => 'a', 1 => 'b'], false];

        yield 'string keys' => [['name' => 'alpha', 'type' => 'beta'], true];
        yield 'single string key' => [['only' => 1], true];

        yield 'non-zero int keys only' => [[1 => 'a', 2 => 'b'], false];
        yield 'mixed string and int keys' => [[0 => 'a', 'key' => 'b'], false];
    }
}
