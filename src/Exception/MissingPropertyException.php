<?php

namespace Northrook\Core\Exception;

use Northrook\Core\Debug\Backtrace;

class MissingPropertyException extends \Exception
{
    public readonly string $caller;

    public function __construct(
        public readonly string $propertyName,
        ?string                $message = null,
        int                    $code = 0,
        ?\Throwable            $previous = null,
    ) {

        $this->caller = Backtrace::get()->caller;

        $message ??= "Property '{$this->propertyName}' does not exist in '{$this->caller}'.";

        parent::__construct( $message, $code, $previous );
    }

}