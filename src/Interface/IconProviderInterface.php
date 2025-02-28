<?php

namespace Core\Interface;

use UnitEnum;

interface IconProviderInterface
{
    public function has( string $icon ) : bool;

    /**
     * @param string                                                         $icon
     * @param null|string                                                    $fallback
     * @param array<array-key, string>|bool|float|int|null[]|string|UnitEnum $attributes
     *
     * @return null|View
     */
    public function get(
        string                                       $icon,
        ?string                                      $fallback = null,
        array|bool|string|int|float|UnitEnum|null ...$attributes,
    ) : ?View;
}
