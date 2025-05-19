<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Interface\SettingsProviderInterface;

trait SettingsAccessor
{
    private readonly SettingsProviderInterface $settings;

    /**
     * Autowired during the instantiation process of the containing class.
     *
     * @internal
     *
     * @param SettingsProviderInterface $provider
     *
     * @return void
     */
    final public function setSettingsProvider( SettingsProviderInterface $provider ) : void
    {
        $this->settings = $provider;
    }

    /**
     * @template Setting of null|array<array-key, scalar>|scalar
     *
     * @param string                                   $key
     * @param null|array|bool|float|int|Setting|string $default
     *
     * @return null|array|bool|float|int|string
     * @phpstan-return Setting
     */
    final protected function getSetting(
        string                           $key,
        null|array|bool|float|int|string $default,
    ) : null|array|bool|float|int|string {
        return $this->settings->get( $key, $default );
    }
}
