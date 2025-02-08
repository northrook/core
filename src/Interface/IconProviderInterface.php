<?php

namespace Core\Interface;

use Core\View\Element\Attributes;

interface IconProviderInterface
{
    public function has( string $icon ) : bool;

    /**
     * @param string                                                              $icon
     * @param array<string, null|array<array-key, string>|bool|string>|Attributes $attributes
     * @param null|string                                                         $fallback
     *
     * @return null|IconInterface
     */
    public function get( string $icon, array|Attributes $attributes = [], ?string $fallback = null ) : ?IconInterface;
}
