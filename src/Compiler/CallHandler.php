<?php

declare(strict_types=1);

namespace Core\Compiler;

use IteratorAggregate;
use LogicException;
use Traversable;

/**
 * @template-implements IteratorAggregate<string, Call>
 */
final class CallHandler implements IteratorAggregate
{
    /** @var array<string, Call> */
    private array $stack = [];

    /** @var array<string, true> */
    private array $called = [];

    /**
     * @param array<int, array{0: string, 1: array<string,mixed>}> $stack
     */
    public function __construct( array $stack )
    {
        foreach ( $stack as [$method, $arguments] ) {
            $this->stack[$method] = new Call( $method, $arguments, $this );
        }
    }

    public function called( string $name ) : bool
    {
        return isset( $this->called[$name] );
    }

    public function setCalled( string $name ) : void
    {
        $this->called[$name] = true;
    }

    public function getIterator() : Traversable
    {
        $stack = \array_keys( $this->stack );

        while ( $stack ) {
            $progress = false;

            foreach ( $stack as $i => $name ) {
                if ( $this->called( $name ) ) {
                    unset( $stack[$i] );

                    continue;
                }

                yield $name => $this->stack[$name];
                $this->setCalled( $name );
                unset( $stack[$i] );
                $progress = true;
            }

            if ( ! $progress ) {
                throw new LogicException( 'Unresolvable hook dependency cycle detected.' );
            }
        }
    }
}
