<?php

namespace Core\Interface;

use InvalidArgumentException;

/**
 * Interface with persistent data cache.
 */
interface StorageInterface
{
    /**
     * Retrieve an existing value from storage by `$key`.
     * If no value is found,the provided callback is called.
     * The resulting value is then stored in storage and returned.
     *
     * @template Value
     *
     * @param string           $key
     * @param callable():Value $callback
     *
     * @return Value
     *
     * @throws InvalidArgumentException if the `$key` is invalid
     */
    public function get( string $key, callable $callback ) : mixed;

    /**
     * Unsets a {@see LocalStorage::$data} value by `$key`.
     *
     * - Changes _may_ be deferred by the implementing class.
     *
     * @param string $key
     *
     * @return bool true if the `$key` existed and was unset
     *
     * @throws InvalidArgumentException if the `$key` is invalid
     */
    public function delete( string $key ) : bool;
}
