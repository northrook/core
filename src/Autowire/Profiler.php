<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Interface\ProfilerInterface;

trait Profiler
{
    private readonly ProfilerInterface $profiler;

    /**
     * @param ProfilerInterface $profiler
     *
     * @return void
     *
     * @final
     */
    final public function setProfiler( ProfilerInterface $profiler ) : void
    {
        $this->profiler = $profiler->setCategory( $this::class );
    }
}
