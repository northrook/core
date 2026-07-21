<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Northrook\Core\get;
use function Northrook\Core\regex_match_all;

final class FunctionsTest extends TestCase
{
    public function testGetReturnsCallbackValue(): void
    {
        self::assertSame(42, get(static fn(): int => 42, 0));
    }

    public function testGetReturnsFallbackOnThrowable(): void
    {
        self::assertSame(
            'fallback',
            get(static function(): string {
                throw new RuntimeException('boom');
            }, 'fallback'),
        );
    }

    public function testRegexMatchAllReturnsSetOrder(): void
    {
        $matches = regex_match_all('/(\w+)=(\d+)/', 'a=1 b=2');

        self::assertSame(
            [
                ['a=1', 'a', '1'],
                ['b=2', 'b', '2'],
            ],
            $matches,
        );
    }
}
