<?php

declare(strict_types=1);

namespace Core\Interface;

use Core\Autowire\Logger;
use JetBrains\PhpStorm\Deprecated;

/**
 * {@see self::log()} events and exceptions.
 *
 * @used-by Loggable,LoggerAwareInterface
 */
#[Deprecated( replacement : Logger::class )]
trait LogHandler
{
    use Logger;
}
