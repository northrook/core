<?php

namespace Core\Compiler\Hook;

use Attribute;
use Core\Compiler\Hook;

#[Attribute( Attribute::TARGET_METHOD )]
final class OnBuild extends Hook
{
    public function __construct( mixed ...$arguments )
    {
        parent::__construct( $this::class, $arguments );
    }
}
