<?php

namespace Core\Interface;

use Stringable;

interface PathfinderInterface
{
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
     * A `normalizeUrl` filtered string.
     *
     * @param string|Stringable      $path
     * @param null|string|Stringable $relativeTo
     *
     * @return string
     */
    public function getUrl(
        string|Stringable      $path,
        null|string|Stringable $relativeTo = null,
    ) : string;
}
