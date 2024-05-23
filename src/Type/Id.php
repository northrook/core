<?php

namespace Northrook\Core\Type;

use Northrook\Core\Type;
use Northrook\Logger\Log;
use Random\RandomException;

/**
 * @property string $value
 *
 * @author  Martin Nielsen <mn@northrook.com>
 *
 */
final class Id extends Type
{

    private string $value;

    /**
     *
     * - Passing an {@see Id} will extract its value.
     * - Passing a string will validate it and convert it to an {@see Id}.
     * - Passing null will create a new {@see Id}.
     *
     * @param null|string|Id  $value
     */
    public function __construct(
        null | string | Id $value = null,
    ) {
        if ( $value instanceof Id ) {
            $value = $value->value;
        }

        $this->value = Id::normalize( !$value ? Id::generate() : $value );
    }

    public function __get( string $name ) : ?string {
        return match ( $name ) {
            'value' => $this->value,
            default => null,
        };
    }
    
    public static function normalize(
        ?string $string,
        ?string $separator = '-', // '-' | '_'
        ?string $preserve = null,
    ) : ?string {

        if ( !$string ) {
            return null;
        }

        $string = mb_strtolower( $string );
        $string = strip_tags( $string );

        $string = preg_replace( '/[^A-Za-z0-9_-]/', '-', $string );

        if ( $separator !== null ) {
            $string = str_replace( [ ' ', '-', '_', $separator ], ' ', trim( $string ) );
        }

        $key = preg_replace( "/[^\w$preserve]+/", $separator, $string );

        return trim( $key, $separator );
    }

    public static function generate( ?string $seed = null, bool $strict = false ) : string {

        if ( $seed ) {
            $base = base64_encode( $seed );
        }
        else {
            try {
                $base = random_bytes( 16 );
            }
            catch ( RandomException $exception ) {
                Log::error( $exception->getMessage() );
                if ( !$strict ) {
                    $base = uniqid( prefix : '', more_entropy : true );
                }
            }
        }

        return bin2hex( $base );
    }
}