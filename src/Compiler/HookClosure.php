<?php

declare(strict_types=1);

namespace Core\Compiler;

use Closure;

/**
 * @used-by Hook
 */
final class HookClosure
{
    public readonly string $id;

    public bool $fired = false;

    /**
     * @param string                  $name
     * @param Closure                 $closure
     * @param array<array-key, mixed> $arguments
     * @param class-string<Hook>      $type
     */
    public function __construct(
        public readonly string  $name,
        public readonly Closure $closure,
        public readonly array   $arguments,
        public readonly string  $type,
    ) {
        $this->id = $name.':'
                    .\spl_object_id( $this )
                    .\spl_object_id( $closure );
    }

    public function call(
        object   $newThis,
        mixed ...$arguments,
    ) : mixed {
        if ( ! $arguments ) {
            $arguments = $this->arguments;
        }
        return $this->handle( $this->closure->call( $newThis, ...$arguments ) );
    }

    public function __invoke() : mixed
    {
        return $this->handle( ( $this->closure )( ...$this->arguments ) );
    }

    private function handle( mixed $result ) : mixed
    {
        // $this->fired[$this->id] = true;
        $this->fired = true;
        return $result;
    }
}
