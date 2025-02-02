<?php

namespace Core\Interface;

use Psr\Cache\InvalidArgumentException;

/**
 * Interface with persistent data cache.
 *
 * Values stored here will never expire on their own.
 *
 * Interoperable with:
 * - {@see \Psr\SimpleCache\CacheInterface} `get( $key )`, `set`, `has`, `delete`, `clear`
 * - {@see \Symfony\Contracts\Cache\CacheInterface} `get( $key, $callback )` and `delete`
 */
interface StorageInterface
{
    /**
     * Check if the Storage contains a value by `$key`.
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws InvalidArgumentException if the `$key` is invalid
     */
    public function has( string $key ) : bool;

    /**
     * Retrieve an existing value from storage by `$key`.
     * If no value is found,the provided callback is called.
     * The resulting value is then set and returned.
     *
     *  - Changes _may_ be deferred by the implementing class.
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
     * Set a value by `$key`.
     *
     *  - Changes _may_ be deferred by the implementing class.
     *
     * @param string $key   the key of the item to store
     * @param mixed  $value the value of the item to store, must be serializable
     *
     * @return bool true on success and false on failure
     *
     * @throws InvalidArgumentException if the `$key` is invalid
     */
    public function set( string $key, mixed $value ) : bool;

    /**
     * Unsets a value by `$key`.
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

    /**
     * Unset all data values.
     *
     *  - Changes _may_ be deferred by the implementing class.
     *
     * @return bool true on success and false on failure
     */
    public function clear() : bool;

    /**
     * Persists any deferred changes.
     *
     * @return bool true on success and false on failure
     */
    public function commit() : bool;
}
