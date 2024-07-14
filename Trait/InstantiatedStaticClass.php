<?php

namespace Northrook\Core\Trait;

trait InstantiatedStaticClass
{
    abstract public function __construct();

    /**
     * Ensure the class has not already been instantiated.
     *
     * - Will check if {@see SingletonClass::$instance} is set by default.
     * - `$check` will validate against {@see SingletonClass::$instance} by default.
     * - Set `$throwOnFail` to `true` to throw a {@see \LogicException}.
     * - Set `$throwOnFail` to `false` to return `$check` as boolean.
     *
     * @param bool|bool[]  $check
     * @param bool         $throwOnFail  [true]
     *
     * @return bool
     */
    final protected function instantiationCheck( bool | array $check, bool $throwOnFail = true ) : bool {

        dump( __METHOD__ );

        $pass = is_bool( $check ) ? $check : in_array( true, $check, true );

        if ( $throwOnFail && $check ) {
            throw new \LogicException(
                "The " . static::class . " has already been instantiated.\nIt cannot be re-instantiated.",
            );
        }

        return $check;
    }

    final protected function __clone() {
        throw new \LogicException( static::class . " is `static`, and should not be cloned.", );
    }
}