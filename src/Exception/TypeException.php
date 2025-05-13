<?php

declare(strict_types=1);

namespace Core\Exception;

use InvalidArgumentException;
use Throwable;

final class TypeException extends InvalidArgumentException
{
    public readonly string $type;

    /**
     * @param 'array'|'bool'|'float'|'int'|'null'|'object'|'string'|class-string $expected
     * @param mixed                                                              $argument
     * @param null|string                                                        $message
     * @param null|Throwable                                                     $previous
     */
    public function __construct(
        public readonly string $expected,
        mixed                  $argument,
        ?string                $message = null,
        ?Throwable             $previous = null,
    ) {
        $type       = \gettype( $argument );
        $this->type = match ( $type ) {
            'boolean' => 'bool',
            'integer' => 'int',
            'double'  => 'float',
            'object'  => ( \class_exists( $argument, false ) && \is_object( $argument ) ) ? $argument::class : 'object',
            default   => $type,
        };
        $message ??= "Expected '{$this->expected}', but got '{$this->type}'";
        parent::__construct( $message, 0, $previous );
    }
}
