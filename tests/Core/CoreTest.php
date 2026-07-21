<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Core;
use PHPUnit\Framework\TestCase;

use function Northrook\Core\normalize_path;

final class CoreTest extends TestCase
{
    public function testIsCli(): void
    {
        self::assertTrue(Core::isCli());
    }

    public function testGetCacheDirectory(): void
    {
        $base = Core::getCacheDirectory();
        $sub  = Core::getCacheDirectory('tmp');

        self::assertSame(Core::get()->cacheDirectory, $base);
        self::assertSame(
            normalize_path(Core::get()->cacheDirectory . \DIR_SEP . 'tmp'),
            $sub,
        );
    }

    public function testLogReturnsRegisteredLogger(): void
    {
        self::assertSame(Core::get()->logger, Core::log());
    }
}
