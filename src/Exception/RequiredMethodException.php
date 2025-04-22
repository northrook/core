<?php

namespace Core\Exception;

use LogicException;
use Throwable;

final class RequiredMethodException extends LogicException
{
    /**
     * @param string                   $method
     * @param ?string                  $type
     * @param null|class-string|object $class
     * @param null|string              $message
     * @param null|Throwable           $previous
     */
    public function __construct(
        public readonly string $method,
        ?string                $type = null,
        null|object|string     $class = null,
        ?string                $message = null,
        ?Throwable             $previous = null,
    ) {
        if ( ! $message ) {
            $method  = $type ? "'\${$method}' of type '{$type}'" : "'{$method}'";
            $message = "Required method {$method} does not exist";
            if ( $class ) {
                $class = \is_object( $class ) ? $class::class : $class;
                $message .= " in class '{$class}'.";
            }
            else {
                $message .= '.';
            }
        }
        parent::__construct( $message, E_ERROR, $previous );
    }
}
