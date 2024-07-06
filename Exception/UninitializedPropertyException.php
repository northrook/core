<?php

declare( strict_types = 1 );

namespace Northrook\Core\Exception;

class UninitializedPropertyException extends \LogicException
{

    public readonly string $caller;

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

        $this->caller = $this->getCaller();

        $message ??= "{$this->caller} could not access property '{$this->propertyName}', but is not initialized in '{$this->caller}'.";

        parent::__construct( $message, $code, $previous );
    }

    private function getCaller() : string {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );

        $caller = $backtrace[ 1 ] ?? $backtrace[ 0 ];

        return implode( '', [ $caller[ 'class' ] ?? null, $caller[ 'type' ] ?? null, $caller[ 'function' ] ?? null ] );
    }

}