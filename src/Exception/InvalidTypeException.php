<?php

declare( strict_types = 1 );

namespace Northrook\Core\Exception;

use Throwable;

class InvalidTypeException extends \InvalidArgumentException
{
    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * @link https://php.net/manual/en/exception.construct.php
     *
     * @param string  $message  The Exception message to throw.
     * @param string  $value    The path to the file that caused the exception.
     * @param int     $code     [optional] The Exception code.
     */
    public function __construct(
        string                $message,
        public readonly mixed $value,
        int                   $code = 422,
        ?Throwable            $previous = null,
    ) {
        parent::__construct( $message, $code, $previous );
    }
}