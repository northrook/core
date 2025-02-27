<?php

namespace Core\Interface;

use UnitEnum;

interface SettingsInterface
{
    /**
     * @param string                              $key
     * @param null|bool|float|int|string|UnitEnum $default
     *
     * @return null|bool|float|int|string|UnitEnum
     */
    public function get( string $key, mixed $default = null ) : mixed;

    public function has( string $key ) : bool;
}
