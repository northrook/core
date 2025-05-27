<?php

namespace Core\Interface;

interface ProfilerInterface
{
    public function start(
        string  $name,
        ?string $category = null,
    ) : void;

    public function lap(
        string  $name,
        ?string $category = null,
    ) : void;

    public function stop(
        ?string $name = null,
        ?string $category = null,
    ) : void;

    public function setCategory( ?string $category ) : self;
}
