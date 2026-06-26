<?php

declare(strict_types=1);

namespace Northrook;

use Framework\Dev\VarDump;
use Framework\Dev\VarDumper;
use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\Exceptions\ErrorException;
use Northrook\Contracts\Interfaces\ErrorHandlerInterface;
use Northrook\Contracts\Interfaces\ErrorRendererInterface;
use Northrook\ErrorHandler\CompositeErrorRenderer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class ErrorHandler implements ErrorHandlerInterface
{
    private static null|self $instance = null;

    private null|string $lastError = null;

    private bool $installed = false;

    private bool $handledException = false;

    /** @var null|callable(int, string, string, int): bool */
    private $previousErrorHandler = null;

    /** @var null|callable(Throwable): void */
    private $previousExceptionHandler = null;

    private function __construct(
        public readonly LoggerInterface $logger,
        public readonly ErrorRendererInterface $renderer,
        public readonly int $throwAt,
    ) {
        if (self::$instance !== null) {
            throw new \LogicException(self::class . ' is a singleton and cannot be instantiated twice.');
        }

        self::$instance = $this;
    }

    public static function isRegistered(): bool
    {
        return self::$instance !== null;
    }

    private static function getInstance(): self
    {
        return (
            self::$instance ?? throw new \LogicException(
                self::class . ' is not registered. Call ' . self::class . '::register() first.',
            )
        );
    }

    public static function register(
        null|LoggerInterface $logger = null,
        null|ErrorRendererInterface $renderer = null,
        int $throwAt = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,
        bool $install = true,
    ): static {
        $instance = new self(
            logger: $logger ?? ( Core::isRegistered() ? Core::log() : new NullLogger() ),
            renderer: $renderer ?? new CompositeErrorRenderer(),
            throwAt: $throwAt,
        );

        if ($install) {
            $instance->install();
        }

        return $instance;
    }

    public static function get(): self
    {
        if (! self::isRegistered()) {
            self::register(install: false);
        }

        return self::getInstance();
    }

    public function install(): void
    {
        if ($this->installed) {
            return;
        }

        $this->previousErrorHandler     = \set_error_handler($this->handleGlobalError(...));
        $this->previousExceptionHandler = \set_exception_handler($this->handleGlobalException(...));
        \register_shutdown_function($this->handleShutdown(...));
        $this->installed = true;
    }

    public function uninstall(): void
    {
        if (! $this->installed) {
            return;
        }

        if ($this->previousErrorHandler !== null) {
            \set_error_handler($this->previousErrorHandler);
        } else {
            \restore_error_handler();
        }

        if ($this->previousExceptionHandler !== null) {
            \set_exception_handler($this->previousExceptionHandler);
        } else {
            \restore_exception_handler();
        }

        $this->installed = false;
    }

    public function box(callable $callback): mixed
    {
        $this->lastError = null;
        \set_error_handler($this->handleScopedError(...));

        try {
            return $callback();
        } finally {
            \restore_error_handler();
        }
    }

    public function getLastError(): null|string
    {
        return $this->lastError;
    }

    public function report(Throwable $throwable, array $context = []): ErrorReport
    {
        return ErrorReport::from(
            $throwable,
            $context,
            $this->collectDumps($throwable),
        );
    }

    public function handle(Throwable $throwable): never
    {
        $this->handledException = true;
        $report                 = $this->report($throwable);

        $this->logger->log(
            $report->severity,
            $report->message,
            [
                'exception' => $throwable,
                'report'    => $report,
            ],
        );

        if (! Core::isCli() && ! \headers_sent()) {
            \http_response_code(500);
        }

        echo $this->renderer->render($report);

        exit(1);
    }

    public function handleGlobalError(
        int $type,
        string $message,
        string $file,
        int $line,
    ): bool {
        if ($type & $this->throwAt) {
            throw new ErrorException();
        }

        $this->logger->log(
            $this->resolveErrorSeverity($type),
            $message,
            [
                'type' => $type,
                'file' => $file,
                'line' => $line,
            ],
        );

        return true;
    }

    public function handleGlobalException(Throwable $throwable): void
    {
        $this->handledException = true;

        if ($this->isRunningUnderPHPUnit()) {
            throw $throwable;
        }

        $this->handle($throwable);
    }

    public function handleShutdown(): void
    {
        if ($this->handledException) {
            return;
        }

        $error = ErrorException::getLast();

        if ($error === null) {
            return;
        }

        if (! $this->isFatalErrorType($error->error['type'])) {
            return;
        }

        if ($this->isRunningUnderPHPUnit()) {
            throw $error;
        }

        $this->handle($error);
    }

    private function handleScopedError(
        int $type,
        string $message,
    ): bool {
        $this->lastError = $message;

        return true;
    }

    /**
     * Collects deferred var-dump output from {@see northrook/var-dump} when installed.
     *
     * @return array<string, mixed>
     *
     * @throws \JsonException
     */
    private function collectDumps(
        Throwable $throwable,
    ): array {
        if (! \class_exists(VarDump::class)) {
            return [];
        }

        VarDump::this($throwable);

        if (! \class_exists(VarDumper::class)) {
            return [];
        }

        $dumps = [];

        foreach (VarDumper::renderDeferred() as $reference => $json) {
            $dumps[$reference] = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }

        return $dumps;
    }

    private function resolveErrorSeverity(int $type): string
    {
        return match (true) {
            ( $type & ( E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR ) ) !== 0
                => 'critical',
            ( $type & ( E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING ) ) !== 0 => 'warning',
            default => 'notice',
        };
    }

    private function isFatalErrorType(int $type): bool
    {
        return ( $type & ( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR ) ) !== 0;
    }

    private function isRunningUnderPHPUnit(): bool
    {
        return \defined('PHPUNIT_COMPOSER_INSTALL') || \getenv('PHPUNIT') !== false;
    }
}
