<?php

declare(strict_types=1);

namespace Northrook\Exception;

use JetBrains\PhpStorm\Language;
use Northrook\Logger\Log;
use Throwable;
use Stringable;
use const Support\AUTO;

final class E_Value extends ExceptionHandler
{
    /**
     * @param string|Stringable        $message
     * @param array<array-key, string> $context
     * @param ?Throwable               $previous
     *
     * @return null
     */
    public static function warning(
        #[Language( 'Smarty' )] string|Stringable $message,
        array             $context = [],
        ?Throwable        $previous = null,
    ) : null {
        Log::exception(
            new ValueError(
                E_Value::handleMessage( (string) $message, $context ),
                file : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[0]['file'] ?? 'unkown file',
                line : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[0]['line'] ?? 0,
                previous: $previous,
            ),
            context : $context,
        );
        return null;
    }

    /**
     * @param string|Stringable        $message
     * @param array<array-key, string> $context
     * @param ?Throwable               $previous
     * @param ?bool                    $throw
     *
     * @return null
     */
    public static function error(
        #[Language( 'Smarty' )] string|Stringable $message,
        array             $context = [],
        ?Throwable        $previous = null,
        ?bool             $throw = AUTO,
    ) : null {

        [$message, $throw] = E_Value::autoHalt( (string) $message, $throw );

        $error = new ValueError(
            E_Value::handleMessage( (string) $message, $context ),
            file : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[0]['file'] ?? 'unkown file',
            line : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[0]['line'] ?? 0,
            previous: $previous,
        );

        Log::exception( $error, message: $message, context : $context );

        return $throw ? throw $error : null;
    }
}
