<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Core\normalize_newline;
use function Northrook\Core\normalize_path;
use function Northrook\Core\normalize_slashes;
use function Northrook\Core\normalize_url;
use function Northrook\Core\normalize_whitespace;

final class NormalizeTest extends TestCase
{
    public function testNormalizeWhitespaceAndNewlineAndSlashes(): void
    {
        self::assertSame('a b c', normalize_whitespace(" a \t\n b   c "));
        self::assertSame("a\nb\nc", normalize_newline("a\r\nb\rc"));
        self::assertSame('a/b/c', normalize_slashes('a\\b/c'));
    }

    /**
     * @param null|string|array<array-key, null|string> $path
     */
    #[DataProvider('pathProvider')]
    public function testNormalizePath(
        null|string|array $path,
        string $expected,
        bool $traversal = false,
    ): void {
        self::assertSame($expected, normalize_path($path, $traversal));
    }

    /**
     * @return iterable<string, array{0: null|string|array<array-key, null|string>, 1: string, 2?: bool}>
     */
    public static function pathProvider(): iterable
    {
        yield 'empty' => [null, ''];
        yield 'dedupe separators' => ['./assets\\\\\\scripts///example.js', './assets/scripts/example.js'];
        yield 'array join' => [['var', 'cache', 'app'], 'var/cache/app'];
        yield 'absolute keeps traversal tokens' => ['/var/cache/../tmp/file', '/var/cache/../tmp/file', true];
        yield 'relative traversal' => ['var/cache/../tmp/file', 'var/tmp/file', true];
        yield 'dot segments dropped' => ['/var/./cache', '/var/cache'];
    }

    public function testNormalizePathEmptyThrowsWhenRequested(): void
    {
        $this->expectException(InvalidArgumentException::class);
        normalize_path(null, throwOnFault: true);
    }

    public function testNormalizePathRelativeTraversalThrowsWhenRequested(): void
    {
        $this->expectException(LogicException::class);
        normalize_path('./foo/../bar', traversal: true, throwOnFault: true);
    }

    /**
     * @param null|string|array<int, null|string> $path
     */
    #[DataProvider('urlProvider')]
    public function testNormalizeUrl(
        null|string|array $path,
        string $expected,
        false|string $whitespace = '-',
        bool $trailingSlash = false,
    ): void {
        self::assertSame($expected, normalize_url($path, $whitespace, $trailingSlash));
    }

    /**
     * @return iterable<string, array{0: null|string|array<int, null|string>, 1: string, 2?: false|string, 3?: bool}>
     */
    public static function urlProvider(): iterable
    {
        yield 'empty' => [null, ''];
        yield 'protocol lowercased' => ['HTTPS://Example.COM/Path', 'https://example.com/path'];
        yield 'whitespace substituted' => ['https://example.com/a b', 'https://example.com/a-b'];
        yield 'query and fragment' => [
            'https://example.com/path?x=1#top',
            'https://example.com/path?x=1#top',
        ];
        yield 'trailing slash' => ['https://example.com/path', 'https://example.com/path/', '-', true];
        yield 'array join' => [['https://example.com', 'a', 'b'], 'https://example.com/a/b'];
    }
}
