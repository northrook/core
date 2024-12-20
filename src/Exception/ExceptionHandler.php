<?php

namespace Northrook\Exception;

use LogicException;
use Northrook\Env;
use const Support\AUTO;

/**
 * @internal
 * @author Martin Nielsen <mn@northrook.com>
 */
abstract class ExceptionHandler
{
    final protected function __construct()
    {
        throw new LogicException( $this::class.' is using the `StaticClass` trait, and should not be instantiated directly.' );
    }

    final protected function __clone()
    {
        throw new LogicException( $this::class.' is using the `StaticClass` trait, and should not be cloned.' );
    }

    /**
     * @param string    $message
     * @param null|bool $halt
     *
     * @return array{0: string, 1: bool}
     */
    final protected static function autoHalt( string $message, ?bool $halt ) : array
    {
        if ( AUTO === $halt && Env::isDebug() ) {
            $halt    = true;
            $message = 'Debug Enabled: '.$message;
        }
        return [$message, $halt ?? false];
    }

    /**
     * @param string                   $message
     * @param array<array-key, string> $context
     *
     * @return string
     */
    final protected static function handleMessage( string $message, array $context ) : string
    {
        foreach ( $context as $key => $value ) {
            $message = \str_replace( "{{$key}}", "{$value}", $message );
        }
        if ( ! $context ) {
            $message = \preg_replace( '#{(.*?)}#', '`$1`', $message );
        }

        return (string) $message;
    }
}
