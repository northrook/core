<?php

declare( strict_types = 1 );

namespace Northrook\Core\Trait;

trait PropertyAccessor
{

    abstract public function __get( string $property );

    /**
     * Check if the property exists.
     *
     * @param string  $property
     *
     * @return bool
     */
    public function __isset( string $property ) : bool {
        return isset( $this->$property );
    }

    public function __set( string $name, mixed $value ) {
        throw new \LogicException( $this::class . ' properties are read-only.' );
    }
}