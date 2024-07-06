<?php

declare( strict_types = 1 );

namespace Northrook\Core\Exception;

class UninitializedPropertyException extends \LogicException
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

        $caller = $this->getCaller();

        $message ??= ( $caller ? "$caller could"
                : "Could" ) . " not access property {$this->className}::{$this->propertyName}, as it is uninitialised.";

        parent::__construct( $message, $code, $previous );
    }

    private function getCaller() : ?string {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );


        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
        $caller    = $backtrace[ 2 ] ?? false;

        if ( $caller ) {
            return implode(
                '', [ $caller[ 'class' ] ?? null, $caller[ 'type' ] ?? null, $caller[ 'function' ] ?? null ],
            );
        }
        return null;
    }

}