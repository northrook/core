<?php

declare( strict_types = 1 );

namespace Northrook;

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
final class Env
{
    public const string
            PRODUCTION = 'prod',
            DEVELOPMENT = 'dev',
            STAGING = 'staging';

    /**
     * @var bool true if the App instance has been instantiated
     */
    private static bool $instantiated = false;

    /**
     * @var bool<Debug>
     */
    private static bool $debug;

    /**
     * @var string<Env>
     */
    private static string $environment = Env::DEVELOPMENT;

    /**
     * @param string<Env>  $env       The environment to check against
     * @param bool<Debug>  $debug     Whether to enable debug mode
     * @param bool         $override  Whether to allow overriding the Env properties
     */
    public function __construct(
            string $env,
            ?bool  $debug = null,
            bool   $override = false,
    )
    {
        if ( Env::$instantiated && !$override ) {
            throw new \LogicException(
                    'The ' . Env::class . ' instance has already been instantiated.
                
                If this was intentional, you can call the constructor with the third argument `override` argument set to `true`.',
            );
        }

        foreach ( [ Env::DEVELOPMENT, Env::STAGING, Env::PRODUCTION ] as $environment ) {
            if ( \str_starts_with( $env, $environment ) ) {
                Env::$environment = $environment;
            }
        }

        Env::$debug        = $debug;
        Env::$instantiated = true;
    }

    /**
     * Check if the current environment is production.
     *
     * Will Log a notice leven entry if {@see Env::$debug} mode is enabled in {@see Env::PRODUCTION}.
     *
     * @return bool<Env>
     */
    public static function isProduction() : bool
    {
        if ( Env::isDebug() ) {
            Log::Notice(
                    message : '{debug} is enabled in {environment}',
                    context : [ 'debug' => 'debug', 'environment' => Env::$environment, ],
            );
        }
        return Env::$environment === Env::PRODUCTION;
    }

    /**
     * @return bool<Env>
     */
    public static function isDevelopment() : bool
    {
        return Env::$environment === Env::DEVELOPMENT;
    }

    /**
     * @return bool<Env>
     */
    public static function isStaging() : bool
    {
        return Env::$environment === Env::STAGING;
    }

    /**
     * @return bool<Debug>
     */
    public static function isDebug() : bool
    {
        return Env::$debug ??= Env::$environment !== Env::PRODUCTION;
    }

}