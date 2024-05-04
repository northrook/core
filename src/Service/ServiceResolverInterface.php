<?php

namespace Northrook\Core\Service;

use Countable;

interface ServiceResolverInterface extends Countable
{
    /**
     * @param string  $service
     *
     * @return ?object
     */
    public function getMappedService( string $service ) : ?object;

    /**
     * Check if a service is present in the {@see serviceMap}.
     *
     * @param string  $service
     *
     * @return bool
     */
    public function has( string $service ) : bool;
}