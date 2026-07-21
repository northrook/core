<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Core\mb_str_ends_with;
use function Northrook\Core\mb_str_starts_with;
use function Northrook\Core\slug;
use function Northrook\Core\str_after;
use function Northrook\Core\str_before;
use function Northrook\Core\str_bisect;
use function Northrook\Core\str_contains_only;
use function Northrook\Core\str_end;
use function Northrook\Core\str_ends_with_any;
use function Northrook\Core\str_excludes;
use function Northrook\Core\str_extract;
use function Northrook\Core\str_first_pos;
use function Northrook\Core\str_includes_all;
use function Northrook\Core\str_includes_any;
use function Northrook\Core\str_last;
use function Northrook\Core\str_last_pos;
use function Northrook\Core\str_replace_each;
use function Northrook\Core\str_squish;
use function Northrook\Core\str_start;
use function Northrook\Core\str_starts_with_any;
use function Northrook\Core\str_to_ascii;

final class StringTest extends TestCase
{
    public function testStrSquish(): void
    {
        self::assertSame('a b c', str_squish("  a \t\n b   c  "));
        self::assertSame("a\tb\nc", str_squish("  a\tb\nc  ", whitespaceOnly: true));
    }

    public function testStrContainsOnly(): void
    {
        self::assertTrue(str_contains_only('abc', 'abcdef'));
        self::assertFalse(str_contains_only('abz', 'abc'));
        self::assertFalse(str_contains_only('', 'abc'));
    }

    public function testStrIncludesAllAndAny(): void
    {
        self::assertTrue(str_includes_all('abcdef', 'ace'));
        self::assertFalse(str_includes_all('abcdef', 'acz'));
        self::assertTrue(str_includes_any('abcdef', 'cx'));
        self::assertFalse(str_includes_any('abcdef', 'xyz'));
    }

    public function testStrExcludes(): void
    {
        self::assertTrue(str_excludes('abcdef', 'xyz'));
        self::assertFalse(str_excludes('abcdef', 'c'));
        self::assertTrue(str_excludes('', 'abc'));
    }

    public function testStrReplaceEach(): void
    {
        self::assertSame(
            'one-TWO',
            str_replace_each(['a' => 'one', 'b' => 'TWO'], 'a-b'),
        );
        self::assertSame(
            'Hi',
            str_replace_each(['hi' => 'Hi'], 'HI', caseSensitive: false),
        );
        self::assertSame('', str_replace_each(['a' => 'b'], ''));
    }

    public function testStrBisect(): void
    {
        $string = 'alpha-beta-gamma';

        self::assertSame('alpha', str_bisect($string, '-'));
        self::assertSame('-beta-gamma', $string);

        $string = 'alpha-beta';
        self::assertSame('alpha-', str_bisect($string, '-', includeNeedle: true));
        self::assertSame('beta', $string);

        $string = 'no-match';
        self::assertNull(str_bisect($string, '::', nullable: true));
        self::assertSame('no-match', $string);
    }

    public function testStrExtract(): void
    {
        self::assertSame('ell', str_extract('hello', 1, 4));
        self::assertSame('hXXo', str_extract('hello', 1, 4, 'XX'));
        self::assertSame('', str_extract(null, 0, 1));
    }

    public function testStrBeforeAndAfter(): void
    {
        self::assertSame('one', str_before('one::two::three', '::'));
        self::assertSame('one::two', str_before('one::two::three', '::', last: true));
        self::assertSame('one::', str_before('one::two', '::', includeNeedle: true));

        self::assertSame('two::three', str_after('one::two::three', '::'));
        self::assertSame('three', str_after('one::two::three', '::', last: true));
        self::assertSame('::two', str_after('one::two', '::', includeNeedle: true));
        self::assertSame('unchanged', str_after('unchanged', '::'));
    }

    public function testStrStartAndEnd(): void
    {
        self::assertSame('/path', str_start('path', '/'));
        self::assertSame('/path', str_start('/path', '/'));
        self::assertSame('file.txt', str_end('file', '.txt'));
        self::assertSame('file.txt', str_end('file.txt', '.txt'));
    }

    public function testMbStrStartsAndEndsWith(): void
    {
        self::assertTrue(mb_str_starts_with('Ångström', 'ång', caseSensitive: false));
        self::assertFalse(mb_str_starts_with('Ångström', 'ång', caseSensitive: true));
        self::assertTrue(mb_str_ends_with('café', 'FÉ', caseSensitive: false));
        self::assertTrue(mb_str_starts_with('abc', ''));
        self::assertTrue(mb_str_ends_with('abc', ''));
    }

    public function testStrStartsAndEndsWithAny(): void
    {
        self::assertTrue(str_starts_with_any('https://x', 'http://', 'https://'));
        self::assertFalse(str_starts_with_any('ftp://x', 'http://', 'https://'));
        self::assertTrue(str_ends_with_any('file.json', '.xml', '.json'));
        self::assertFalse(str_ends_with_any('', '.json'));
    }

    public function testStrFirstAndLastPos(): void
    {
        self::assertSame(0, str_first_pos('AbcAbc', 'abc'));
        self::assertFalse(str_first_pos('AbcAbc', 'abc', caseSensitive: true));
        self::assertSame(3, str_last_pos('AbcAbc', 'abc'));
    }

    public function testStrLast(): void
    {
        self::assertSame('tail', str_last('head::mid::tail', '::'));
        self::assertSame('head::mid', str_last('head::mid::tail', '::', before: true));
        self::assertFalse(str_last('head', '::'));
    }

    public function testStrToAscii(): void
    {
        self::assertSame('cafe', str_to_ascii('café'));
        self::assertSame('', str_to_ascii(null));
    }

    #[DataProvider('slugProvider')]
    public function testSlug(
        null|string $input,
        null|string $expected,
        string $separator = '-',
    ): void {
        self::assertSame($expected, slug($input, $separator));
    }

    /**
     * @return iterable<string, array{0: null|string, 1: null|string, 2?: string}>
     */
    public static function slugProvider(): iterable
    {
        yield 'null' => [null, null];
        yield 'blank' => ['   ', null];
        yield 'simple' => ['Hello World', 'hello-world'];
        yield 'accents' => ['Café au lait', 'cafe-au-lait'];
        yield 'custom separator' => ['Hello World', 'hello_world', '_'];
        yield 'punctuation collapsed' => ['foo---bar!!!baz', 'foo-bar-baz'];
    }
}
