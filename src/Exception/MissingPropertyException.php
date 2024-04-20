<?php

namespace Northrook\Core\Exception;

use Northrook\Logger\Debug;

class MissingPropertyException extends \Exception
{

    public readonly string $caller;

    public function __construct(
        public readonly string $propertyName,
        ?string                $message = null,
        int                    $code = 0,
        ?\Throwable            $previous = null,
    ) {

        $this->caller =Debug::backtrace()->getCaller();

        $message ??= "Property '{$this->propertyName}' does not exist in '{$this->caller}'.";

        parent::__construct( $message, $code, $previous );
    }

}