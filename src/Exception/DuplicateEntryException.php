<?php

namespace Core\Exception;

use LogicException, Throwable, Stringable;

class DuplicateEntryException extends LogicException
{
    public function __construct(
        ?string                  $message = null,
        ?string                  $key = null,
        protected readonly mixed $value = null,
        ?Throwable               $previous = null,
    ) {
        if ( ! $message && $key ) {
            $message = "The key '{$key}' already exists.";
        }

        if ( ! $message && ( \is_scalar( $value ) || $value instanceof Stringable ) ) {
            $value   = (string) $value;
            $message = "The value '{$value}' already exists.";
        }

        parent::__construct( (string) $message, E_ERROR, $previous );
    }
}
