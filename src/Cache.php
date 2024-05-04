<?php

namespace Northrook\Core;

final class Cache
{
    private static array $objectCache = [];

    public function value( string $key, mixed $value, bool $global = false ) : mixed {

        $key = $global ? $key : $key . ':' . spl_object_id( $this );

        if ( !isset( Cache::$objectCache[ $key ] ) ) {
            Cache::$objectCache[ $key ] = $value;
        }

        return Cache::$objectCache[ $key ];
    }
}