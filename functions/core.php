<?php

declare(strict_types=1);

namespace Support;

use Psr\Log\{LogLevel, LoggerInterface};
use Throwable;
use RuntimeException;

/**
 * @template Value
 *
 * @param callable(): Value    $callback
 * @param Value                $fallback
 * @param null|LoggerInterface $log
 * @param LogLevel::*          $level
 *
 * @return Value
 */
function get(
    callable         $callback,
    mixed            $fallback,
    ?LoggerInterface $log = null,
    string           $level = LogLevel::WARNING,
) : mixed {
    try {
        return $callback();
    }
    catch ( Throwable $exception ) {
        $log?->{$level}( $exception->getMessage(), ['exception' => $exception] );
    }
    return $fallback;
}

/**
 * Capture the output buffer from a provided `callback`.
 *
 * - Will throw a {@see RuntimeException} if the `callback` throws any exceptions.
 *
 * @param callable $callback
 * @param mixed    ...$args
 *
 * @return string
 */
function ob_get( callable $callback, mixed ...$args ) : string
{
    \ob_start();
    try {
        $callback( ...$args );
    }
    catch ( Throwable $exception ) {
        \ob_end_clean();
        throw new RuntimeException(
            message  : 'An error occurred while capturinb the callback.',
            code     : 500,
            previous : $exception,
        );
    }
    return \ob_get_clean() ?: '';
}

/**
 * Check whether the script is being executed from a command line.
 */
function is_cli() : bool
{
    return PHP_SAPI === 'cli' || \defined( 'STDIN' );
}

/**
 * Checks whether OPcache is installed and enabled for the given environment.
 */
function opcache_enabled() : bool
{
    // Ensure OPcache is installed and not disabled
    if (
        ! \function_exists( 'opcache_invalidate' )
        || ! \ini_get( 'opcache.enable' )
    ) {
        return false;
    }

    // If called from CLI, check accordingly, otherwise true
    return ! is_cli() || \ini_get( 'opcache.enable_cli' );
}
