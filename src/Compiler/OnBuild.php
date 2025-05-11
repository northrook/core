<?php

namespace Core\Compiler;

use Attribute;
use const Support\AUTO;

#[Attribute( Attribute::TARGET_METHOD )]
final readonly class OnBuild
{
    /**
     * @param null|int                        $priority  Higher priority methods executed first
     * @param callable|false|non-empty-string $condition
     */
    public function __construct(
        public ?int  $priority = AUTO,
        public mixed $condition = false,
    ) {}
}
