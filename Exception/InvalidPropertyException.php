<?php

namespace Northrook\Core\Exception;

class InvalidPropertyException extends \Exception
{

    public readonly string $caller;

    public function __construct(
        private readonly mixed $invalidProperty,
        private readonly mixed $requiredProperty,
        ?string                $message = null,
        int                    $code = 0,
        ?\Throwable            $previous = null,
    ) {
        $this->caller = $this->getCaller();
        
        $invalid = $this->getType( $this->invalidProperty );

        $invalid = match ( $invalid ) {
            'class'  => $this->invalidProperty . "::class",
            'string' => $this->invalidProperty . "<$invalid>",
            default  => "type <$invalid>",
        };

        $required = $this->getType( $this->requiredProperty );

        $required = match ( $required ) {
            'class'  => $this->requiredProperty . "::class",
            'string' => $this->requiredProperty . "<$required>",
            default  => $required,
        };


        $message ??= "Invalid property $invalid in '{$this->caller}', should be $required.";

        parent::__construct( $message, $code, $previous );
    }

    private function getCaller() : string {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );

        $caller = $backtrace[ 1 ] ?? $backtrace[ 0 ];

        return implode( '', [ $caller[ 'class' ] ?? null, $caller[ 'type' ] ?? null, $caller[ 'function' ] ?? null ] );
    }

    private function getType( mixed $value ) : string {
        $class = is_string( $value ) && class_exists( $value );

        return $class ? $value : gettype( $value );

    }


}