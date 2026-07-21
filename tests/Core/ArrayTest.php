<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function Northrook\Core\arr_closest;
use function Northrook\Core\arr_flatten;
use function Northrook\Core\arr_merge;
use function Northrook\Core\arr_replace_key;
use function Northrook\Core\arr_search;
use function Northrook\Core\array_filter_recursive;
use function Northrook\Core\array_has_keys;
use function Northrook\Core\array_is_associative;
use function Northrook\Core\array_is_multidimensional;

final class ArrayTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $array
     */
    #[DataProvider('associativeProvider')]
    public function testArrayIsAssociative(
        array $array,
        bool $expected,
    ): void {
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

    /**
     * @param array<array-key, mixed> ...$arrays
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('mergeProvider')]
    public function testArrMerge(
        array $expected,
        array ...$arrays,
    ): void {
        self::assertSame($expected, arr_merge(...$arrays));
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>, ...}>
     */
    public static function mergeProvider(): iterable
    {
        yield 'no arguments' => [[]];
        yield 'single array' => [['a' => 1], ['a' => 1]];

        yield 'nested keys merge' => [
            ['a' => ['b' => 1, 'c' => 2], 'd' => 3],
            ['a' => ['b' => 1], 'd' => 3],
            ['a' => ['c' => 2]],
        ];

        yield 'later scalar overrides' => [
            ['a' => 2, 'b' => 1],
            ['a' => 1, 'b' => 1],
            ['a' => 2],
        ];

        yield 'list indices overwrite' => [
            ['x', 'z'],
            ['x', 'y'],
            [1 => 'z'],
        ];

        yield 'array replaces scalar' => [
            ['a' => ['b' => 1]],
            ['a' => 1],
            ['a' => ['b' => 1]],
        ];

        yield 'scalar replaces array' => [
            ['a' => 9],
            ['a' => ['b' => 1]],
            ['a' => 9],
        ];

        yield 'three-way overlay' => [
            ['a' => 3, 'c' => 1, 'b' => 2],
            ['a' => 1, 'c' => 1],
            ['a' => 2, 'b' => 2],
            ['a' => 3],
        ];
    }

    public function testArrMergeLaterObjectOverrides(): void
    {
        $first  = new stdClass();
        $second = new stdClass();

        self::assertSame(
            ['obj' => $second],
            arr_merge(['obj' => $first], ['obj' => $second]),
        );
    }

    #[DataProvider('multidimensionalProvider')]
    public function testArrayIsMultidimensional(
        mixed $value,
        bool $expected,
    ): void {
        self::assertSame($expected, array_is_multidimensional($value));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function multidimensionalProvider(): iterable
    {
        yield 'scalar' => [1, false];
        yield 'flat list' => [[1, 2], false];
        yield 'nested list' => [[1, [2]], true];
        yield 'nested assoc' => [['a' => ['b' => 1]], true];
    }

    public function testArrayHasKeys(): void
    {
        $array = ['a' => 1, 'b' => 2, 0 => 'z'];

        self::assertTrue(array_has_keys($array, 'a', 'b'));
        self::assertTrue(array_has_keys($array, 0));
        self::assertFalse(array_has_keys($array, 'a', 'missing'));
    }

    public function testArrayFilterRecursiveDefaultDropsEmptyKeepsZeroAndFalse(): void
    {
        self::assertSame(
            ['keep' => 0, 'flag' => false, 'nested' => ['ok' => 'x']],
            array_filter_recursive([
                'keep'   => 0,
                'flag'   => false,
                'drop'   => null,
                'empty'  => '',
                'nested' => ['ok' => 'x', 'gone' => null],
            ]),
        );
    }

    public function testArrFlatten(): void
    {
        $nested = ['a' => [1, 'b' => [2, 3]], 'c' => 4];

        self::assertSame([1, 2, 3, 4], arr_flatten($nested));
        self::assertSame(
            [0 => 2, 1 => 3, 'c' => 4],
            arr_flatten($nested, preserveKeys: true),
        );
        self::assertSame(
            [0 => 1, 2 => 2],
            arr_flatten(['a' => [1, null, 2, '']], filter: true),
        );
    }

    public function testArrReplaceKeyPreservesOrder(): void
    {
        self::assertSame(
            ['x' => 1, 'renamed' => 2, 'z' => 3],
            arr_replace_key(['x' => 1, 'y' => 2, 'z' => 3], 'y', 'renamed'),
        );
        self::assertSame(
            ['x' => 1],
            arr_replace_key(['x' => 1], 'missing', 'y'),
        );
    }

    public function testArrSearch(): void
    {
        $array = [
            'outer' => [
                'inner' => 'needle',
            ],
            'other' => 2,
        ];

        self::assertSame('other', arr_search($array, 2));
        self::assertSame('outer', arr_search($array, 'needle'));
        self::assertSame(
            'outer',
            arr_search($array, static fn($value): bool => $value === 'needle'),
        );
        self::assertNull(arr_search($array, 'absent'));
    }

    public function testArrClosest(): void
    {
        self::assertNull(arr_closest(5, []));
        self::assertSame(10.0, arr_closest(9, [1, 10, 20]));
        self::assertSame(1.5, arr_closest('2', [1.5, 8]));
    }

    public function testArrClosestRejectsNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        arr_closest(1, [1, 'x']);
    }
}
