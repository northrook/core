<?php

namespace Northrook\Core\Exception;

/**
 * An exception that indicates a breaking change.
 *
 * This is caused by the deprecation of a class or function.
 *
 * This exception must never be used in a production environment.
 *
 */
class BreakingChangeException extends \LogicException
{
    
    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * @link https://php.net/manual/en/exception.construct.php
     *
     * @param string       $message  [optional] The Exception message to throw.
     * @param string|null  $string
     * @param int          $code     [optional] The Exception code.
     */
    public function __construct(
        string $message = '',
        int    $code = 422,
    ) {
        parent::__construct( $message, $code );
        $this->message = $message;
        $this->code    = $code;
    }
}