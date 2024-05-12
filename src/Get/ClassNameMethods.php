<?php

namespace Northrook\Core\Get;

trait ClassNameMethods
{

    protected function getExtendingClasses( ?string $parent ) : array {

        $classes = get_declared_classes();

        if ( $parent ) {
            return array_filter( $classes, static fn ( $class ) => is_subclass_of( $class, $parent ) );
        }

        return array_filter( $classes, static fn ( $class ) => is_subclass_of( $class, self::class ) );
    }

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