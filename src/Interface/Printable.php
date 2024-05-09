<?php

namespace Northrook\Core\Interface;

use Northrook\Logger\Log;

interface Printable
{
    /**
     * Prints the resulting HTML, or null if the element is not printable.
     *
     * - Must handle all parsing, optimization, escaping, and encoding.
     * - Must return null if the element is not printable.
     * - Null returns must {@see Log} a warning with actionable information.
     *
     * @return ?string
     */
    public function print() : ?string;
}