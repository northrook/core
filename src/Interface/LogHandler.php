<?php

namespace Core\Interface;

use Core\Exception\LogEventException;
use JetBrains\PhpStorm\{Language};
use Psr\Log\{LoggerInterface};
use Stringable;
use Throwable;
use LogicException;
use RuntimeException;
use const Support\LOG_LEVEL;

/**
 * @phpstan-require-implements \Core\Interface\Loggable
 */
trait LogHandler
{
    /**
     * Use {@see self::log()}.
     *
     * @internal
     * @var null|LoggerInterface
     */
    protected ?LoggerInterface $logger = null;

    /**
     * @param null|LoggerInterface $logger
     *
     * @return void
     */
    final public function setLogger( ?LoggerInterface $logger ) : void
    {
        $this->logger = $logger;
    }

    /**
     * Internal logging helper.
     *
     * @param string                                                                   $message
     * @param array<string, mixed>                                                     $context
     * @param 'alert'|'critical'|'debug'|'emergency'|'error'|'info'|'notice'|'warning' $level
     * @param 'alert'|'critical'|'debug'|'emergency'|'error'|'info'|'notice'|'warning' $threshold
     */
    final protected function log(
        #[Language( 'Smarty' )]
        string|Stringable|Throwable $message,
        array                       $context = [],
        string                      $level = 'info',
        string                      $threshold = 'error',
    ) : void {
        // Auto-fill exceptions
        if ( $exception = ( $message instanceof Throwable ? $message : null ) ) {
            $level = LOG_LEVEL[$exception->getCode()] ?? match ( true ) {
                $exception instanceof RuntimeException,
                $exception instanceof LogicException => 'critical',
                default                              => 'error',
            };
            $context = ['exception' => $exception];
            $message = $exception->getMessage();
        }

        \assert( \in_array( $level, LOG_LEVEL ) && \in_array( $threshold, LOG_LEVEL ) );

        /** Log using the provided {@see LoggerInterface} if available */
        if ( $this->logger ) {
            $this->logger->{$level}( $message, $context );
            return;
        }

        dump( \get_defined_vars() );
        // Only throw for [error] and above
        if ( LOG_LEVEL[$level] < LOG_LEVEL[$threshold] ) {
            return;
        }

        /** Throw a {@see RuntimeException} as last resort */
        throw new LogEventException( $message, $context, $exception );
    }
}
