<?php

declare( strict_types = 1 );

namespace Northrook\Core;

// TODO : Add option to use a Symfony CacheAdapter

/**
 * # A simple cache store.
 *
 */
final class Cache
{
    private static array $meta = [];

    /**
     * Globally available cache store.
     */
    private static array $globalCache = [];

    /**
     * Global cache for memoized functions.
     */
    private static array $callbackCache = [];

    /**
     * Cache store for a single instance.
     */
    private array $objectCache = [];

    /**
     * Cache a callback result.
     *
     * - The results will be cached for the given arguments.
     * - The cache key is a hash of the arguments.
     * - Optionally, a unique id can be provided.
     * - Manual IDs can be retrieved globally via `Cache::get( 'callback.$id' )`.
     *
     * @param callable     $callback
     * @param array        $arguments
     * @param null|string  $id
     *
     * @return mixed
     */
    public static function callback(
        callable $callback,
        array    $arguments,
        ?string  $id = null,
    ) : mixed {

        $fn = Cache::callbackKey( $callback );
        $id ??= hash( 'xxh128', print_r( $arguments, true ) );

        return Cache::$callbackCache[ $fn ][ $id ] ??= $callback( ... $arguments );

    }

    private static function callbackKey( callable $callback ) : string {

        // Format
        if ( is_array( $callback ) && count( $callback ) === 2 ) {
            $callback = $callback[ 0 ] . ':' . $callback[ 1 ];
        }

        // Ensure the callback is a string
        if ( !is_string( $callback ) ) {
            throw new \LogicException( 'Unknown callback type. The callback must return a string or an array.' );
        }

        // Set the callback key
        if ( !isset( Cache::$callbackCache[ $callback ] ) ) {
            Cache::$callbackCache[ $callback ] = [];
        }

        return $callback;
    }

    /**
     * # Clear a given cache key.
     *
     * - Passing `all` will clear all cache keys.
     * - Passing a string will clear the respective cache value.
     *
     * @param string  $key  = ['all'][$any]
     *
     * @return bool  True if the cache was cleared, false otherwise.
     */
    public static function clear( string $key ) : bool {

        if ( $key === 'all' ) {
            $empty              = empty( Cache::$globalCache );
            Cache::$globalCache = [];
            return $empty;
        }

        if ( isset( Cache::$globalCache[ $key ] ) ) {
            unset( Cache::$globalCache[ $key ] );
            return true;
        }

        return false;
    }

    /**
     * # Get the current cache store.
     *
     *  ⚠️ `$provideObjects` Returns full objects. This these can be massive.
     *
     * @param bool  $provideObjects  Full objects, instead of className.
     *
     * @return array
     */
    public static function getCacheStore( bool $provideObjects = false ) : array {


        $globalCache = Cache::$globalCache;

        if ( false === $provideObjects ) {

            foreach ( Cache::$globalCache as $key => $value ) {
                $globalCache[ $key ] = is_object( $value ) ? get_class( $value ) : $value;
            }
        }

        return [
            'callback' => Cache::$callbackCache,
            'global'   => $globalCache,
        ];
    }

    public static function __callStatic( string $name, array $arguments ) {
        return match ( $name ) {
            'get' => ( new Cache() )->get( $arguments[ 0 ], true ),
        };
    }

    public function set( string $key, mixed $value, bool $global = false, bool $override = false ) : bool {

        $key = $global ? $key : $key . ':' . spl_object_id( $this );

        if ( true === $override || !isset( Cache::$globalCache[ $key ] ) ) {
            Cache::$globalCache[ $key ] = $value;
            return true;
        }

        return false;
    }

    public function has( string $key, bool $global = false ) : bool {
        return isset( Cache::$globalCache[ $this->key( $key, $global ) ] );
    }

    public function get( string $key, bool $global = false ) : mixed {
        return Cache::$globalCache[ $this->key( $key, $global ) ];
    }

    public function value( string $key, mixed $value, bool $global = false ) : mixed {

        $key = $global ? $key : $key . ':' . spl_object_id( $this );

        if ( !isset( Cache::$globalCache[ $key ] ) ) {
            Cache::$globalCache[ $key ] = $value;
        }

        return Cache::$globalCache[ $key ];
    }

    private function key( string $key, bool $global = false ) : string {
        return $global ? $key : $key . ':' . spl_object_id( $this );
    }
}