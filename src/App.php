<?php

namespace Northrook\Core;

use JetBrains\PhpStorm\ExpectedValues;

final class App
{
    private static ?App $instance;

    public function __construct(
        public readonly string $environment,
        public readonly bool   $debug,
        public readonly bool   $public,
        public readonly string $projectDir,
    ) {
        App::$instance = $this;
    }


    /**
     * @param string  $is
     *
     * @return bool
     * @todo 'public' functionality reads the Site Settings Entity, but this is not yet implemented
     */
    public static function env(
        #[ExpectedValues( [ 'dev', 'prod', 'debug', 'public' ] )]
        string $is,
    ) : bool {
        return match ( $is ) {
            'dev'    => App::get()->environment === 'dev',
            'prod'   => App::get()->environment === 'prod',
            'debug'  => App::get()->debug,
            'public' => App::get()->public,
            default  => false,
        };
    }

    private static function get() : App {
        return App::$instance ?? throw new \RuntimeException( App::class . ' has not been instantiated.' );
    }
}