<?php

declare(strict_types=1);

namespace Core\Exception;

trait ExceptionMethods
{
    final protected function getThrowCall( int $limit = 3 ) : ?string
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
}
