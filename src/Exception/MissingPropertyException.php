<?php

declare(strict_types=1);

namespace Core\Exception;

use LogicException, Throwable;

class MissingPropertyException extends LogicException
{
    /**
     * @param string                   $property
     * @param ?string                  $type
     * @param null|class-string|object $class
     * @param null|string              $message
     * @param null|Throwable           $previous
     */
    public function __construct(
        public readonly string $property,
        ?string                $type = null,
        null|object|string     $class = null,
        ?string                $message = null,
        ?Throwable             $previous = null,
    ) {
        if ( ! $message ) {
            $property = $type ? "'\${$property}' of type '{$type}'" : "'{$property}'";
            $message  = "Property {$property} does not exist";
            if ( $class ) {
                $class = \is_object( $class ) ? $class::class : $class;
                $message .= " in class '{$class}'.";
            }
            else {
                $message .= '.';
            }
        }
        parent::__construct(
            $message,
            E_ERROR,
            $previous ?? ErrorException::getLast(),
        );
    }
}
