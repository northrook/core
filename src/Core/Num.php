<?php

declare(strict_types=1);

namespace Northrook\Core;

class Num
{
    private function __construct() {}

    /**
     * @param float|int $num
     * @param float|int $min
     * @param float|int $max
     *
     * @return bool
     */
    public static function within(
        float|int $num,
        float|int $min,
        float|int $max,
    ): bool {
        return $num >= $min && $num <= $max;
    }

    /**
     * @param array<array-key, scalar> $data
     * @param int                      $seed
     *
     * @return int
     */
    public static function xor(
        array $data,
        int &$seed = 0,
    ): int {
        foreach ($data as $value) {
            $seed ^= (int) $value;
        }
        return $seed;
    }

    /**
     * Calculate the greatest common divisor between `$a` and `$b`.
     *
     * @param float|int $a
     * @param float|int $b
     *
     * @return float|int
     */
    public static function gcd(
        float|int $a,
        float|int $b,
    ): float|int {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }

    /**
     * @param float|int $num
     * @param float|int $min
     * @param float|int $max
     *
     * @return float|int
     */
    public static function clamp(
        float|int $num,
        float|int $min,
        float|int $max,
    ): float|int {
        return \max($min, \min($num, $max));
    }


    /**
     * @see https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array stackoverflow
     *
     * @param int   $num
     * @param int[] $in
     * @param bool  $returnKey
     *
     * @return null|int|string
     */
    public static function closest(int $num, array $in, bool $returnKey = false): string|int|null
    {
        foreach ($in as $key => $value) {
            if ($num <= $value) {
                return $returnKey ? $key : $value;
            }
        }

        return null;
    }
}
