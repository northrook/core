<?php

namespace Northrook\Core\Support;

final class Minify
{

    // We could potentially run this through the Stylesheet Generator
    // Should also cache the results when rendering (handle this outside of Minify)
    public static function styles( string $css ) : string {

        $css = Trim::whitespace(
            string         : Trim::comments( $css, css : true ),
            removeTabs     : true,
            removeNewlines : true,
        );

        return str_replace( ' :', ':', $css );;
    }
}