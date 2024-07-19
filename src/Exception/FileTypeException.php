<?php

declare( strict_types = 1 );

namespace Northrook\Core\Exception;

class FileTypeException extends \RuntimeException
{
    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * @link https://php.net/manual/en/exception.construct.php
     *
     * @param string  $message  The Exception message to throw.
     * @param string  $path     The path to the file that caused the exception.
     * @param int     $code     [optional] The Exception code.
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