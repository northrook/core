<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Compiler\Autowire;
use Core\Exception\LogEventRuntimeException;
use JetBrains\PhpStorm\Language;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;
use LogicException;
use RuntimeException;
use Exception;
use const Support\LOG_LEVEL;

/**
 * {@see self::log()} events and exceptions.
 *
 * @used-by Loggable,LoggerAwareInterface
 */
trait Logger
{
    /** @var null|LoggerInterface */
    protected readonly ?LoggerInterface $logger;

    /**
     * Autowired during the instantiation process of the containing class.
     *
     * @internal
     *
     * @param null|LoggerInterface $logger
     * @param bool                 $assignNull
     *
     * @return void
     * @final
     */
    #[Autowire]
    final public function setLogger(
        ?LoggerInterface $logger,
        bool             $assignNull = false,
    ) : void {
        if ( $logger === null && $assignNull === false ) {
            return;
        }

        $this->logger = $logger;
    }

    /**
     * Internal logging helper.
     *
     * @param string                                                                   $message
     * @param array<string, mixed>                                                     $context
     * @param 'alert'|'critical'|'debug'|'emergency'|'error'|'info'|'notice'|'warning' $level
     * @param 'alert'|'critical'|'debug'|'emergency'|'error'|'info'|'notice'|'warning' $threshold
     *
     * @final
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
                $exception instanceof Exception      => 'error',
                default                              => 'warning',
            };
            $context = ['exception' => $exception];
            $message = $exception->getMessage();
        }

        \assert( \in_array( $level, LOG_LEVEL ) && \in_array( $threshold, LOG_LEVEL ) );

        /** Log using the provided {@see LoggerInterface} if available */
        if ( isset( $this->logger ) ) {
            $this->logger->{$level}( $message, $context );
            return;
        }

        // Only throw for [error] and above
        if ( LOG_LEVEL[$level] < LOG_LEVEL[$threshold] ) {
            return;
        }

        /** Throw a {@see RuntimeException} as last resort */
        throw new LogEventRuntimeException( $message, $context, $exception );
    }
}
