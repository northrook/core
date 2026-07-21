<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function Northrook\Core\is_email;
use function Northrook\Core\is_empty;
use function Northrook\Core\is_path;
use function Northrook\Core\is_url;

final class ValueTest extends TestCase
{
    #[DataProvider('emptyProvider')]
    public function testIsEmpty(
        mixed $value,
        bool $expected,
    ): void {
        self::assertSame($expected, is_empty($value));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function emptyProvider(): iterable
    {
        yield 'null' => [null, true];
        yield 'empty string' => ['', true];
        yield 'empty array' => [[], true];
        yield 'zero int' => [0, false];
        yield 'zero float' => [0.0, false];
        yield 'false' => [false, false];
        yield 'true' => [true, false];
        yield 'string' => ['x', false];
        yield 'object' => [new stdClass(), false];
    }

    #[DataProvider('pathProvider')]
    public function testIsPath(
        mixed $value,
        bool $expected,
    ): void {
        self::assertSame($expected, is_path($value));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function pathProvider(): iterable
    {
        yield 'non-string' => [1, false];
        yield 'too short' => ['a', false];
        yield 'slash path' => ['var/cache', true];
        yield 'backslash path' => ['var\\cache', true];
        yield 'extension-like' => ['readme.md', true];
        yield 'hidden' => ['.env', true];
        yield 'illegal braces' => ['path/{id}', false];
        yield 'plain word' => ['cache', false];
    }

    #[DataProvider('urlProvider')]
    public function testIsUrl(
        mixed $value,
        bool $expected,
        null|string $protocol = null,
    ): void {
        self::assertSame($expected, is_url($value, $protocol));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool, 2?: null|string}>
     */
    public static function urlProvider(): iterable
    {
        yield 'non-string' => [1, false];
        yield 'empty' => ['', false];
        yield 'leading digit' => ['1http://example.com', false];
        yield 'valid' => ['https://example.com/path', true];
        yield 'required protocol match' => ['https://example.com', true, 'https'];
        yield 'required protocol miss' => ['http://example.com', false, 'https'];
        yield 'not a url' => ['example.com', false];
    }

    #[DataProvider('emailProvider')]
    public function testIsEmail(
        mixed $value,
        bool $expected,
        string ...$domains,
    ): void {
        self::assertSame($expected, is_email($value, ...$domains));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool, ...}>
     */
    public static function emailProvider(): iterable
    {
        yield 'non-string' => [1, false];
        yield 'empty' => ['', false];
        yield 'valid' => ['user@example.com', true];
        yield 'double at' => ['a@@b.com', false];
        yield 'double dot' => ['a@b..com', false];
        yield 'leading dot' => ['.a@b.com', false];
        yield 'ip host' => ['user@1.2.3.4', false];
        yield 'domain enforced' => ['a@example.com', true, 'example.com'];
        yield 'domain rejected' => ['a@other.com', false, 'example.com'];
    }
}
