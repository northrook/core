<?php

declare( strict_types = 1 );

namespace Northrook\Core\Type;

use Northrook\Core\Interface\Printable;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * @property string  $value
 * @property string  $filename
 * @property ?string $extension
 * @property bool    $exists
 * @property bool    $isDir
 * @property int     $lastModified
 */
class PathType extends Type implements Printable
{
    /**
     * @param string | PathType  $value   Passing a {@see PathType} will extract its value.
     * @param bool               $strict  Strict mode will throw an exception if the path does not exist.
     */
    public function __construct(
        protected string | PathType $value,
        private readonly bool       $strict = false,
    ) {
        $this->value = $value instanceof PathType ? $value->value : $value;
    }

    public function append( string $string ) : PathType {
        $this->value .= $string;
        return $this;
    }

    public function print( bool $versioning = false ) : string {
        return $this->__toString();
    }

    public function __toString() : string {
        $this->validate();
        return $this->value;
    }

    public function __get( string $name ) {
        return match ( $name ) {
            'value'        => $this->value,
            'filename'     => pathinfo( $this->value, PATHINFO_FILENAME ),
            'extension'    => pathinfo( $this->value, PATHINFO_EXTENSION ),
            'exists'       => file_exists( $this->value ),
            'isDir'        => is_dir( $this->value ),
            'lastModified' => filemtime( $this->value ),
            default        => null,
        };
    }

    /**
     * {@see PathType} does not allow dynamic properties.
     *
     * @param string  $name
     * @param mixed   $value
     *
     * @return void
     */
    public function __set( string $name, mixed $value ) {}

    public function __isset( string $name ) : bool {
        return isset( $this->{$name} );
    }

    /**
     * Normalise a `string`, assuming it is a `path`.
     *
     * - Removes repeated slashes.
     * - Normalises slashes to system separator.
     * - Prevents backtracking.
     * - Optional trailing slash for directories.
     * - No validation is performed.
     *
     * @param string   $string         The string to normalize.
     * @param ?string  $append         Optional appended string to append.
     * @param bool     $trailingSlash  Whether to append a trailing slash to the path.
     *
     * @return string  The normalized path.
     */
    public static function normalize( string $string, ?string $append = null, bool $trailingSlash = true ) : string {

        if ( $append ) {
            $string .= "/$append";
        }

        $string = mb_strtolower( strtr( $string, "\\", "/" ) );

        if ( str_contains( $string, '/' ) ) {


            $path = [];

            foreach ( array_filter( explode( '/', $string ) ) as $part ) {
                if ( $part === '..' && $path && end( $path ) !== '..' ) {
                    array_pop( $path );
                }
                elseif ( $part !== '.' ) {
                    $path[] = trim( $part );
                }
            }

            $path = implode(
                separator : DIRECTORY_SEPARATOR,
                array     : $path,
            );
        }
        else {
            $path = $string;
        }

        // If the string contains a valid extension, return it as-is
        if ( isset( pathinfo( $path )[ 'extension' ] ) && !str_contains( pathinfo( $path )[ 'extension' ], '%' ) ) {
            return $path;
        }

        return $trailingSlash ? $path . DIRECTORY_SEPARATOR : $path;
    }

    private function validate() : void {
        $this->value = static::normalize( $this->value );

        if ( $this->strict && !$this->exists ) {
            throw new FileNotFoundException(
                message : 'The path does not exist. Please verify the filename and try again.',
                path    : $this->value,
            );
        }
    }
}