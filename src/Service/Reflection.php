<?php

namespace Northrook\Core\Service;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

final class Reflection
{

    /**
     * Constructs a ReflectionFunction object
     *
     * @param Closure|string  $class
     *
     * @return ?ReflectionFunction
     */
    public static function getFunction( Closure | string $class ) : ?ReflectionFunction {
        trigger_deprecation(
            Reflection::class,
            '1.0.0',
            __METHOD__ . ' is no longer supported',
        );
        try {
            return new ReflectionFunction( $class );
        }
        catch ( ReflectionException $e ) {
            return null;
        }
    }


    /**
     * Constructs a ReflectionClass
     *
     * @param class-string|object  $object  Either a string containing the name of
     *                                      the class to reflect, or an object.
     *
     * @return ?ReflectionClass
     */
    public static function getClass( string | object $object ) : ?ReflectionClass {
        trigger_deprecation(
            Reflection::class,
            '1.0.0',
            __METHOD__ . ' is no longer supported',
        );
        try {
            return new ReflectionClass( $object );
        }
        catch ( ReflectionException $e ) {
            return null;
        }
    }

    public static function getMethod( string $class, string $method ) : ?ReflectionMethod {
        trigger_deprecation(
            Reflection::class,
            '1.0.0',
            __METHOD__ . ' is no longer supported',
        );
        try {
            return new ReflectionMethod( $class, $method );
        }
        catch ( ReflectionException $e ) {
            return null;
        }
    }

    public static function getProperty( string $class, string $property ) : ?ReflectionProperty {
        trigger_deprecation(
            Reflection::class,
            '1.0.0',
            __METHOD__ . ' is no longer supported',
        );
        try {
            return new ReflectionProperty( $class, $property );
        }
        catch ( ReflectionException $e ) {
            return null;
        }
    }
}