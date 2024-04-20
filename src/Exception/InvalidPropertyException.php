<?php

namespace Northrook\Core\Exception;

use Northrook\Logger\Debug;

class InvalidPropertyException extends \Exception
{

    public readonly string $caller;

    public function __construct(
        public readonly string $invalidProperty,
        public readonly string $requiredProperty,
        ?string                $message = null,
        int                    $code = 0,
        ?\Throwable            $previous = null,
    ) {

        $this->caller =Debug::backtrace()->getCaller();

        $message ??=  "Invalid property '{$this->invalidProperty}' in '{$this->caller}', should be '{$this->requiredProperty}'.";

        parent::__construct( $message, $code, $previous );
    }

}