<?php

declare(strict_types=1);

namespace Core\Exception;

trait ExceptionMethods
{
    final protected function getThrowCall() : ?string
    {
        $trace = \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );

        if ( \count( $trace ) === 2 ) {
            return "{$trace[1]['file']}:{$trace[1]['line']}";
        }

        $caller = $trace[2] ?? null;

        if ( $caller ) {
            if ( isset( $caller['class'], $caller['function'] ) ) {
                return "{$caller['class']}::{$caller['function']}";
            }
            if ( isset( $caller['function'] ) ) {
                return "{$caller['function']}";
            }
            if ( isset( $caller['file'] ) ) {
                return "{$caller['file']}:{$caller['line']}";
            }
        }

        return null;
    }
}
