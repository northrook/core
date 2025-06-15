<?php

namespace Core\Interface;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated( 'Removed' )]
interface Profilable
{
    /**
     * @param ProfilerInterface     $profiler
     * @param null|non-empty-string $category
     *
     * @return void
     */
    public function setProfiler(
        ProfilerInterface $profiler,
        ?string           $category = null,
    ) : void;
}
