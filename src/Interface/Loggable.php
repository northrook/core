<?php

namespace Core\Interface;

use JetBrains\PhpStorm\Deprecated;
use Psr\Log\LoggerAwareInterface;

#[Deprecated( 'Considered for removal' )]
interface Loggable extends LoggerAwareInterface {}
