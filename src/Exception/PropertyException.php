<?php

declare(strict_types=1);

namespace Northrook\Exception;

use RuntimeException;

class PropertyException extends RuntimeException
{
    public const string
        UNINITIALIZED = 'uninitialized', // exists, but
        MISSING       = 'missing',
        INVALID       = 'invalid';

    /**
     * Construct the exception. Note: The message is NOT binary safe.
     *
     * @link https://php.net/manual/en/exception.construct.php
     *
     * @param string  $propertyName
     * @param ?string $message      the Exception message to throw
     * @param int     $code         [optional] The Exception code
     */
    public function __construct(
        public readonly string $propertyName,
        ?string                $message = null,
        int                    $code = 422,
    ) {
        $message ??= "Property '{$this->propertyName}' does not exist in '{$this->getCaller()}'.";
        parent::__construct( $message, $code );
        $this->message = $message;
        $this->code    = $code;
    }

    final protected function getCaller() : string
    {
        $backtrace = \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );

        $caller = $backtrace[1] ?? $backtrace[0];

        return \implode( '', [$caller['class'] ?? null, $caller['type'] ?? null, $caller['function']] );
    }
}
