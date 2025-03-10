<?php

declare(strict_types=1);

namespace Core\Exception;

use LogicException, Throwable;

class MissingPropertyException extends LogicException
{
    /**
     * @param string         $property
     * @param ?string        $type
     * @param ?class-string  $class
     * @param null|string    $message
     * @param int            $code
     * @param null|Throwable $previous
     */
    public function __construct(
        public readonly string $property,
        ?string                $type = null,
        ?string                $class = null,
        ?string                $message = null,
        int                    $code = 500,
        ?Throwable             $previous = null,
    ) {
        if ( ! $message ) {
            $property = $type ? "'\${$property}' of type '{$type}'" : "'{$property}'";
            $message  = "Property {$property} does not exist";
            if ( $class ) {
                $message .= " in class '{$class}'.";
            }
            else {
                $message .= '.';
            }
        }
        parent::__construct( $message, $code, $previous );
    }
}
