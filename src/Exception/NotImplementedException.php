<?php

declare(strict_types=1);

namespace Core\Exception;

use BadMethodCallException, Throwable;

class NotImplementedException extends BadMethodCallException
{
    public readonly bool $classExists;

    public readonly bool $interfaceExists;

    public function __construct(
        public readonly string $class,
        public readonly string $interface,
        ?string                $message = null,
        ?Throwable             $previous = null,
    ) {
        $this->classExists     = \class_exists( $this->class );
        $this->interfaceExists = \class_exists( $this->interface );

        $message ??= match ( true ) {
            ! $this->classExists     => "The class '{$this->class}' does not exist.",
            ! $this->interfaceExists => "The interface '{$this->interface}' does not exist.",
            default                  => "The {$this->class} does not implement the '{$this->interface}' interface.",
        };

        parent::__construct(
            message  : $message,
            previous : $previous ?? ErrorException::getLast(),
        );
    }
}
