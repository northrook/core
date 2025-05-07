<?php

declare(strict_types=1);

namespace Core\Exception;

trait ExceptionMethods
{
    final protected function getThrowCall() : string
    {
        $trace = \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );

        $caller = $trace[1] ?? null;

        if ( $caller ) {
            if ( isset( $caller['class'], $caller['function'] ) ) {
                return "{$caller['class']}::{$caller['function']}";
            }
            if ( isset( $caller['function'] ) ) {
                return "{$caller['function']}";
            }
            if ( isset( $caller['file'] ) ) {
                return \basename( $caller['file'] );
            }
        }

        return $this::class;
    }
}
