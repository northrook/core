<?php

namespace Northrook\Core;


// Abstract Singleton?

// loads to and saves from a cache dir
// has %project.dir% var based on __DIR__ ~/src/__FILE or ~/vendor/northrook/core/__FILE


class Config
{
    private static self       $instance;
    protected readonly string $cacheDir;
    protected array $pools = [];
    public readonly string    $projectDir;
    public readonly string    $publicDir;

    private function __construct(
        ?string $projectDir = null,
        ?string $publicDir = null,
        ?string $cacheDir = null,
    ) {
        $this->projectDir = Config::normalizePath( $projectDir ?? $this->autoRootDir() );
        $this->publicDir  = Config::normalizePath( $publicDir ?? $this->autoRootDir( 'public' ) );
        $this->cacheDir   = $this->dir( $cacheDir ?? $this->autoRootDir( 'var/cache/core' ), '/config' );
    }

    public static function config(
        ?string $projectDir = null,
        ?string $publicDir = null,
        ?string $cacheDir = null,
    ) : static {
        return static::$instance ?? static::$instance = new static(
            $projectDir,
            $publicDir,
            $cacheDir,
        );
    }

    protected function dir( string $path, ?string $append = null ) : string {
        return Config::normalizePath( $path . DIRECTORY_SEPARATOR . $append );
    }

    private function autoRootDir( ?string $append = null ) : string {

        $composerDir = Config::normalizePath( '/vendor/' . __NAMESPACE__ );
        $vendorDir   = strstr( __DIR__, $composerDir, true );

        if ( !$vendorDir ) {
            $vendorDir = strstr( __FILE__, 'src' . DIRECTORY_SEPARATOR . 'Config.php', true );
        }

        return Config::normalizePath( $vendorDir . $append );
    }

    /**
     * @param string  $string
     *
     * @return string
     */
    protected static function normalizePath( string $string ) : string {

        $string = mb_strtolower( strtr( $string, "\\", "/" ) );

        if ( str_contains( $string, '/' ) === false ) {
            return $string;
        }

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

        if ( false === isset( pathinfo( $path )[ 'extension' ] ) ) {
            $path .= DIRECTORY_SEPARATOR;
        }

        return $path;
    }

}