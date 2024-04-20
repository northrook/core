<?php

namespace Northrook\Core\Exception;

use Northrook\Core\Debug\Backtrace;

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
        $this->caller = Backtrace::get()->caller;

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

    private function getType( mixed $value ) : string {
        $class = is_string( $value ) && class_exists( $value );

        return $class ? $value : gettype( $value );

    }


}