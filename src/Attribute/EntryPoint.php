<?php

declare( strict_types = 1 );

namespace Northrook\Attribute;

use Attribute;

/**
 * Indicate that this function or method is the first step in a process.
 *
 * @link    https://github.com/northrook/core
 */
#[Attribute( Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD )]
class EntryPoint
{

    /**
     * @param null|string  $via  = ['config/service.php', 'autowire', 'new', 'static'][$any]
     * @param null|string  $usedBy
     */
    public function __construct(
            ?string $via = null,
            ?string $usedBy = null,
    ) {}
}