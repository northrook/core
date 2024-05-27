<?php

namespace Northrook\Core\Support;

use JetBrains\PhpStorm\Pure;
use Northrook\Core\Type\Path;

final class Str
{
    
    #[Pure]
    public static function sanitize( ?string $string, bool $stripTags = false ) : string {
        if ( $stripTags ) {
            $string = strip_tags( $string );
        }
        return htmlspecialchars( (string) $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8' );
    }


    /**
     * @param string|Path  $path
     * @param null|string  $scheme  'http' | 'https' | 'ftp' | 'ftps' | 'file' | null as any
     *
     * @return bool
     */
    public static function isURL( string | Path $path, ?string $scheme = 'https' ) : bool {

        if ( $scheme && !str_starts_with( $path, "$scheme://" ) ) {
            return false;
        }
        if ( !( str_contains( $path, "//" ) && str_contains( $path, '.' ) ) ) {
            return false;
        }

        return (bool) filter_var( $path, FILTER_VALIDATE_URL );
    }


}