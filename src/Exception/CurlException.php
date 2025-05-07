<?php

declare(strict_types=1);

namespace Core\Exception;

use Exception;
use Throwable;

final class CurlException extends Exception
{
    use ExceptionMethods;

    public function __construct(
        int        $httpCode,
        string     $curlError,
        ?string    $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? $this->getThrowCall()." cURL [{$httpCode}] error: ".$curlError,
            E_RECOVERABLE_ERROR,
            $previous,
        );
    }
}
