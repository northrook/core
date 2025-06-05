<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Compiler\Autowire;
use Core\Interface\ProfilerInterface;
use LogicException;

/**
 * @used-by Profilable
 */
trait Profiler
{
    private ?ProfilerInterface $profiler = null;

    /**
     * Autowired during the instantiation process of the containing class.
     *
     * @internal
     *
     * @param ProfilerInterface     $profiler
     * @param null|non-empty-string $category
     *
     * @return void
     *
     * @final
     */
    #[Autowire]
    final public function setProfiler(
        ProfilerInterface $profiler,
        ?string           $category = null,
    ) : void {
        if ( $this->profiler ) {
            throw new LogicException( 'Profiler is already set' );
        }

        $this->profiler = $profiler->setCategory( $this::class );
    }

    /**
     * @param non-empty-string      $event
     * @param null|non-empty-string $category
     *
     * @return void
     *
     * @final
     */
    final protected function profilerStart(
        string  $event,
        ?string $category = null,
    ) : void {
        $this->profiler?->start( $event, $category );
    }

    /**
     * @param non-empty-string      $event
     * @param null|non-empty-string $category
     *
     * @return void
     *
     * @final
     */
    final protected function profilerLap(
        string  $event,
        ?string $category = null,
    ) : void {
        $this->profiler?->lap( $event, $category );
    }

    /**
     * @param null|non-empty-string $event
     * @param null|non-empty-string $category
     *
     * @return void
     *
     * @final
     */
    final protected function profilerStop(
        ?string $event = null,
        ?string $category = null,
    ) : void {
        $this->profiler?->stop( $event, $category );
    }
}
