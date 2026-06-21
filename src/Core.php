<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\ContractSingleton;
use Northrook\Core\DateTime\{DateFormat, TimeZone};
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;

final class Core extends ContractSingleton
{
    public readonly string $projectRoot;

    public readonly LoggerInterface $logger;

    public readonly DateFormat $dateFormat;

    public readonly TimeZone $timezone;

    /**
     * @param string|Stringable     $projectRoot
     * @param DateFormat            $dateFormat
     * @param null|TimeZone         $timezone
     * @param null|LoggerInterface  $logger
     */
    protected function __construct(
        string|Stringable $projectRoot,
        DateFormat $dateFormat = DateFormat::SORTABLE,
        null|TimeZone $timezone = TimeZone::AUTO,
        null|LoggerInterface $logger = null,
    ) {
        parent::__construct();

        // TODO: Replace with EphemeralLogger
        $this->logger = $logger ?? new NullLogger();

        $this->projectRoot = $this->resolveProjectRoot($projectRoot);
        $this->dateFormat  = $this->resolveDateFormat($dateFormat);
        $this->timezone    = $this->resolveTimeZone($timezone);
    }

    // Requires Core::register() to be called first
    public static function get(): self
    {
        return self::getInstance();
    }

    private function resolveProjectRoot(
        string|Stringable $projectRoot,
    ): string {
        $directoryPath = (string) $projectRoot;

        if (! \is_dir($directoryPath)) {
            throw new \InvalidArgumentException("Project root directory does not exist: {$directoryPath}");
        }

        return $directoryPath;
    }

    /**
     * @stub deferred
     * @todo Implement string validation for DateFormat
     *
     * @param \Northrook\Core\DateTime\DateFormat $dateFormat
     * @return \Northrook\Core\DateTime\DateFormat
     */
    private function resolveDateFormat(DateFormat $dateFormat): DateFormat
    {
        return $dateFormat;
    }

    private function resolveTimeZone(
        null|TimeZone $timezone = TimeZone::AUTO,
    ): TimeZone {
        if ($timezone === TimeZone::AUTO) {
            $default  = \date_default_timezone_get();
            $timezone = TimeZone::tryFrom($default) ?? TimeZone::UTC;
        }

        \date_default_timezone_set($timezone->value);

        return $timezone;
    }

    /**
     * @param string|Stringable    $projectRoot
     * @param DateFormat           $dateFormat
     * @param TimeZone|null        $timezone
     * @param LoggerInterface|null $logger
     *
     * @return \Northrook\Core
     */
    public static function register(
        string|Stringable $projectRoot,
        DateFormat $dateFormat = DateFormat::SORTABLE,
        null|TimeZone $timezone = TimeZone::AUTO,
        null|LoggerInterface $logger = null,
    ): Core {
        return new Core(
            $projectRoot,
            $dateFormat,
            $timezone,
            $logger,
        );
    }

    public static function log(): LoggerInterface
    {
        return self::get()->logger;
    }

    /**
     * @template T
     * @param callable(): T $resolver
     * @return T
     */
    public static function assertive(
        callable $resolver,
    ): mixed {
        try {
            return $resolver();
        } catch (\Throwable $exception) {
            Core::log()->error(
                $exception->getMessage(),
                ['exception' => $exception],
            );
        }

        throw new \RuntimeException(
            'Unable to resolve the value.',
        );
    }

    /**
     * Check whether the script is being executed from a command line.
     */
    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli' || \defined('STDIN');
    }

    /**
     * Checks whether OPcache is installed and enabled for the given environment.
     */
    public static function isOpcacheEnabled(): bool
    {
        // Ensure OPcache is installed and not disabled
        if (! \function_exists('opcache_invalidate') || ! \ini_get('opcache.enable')) {
            return false;
        }

        // If called from CLI, check accordingly, otherwise true
        return ! Core::isCli() || \ini_get('opcache.enable_cli');
    }
}
