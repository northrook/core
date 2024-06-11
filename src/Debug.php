<?php

namespace Northrook\Core;

use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\ErrorHandler;


final class Debug
{
    public static function enable(
        ?string $title = null,
        #[ExpectedValues( values : [ Env::PRODUCTION, Env::DEVELOPMENT, Env::STAGING ] )]
        string  $env = Env::DEVELOPMENT,
        bool    $debug = true,
        bool    $styles = true,
    ) : void {
        new Env( $env, $debug );

        ErrorHandler\Debug::enable();

        if ( $styles ) {
            Debug::echoStyles();
        }

        if ( $title ) {
            echo "<div style='display: block; font-family: monospace; opacity: .5'>{$title}</div>";
        }
    }

    private static function echoStyles() : void {
        echo <<<STYLE
            <style>
                body {
                    font-family: sans-serif;
                }
                body pre.sf-dump .sf-dump-ellipsis {
                    direction: rtl;
                }
                body xmp, body pre {
                    max-width: 100%;
                    white-space: pre-wrap;
                }
            </style>
        STYLE;
    }
}