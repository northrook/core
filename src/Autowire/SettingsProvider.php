<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Compiler\Autowire;
use Core\Interface\SettingsInterface;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated( 'Moved to Contracts as Settings' )]
trait SettingsProvider
{
    private readonly SettingsInterface $settings;

    /**
     * Autowired during the instantiation process of the containing class.
     *
     * @internal
     *
     * @param SettingsInterface $provider
     *
     * @return void
     *
     * @final
     */
    #[Autowire]
    final public function setSettingsProvider( SettingsInterface $provider ) : void
    {
        $this->settings = $provider;
    }

    /**
     * @template T_Setting of null|array<array-key, scalar>|scalar
     *
     * @param non-empty-string $key
     * @param T_Setting        $default
     *
     * @return T_Setting
     *
     * @final
     */
    final protected function getSetting(
        string $key,
        mixed  $default,
    ) : mixed {
        return $this->settings->get( $key, $default );
    }
}
