<?php

namespace Core\Interface;

use Stringable;

interface ViewInterface extends Stringable
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable}.
     *
     * @return Stringable
     */
    public function getHtml() : Stringable;
}
