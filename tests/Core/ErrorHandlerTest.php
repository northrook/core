<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Contracts\ErrorHandler\ErrorBuffer;
use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Contracts\Exceptions\ErrorException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\ErrorHandler\CliErrorRenderer;
use Northrook\ErrorHandler\JsonErrorRenderer;
use Northrook\ErrorHandler;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorBuffer::setShared(new ErrorBuffer());
    }

    protected function tearDown(): void
    {
        if (ErrorHandler::isRegistered()) {
            ErrorHandler::get()->uninstall();
        }

        ErrorBuffer::setShared(null);
    }

    public function testBoxCapturesWarningWithoutThrowing(): void
    {
        $handler = ErrorHandler::get();

        $result = $handler->box(static function (): int {
            \trigger_error('scoped warning', E_USER_WARNING);

            return 42;
        });

        self::assertSame(42, $result);
        self::assertSame('scoped warning', $handler->lastBoxError()?->message);
        self::assertGreaterThanOrEqual(1, $handler->errors()->count());
    }

    public function testGlobalErrorHandlerThrowsErrorExceptionForMaskedTypes(): void
    {
        $handler = ErrorHandler::get();

        $this->expectException(ErrorException::class);
        $handler->handleGlobalError(E_USER_WARNING, 'global warning', __FILE__, __LINE__);
    }

    public function testRegisterResetsErrorBuffer(): void
    {
        ErrorHandler::get()->errors()->recordFrom(E_USER_NOTICE, 'stale', __FILE__, __LINE__);

        if (ErrorHandler::isRegistered()) {
            ErrorHandler::get()->uninstall();
        }

        $reflection = new ReflectionClass(ErrorHandler::class);
        $property   = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);

        ErrorHandler::register(install: false);

        self::assertSame(0, ErrorHandler::get()->errors()->count());
    }

    public function testGlobalErrorHandlerRecordsToBufferWhenNotThrowing(): void
    {
        $handler = ErrorHandler::get();

        $handler->handleGlobalError(E_USER_DEPRECATED, 'logged only', __FILE__, 42);

        self::assertSame(1, $handler->errors()->count());
        $lastError = $handler->errors()->last();
        self::assertNotNull($lastError);
        self::assertSame('logged only', $lastError->message);
        self::assertSame(42, $lastError->line);
    }

    public function testGlobalErrorHandlerRecordsBeforeThrowingErrorException(): void
    {
        $handler = ErrorHandler::get();

        try {
            $handler->handleGlobalError(E_USER_WARNING, 'will throw', __FILE__, 99);
            self::fail('Expected ErrorException was not thrown.');
        } catch (ErrorException) {
            $lastError = $handler->errors()->last();
            self::assertNotNull($lastError);
            self::assertSame('will throw', $lastError->message);
            self::assertSame(99, $lastError->line);
        }
    }

    public function testLastBoxErrorIsNullAfterCleanBox(): void
    {
        $handler = ErrorHandler::get();

        $handler->box(static fn (): int => 1);

        self::assertNull($handler->lastBoxError());
    }

    public function testLastBoxErrorDoesNotIncludePreBoxBufferEntries(): void
    {
        $handler = ErrorHandler::get();
        $handler->errors()->recordFrom(E_USER_NOTICE, 'before box', __FILE__, __LINE__);

        $handler->box(static fn (): int => 1);

        self::assertNull($handler->lastBoxError());
    }

    public function testScopedErrorRecordsFileAndLine(): void
    {
        $handler = ErrorHandler::get();

        $handler->box(static function (): void {
            \trigger_error('scoped with location', E_USER_WARNING);
        });

        $boxError = $handler->lastBoxError();
        self::assertNotNull($boxError);
        self::assertNotEmpty($boxError->file);
        self::assertGreaterThan(0, $boxError->line);
    }

    public function testReportPassesBufferToErrorReport(): void
    {
        $handler = ErrorHandler::get();
        $handler->errors()->recordFrom(E_USER_NOTICE, 'buffered', '/tmp/buffer.php', 4);

        $report = $handler->report(new \Exception('plain'));

        self::assertSame([[
            'type'    => E_USER_NOTICE,
            'message' => 'buffered',
            'file'    => '/tmp/buffer.php',
            'line'    => 4,
        ]], $report->phpErrors);
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
        $report  = ErrorReport::from(new RuntimeException('json test'));
        $json    = ( new JsonErrorRenderer() )->render($report);
        $decoded = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['throwable']);

        self::assertSame('json test', $decoded['throwable']['message']);
        self::assertSame($report->reference, $decoded['reference']);
        self::assertArrayHasKey('phpErrors', $decoded);
    }

    public function testCliErrorRendererIncludesMessageAndTrace(): void
    {
        $report = ErrorReport::from(new RuntimeException('cli test'));
        $output = ( new CliErrorRenderer() )->render($report);

        self::assertStringContainsString('cli test', $output);
        self::assertStringContainsString('Stack trace:', $output);
        self::assertTrue(( new CliErrorRenderer() )->supports($report));
    }

    public function testCliErrorRendererShowsPhpErrorsTrailWhenMultiple(): void
    {
        $report = new ErrorReport(
            reference: 'error-test',
            timestamp: 1.0,
            severity: 'critical',
            class: RuntimeException::class,
            message: 'multi',
            code: 0,
            file: __FILE__,
            line: 1,
            trace: [],
            phpError: [
                'type'    => E_USER_NOTICE,
                'message' => 'first',
                'file'    => '/tmp/a.php',
                'line'    => 1,
            ],
            phpErrors: [
                [
                    'type'    => E_USER_NOTICE,
                    'message' => 'first',
                    'file'    => '/tmp/a.php',
                    'line'    => 1,
                ],
                [
                    'type'    => E_USER_WARNING,
                    'message' => 'second',
                    'file'    => '/tmp/b.php',
                    'line'    => 2,
                ],
            ],
        );

        $output = ( new CliErrorRenderer() )->render($report);

        self::assertStringContainsString('php errors (2):', $output);
        self::assertStringContainsString('[1] /tmp/b.php:2 second', $output);
    }

    #[WithoutErrorHandler]
    public function testInstallAndUninstallRestoreHandlers(): void
    {
        $handler = ErrorHandler::get();
        $handler->install();
        $handler->uninstall();

        $probeCalled = false;
        \set_error_handler(static function () use (&$probeCalled): bool {
            $probeCalled = true;

            return true;
        });

        try {
            \trigger_error('restore test', E_USER_DEPRECATED);
            self::assertTrue($probeCalled);
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
