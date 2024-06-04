<?php

namespace Northrook\Core\Service;

/**
 * @method getMappedService( string $service )
 * @method has( string $service )
 */
trait ServiceResolverTrait
{

    /**
     * @param string  $service  The property name to retrieve.
     *
     * @return ?object
     */
    public function __get( string $service ) : ?object {
        trigger_deprecation(
            $this::class,
            '1.0.0',
            __METHOD__ . ' is no longer supported',
        );
        return $this->getMappedService( $service );
    }

    /** {@see ServiceResolver} does not allow dynamic properties. */
    public function __set( string $name, $service ) : void {}

    /**
     * Check if a service is present in the {@see serviceMap}.
     *
     * @param string  $service
     *
     * @return bool
     */
    public function __isset( string $service ) : bool {
        return $this->has( $service );
    }
}