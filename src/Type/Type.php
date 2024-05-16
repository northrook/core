<?php

namespace Northrook\Core\Type;

/**
 * @internal
 *
 * @author  Martin Nielsen <mn@northrook.com>
 *
 * @link    https://github.com/northrook/core
 * @todo    Provide link to documentation
 */
abstract class Type
{
    public function returnType() : string {
        return gettype( $this->value );
    }
}