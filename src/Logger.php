<?php

namespace Northrook\Core;

use Psr\Log as Psr;
use function Northrook\Core\Function\isScalar;

final class Logger extends Psr\AbstractLogger
{
    use Psr\LoggerTrait;

    private array $entries = [];

    public function log( $level, $message = null, array $context = [] ) : void {
        $this->entries[] = [ $level, $message, $context ];
    }

    public function getLogs() : array {
        return $this->entries;
    }

    public function cleanLogs() : array {
        $logs          = $this->entries;
        $this->entries = [];

        return $logs;
    }

    /**
     * Print each log entry into an array, as human-readable strings.
     *
     * - Cleans the log by default.
     * - Does not include Timestamp by default.
     *
     * @param bool  $clean
     *
     * @return array
     */
    public function printLogs( bool $clean = true, bool $timestamp = false ) : array {

        $entries = $clean ? $this->cleanLogs() : $this->getLogs();

        $logs = [];

        foreach ( $entries as [ $level, $message, $context ] ) {
            $level = ucfirst( $level );

            if ( str_contains( $message, '{' ) ) {
                foreach ( $context as $key => $value ) {
                    $value   = match ( true ) {
                        isScalar( $value )                   => (string) $value,
                        $value instanceof \DateTimeInterface => $value->format( \DateTimeInterface::RFC3339 ),
                        \is_object( $value )                 => '[object ' . get_debug_type( $value ) . ']',
                        default                              => '[' . \gettype( $value ) . ']',
                    };
                    $message = str_replace( "{{$key}}", $value, $message );
                }
            }

            $time = $timestamp ? '[' . date( \DateTimeInterface::RFC3339 ) . '] ' : '';

            $logs[] = "{$time}{$level}: {$message}";
        }

        return $logs;

    }

    /**
     * Dump the logs to the PHP error log if the logger is destroyed without first calling {@see cleanLogs()}.
     */
    public function __destruct() {
        array_map( 'error_log', $this->printLogs() );
    }

    /**
     * LoggerInterfaces cannot be serialized for security reasons.
     *
     * @return array
     */
    public function __sleep() : array {
        throw new \BadMethodCallException( Psr\LoggerInterface::class . ' cannot be serialized' );
    }

    /**
     * LoggerInterfaces cannot be serialized for security reasons.
     */
    public function __wakeup() : void {
        throw new \BadMethodCallException( Psr\LoggerInterface::class . ' cannot be unserialized' );
    }
}