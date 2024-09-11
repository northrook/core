<?php

declare( strict_types = 1 );

namespace Northrook\Exception;

use JetBrains\PhpStorm\Language;
use Northrook\Env;
use Northrook\Logger\Log;
use Throwable;


final class Trigger
{

    public static function valueWarning(
        #[Language( 'Smarty' )]
        string | \Stringable $message,
        array                $context = [],
        ?Throwable           $previous = null,
    ) : void
    {
        Log::exception(
                      new ValueError(
                                 $message,
                          file : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[ 0 ][ 'file' ],
                          line : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[ 0 ][ 'line' ],
                      ),
            context : $context,
        );
    }

    public static function error(
        #[Language( 'Smarty' )]
        string | \Stringable $message,
        array                $context = [],
        ?Throwable           $previous = null,
        bool                 $halt = false,
    ) : void
    {
        if ( Env::isDebug() ) {
            $halt = true;
        }
        if ( $halt ) {
            foreach ( $context as $key => $value ) {
                $message = \str_replace( "{{$key}}", "'{$value}'", $message );
            }
        }
        $error = new \RuntimeException(
                       $message,
            severity : E_ERROR,
            file     : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[ 0 ][ 'file' ],
            line     : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[ 0 ][ 'line' ],
        );
        Trigger::handleException( $error, $context, $halt );
    }

    public static function valueError(
        #[Language( 'Smarty' )]
        string | \Stringable $message,
        array                $context = [],
        ?Throwable           $previous = null,
        bool                 $halt = false,
    ) : void
    {
        if ( Env::isDebug() ) {
            $halt = true;
        }
        if ( $halt ) {
            foreach ( $context as $key => $value ) {
                $message = \str_replace( "{{$key}}", "'{$value}'", $message );
            }
        }
        $error = new ValueError(
                   $message,
            file : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[ 0 ][ 'file' ],
            line : \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[ 0 ][ 'line' ],
        );
        Trigger::handleException( $error, $context, $halt );
    }

    /**
     * @param \RuntimeException  $exception
     * @param array              $context
     * @param bool               $halt
     *
     * @return void
     * @throws \RuntimeException
     */
    private static function handleException(
        \RuntimeException $exception, array $context = [], bool $halt = false,
    ) : void
    {
        if ( $halt ) {
            throw $exception;
        }

        Log::exception( $exception, context : $context );
    }
}