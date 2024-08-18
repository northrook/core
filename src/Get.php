<?php

declare( strict_types = 1 );

namespace Northrook;

use function Northrook\Cache\memoize;
use Northrook\Resource\Path;

final class Get
{

    /** # ../
     * Path resolver
     *
     * @param string  $path  = [ 'dir.root', 'dir.var', 'dir.cache', 'dir.storage', 'dir.uploads', 'dir.assets', 'dir.public', 'dir.public.assets', 'dir.public.uploads'][$any]
     */
    public static function path( string $path, bool $object = false ) : null | string | Path {
        return memoize(
            static function () use ( $path, $object ) {

                $separator = \strrpos( $path, '/' ) ?: \strrpos( $path, '\\' );

                if ( $separator === false ) {
                    $value = Settings::get( $path );

                    if ( !$value ) {
                        return $path;
                    }

                    return $object ? new Path( $value ) : $value;
                }

                [ $root, $tail ] = \explode( $path[ $separator ], $path, 2 );

                $root = Settings::get( $root );

                if ( !$root ) {
                    return $path;
                }

                $value = normalizePath( [ $root, $tail ] );

                if ( !$value ) {
                    return null;
                }

                return $object ? new Path( $value ) : $value;
            },
            ( $object ? 'string:' : 'object:' ) . $path,
        );
    }

}