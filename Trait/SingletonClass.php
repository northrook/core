<?php

declare( strict_types = 1 );

namespace Northrook\Core\Trait;

use Northrook\Core\Exception\ClassStructureException;

/**
 * Designate a class as a Singleton.
 *
 * - Store {@see static::$this} in the static property {@see SingletonClass::$instance}.
 * - If this is done in the constructor, you should call {@see SingletonClass::instantiationCheck()} to prevent re-instantiation
 * - The constructor can be public.
 * - The {@see SingletonClass::getInstance()} method should be used to retrieve the instance.
 * - It is protected by default, you can override the visibility if needed.
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
trait SingletonClass
{
    private static ?self $instance = null;

    /**
     * Retrieve the Singleton instance.
     *
     * @param bool   $selfInstantiate
     * @param array  $arguments
     *
     * @return static
     */
    protected static function getInstance(
        bool  $selfInstantiate = false,
        mixed ...$arguments,
    ) : static {
        return self::$instance ??= $selfInstantiate
            ? new static( ...$arguments )
            : throw new ClassStructureException(
                "The " . self::class . " has not yet been instantiated.\nPlease initialise it before using the getInstance method.",
            );
    }

    /**
     * Ensure the class has not already been instantiated.
     *
     * - Will check if {@see SingletonClass::$instance} is set by default.
     * - `$check` will validate against {@see SingletonClass::$instance} by default.
     * - Set `$throwOnFail` to `true` to throw a {@see \LogicException}.
     * - Set `$throwOnFail` to `false` to return `$check` as boolean.
     *
     * @param ?bool  $check        [isset(self::$instance)]
     * @param bool   $throwOnFail  [true]
     *
     * @return bool
     */
    final protected function instantiationCheck( ?bool $check = null, bool $throwOnFail = true ) : bool {

        $check ??= isset( self::$instance );

        if ( $throwOnFail && $check ) {
            throw new \LogicException(
                "The " . self::class . " has already been instantiated.\nIt cannot be re-instantiated.",
            );
        }

        return $check;
    }

    /**
     * Singletons should not be cloned.
     *
     * @return void
     */
    private function __clone() : void {}
}