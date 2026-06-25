<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Contracts\Exceptions\ErrorException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\ErrorHandler\CliErrorRenderer;
use Northrook\ErrorHandler\JsonErrorRenderer;
use Northrook\ErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        if (ErrorHandler::isRegistered()) {
            ErrorHandler::get()->uninstall();
        }
    }

    public function testBoxCapturesWarningWithoutThrowing(): void
    {
        $handler = ErrorHandler::get();

        $result = $handler->box(static function (): int {
            \trigger_error('scoped warning', E_USER_WARNING);

            return 42;
        });

        self::assertSame(42, $result);
        self::assertSame('scoped warning', $handler->getLastError());
    }

    public function testGlobalErrorHandlerThrowsErrorExceptionForMaskedTypes(): void
    {
        $handler = ErrorHandler::get();
        $handler->install();

        try {
            $this->expectException(ErrorException::class);
            \trigger_error('global warning', E_USER_WARNING);
        } finally {
            $handler->uninstall();
        }
    }

    public function testErrorReportFromSerializesThrowableAndPreviousChain(): void
    {
        $previous = new RuntimeException('previous');
        $current  = new FilesystemException('failed', previous: $previous, path: '/tmp/test');

        $report = ErrorReport::from($current, ['source' => 'test']);

        self::assertStringStartsWith('error-', $report->reference);
        self::assertSame(FilesystemException::class, $report->class);
        self::assertSame('failed', $report->message);
        self::assertSame('/tmp/test', $report->meta['path']);
        self::assertSame('test', $report->context['source']);
        self::assertCount(1, $report->previous);
        self::assertSame('previous', $report->previous[0]->message);
        self::assertNotEmpty($report->trace);
    }

    public function testErrorReportIncludesCurlExceptionMeta(): void
    {
        $report = ErrorReport::from(new CurlException('https://example.com'));

        self::assertSame('https://example.com', $report->meta['url']);
    }

    public function testJsonErrorRendererProducesValidJson(): void
    {
        $report = ErrorReport::from(new RuntimeException('json test'));
        $json    = ( new JsonErrorRenderer() )->render($report);
        $decoded = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['throwable']);

        self::assertSame('json test', $decoded['throwable']['message']);
        self::assertSame($report->reference, $decoded['reference']);
    }

    public function testCliErrorRendererIncludesMessageAndTrace(): void
    {
        $report = ErrorReport::from(new RuntimeException('cli test'));
        $output = ( new CliErrorRenderer() )->render($report);

        self::assertStringContainsString('cli test', $output);
        self::assertStringContainsString('Stack trace:', $output);
        self::assertTrue(( new CliErrorRenderer() )->supports($report));
    }

    public function testInstallAndUninstallRestoreHandlers(): void
    {
        $tracker = new class {
            public bool $called = false;
        };

        \set_error_handler(static function () use ($tracker): bool {
            $tracker->called = true;

            return true;
        });

        try {
            $handler = ErrorHandler::get();
            $handler->install();
            $handler->uninstall();

            $tracker->called = false;
            \trigger_error('restore test', E_USER_NOTICE);
            self::assertTrue($tracker->called);
        } finally {
            \restore_error_handler();
        }
    }

    public function testReportBuildsThroughHandler(): void
    {
        $handler = ErrorHandler::get();
        $report  = $handler->report(new RuntimeException('handler report'));

        self::assertSame('handler report', $report->message);
        self::assertArrayHasKey('sapi', $report->context);
    }
}
