<?php

namespace Northrook\Core\Type;

/**
 * @property mixed $value
 */
trait ValueTypeTrait
{
    public function returnType() : string {
        return gettype( $this->value );
    }
}