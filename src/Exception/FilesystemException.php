<?php

namespace Core\Exception;

use RuntimeException;
use Throwable;

class FilesystemException extends RuntimeException
{
    use ExceptionMethods;

    public readonly string $caller;

    public function __construct(
        string     $message,
        ?Throwable $previous = null,
    ) {
        $this->caller = $this->getThrowCall() ?? $this->getTraceAsString();

        parent::__construct(
            $message,
            E_RECOVERABLE_ERROR,
            $previous ?? ErrorException::getLast(),
        );
    }
}
