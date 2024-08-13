<?php

declare( strict_types = 1 );

namespace Northrook;

use Northrook\Logger\Log;
use Northrook\Trait\SingletonClass;

// Inject dir.root, dir.storage... etc
// do assume some, like root, cache, and storage;
// calculate at runtime if unset.

// Extend as an EntityStore for persistence
// do not add auto-resolved above?

// Intended to be statically accessed,
// can be dynamically edited.

// Find a way, likely in the EntityStore,
// to set a history count, where a separate EntityStore
// will be generated, holding historical changes.

final class Settings
{
    use SingletonClass;

    // NOTE: Auto-generation only occurs on missing values
    private const DEFAULTS = [
        'dir.root'           => null, // auto-generate - ./
        'dir.var'            => null, // auto-generate - ./var
        'dir.cache'          => null, // auto-generate - ./var/cache
        'dir.storage'        => null, // auto-generate - ./storage
        'dir.uploads'        => null, // auto-generate - ./storage/uploads
        'dir.assets'         => null, // auto-generate - ./assets
        'dir.public'         => null, // auto-generate - ./public
        'dir.public.assets'  => null, // auto-generate - ./public/assets
        'dir.public.uploads' => null, // auto-generate - ./public/uploads
    ];

    private const GENERATE_PATH = [
        'dir.root'           => null,
        'dir.var'            => '/var',
        'dir.cache'          => '/var/cache',
        'dir.storage'        => '/storage',
        'dir.uploads'        => '/storage/uploads',
        'dir.assets'         => '/assets',
        'dir.public'         => '/public',
        'dir.public.assets'  => '/public/assets',
        'dir.public.uploads' => '/public/uploads',
    ];


    /**
     * @var bool Whether the entire {@see $settings} array is locked.
     */
    private readonly bool $frozen;

    private array $locked   = [];
    private array $settings = [];

    /**
     * @param array  $settings  Merged with defaults at runtime
     * @param bool   $freeze    Prevent the addition new settings
     */
    public function __construct(
        array $settings = [],
        bool  $lockInjected = true,
        bool  $freeze = false,
    ) {
        $this->instantiationCheck();
        $this::$instance = $this->inject(
            [
                ...$this::DEFAULTS,
                ...$settings,
            ], $lockInjected,
        );
        $this->frozen    = $freeze;
    }


    public static function get( string $setting ) : mixed {
        return Settings::getInstance( true )->settings[ $setting ]
            ??= Settings::$instance->generate( $setting );
    }

    public static function set( string | array $setting, mixed $value ) : mixed {

        if ( \in_array( $setting, Settings::$instance->locked ) ) {
            // return false;
            throw new \ValueError( "The setting '{$setting}' is frozen, and cannot be set or modified at runtime." );
        }

        return Settings::$instance->settings[ $setting ] = $value;
    }

    public static function add( string $setting, mixed $value ) : bool {
        if ( isset( Settings::$instance->settings[ $setting ] ) ) {
            return false;
        }
        Settings::$instance->settings[ $setting ] ??= $value;

        return isset( Settings::$instance->settings[ $setting ] );
    }

    public function inject( array $settings, array | bool $lock = false ) : self {
        if ( $lock ) {
            $this->locked = [ ...$this->locked, ...\array_keys( $settings ) ];
        }
        $this->settings += $settings;
        return $this;
    }

    public static function entity() : Settings {
        return Settings::$instance;
    }

    private function generate( string $setting ) : mixed {

        if ( \array_key_exists( $setting, self::GENERATE_PATH ) ) {

            $generated = normalizePath(
                [
                    $this->settings[ 'dir.root' ] ??= getProjectRootDirectory(),
                    $this::GENERATE_PATH[ $setting ],
                ],
            );

            Log::notice(
                "Generated {setting}: {result}",
                [ 'setting' => $setting, 'result' => $generated, ],
            );

            return $this->frozen
                ? $generated
                : $this->settings[ $setting ] = $generated;
        }


        return $this->settings[ $setting ] ?? null;
    }
}