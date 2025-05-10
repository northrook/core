<?php

declare(strict_types=1);

namespace Support;

use Core\Exception\RegexpException;
use JetBrains\PhpStorm\Language;

/**
 * @param string $pattern
 * @param string $subject
 * @param int    $offset
 *
 * @return string[][]
 */
function regex_match_all(
    #[Language( 'PhpRegExp' )]
    string $pattern,
    string $subject,
    int    $offset = 0,
) : array {
    \preg_match_all( $pattern, $subject, $matches, PREG_SET_ORDER, $offset );
    RegexpException::check();
    return $matches;
}
