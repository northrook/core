<?php

declare(strict_types=1);

namespace Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Psr\Log\{LogLevel, LoggerInterface};
use Throwable;
use RuntimeException;
use Exception;

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
            message  : 'An error occurred while capturing the callback.',
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

/**
 * @param DateTimeInterface|int|string $when
 * @param null|DateTimeZone|string     $timezone [UTC]
 *
 * @return DateTimeImmutable
 */
function datetime(
    int|string|DateTimeInterface $when = 'now',
    string|DateTimeZone|null     $timezone = AUTO,
) : DateTimeImmutable {
    $fromDateTime = $when instanceof DateTimeInterface;
    $datetime     = $fromDateTime ? $when->getTimestamp() : $when;

    if ( \is_int( $datetime ) ) {
        $datetime = "@{$datetime}";
    }

    $timezone = match ( true ) {
        \is_null( $timezone )   => $fromDateTime ? $when->getTimezone() : \timezone_open( 'UTC' ),
        \is_string( $timezone ) => \timezone_open( $timezone ),
        default                 => $timezone,
    } ?: null;

    try {
        return new DateTimeImmutable( $datetime, $timezone );
    }
    catch ( Exception $exception ) {
        $message = 'Unable to create a new DateTimeImmutable object: '.$exception->getMessage();
        throw new InvalidArgumentException(
            $message,
            E_RECOVERABLE_ERROR,
            $exception,
        );
    }
}

function get_throw_call( int $limit = 3 ) : ?string
{
    $trace = \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $limit );

    $caller = $trace[2] ?? null;

    if ( \count( $trace ) > 2 && $caller ) {
        $class    = $caller['class'] ?? null;
        $function = $caller['function'] ?: null;

        if ( $class ) {
            return "{$class}::{$function}";
        }
        if ( $function ) {
            return $function;
        }
    }

    $file = $trace[1]['file'] ?? null;
    $line = $trace[1]['line'] ?? null;

    if ( $line !== null ) {
        $file .= ":{$line}";
    }

    return $file;
}

function cli_format(
    string    $text,
    string ...$style,
) : string {
    $styles = [
        'black'   => '0;30',
        'red'     => '0;31',
        'green'   => '0;32',
        'yellow'  => '0;33',
        'blue'    => '0;34',
        'magenta' => '0;35',
        'cyan'    => '0;36',
        'white'   => '0;37',

        'bold'       => '1',
        'dim'        => '2',
        'underscore' => '4',
        'blink'      => '5',
        'reverse'    => '7',
        'conceal'    => '8',
    ];

    $backgrounds = [
        'bg_black'   => '40',
        'bg_red'     => '41',
        'bg_green'   => '42',
        'bg_yellow'  => '43',
        'bg_blue'    => '44',
        'bg_magenta' => '45',
        'bg_cyan'    => '46',
        'bg_white'   => '47',
    ];

    $format = [];

    foreach ( $style as $code ) {
        $code = \trim( $code );
        if ( isset( $styles[$code] ) ) {
            $format[] = $styles[$code];
        }
        elseif ( isset( $backgrounds[$code] ) ) {
            $format[] = $backgrounds[$code];
        }
    }

    if ( ! $format ) {
        return $text;
    }

    return "\033[".\implode( ';', $format )."m{$text}\033[0m";
}
