<?php

declare(strict_types=1);

namespace Core\Exception;

use Exception;
use Throwable;

final class CurlException extends Exception
{
    use ExceptionMethods;

    public function __construct(
        public readonly int    $httpCode,
        public readonly string $curlError,
        ?string                $message = null,
        ?Throwable             $previous = null,
    ) {
        if ( ! $message ) {
            $message = $this->getThrowCall();
            $message = $message ? ' ' : '';
            $message .= "[{$httpCode}] ".$curlError;
        }

        parent::__construct( $message, E_RECOVERABLE_ERROR, $previous );
    }
}
