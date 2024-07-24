<?php

declare( strict_types = 1 );

namespace Northrook\Core\Trait;

use Northrook\Core\Exception\UninitializedPropertyException;

trait InstantiatedStaticClass
{
    private static ?self $instance = null;

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
            : throw new UninitializedPropertyException( '$instance', self::class );
    }

    final protected function __clone() {
        throw new \LogicException( static::class . " is `static`, and should not be cloned.", );
    }
}