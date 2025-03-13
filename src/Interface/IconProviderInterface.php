<?php

namespace Core\Interface;

use UnitEnum;

interface IconProviderInterface extends ProviderInterface
{
    public function has( string $icon ) : bool;

    /**
     * @param string                                                        $icon
     * @param null|string                                                   $fallback
     * @param null|array<array-key, ?string>|bool|float|int|string|UnitEnum ...$attributes
     *
     * @return null|View
     */
    public function get(
        string                                       $icon,
        ?string                                      $fallback = null,
        array|null|bool|float|int|string|UnitEnum ...$attributes,
    ) : ?View;
}
