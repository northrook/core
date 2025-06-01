<?php

declare(strict_types=1);

namespace Core\Exception;

use Throwable, Stringable;

class DuplicateEntryRuntimeException extends RuntimeException
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

        parent::__construct(
            $message,
            $previous,
        );
    }
}
