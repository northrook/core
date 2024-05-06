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
 * ## Runtime:
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
    private array $serviceMap = [];

    /**
     * @param array<string, object|Closure>  $services
     * @param bool                           $logErrors
     *
     * @return void
     */
    final protected function setMappedService( array $services, bool $logErrors = true ) : void {

        foreach ( $services as $property => $service ) {

            if ( false === $this->propertyNameIsString( $property ) ) {
                continue;
            }

            if ( $service === null ) {
                $this->serviceMap[ $property ] = null;
                continue;
            }

            if ( !is_object( $service ) ) {
                continue;
            }

            if ( $service instanceof Closure ) {

                $serviceId = $this->getServiceId( $service );

                $this->serviceMap[ $property ] = $serviceId;

                if ( $serviceId ) {
                    ServiceResolver::$services[ $serviceId ] = $service;
                }

                continue;
            }

            if ( property_exists( $service, $property ) ) {
                $this->{$property} = $service;
                continue;
            }

            if ( $logErrors ) {
                Log::Error(
                    'Injected service {service} does not have a matching {propertyName} in {class}',
                    [
                        'service'      => $service,
                        'propertyName' => $property,
                        'class'        => get_class( $service ),
                    ],
                );
            }

            $this->serviceMap[ $property ] = null;
        }
    }

    /**
     * @template Service of object
     * @param ?string                          $service
     * @param ?string | class-string<Service>  $className
     *
     * @return ?object Object of type $className
     */
    final public function getMappedService( ?string $service = null, ?string $className = null ) : ?object {

        $className = $service ? $this->serviceMap[ $service ] ?? null : $className;

        if ( !$className ) {
            return null;
        }

        $mappedService = ServiceResolver::$services[ $className ] ?? null;

        if ( !$mappedService ) {
            Log::Error(
                'Attempted to access unmapped service {serviceId}.',
                [ 'serviceId' => $className, 'services' => $this->serviceMap ],
            );
            return null;
        }


        if ( $mappedService instanceof Closure ) {
            ServiceResolver::$services[ $className ] = ( $mappedService )();
        }

        return ServiceResolver::$services[ $className ] ?? null;
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
                array_filter( ServiceResolver::$services, static fn ( $service ) => $service instanceof Closure ),
            );
        }

        return count( $this->serviceMap );
    }

    public static function getServiceMap() : array {
        return [
            'registered'   => count( ServiceResolver::$services ),
            'instantiated' => count(
                array_filter( ServiceResolver::$services, static fn ( $service ) => $service instanceof Closure ),
            ),
            'services'     => array_keys( ServiceResolver::$services ),
        ];
    }

    /**
     * @param Closure  $service
     *
     * @return ?class-string
     */
    private function getServiceId( Closure $service ) : ?string {

        $get = Reflection::getFunction( $service );

        return ( $get?->getAttributes()[ 0 ] ?? null )?->getArguments()[ 'class' ] ?? null;
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