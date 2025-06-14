<?php

declare(strict_types=1);

namespace Core\Interface;

use JetBrains\PhpStorm\Deprecated;

/**
 * Designate a class as printable.
 *
 * - Ensure the class implements the {@see Printable} interface.
 *
 * @method string __toString()
 *
 * @phpstan-require-implements Printable
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
#[Deprecated( 'Removed' )]
trait PrintableClass
{
    /**
     * Nullable implementation of the {@see __toString()} method.
     *
     * @return ?string
     */
    public function toString() : ?string
    {
        return $this->__toString() ?: null;
    }

    /**
     * Echo {@see self::__toString}.
     *
     * @return void
     */
    public function print() : void
    {
        echo $this->__toString();
    }
}
