<?php

namespace Northrook\Core;

/**
 * @internal
 *
 * @author  Martin Nielsen <mn@northrook.com>
 *
 * @link    https://github.com/northrook/core
 * @todo    Provide link to documentation
 */
abstract class Type
{

    abstract public function __get( string $name );

    /**
     * {@see PathType} does not allow dynamic properties.
     *
     * @param string  $name
     * @param mixed   $value
     *
     * @return void
     */
    public function __set( string $name, mixed $value ) {}

    public function __isset( string $name ) : bool {
        return isset( $this->{$name} );
    }

    public function returnType() : string {
        return gettype( $this->value );
    }
}