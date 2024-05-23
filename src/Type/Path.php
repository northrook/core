<?php

namespace Northrook\Core\Type;

use Northrook\Core\Exception\FileTypeException;
use Northrook\Core\Type;
use Northrook\Logger\Log;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * @property string  $value
 * @property string  $type
 * @property string  $filename
 * @property ?string $extension
 * @property bool    $exists
 * @property bool    $isValid
 * @property bool    $isDir
 * @property bool    $isFile
 * @property bool    $isUrl
 * @property bool    $isWritable
 * @property int     $lastModified
 */
final class Path extends Type
{
    private string $type; // 'file' | 'dir' | 'url
    private string $value;
    private bool   $isValid;


    public function __construct(
        string | Path         $value,
        private readonly bool $strict = false,
    ) {
        $this->value = $value instanceof Path ? $value->value : $value;
    }

    public function __toString() : string {
        $this->validate();
        return $this->value;
    }

    public function __get( string $name ) {
        return match ( $name ) {
            'value'        => $this->value,
            'type'         => $this->type,
            'filename'     => pathinfo( $this->value, PATHINFO_FILENAME ),
            'extension'    => pathinfo( $this->value, PATHINFO_EXTENSION ),
            'exists'       => file_exists( $this->value ),
            'isValid'      => $this->validate(),
            'isUrl'        => Path::isUrl( $this->value ),
            'isDir'        => is_dir( $this->value ),
            'isFile'       => is_file( $this->value ),
            'isWritable'   => is_writable( $this->value ),
            'isReadable'   => is_readable( $this->value ),
            'lastModified' => filemtime( $this->value ),
        };
    }

    private function validate() : bool {
        $this->value = Path::normalize( $this->value );
        $this->type  = $this->getType();

        if ( !$this->exists ) {

            $this->isValid = false;

            if ( $this->strict ) {
                throw new FileNotFoundException(
                    message : 'The path does not exist. Please verify the filename and try again.',
                    path    : $this->value,
                );
            }
        }

        if ( $this->type === 'unknown' ) {

            $this->isValid = false;

            if ( $this->strict ) {
                throw new FileTypeException(
                    message : 'The path is not a file or directory.',
                    path    : $this->value,
                );
            }
        }


        if ( $this->type === 'url' ) {
            $headers = get_headers( $this->value, 1 );

            if ( false === $headers || false === str_contains( $headers[ 0 ], '200' ) ) {
                return $this->isValid = false;
            }
        }
        
        if ( $this->exists ) {
            return $this->isValid = true;
        }

        return $this->isValid = false;
    }

    private function getType() : string {

        if ( Path::isUrl( $this->value ) ) {
            return 'url';
        }
        if ( is_dir( $this->value ) ) {
            return 'dir';
        }
        if ( is_file( $this->value ) ) {
            return 'file';
        }

        Log::Error(
            'Could not determine Path type for {path}. Returned string {return}.',
            [ 'path' => $this->value, 'return' => 'unknown' ],
        );

        return 'unknown';
    }

    /**
     * @param string|Path  $path
     * @param null|string  $scheme  'http' | 'https' | 'ftp' | 'ftps' | 'file' | null as any
     *
     * @return bool
     */
    public static function isUrl( string | Path $path, ?string $scheme = 'https' ) : bool {
        if ( $scheme && !str_starts_with( $path, $scheme . '://' ) ) {
            return false;
        }
        if ( !( str_contains( $path, "://" ) && str_contains( $path, '.' ) ) ) {
            return false;
        };

        return filter_var( $path, FILTER_VALIDATE_URL );
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
}