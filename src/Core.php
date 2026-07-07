<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\ContractSingleton;
use Northrook\Core\DateTime\{DateFormat, TimeZone};
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;

use function Northrook\Core\{get_hash, normalize_path};

final class Core extends ContractSingleton
{
    public readonly string $rootDirectory;

    public readonly string $cacheDirectory;

    public readonly LoggerInterface $logger;

    public readonly DateFormat $dateFormat;

    public readonly TimeZone $timezone;

    /**
     * @param null|string|Stringable        $rootDirectory
     * @param null|string|Stringable  $cacheDirectory
     * @param DateFormat               $dateFormat
     * @param null|TimeZone            $timezone
     * @param null|LoggerInterface     $logger
     */
    protected function __construct(
        null|string|Stringable $rootDirectory,
        null|string|Stringable $cacheDirectory = null,

        DateFormat $dateFormat = DateFormat::SORTABLE,
        null|TimeZone $timezone = TimeZone::AUTO,
        null|LoggerInterface $logger = null,
    ) {
        parent::__construct();

        // TODO: Replace with EphemeralLogger
        $this->logger = $logger ?? new NullLogger();

        $this->rootDirectory  = $this->resolveRootDirectory($rootDirectory);
        $this->cacheDirectory = $this->resolveCacheDirectory($cacheDirectory);
        $this->dateFormat     = $this->resolveDateFormat($dateFormat);
        $this->timezone       = $this->resolveTimeZone($timezone);
    }

    public static function getCacheDirectory(
        null|string $subDirectory = null,
    ): string {
        $cacheDirectory = self::get()->cacheDirectory;

        if ($subDirectory) {
            $cacheDirectory .= \DIR_SEP . $subDirectory;
        }

        return normalize_path($cacheDirectory);
    }

    private function resolveRootDirectory(
        null|string|Stringable $rootDirectory,
    ): string {
        if ($rootDirectory === null) {
            // Split the current directory into an array of directory segments
            $segments = \explode(DIRECTORY_SEPARATOR, __DIR__);

            // Ensure the directory array has at least 5 segments and a valid vendor value
            if (\count($segments) >= 5 && $segments[\count($segments) - 4] === 'vendor') {
                // Remove the last 4 segments (vendor, package name, and Composer structure)
                $segments = \array_slice($segments, 0, -4);
            } else {
                $caller = __METHOD__;
                $dir    = __DIR__;
                throw new \RuntimeException(
                    "{$caller} was unable to determine a valid root directory relative to {$dir}",
                );
            }

            $resolveDirectory = $segments;
        } else {
            $resolveDirectory = $rootDirectory;
        }

        $directory = normalize_path(
            $resolveDirectory,
            throwOnFault: true,
        );

        return \is_dir($directory)
            ? $directory
            : throw new \InvalidArgumentException(
                "The resolved root directory does not exist: {$directory}",
            );
    }

    private function resolveCacheDirectory(
        null|string|Stringable $cacheDirectory = null,
    ): string {
        $useSystemCache = $cacheDirectory === null;

        $cacheDirectory = (string) ( $cacheDirectory ?? \sys_get_temp_dir() );

        if (! \is_dir($cacheDirectory)) {
            throw new \InvalidArgumentException(
                "The resolved cache directory does not exist: {$cacheDirectory}",
            );
        }

        if ($useSystemCache) {
            $cacheDirectory .= \DIR_SEP . get_hash($this->rootDirectory);
        }

        return normalize_path(
            $cacheDirectory,
            throwOnFault: true,
        );
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
     * @param null|string|\Stringable  $rootDirectory
     * @param null|string|\Stringable  $cacheDirectory
     * @param DateFormat               $dateFormat
     * @param TimeZone|null            $timezone
     * @param LoggerInterface|null     $logger
     *
     * @return static(ContractSingleton)
     */
    public static function register(
        null|string|Stringable $rootDirectory,
        null|string|Stringable $cacheDirectory = null,
        DateFormat $dateFormat = DateFormat::SORTABLE,
        null|TimeZone $timezone = TimeZone::AUTO,
        null|LoggerInterface $logger = null,
    ): static {
        return new Core(
            $rootDirectory,
            $cacheDirectory,
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
