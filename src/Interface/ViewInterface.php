<?php

namespace Core\Interface;

use JetBrains\PhpStorm\Deprecated;
use Stringable;

#[Deprecated( 'Moved to Contracts' )]
interface ViewInterface extends Stringable
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable}.
     *
     * @return Stringable
     */
    public function getHtml() : Stringable;
}
