<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Interface\SettingsInterface;

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
    final public function setSettingsProvider( SettingsInterface $provider ) : void
    {
        $this->settings = $provider;
    }

    /**
     * @template T_setting of null|array<array-key, scalar>|scalar
     *
     * @param string    $key
     * @param T_setting $default
     *
     * @return T_setting
     *
     * @final
     */
    final protected function getSetting(
        string                           $key,
        null|array|bool|float|int|string $default,
    ) : mixed {
        return $this->settings->get( $key, $default );
    }
}
