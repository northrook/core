<?php

namespace Northrook\Core\Support;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use Northrook\Core\Type\Path;

final class Str
{
    
    /**
     * @param string[]     $string
     * @param string       $separator
     * @param null|string  $case
     *
     * @return string
     */
    public static function key(
        string | array $string,
        string         $separator = '-',
        #[ExpectedValues( values : [
            null,
            'strtoupper',
            'strtolower',
            // 'camel',
            // 'snake'
        ] )]
        ?string        $case = 'strtolower',
    ) : string {

        $string = is_array( $string ) ? implode( $separator, $string ) : $string;

        $string = preg_replace( "/[^A-Za-z0-9$separator]/", $separator, $string );
        $string = implode( $separator, array_filter( explode( $separator, $string ) ) );

        return match ( $case ) {
            'strtoupper' => strtoupper( $string ),
            'strtolower' => strtolower( $string ),
            // 'camel'      => Str::camel( $string ),
            // 'snake'      => Str::snake( $string ),
            default      => $string,
        };
    }

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


    // Split string methods ----------------------------------------------------------------------


    /** Returns a substring after the first or last occurrence of a $needle in a $string.
     *
     * @param string  $string
     * @param string  $needle
     * @param bool    $last
     * @param bool    $strict
     *
     * @return null|string
     */
    public static function after(
        string $string,
        string $needle,
        bool   $last = false,
        bool   $strict = false,
    ) : ?string {

        if ( $last ) {
            $needle = strrpos( $string, $needle );
        }
        else {
            $needle = strpos( $string, $needle );
        }

        if ( $strict && $needle === false ) {
            return null;
        }

        if ( $needle !== false ) {
            return substr( $string, $needle + 1 );
        }

        return $string;
    }

    /** Returns a substring before the first or last occurrence of a $needle in a $string.
     *
     * @param string        $string
     * @param string|array  $match
     * @param bool          $last
     *
     * @return null|string
     */
    public static function before(
        string         $string,
        string | array $match,
        bool           $last = false,
    ) : ?string {

        $needles = [];
        foreach ( (array) $match as $value ) {
            if ( $last ) {
                $needle = strrpos( $string, $value );
            }
            else {
                $needle = strpos( $string, $value );
            }

            if ( $needle !== false ) {
                $needles[] = $needle;
            }
        }

        if ( empty( $needles ) ) {
            return $string;
        }

        $needle = $last ? max( $needles ) : min( $needles );

        return substr( $string, 0, $needle );
    }

}