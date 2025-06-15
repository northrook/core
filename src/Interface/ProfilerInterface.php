<?php

namespace Core\Interface;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated( 'Moved to Contracts' )]
interface ProfilerInterface
{
    /**
     * @param non-empty-string      $name
     * @param null|non-empty-string $category
     *
     * @return void
     */
    public function start(
        string  $name,
        ?string $category = null,
    ) : void;

    /**
     * @param non-empty-string      $name
     * @param null|non-empty-string $category
     *
     * @return void
     */
    public function lap(
        string  $name,
        ?string $category = null,
    ) : void;

    /**
     * @param null|non-empty-string $name
     * @param null|non-empty-string $category
     *
     * @return void
     */
    public function stop(
        ?string $name = null,
        ?string $category = null,
    ) : void;

    /**
     * @param null|non-empty-string $category
     *
     * @return self
     */
    public function setCategory(
        ?string $category,
    ) : self;
}
