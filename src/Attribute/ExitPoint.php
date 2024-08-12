<?php

declare( strict_types = 1 );

namespace Northrook\Attribute;

use Attribute;

/**
 * Indicate that this function or method is the final step in a process.
 *
 * @link    https://github.com/northrook/core
 */
#[Attribute( Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD )]
class ExitPoint
{
    public function __construct( mixed $consumedBy = null ) {}
}