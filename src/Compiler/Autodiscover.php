<?php

declare(strict_types=1);

namespace Core\Compiler;

use Attribute;
use JetBrains\PhpStorm\Deprecated;
use ReflectionClass;
use Throwable, ValueError, InvalidArgumentException;
use function Support\normalize_path;
use const Support\{AUTO, INFER};

/**
 * @template T of object
 */
#[Deprecated( 'Moved to Contracts' )]
#[Attribute( Attribute::TARGET_CLASS )]
class Autodiscover
{
    /** @var class-string<T> */
    public readonly string $className;

    public readonly string $serviceId;

    /** @var null|array<array-key, array<array-key, string>|string> */
    public readonly ?array $tag;

    /** @var null|class-string[]|false|string[] */
    public readonly null|false|array $alias;

    /**
     * ## `$serviceID`
     * Define a serviceID for this service.
     *
     * Will register using the className by default.
     *
     * ## `$tag`
     * The tags to add to the service.
     *
     * ```
     * Attribute( tag: 'add.single_tag' )
     * Attribute( tag: ['tag.multiple', 'at.once'] )
     * Attribute( tag: ['tag.with_properties' => ['property'=>'argument']] )
     * ```
     *
     * ## `$calls`
     * Array of `[method, arguments[], returnsClone]`.
     *
     * ## `$bind`
     * ## `$lazy`
     * ## `$public`
     * ## `$shared`
     * ## `$autowire`
     * ## `$alias`
     * ## `$properties`
     * ## `$configurator`
     * ## `$constructor`
     *
     * @param null|string                                                $serviceId
     * @param null|array<array-key, array<string, string>|string>|string $tag
     * @param null|array{string,array<array-key,mixed>,bool}             $calls
     * @param null|array<string, string>                                 $bind
     * @param null|bool                                                  $lazy
     * @param null|bool                                                  $public
     * @param null|bool                                                  $shared
     * @param null|bool                                                  $autowire
     * @param null|false|string|string[]                                 $alias
     * @param null|array<string, mixed>                                  $properties
     * @param null|array<class-string, string>|string                    $configurator
     * @param null|string                                                $constructor
     */
    public function __construct(
        ?string                           $serviceId = INFER,
        null|string|array                 $tag = null,
        public readonly ?array            $calls = null,
        public readonly ?array            $bind = null,
        public readonly ?bool             $lazy = null,
        public readonly ?bool             $public = null,
        public readonly ?bool             $shared = null,
        public readonly ?bool             $autowire = null,
        null|false|string|array           $alias = AUTO, // / @ TODO : implement auto-aliasing
        public readonly ?array            $properties = null,
        public readonly null|string|array $configurator = null,
        public readonly ?string           $constructor = null,
    ) {
        if ( $serviceId ) {
            $this->serviceId = $serviceId;
        }

        $this->tag = match ( \is_string( $tag ) ) {
            true    => [$tag => []],
            default => $tag,
        };

        $this->alias = match ( \is_string( $alias ) ) {
            true    => [$alias],
            default => $alias,
        };
    }

    /**
     * @param class-string<T> $className
     *
     * @return self<T>
     */
    final public function configure( string $className ) : self
    {
        \assert(
            \class_exists( $className ),
            $this::class." expected a valid \$className; {$className} does not exist.",
        );

        $this->className ??= $className;
        $this->serviceId ??= $this->serviceId();

        return $this;
    }

    /**
     * @return ReflectionClass<T>
     */
    final public function getReflectionClass() : ReflectionClass
    {
        \assert(
            \class_exists( $this->className ),
            "Class '{$this->className}' does not exist.",
        );

        return new ReflectionClass( $this->className );
    }

    /**
     * @return string
     */
    final public function getClassFilePath() : string
    {
        static $classFilePath;

        if ( isset( $classFilePath ) ) {
            return $classFilePath;
        }

        try {
            $reflect              = $this->getReflectionClass();
            $filePath             = $reflect->getFileName() ?: throw new ValueError();
            return $classFilePath = normalize_path( $filePath );
        }
        catch ( Throwable $exception ) {
            throw new InvalidArgumentException(
                message  : "Could not derive directory path from '{$this->className}'.\n {$exception->getMessage()}.",
                previous : $exception,
            );
        }
    }

    /**
     * Run on discovery when the `className` and `serviceID` have been set.
     *
     * @return void
     */
    protected function register() : void {}

    /**
     * Override this method to filter the `serviceID` string.
     *
     * @return string
     */
    protected function serviceId() : string
    {
        return $this->className;
    }
}
