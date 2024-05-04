<?php

namespace Northrook\Core;

final class Cache
{
    private static array $objectCache = [];

    public static function __callStatic( string $name, array $arguments ) {
        return match ( $name ) {
            'get' => ( new Cache() )->get( $arguments[ 0 ], true ),
        };
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