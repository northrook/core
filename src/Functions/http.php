<?php

declare(strict_types=1);

namespace Northrook\Core;

/**
 * Shared {@see Curl} instance for Core HTTP helpers.
 */
function curl(): Curl
{
    static $instance = null;
    $instance ??= new Curl();

    return $instance;
}
