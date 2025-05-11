<?php

namespace Core\Compiler;

final readonly class Call
{
    /**
     * @param string                   $method
     * @param array<string,mixed>      $arguments
     * @param CallHandler<string,Call> $handler
     */
    public function __construct(
        public string       $method,
        public array        $arguments,
        private CallHandler $handler,
    ) {}

    public function called( string $method ) : bool
    {
        return $this->handler->called( $method );
    }

    public function __invoke( object $call ) : mixed
    {
        return $call->{$this->method}( ...$this->arguments );
    }
}
