<?php

declare(strict_types=1);

namespace Core\Compiler;

use Attribute;
use ReflectionAttribute, ReflectionClass;
use LogicException, ReflectionException, InvalidArgumentException;
use function Support\array_is_associative;

#[Attribute( Attribute::TARGET_METHOD )]
class Hook
{
    /** @var array<string, array<string,HookClosure>> */
    private static array $cache = [];

    /** @var array<non-empty-string, mixed> */
    public readonly array $arguments;

    /**
     * @param ?string                 $name
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(
        public readonly ?string $name = null,
        array                   $arguments = [],
    ) {
        \assert(
            array_is_associative( $arguments ),
            $this::class.' only accepts named arguments.',
        );
        $this->arguments = $arguments;
    }

    final public static function trigger( object $class, string ...$hook ) : void
    {
        foreach ( Hook::get( $class, ...$hook ) as $closure ) {
            $closure( $class );
        }
    }

    final public static function fire( object $class, string ...$hook ) : void
    {
        foreach ( Hook::get( $class, ...$hook ) as $closure ) {
            if ( $closure->fired ) {
                continue;
            }
            $closure( $class );
        }
    }

    /**
     * @param object $class
     * @param string $hook
     *
     * @return HookClosure[]
     */
    final public static function get( object $class, string ...$hook ) : array
    {
        if ( ! $hook ) {
            return Hook::resolve( $class );
        }

        $hooks = [];

        foreach ( Hook::resolve( $class ) as $call ) {
            if ( \in_array( $call->name, $hook, true ) ) {
                $hooks[] = $call;
            }
        }

        return $hooks;
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
     * @param object $class
     *
     * @return array<string, HookClosure>
     */
    final public static function resolve( object $class ) : array
    {
        $className = $class::class;

        if ( isset( self::$cache[$className] ) ) {
            return self::$cache[$className];
        }

        /** @var array<string, HookClosure> $hooks */
        $hooks = [];

        foreach ( ( new ReflectionClass( $class ) )->getMethods() as $method ) {
            // Get annotated methods
            $attribute = $method->getAttributes(
                self::class,
                ReflectionAttribute::IS_INSTANCEOF,
            )[0] ?? null;

            if ( ! $attribute ) {
                continue;
            }
            $methodName = $method->getName();
            $action     = \strrchr( $methodName, '\\', true ) ?: $methodName;
            $arguments  = [];

            /** @var self $hook */
            $hook = $attribute->newInstance();
            $name = $hook->name ?? $methodName;

            foreach ( $method->getParameters() as $parameter ) {
                $argument = $parameter->getName();

                try {
                    $value = $hook->arguments[$argument] ?? $parameter->getDefaultValue();
                }
                catch ( ReflectionException $exception ) {
                    throw new InvalidArgumentException(
                        message  : "The {$className}::{$methodName} argument {$argument} has no #[OnBuild] or default value.",
                        previous : $exception,
                    );
                }

                $arguments[$argument] = $value;
            }

            try {
                $closure = $method->getClosure( $class );
            }
            catch ( ReflectionException $exception ) {
                throw new LogicException(
                    $exception->getMessage(),
                    previous : $exception,
                );
            }

            $hookClosure = new HookClosure(
                $name,
                $closure,
                $action,
                $arguments,
                $hook::class,
            );

            $hooks[$hookClosure->id] = $hookClosure;
        }

        return self::$cache[$className] = $hooks;
    }

    /**
     * @return HookClosure[][]
     */
    public static function peekCache() : array
    {
        return self::$cache;
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
