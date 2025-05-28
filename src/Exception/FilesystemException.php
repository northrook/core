<?php

namespace Core\Exception;

use RuntimeException;
use Throwable;

class FilesystemException extends RuntimeException
{
    use ExceptionMethods;

    public readonly string $caller;

    public function __construct( string $message = '', int $code = 0, ?Throwable $previous = null )
    {
        $this->caller = $this->getThrowCall() ?? $this->getTraceAsString();

        parent::__construct( $message, $code, $previous );
    }
}
