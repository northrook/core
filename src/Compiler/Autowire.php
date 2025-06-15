<?php

namespace Core\Compiler;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

/**
 * Autowire-only setter method.
 *
 * - Called by the Container during initialization.
 * - This method should not be invoked manually.
 */
#[Deprecated( 'Moved to Contracts' )]
#[Attribute( Attribute::TARGET_METHOD )]
final class Autowire {}
