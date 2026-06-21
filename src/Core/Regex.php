<?php

declare(strict_types=1);

namespace Northrook\Core;

use JetBrains\PhpStorm\Language;
use Northrook\Exceptions\RegexpException;

final class Regex
{
    private function __construct() {}

    /**
     * @param string $pattern
     * @param string $subject
     * @param int    $offset
     *
     * @return string[][]
     */
    public static function matchAll(
        #[Language('PhpRegExp')]
        string $pattern,
        string $subject,
        int $offset = 0,
    ): array {
        \preg_match_all(
            $pattern,
            $subject,
            $matches,
            PREG_SET_ORDER,
            $offset,
        );
        RegexpException::check();
        return $matches;
    }
}
