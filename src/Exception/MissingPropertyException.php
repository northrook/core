<?php

declare( strict_types = 1 );

namespace Northrook\Exception;

class MissingPropertyException extends \LogicException
{
    /**
     * @param string           $propertyName
     * @param class-string     $className
     * @param null|string      $message
     * @param int              $code
     * @param null|\Throwable  $previous
     */
    public function __construct(
        public readonly string $propertyName,
        public readonly string $className,
        ?string                $message = null,
        int                    $code = 0,
        ?\Throwable            $previous = null,
    ) {
        $message ??= "Property '{$this->propertyName}' does not exist in '{$this->className}'.";
        parent::__construct( $message, $code, $previous );
    }


}