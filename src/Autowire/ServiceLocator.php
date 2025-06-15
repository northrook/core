<?php

declare(strict_types=1);

namespace Core\Autowire;

use Core\Compiler\Autowire;
use JetBrains\PhpStorm\Deprecated;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable, LogicException, InvalidArgumentException;

#[Deprecated( 'Removed' )]
trait ServiceLocator
{
    private readonly ContainerInterface $serviceLocator;

    /**
     * Autowired during the instantiation process of the containing class.
     *
     * {@see ServiceProviderInterface}, exposing only required `services`.
     *
     * @internal
     *
     * @param ContainerInterface $serviceLocator
     *
     * @return void
     *
     * @final
     */
    #[Autowire]
    final public function setServiceLocator( ContainerInterface $serviceLocator ) : void
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * @template T_ContainerService of object
     *
     * @param class-string<T_ContainerService> $id
     * @param bool                             $nullable
     *
     * @return ($nullable is true ? null|T_ContainerService : T_ContainerService)
     *
     * @final
     */
    final protected function getService(
        string $id,
        bool   $nullable = false,
    ) : ?object {
        try {
            $service = match ( $id ) {
                // @phpstan-ignore-next-line
                '\\Symfony\\Component\\HttpFoundation\\Request' => $this->serviceLocator
                    ->get( '\\Symfony\\Component\\HttpFoundation\\RequestStack' )
                    ->getCurrentRequest(),
                default => $this->serviceLocator->get( $id ),
            };

            \assert(
                $service instanceof $id,
                "The located service must be instance of '{$id}.'",
            );
        }
        catch ( Throwable $exception ) {
            if ( \property_exists( $this, 'logger' ) && $this->logger instanceof LoggerInterface ) {
                $this->logger->critical(
                    '{class}::serviceLocator failed: {message}',
                    ['class' => $this::class, 'message' => $exception->getMessage()],
                );
            }

            if ( $nullable ) {
                $service = null;
            }
            elseif ( $exception instanceof InvalidArgumentException ) {
                throw $exception;
            }
            else {
                throw new LogicException( $exception->getMessage() );
            }
        }

        return $service;
    }
}
