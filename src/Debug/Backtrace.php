<?php

declare( strict_types = 1 );

namespace Northrook\Core\Debug;

/**
 * Debug_backtrace helper class.
 *
 * @author  Martin Nielsen <mn@northrook.com>
 *
 * @link    https://github.com/northrook/logger
 * @todo    Provide link to documentation
 */
final class Backtrace
{

    private array          $backtrace;
    public readonly string $caller;

    public function __invoke() : array {
        return $this->backtrace;
    }

    private function __construct(
        int  $limit = 0,
        ?int $depth = null,
    ) {
        $this->backtrace = array_slice(
            debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit ),
            0,
            $depth,
        );

        $this->caller = $this->getCaller();

    }


    public function getCaller( ?int $key = null ) : string {

        $backtrace = $key ? $this->backtrace[ $key ] : end( $this->backtrace );
        return $backtrace[ 'class' ] . $backtrace[ 'type' ] . $backtrace[ 'function' ];
    }

    public function getLine( ?int $key = null ) : int {
        $backtrace = $key ? $this->backtrace[ $key ] : end( $this->backtrace );
        return $backtrace[ 'line' ];
    }

    public function getFile( ?int $key = null ) : string {
        $backtrace = $key ? $this->backtrace[ $key ] : end( $this->backtrace );
        return $backtrace[ 'file' ];
    }

    public function getClass( ?int $key = null ) : string {
        $backtrace = $key ? $this->backtrace[ $key ] : end( $this->backtrace );
        return $backtrace[ 'class' ];
    }

    public static function get( int $limit = 0, ?int $depth = null ) : Backtrace {
        return new Backtrace( $limit, $depth );
    }

}