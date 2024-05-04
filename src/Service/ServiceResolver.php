<?php

namespace Northrook\Core\Service;

use Closure;
use Northrook\Logger\Log;

/**
 * # Provides lazy service resolution using {@see Closure}s.
 *
 * - Inject services into the constructor.
 * - Pass all values to the constructor using {@see setMappedService}`(get_defined_vars)`.
 * - Use {@see getMappedService()} to retrieve a service.
 * - Use {@see has()} to check if a service is present.
 *
 * ## Lazy:
 * Services wrapped as {@see Closure}s will be added to the {@see serviceMap},
 * and resolved when the service is first accessed.
 *
 * ## Eager:
 * Injected services will be assigned to their respective properties if they exist,
 * otherwise they will be added to the {@see serviceMap}, but not lazily resolved.
 *
 * @link   https://symfony.com/doc/current/service_container/service_closures.html Symfony Service Closures
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
abstract class ServiceResolver implements ServiceResolverInterface
{
    /** @var array<string, object|Closure> */
    private array $serviceMap = [];

    /**
     * @param array<string, object|Closure>  $services
     *
     * @return void
     */
    final protected function setMappedService( array $services ) : void {

        foreach ( $services as $property => $service ) {

            if ( !is_string( $property ) || !is_object( $service ) ) {
                Log::Error(
                    'Eager service {service} does not have a matching {propertyName} in {class}',
                    [
                        'service'      => $service,
                        'propertyName' => $property,
                        'class'        => $this::class,
                    ],
                );
                $this->serviceMap[ $property ] = null;
                continue;
            }

            if ( $service instanceof Closure ) {
                $this->serviceMap[ $property ] = $service;
                continue;
            }

            if ( property_exists( $service, $property ) ) {
                $this->{$property} = $service;
            }
            else {
                Log::Error(
                    'Eager service {service} does not have a matching {propertyName} in {class}',
                    [
                        'service'      => $service,
                        'propertyName' => $property,
                        'class'        => get_class( $service ),
                    ],
                );
                $this->serviceMap[ $property ] = null;
            }
        }
    }

    /**
     * @param string  $service
     *
     * @return ?object
     */
    final public function getMappedService( string $service ) : ?object {

        $get = $this->serviceMap[ $service ] ?? null;

        if ( !$get ) {
            Log::Error(
                'Attempted to access unmapped service {service}.',
                [ 'service' => $service, 'serviceMap' => $this->serviceMap ],
            );
            return null;
        }

        if ( $get instanceof Closure ) {
            $this->serviceMap[ $service ] = ( $get )();
        }

        /** @var ?object */
        return $this->serviceMap[ $service ] ?? null;
    }

    /**
     * Check if a service is present in the {@see serviceMap}.
     *
     * @param string  $service
     *
     * @return bool
     */
    final public function has( string $service ) : bool {
        return array_key_exists( $service, $this->serviceMap );
    }

    /**
     * Returns the number of services in the {@see serviceMap}.
     *
     * @param bool  $instantiated  Count only instantiated services
     *
     * @return int
     */
    final public function count( bool $instantiated = false ) : int {

        if ( $instantiated ) {
            return count(
                array_filter( $this->serviceMap, static fn ( $service ) => $service instanceof Closure ),
            );
        }

        return count( $this->serviceMap );
    }
}