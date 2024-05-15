<?php

namespace Northrook\Core;


// Abstract Singleton?

// loads to and saves from a cache dir
// has %project.dir% var based on __DIR__ ~/src/__FILE or ~/vendor/northrook/core/__FILE


use Northrook\Core\Type\PathType;

class Config
{
    private static self       $instance;
    protected readonly string $cacheDir;
    protected array           $pools = [];
    public readonly string    $projectDir;
    public readonly string    $publicDir;

    private function __construct(
        ?string $projectDir = null,
        ?string $publicDir = null,
        ?string $cacheDir = null,
    ) {
        $this->projectDir = $this->dir( $projectDir ?? $this->autoRootDir() );
        $this->publicDir  = $this->dir( $publicDir ?? $this->autoRootDir( 'public' ) );
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
        return PathType::normalize( $path . DIRECTORY_SEPARATOR . $append );
    }

    private function autoRootDir( ?string $append = null ) : string {

        $composerDir = PathType::normalize( '/vendor/' . __NAMESPACE__ );
        $vendorDir   = strstr( __DIR__, $composerDir, true );

        if ( !$vendorDir ) {
            $vendorDir = strstr( __FILE__, 'src' . DIRECTORY_SEPARATOR . 'Config.php', true );
        }

        return PathType::normalize( $vendorDir . $append );
    }

}