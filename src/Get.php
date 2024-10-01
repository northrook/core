<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Resource\Path;
use Support\Normalize;
use function Northrook\Cache\memoize;

final class Get
{
    /** # ../
     * Path resolver.
     *
     * @param string $path   = [ 'dir.root', 'dir.var', 'dir.cache', 'dir.storage', 'dir.uploads', 'dir.assets', 'dir.public', 'dir.public.assets', 'dir.public.uploads'][$any]
     * @param bool   $object
     *
     * @return null|Path|string
     */
    public static function path( string $path, bool $object = false ) : null|string|Path
    {
        return memoize(
            static function() use ( $path, $object ) {
                // If we're an actual path to something in the project, return as-is
                if ( \stripos( $path, Settings::get( 'dir.root' ) ) === 0 ) {
                    return $object ? new Path( $path ) : Normalize::path( $path );
                }

                // Determine what, if any, separator is used
                $separator = \strrpos( $path, '/' ) ?: \strrpos( $path, '\\' );

                // If the requested $path has no separator, should be a key
                if ( false === $separator ) {
                    $value = Settings::get( $path );

                    if ( ! $value ) {
                        return $path;
                    }

                    return $object ? new Path( $value ) : $value;
                }

                // Split the $path by the first $separator
                [$root, $tail] = \explode( $path[$separator], $path, 2 );

                // Resolve the $root key
                $root = Settings::get( $root );

                // If none is found, return as-is
                if ( ! $root ) {
                    $value = Normalize::path( $path );
                }
                // Combine $root and $ail
                else {
                    $value = Normalize::path( [$root, $tail] );
                }

                return $object ? new Path( $value ) : $value;
            },
            ( $object ? 'string:' : 'object:' ).$path,
        );
    }
}
