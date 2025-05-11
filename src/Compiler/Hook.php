<?php

namespace Core\Compiler;

use Attribute;
use ReflectionAttribute, ReflectionClass;
use ReflectionException, InvalidArgumentException;
use function Support\array_is_associative;

#[Attribute( Attribute::TARGET_METHOD )]
class Hook
{
    /** @var array<string, array<int, array{0: non-empty-string, 1: array<non-empty-string,mixed>}>> */
    private static array $cache = [];

    /** @var array<non-empty-string, mixed> */
    public readonly array $arguments;

    /**
     * @param mixed ...$arguments
     */
    public function __construct( mixed ...$arguments )
    {
        \assert(
            array_is_associative( $arguments ),
            $this::class.' only accepts named arguments.',
        );
        $this->arguments = $arguments;
    }

    /**
     * Returns an array of methods to call in a `build` step.
     *
     * ```
     *  // Returns
     *  [ priority => [
     *    0 => $method
     *    1 => ... $arguments
     *  ] ]
     *
     *  // Example
     *  foreach (
     *      OnBuild::resolve( $this::class ) as [$method, $arguments]
     *  ) {
     *      $this->{$method}( ...$arguments );
     *  }
     *  ```
     *
     * @param class-string $className
     *
     * @return array<int, array{0: non-empty-string, 1: array<non-empty-string,mixed>}>
     */
    final public static function resolve( string $className ) : array
    {
        if ( isset( self::$cache[$className] ) ) {
            return self::$cache[$className];
        }

        \assert(
            \class_exists( $className ),
            __METHOD__." expected an existing class; {$className} does not exist.",
        );

        $onBuildMethods = [];

        foreach ( ( new ReflectionClass( $className ) )->getMethods() as $method ) {
            $attribute = $method->getAttributes(
                self::class,
                ReflectionAttribute::IS_INSTANCEOF,
            )[0] ?? null;

            $onBuild = $attribute?->newInstance();

            if ( ! $onBuild instanceof self ) {
                continue;
            }

            $priority = $onBuild->_priority ?? \count( $onBuildMethods );
            while ( isset( $onBuildMethods[$priority] ) ) {
                $priority++;
            }

            $methodName = $method->getName();
            $parameters = [];

            foreach ( $method->getParameters() as $parameter ) {
                $argument = $parameter->getName();

                try {
                    $value = $onBuild->arguments[$argument] ?? $parameter->getDefaultValue();
                }
                catch ( ReflectionException $exception ) {
                    throw new InvalidArgumentException(
                        message  : "The {$className}::{$methodName} argument {$argument} has no #[OnBuild] or default value.",
                        previous : $exception,
                    );
                }

                $parameters[$argument] = $value;
            }

            $onBuildMethods[$priority] = [$methodName, $parameters];
        }

        return self::$cache[$className] = $onBuildMethods;
    }

    /**
     * @return bool [true] if any cached values were cleared
     */
    public static function clearCache() : bool
    {
        $cleared = ! empty( self::$cache );

        self::$cache = [];

        return $cleared;
    }
}
