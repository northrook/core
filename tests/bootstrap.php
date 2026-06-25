<?php

declare(strict_types=1);

use Northrook\Core;
use Northrook\ErrorHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

Core::register(dirname(__DIR__));
ErrorHandler::register(install: false);
