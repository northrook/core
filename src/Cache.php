<?php

declare( strict_types = 1 );

namespace Northrook\Core;

// TODO : Add option to use a Symfony CacheAdapter
use JetBrains\PhpStorm\ExpectedValues;
use Northrook\Logger\Log;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Exception\CacheException;

/**
 * # A simple cache store.
 *
 */
final class Cache
{
    public const TTL_MINUTE  = 60;
    public const TTL_HOUR    = 3600;
    public const TTL_HOUR_4  = 14400;
    public const TTL_HOUR_8  = 28800;
    public const TTL_HOUR_12 = 43200;
    public const TTL_DAY     = 86400;

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
     * Will assign the provided {@see AdapterInterface} to the asset cache.
     *
     * If no adapter is provided, a {@see PhpFilesAdapter} will be used.
     * The PhpFilesAdapter requires the PHP extension OPcache to be installed and activated.
     *
     * If OPcache is not available, `$onOPcacheError` will be used to eiter:
     * - Fall back to a {@see FilesystemAdapter}.
     *    - With or without an {@see Log::Error} message.
     * - Throw a {@see LogicException}.
     * - Ignore the error.
     *
     * @param string                 $cacheKey        The cache key
     * @param string                 $cachePath       The cache path
     * @param null|AdapterInterface  $adapter         The adapter to use, defaults to a {@see PhpFilesAdapter} if not provided
     * @param int                    $cacheTtl        The cache TTL in seconds, defaults to a day
     * @param string                 $onOPcacheError  How to handle errors
     *
     * @return AdapterInterface
     */
    public static function assignAdapterInterface(
        ?AdapterInterface $adapter = null,
        ?string           $cacheKey = null,
        ?string           $cachePath = null,
        int               $cacheTtl = Cache::TTL_DAY,
        #[ExpectedValues( values : [ 'ignore', 'log', 'throw' ] )]
        string            $onOPcacheError = 'log',
    ) : AdapterInterface {
        try {
            $cache ??= new PhpFilesAdapter( $cacheKey, $cacheTtl, $cachePath );
        }
        catch ( CacheException $exception ) {
            if ( $onOPcacheError === 'log' ) {
                Log::Error(
                    'Could not assign {adapter}, {requirement} not available. Ensure the {requirement} PHP extension is installed and activated.',
                    [
                        'adapter'     => 'PhpFilesAdapter',
                        'requirement' => 'OPcache',
                    ],
                );
            }
            elseif ( $onOPcacheError === 'throw' ) {
                throw new \LogicException(
                    message  : 'Could not assign PhpFilesAdapter, OPcache is not available. Ensure the PHP extension is installed and activated.',
                    code     : 510,
                    previous : $exception,
                );
            }
        }

        return $cache ?? new FilesystemAdapter();
    }

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