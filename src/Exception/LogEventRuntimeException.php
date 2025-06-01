<?php

declare(strict_types=1);

namespace Core\Exception;

use Throwable;
use function Support\{as_string, regex_match_all};

/**
 * Thrown when an expected {@see \Psr\Log\LoggerInterface} is unavailable.
 *
 * @used-by \Core\Autowire\Logger
 */
final class LogEventRuntimeException extends RuntimeException
{
    /**
     * @param string               $message
     * @param array<string, mixed> $context
     * @param null|Throwable       $exception
     */
    public function __construct(
        string                $message,
        public readonly array $context = [],
        ?Throwable            $exception = null,
    ) {
        parent::__construct(
            message  : $this->resolve( $message, $context ),
            previous : $exception,
        );
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return string
     */
    private function resolve( string $message, array $context = [] ) : string
    {
        if ( ! ( \str_contains( $message, '{' ) && \str_contains( $message, '}' ) ) ) {
            return $message;
        }

        foreach ( $context as $k => $v ) {
            unset( $context[$k] );
            $context['{'.$k.'}'] = "'".as_string( $v )."'";
        }

        foreach ( regex_match_all( '#{([^}]+)}#', $message ) as $match ) {
            [$key, $value] = $match;

            if ( $key ) {
                $context[$key] ??= "'{$value}'";
            }
        }

        return \strtr( $message, $context );
    }
}
