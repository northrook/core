<?php

namespace Northrook\Core\Support;

use JetBrains\PhpStorm\Pure;

final class Str
{

    #[Pure]
    public static function sanitize( ?string $string, bool $stripTags = false ) : string {
        if ( $stripTags ) {
            $string = strip_tags( $string );
        }
        return htmlspecialchars( (string) $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8' );
    }
}