<?php

declare(strict_types=1);

namespace Core\Interface;

use Stringable;

interface ViewInterface extends Stringable
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable} or `string`.
     *
     * Pass `true` to return as `string`.
     *
     * @param bool $string [false]
     *
     * @return Stringable
     */
    public function getHtml( bool $string = false ) : string|Stringable;
}
