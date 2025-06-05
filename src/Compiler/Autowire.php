<?php

namespace Core\Compiler;

use Attribute;

/**
 * Autowire-only setter method.
 *
 * - Called by the Container during initialization.
 * - This method should not be invoked manually.
 */
#[Attribute( Attribute::TARGET_METHOD )]
final class Autowire {}
