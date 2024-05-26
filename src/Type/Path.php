<?php

namespace Northrook\Core\Type;

use JetBrains\PhpStorm\Deprecated;
use Northrook\Core\Interface\Validated;
use Northrook\Core\Support\Normalize;
use Northrook\Core\Type;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * @property string  $value
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
final class Path extends Type implements Validated
{
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
            'filename'     => pathinfo( $this->value, PATHINFO_FILENAME ),
            'extension'    => pathinfo( $this->value, PATHINFO_EXTENSION ),
            'exists'       => file_exists( $this->value ),
            'isValid'      => $this->validate(),
            'isDir'        => is_dir( $this->value ),
            'isFile'       => is_file( $this->value ),
            'isWritable'   => is_writable( $this->value ),
            'isReadable'   => is_readable( $this->value ),
            'lastModified' => filemtime( $this->value ),
        };
    }

    public function append( string $string, bool $trailingSlash = true ) : Path {
        $this->value = Path::normalize( $this->value, $string, $trailingSlash );
        return $this;
    }

    public function validate() : bool {

        if ( isset( $this->isValid ) ) {
            return $this->isValid;
        }

        $this->value = Path::normalize( $this->value );

        if ( $this->strict && !file_exists( $this->value ) ) {
            $this->isValid = false;
            throw new FileNotFoundException(
                message : "The $this->type $this->value does not exist. Please verify the filename and try again.",
                path    : $this->value,
            );
        }

        return $this->isValid = true;
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
    #[Deprecated( 'Use Normalize::path() instead.', Normalize::class )]
    public static function normalize( string $string, ?string $append = null, bool $trailingSlash = true ) : string {
        return Normalize::path( $string, $append, $trailingSlash );
    }
}