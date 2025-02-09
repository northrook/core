<?php

declare(strict_types=1);

namespace Core\Interface;

use Core\Pathfinder\Path;
use Stringable;

interface PathfinderInterface
{
    /**
     * @param string|Stringable      $path
     * @param null|string|Stringable $relativeTo
     *
     * @return string
     */
    public function __invoke(
        string|Stringable      $path,
        null|string|Stringable $relativeTo = null,
    ) : string;

    /**
     * @param string|Stringable      $path
     * @param null|string|Stringable $relativeTo
     *
     * @return string
     */
    public function get(
        string|Stringable      $path,
        null|string|Stringable $relativeTo = null,
    ) : string;

    /**
     * @param string|Stringable      $path
     * @param null|string|Stringable $relativeTo
     *
     * @return Path
     */
    public function getPath(
        string|Stringable      $path,
        null|string|Stringable $relativeTo = null,
    ) : Path;
}
