<?php

namespace Core\Interface;

use JetBrains\PhpStorm\Deprecated;
use Stringable;

#[Deprecated( 'Moved to Contracts' )]
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
