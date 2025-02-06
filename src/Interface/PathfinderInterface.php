<?php

declare(strict_types=1);

namespace Core\Interface;

use Core\Pathfinder\Path;
use Stringable;

interface PathfinderInterface
{
    /**
     * @param string|Stringable $path
     * @param null|string       $relativeTo
     *
     * @return ?string
     */
    public function __invoke(
        string|Stringable $path,
        ?string           $relativeTo = null,
    ) : ?string;

    /**
     * @param string|Stringable $path
     * @param null|string       $relativeTo
     *
     * @return ?string
     */
    public function get(
        string|Stringable $path,
        ?string           $relativeTo = null,
    ) : ?string;

    /**
     * @param string|Stringable $path
     * @param null|string       $relativeTo
     *
     * @return ?Path
     */
    public function getPath(
        string|Stringable $path,
        ?string           $relativeTo = null,
    ) : ?Path;
}
