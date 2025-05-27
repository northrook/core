<?php

namespace Core\Interface;

use LogicException;

interface SettingsInterface extends ProviderInterface
{
    /**
     * Check if a given Setting is defined.
     *
     * @param string $setting
     *
     * @return bool
     */
    public function has( string $setting ) : bool;

    /**
     * Get a setting by its key.
     *
     * If no setting is found, but a valid `set` key and `value` is provided, and given the current `user` has relevant permissions, the Setting will be set and saved.
     *
     * @template Setting of null|array<array-key, scalar>|scalar
     *
     * @param string  $setting
     * @param Setting $default
     *
     * @return null|array|bool|float|int|string
     * @phpstan-return Setting
     */
    public function get(
        string                           $setting,
        null|array|bool|float|int|string $default,
    ) : null|array|bool|float|int|string;

    /**
     * Set a `setting`, overriding existing values.
     *
     * @param string                               $setting
     * @param null|array<array-key, scalar>|scalar $set
     *
     * @return self
     *
     * @throws LogicException if the parameter cannot be set
     */
    public function set(
        string $setting,
        mixed  $set,
    ) : self;

    /**
     * Add one or more settings.
     *
     * If the `key` matches a setting and the `user` has permissions, it will be updated.
     *
     * @param string                               $setting
     * @param null|array<array-key, scalar>|scalar $add
     *
     * @return self
     *
     * @throws LogicException if the setting cannot be updated or added
     */
    public function add(
        string $setting,
        mixed  $add,
    ) : self;

    /**
     * Get all defined Settings.
     *
     * @return array<string, null|array<array-key, scalar>|scalar>
     */
    public function all() : array;

    /**
     * Reset hierarchy:
     * - User can reset their own settings.
     * - Site settings require [ADMIN] or higher
     * - Server configuration unaffected.
     *
     * @return void
     */
    public function reset() : void;
}
