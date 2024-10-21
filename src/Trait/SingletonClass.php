<?php

declare(strict_types=1);

namespace Northrook\Trait;

use BadMethodCallException;
use LogicException;
use Northrook\Exception\E_Class;
use Northrook\Interface\Singleton;

/**
 * Designate a class as a Singleton.
 *
 * - Should implement the {@see Singleton} interface.
 * - Store {@see static::$this} in the static property {@see SingletonClass::$__instance}.
 * - If this is done in the constructor, you should call {@see SingletonClass::instantiationCheck()} to prevent re-instantiation
 * - The constructor can be public.
 * - The {@see SingletonClass::getInstance()} method should be used to retrieve the instance.
 *   It is protected by default, you can override the visibility if needed.
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
trait SingletonClass
{
    private static ?self $__instance = null;

    /**
     * Retrieve the Singleton instance.
     *
     * @param bool  $construct
     * @param array $arguments
     *
     * @return static
     */
    protected static function getInstance(
        bool     $construct = false,
        mixed ...$arguments,
    ) : static {
        return self::$__instance ??= $construct
                ? new static( ...$arguments )
                : throw new LogicException();
    }

    /**
     * Ensure the class has not already been instantiated.
     *
     * - Will check if {@see SingletonClass::$__instance} is set by default.
     * - `$check` will validate against {@see SingletonClass::$__instance} by default.
     * - Set `$throwOnFail` to `true` to throw a {@see LogicException}.
     * - Set `$throwOnFail` to `false` to return `$check` as boolean.
     *
     * @param ?bool $check [isset(self::$instance)]
     * @param bool  $throw [false]
     *
     * @return bool
     */
    final protected function instantiationCheck( ?bool $check = null, bool $throw = false ) : bool
    {
        if ( ! $this instanceof Singleton ) {
            E_Class::error(
                '{class} is using the {trait} trait, but does not implement the {singletonInterface}.',
                [
                    'class'              => $this::class,
                    'trait'              => 'SingletonClass',
                    'singletonInterface' => Singleton::class,
                ],
            );
        }

        $check ??= isset( self::$__instance );

        if ( $throw && $check ) {
            throw new LogicException( 'The '.self::class." has already been instantiated.\nIt cannot be re-instantiated." );
        }

        return $check;
    }

    public function __serialize() : array
    {
        throw $this->singletonBadMethodCallException( __METHOD__ );
    }

    public function __unserialize( array $data ) : void
    {
        throw $this->singletonBadMethodCallException( __METHOD__ );
    }

    private function __clone() : void
    {
        throw $this->singletonBadMethodCallException( __METHOD__ );
    }

    public function __sleep() : array
    {
        throw $this->singletonBadMethodCallException( __METHOD__ );
    }

    public function __wakeup() : void
    {
        throw $this->singletonBadMethodCallException( __METHOD__ );
    }

    private function singletonBadMethodCallException( string $method ) : BadMethodCallException
    {
        return new BadMethodCallException(
            "Calling {$method} is not allowed, ".static::class.' is a Singleton.',
        );
    }
}
