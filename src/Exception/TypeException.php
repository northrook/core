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
        $type = \is_object( $argument )
                ? $argument::class
                : \gettype( $argument );

        $this->type = match ( $type ) {
            'boolean' => 'bool',
            'integer' => 'int',
            'double'  => 'float',
            default   => $type,
        };

        parent::__construct(
            $message ?? "Expected '{$this->expected}', but got '{$this->type}'",
            E_ERROR,
            $previous ?? ErrorException::getLast(),
        );
    }
}
