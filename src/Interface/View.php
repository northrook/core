<?php

declare(strict_types=1);

namespace Core\Interface;

use Stringable;

abstract class View implements ViewInterface
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable}.
     *
     * @return Stringable
     */
    public function getHtml() : Stringable
    {
        return $this;
    }
}
