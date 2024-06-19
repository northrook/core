<?php

declare( strict_types = 1 );

namespace Northrook\Core;

use JetBrains\PhpStorm\ExpectedValues;
use Northrook\Logger\Log;

/**
 * @template Debug of bool
 * @template Env of non-empty-string
 *
 * @link    https://github.com/northrook/core Documentation
 *
 * @author  Martin Nielsen <mn@northrook.com>
 *
 */
class Env
{
    private const ENVIRONMENTS = [ 'prod', 'dev', 'staging', Env::PRODUCTION, Env::DEVELOPMENT, Env::STAGING ];

    public const PRODUCTION  = 'prod';
    public const DEVELOPMENT = 'dev';
    public const STAGING     = 'staging';

    /**
     * @var bool true if the App instance has been instantiated
     */
    private static bool $instantiated = false;

    /**
     * @var bool<Debug>
     */
    private static bool $debug = false;

    /**
     * @var string<Env>
     */
    private static string $environment = 'dev';

    /**
     * @param string<Env>  $env       The environment to check against
     * @param bool<Debug>  $debug     Whether to enable debug mode
     * @param bool         $override  Whether to allow overriding the Env properties
     */
    public function __construct(
        #[ExpectedValues( Env::ENVIRONMENTS )]
        string $env,
        bool   $debug,
        bool   $override = false,
    ) {

        if ( Env::$instantiated && !$override ) {
            throw new \LogicException(
                'The ' . Env::class . ' instance has already been instantiated.
                
                If this was intentional, you can call the constructor with the third argument `override` argument set to `true`.',
            );
        }

        if ( str_starts_with( $env, Env::PRODUCTION ) ) {
            $env = Env::PRODUCTION;
        }

        if ( str_starts_with( $env, Env::DEVELOPMENT ) ) {
            $env = Env::DEVELOPMENT;
        }

        if ( str_starts_with( $env, Env::STAGING ) ) {
            $env = Env::STAGING;
        }

        Env::$environment = strtolower( $env );
        Env::$debug       = $debug;

        Env::$instantiated = true;
    }

    public static function __callStatic( string $name, array $arguments ) : bool {
        $name = str_starts_with( $name, 'is' ) ? substr( $name, 2 ) : $name;
        return Env::$environment === strtolower( $name );
    }

    /**
     * Retrieve an array of all the properties of the {@see Env} instance.
     *
     * Optionally, you can pass a property name to retrieve a specific property.
     *
     * @param ?string  $property  Return a specific property of the {@see Env}
     *
     * @return bool<Debug>|string<Env>|array
     */
    public static function get(
        #[ExpectedValues( [ 'environment', 'debug', 'instantiated' ] )]
        ?string $property = null,
    ) : bool | string | array {
        return $property
            ? Env::${$property} ?? false
            : [
                'instantiated' => Env::$instantiated,
                'environment'  => Env::$environment,
                'debug'        => Env::$debug,
            ];
    }

    /**
     * Check if the current environment is production.
     *
     * Will Log a notice leven entry if {@see Env::$debug} mode is enabled in {@see Env::PRODUCTION}.
     *
     * @return bool<Env>
     */
    public static function isProduction() : bool {
        if ( Env::$debug ) {
            Log::Notice(
                message : '{debug} is enabled in {environment}',
                context : [ 'debug' => 'debug', 'environment' => Env::$environment, ],
            );
        }
        return Env::$environment === 'prod';
    }

    /**
     * @return bool<Env>
     */
    public static function isDevelopment() : bool {
        return Env::$environment === 'dev';
    }

    /**
     * @return bool<Env>
     */
    public static function isStaging() : bool {
        return Env::$environment === 'staging';
    }

    /**
     * @return bool<Debug>
     */
    public static function isDebug() : bool {
        return Env::$debug;
    }

}