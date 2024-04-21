<?php

namespace Northrook\Core;


// Abstract Singleton?

// loads to and saves from a cache dir
// has %project.dir% var based on __DIR__ ~/src/__FILE or ~/vendor/northrook/core/__FILE


class Config
{

    private static Config $instance;

    protected readonly string $projectDir;
    protected readonly string $publicDir;

    private function __construct(
        ?string $projectDir = null,
        ?string $publicDir = null,
    ) {
        $this->projectDir = $projectDir ?? $this->getProjectDir();
        $this->publicDir  = $publicDir ?? getcwd();
    }

    public static function config(
        ?string $projectDir = null,
        ?string $publicDir = null,
    ) : Config {
        return Config::$instance ?? Config::$instance = new Config(
            $projectDir,
            $publicDir,
        );
    }

    private function getProjectDir( ?string $ppend = null ) : string {
        return __FILE__;
    }

}