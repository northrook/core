<?php

namespace Core\Interface;

use UnitEnum;
use InvalidArgumentException;
use LogicException;

interface SettingsProviderInterface extends ProviderInterface
{
    /**
     * Get a setting by its key.
     *
     * If a no setting is found, but a valid `set` key and `value` is provided, and given the current `user` has relevant permissions, the Setting will be set and saved.
     *
     * @template Setting of null|array<array-key, scalar>|scalar
     *
     * @param string  $key
     * @param Setting $default
     *
     * @return null|array|bool|float|int|string
     * @phpstan-return Setting
     *
     * @throws InvalidArgumentException if the setting does not exist
     */
    public function get(
        string                           $key,
        null|array|bool|float|int|string $default,
    ) : null|array|bool|float|int|string;

    /**
     * Return an array of previous versions of a given setting.
     *
     * @param string   $settings
     * @param null|int $limit
     *
     * @return array<int, null|array<array-key, scalar>|scalar>
     */
    public function versions( string $settings, ?int $limit = null ) : array;

    /**
     * Attempt to restore a given `setting` from a previous version by versionId.
     *
     * @param string $setting
     * @param int    $versionId
     *
     * @return bool
     * @throws LogicException on error
     */
    public function restore( string $setting, int $versionId ) : bool;

    /**
     * Add one or more settings.
     *
     * If the `key` matches a setting and the `user` has permissions, it will be updated.
     *
     * @param array<string, null|array<array-key, scalar>|scalar> $parameters
     *
     * @throws LogicException if the setting cannot be updated or added
     */
    public function add( array $parameters ) : void;

    /**
     * Check if a given Setting is defined.
     *
     * @param string $setting
     *
     * @return bool
     */
    public function has( string $setting ) : bool;

    /**
     * Get all defined Settings.
     *
     * @return array<string, null|array<array-key, scalar>|scalar>
     */
    public function all() : array;

    /**
     * Reset hierarchy:
     * - User can reset own settings.
     * - Site settings require [ADMIN] or higher
     * - Server configuration unaffected.
     *
     * @return void
     */
    public function reset() : void;

    /**
     * Removes a parameter.
     *
     * @param string $name
     */
    public function remove( string $name ) : void;

    /**
     * Sets a service container parameter.
     *
     * @param string                               $name
     * @param null|array<array-key, scalar>|scalar $value
     *
     * @throws LogicException if the parameter cannot be set
     */
    public function set( string $name, array|bool|string|int|float|UnitEnum|null $value ) : void;
}
