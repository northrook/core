<?php

namespace Northrook\Core\Service;

use Closure;
use Northrook\Core\Get\Reflection;
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

    /** @var array<class-string, object> */
    private static array $services = [];

    /** @var array<string, class-string> */
    private static array $serviceMap = [];

    /**
     * @param array<string, object|Closure>  $services
     *
     * @return void
     */
    final protected function setMappedService( array $services ) : void {

        foreach ( $services as $property => $service ) {

            if ( false === $this->propertyNameIsString( $property ) ) {
                continue;
            }

            if ( $service === null ) {
                ServiceResolver::$serviceMap[ $property ] = null;
                continue;
            }

            if ( !is_object( $service ) ) {
                continue;
            }

            if ( $service instanceof Closure ) {

                $serviceId = $this->getServiceId( $service );

                ServiceResolver::$serviceMap[ $property ] = $serviceId;

                if ( $serviceId ) {
                    ServiceResolver::$services[ $serviceId ] = $service;
                }

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
                ServiceResolver::$serviceMap[ $property ] = null;
            }
        }
    }

    /**
     * @param string  $service
     *
     * @return ?object
     */
    final public function getMappedService( string $service ) : ?object {

        $serviceId = ServiceResolver::$serviceMap[ $service ] ?? null;

        if ( !$serviceId ) {
            Log::Error(
                'Attempted to access unmapped service {serviceId}.',
                [ 'serviceId' => $serviceId, 'services' => ServiceResolver::$serviceMap ],
            );
            return null;
        }

        $mappedService = ServiceResolver::$services[ $serviceId ] ?? null;

        if ( $mappedService instanceof Closure ) {
            ServiceResolver::$services[ $serviceId ] = ( $mappedService )();
        }

        return ServiceResolver::$services[ $serviceId ] ?? null;
    }

    /**
     * Check if a service is present in the {@see serviceMap}.
     *
     * @param string  $service
     *
     * @return bool
     */
    final public function has( string $service ) : bool {
        return array_key_exists( $service, ServiceResolver::$serviceMap );
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
                array_filter( ServiceResolver::$services, static fn ( $service ) => $service instanceof Closure ),
            );
        }

        return count( ServiceResolver::$serviceMap );
    }

    public static function getServiceMap() : array {
        return [
            'registered'   => count( ServiceResolver::$serviceMap ),
            'instantiated' => count(
                array_filter( ServiceResolver::$services, static fn ( $service ) => $service instanceof Closure ),
            ),
            'map'          => ServiceResolver::$serviceMap,
            'services'     => ServiceResolver::$services,
        ];
    }

    private function getServiceId( Closure $service ) : ?string {

        $get = Reflection::getFunction( $service );

        return ( $get?->getAttributes()[ 0 ] ?? null )?->getArguments()[ 'name' ] ?? null;
    }

    private function propertyNameIsString( mixed $property ) : bool {

        if ( is_string( $property ) ) {
            return true;
        }

        Log::Error(
            'Eager service {propertyName} does not have a matching propertyName in {class}',
            [ 'propertyName' => $property, 'class' => $this::class ],
        );

        return false;
    }

}