<?php

namespace Northrook\Core\Exception;

use Northrook\Core\Debug\Backtrace;

class UninitializedPropertyException extends \Exception
{

    public readonly string  $caller;
    public readonly ?string $propertyValue;

    public function __construct(
        public readonly string $propertyName,
        mixed                  $propertyValue = null,
        ?string                $message = null,
        int                    $code = 0,
        ?\Throwable            $previous = null,
    ) {

        $this->caller = Backtrace::get()->getCaller();

        $message ??= "Property '{$this->propertyName}' is not initialized in '{$this->caller}'.";

        if ( $propertyValue ) {
            $this->propertyValue =
                $propertyValue instanceof \stdClass ? $propertyValue::class : print_r( $propertyValue, true );

            $message .= " Expected value '{$this->propertyValue}'.";
        }
        else {
            $this->propertyValue = $propertyValue;
        }


        parent::__construct( $message, $code, $previous );
    }

}