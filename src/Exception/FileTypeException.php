<?php

declare(strict_types=1);

namespace Northrook\Exception;

use RuntimeException;

class FileTypeException extends RuntimeException
{
    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * @link https://php.net/manual/en/exception.construct.php
     *
     * @param string $message the Exception message to throw
     * @param string $path    the path to the file that caused the exception
     * @param int    $code    [optional] The Exception code
     */
    public function __construct(
        string                 $message,
        public readonly string $path,
        int                    $code = 422,
    ) {
        parent::__construct( $message, $code );
        $this->message = $message;
        $this->code    = $code;
    }
}
