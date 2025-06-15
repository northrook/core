<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Compiler\Autowire;
use Core\Interface\PathfinderInterface;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated( 'Moved to Contracts' )]
trait Pathfinder
{
    protected readonly PathfinderInterface $pathfinder;

    /**
     * Autowired during the instantiation process of the containing class.
     *
     * @internal
     *
     * @param PathfinderInterface $pathfinder
     *
     * @return void
     *
     * @final
     */
    #[Autowire]
    final public function setPathfinder(
        PathfinderInterface $pathfinder,
    ) : void {
        $this->pathfinder = $pathfinder;
    }
}
