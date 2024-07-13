<?php

declare( strict_types = 1 );

namespace Northrook\Core\Abstract;

/**
 * Designate a class as static.
 *
 * - Prevent instantiation of a class.
 *
 * @internal
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
abstract class StaticClass
{
    final protected function __construct() {
        throw new \LogicException(
            $this::class . " is using the `StaticClass` trait, and should not be instantiated directly.",
        );
    }

    final protected function __clone() {
        throw new \LogicException(
            $this::class . " is using the `StaticClass` trait, and should not be cloned.",
        );
    }
}