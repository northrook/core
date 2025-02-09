<?php

declare(strict_types=1);

namespace Core\Interface;

use Latte\Runtime as Latte;
use Stringable;

abstract class View implements ViewInterface
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable}.
     *
     * @return Stringable
     */
    final public function getHtml() : Stringable
    {
        if ( \class_exists( Latte\Html::class ) ) {
            return new Latte\Html( $this->__toString() );
        }
        return $this;
    }
}
