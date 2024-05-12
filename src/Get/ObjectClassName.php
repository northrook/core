<?php

namespace Northrook\Core\Get;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated( 'Use ClassNameMethods instead', \Northrook\Core\Get\ClassNameMethods::class )]
trait ObjectClassName
{

    /**
     * Returns the class name of the current calling object.
     *
     * Does not include the namespace.
     *
     * @return string
     */
    protected function getObjectClassName() : string {
        return substr( $this::class, strrpos( $this::class, '\\' ) + 1 );
    }
}