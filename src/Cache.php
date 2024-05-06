<?php

declare( strict_types = 1 );

namespace Northrook\Core;

/**
 * # A simple cache store.
 *
 */
final class Cache
{
    private static array $objectCache = [];

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
            $empty              = empty( Cache::$objectCache );
            Cache::$objectCache = [];
            return $empty;
        }

        if ( isset( Cache::$objectCache[ $key ] ) ) {
            unset( Cache::$objectCache[ $key ] );
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

        if ( $provideObjects ) {
            return Cache::$objectCache;
        }

        $store = [];

        foreach ( Cache::$objectCache as $key => $value ) {
            $store[ $key ] = is_object( $value ) ? get_class( $value ) : $value;
        }

        return $store;
    }

    public static function __callStatic( string $name, array $arguments ) {
        return match ( $name ) {
            'get' => ( new Cache() )->get( $arguments[ 0 ], true ),
        };
    }
    
    public function has( string $key, bool $global = false ) : bool {
        return isset( Cache::$objectCache[ $this->key( $key, $global ) ] );
    }

    public function get( string $key, bool $global = false ) : mixed {
        return Cache::$objectCache[ $this->key( $key, $global ) ];
    }

    public function value( string $key, mixed $value, bool $global = false ) : mixed {

        $key = $global ? $key : $key . ':' . spl_object_id( $this );

        if ( !isset( Cache::$objectCache[ $key ] ) ) {
            Cache::$objectCache[ $key ] = $value;
        }

        return Cache::$objectCache[ $key ];
    }

    private function key( string $key, bool $global = false ) : string {
        return $global ? $key : $key . ':' . spl_object_id( $this );
    }
}