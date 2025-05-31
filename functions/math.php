<?php

declare(strict_types=1);

namespace Support;

/**
 * @param array<array-key, scalar> $data
 * @param int                      $seed
 *
 * @return int
 */
function num_xor(
    array $data,
    int &   $seed = 0,
) : int {
    foreach ( $data as $value ) {
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
function num_gcd(
    float|int $a,
    float|int $b,
) : float|int {
    while ( $b !== 0 ) {
        [$a, $b] = [$b, $a % $b];
    }

    return $a;
}

/**
 * @param float|int $num
 * @param float|int $min
 * @param float|int $max
 *
 * @return bool
 */
function num_within(
    float|int $num,
    float|int $min,
    float|int $max,
) : bool {
    return $num >= $min && $num <= $max;
}

/**
 * @param float|int $num
 * @param float|int $min
 * @param float|int $max
 *
 * @return float|int
 */
function num_clamp(
    float|int $num,
    float|int $min,
    float|int $max,
) : float|int {
    return \max( $min, \min( $num, $max ) );
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
function num_closest( int $num, array $in, bool $returnKey = false ) : string|int|null
{
    foreach ( $in as $key => $value ) {
        if ( $num <= $value ) {
            return $returnKey ? $key : $value;
        }
    }

    return null;
}

/**
 * Calculate the difference in percentage `$from` `$to` given numbers.
 *
 * @param float $from
 * @param float $to
 *
 * @return float
 */
function num_percent( float $from, float $to ) : float
{
    if ( ! $from || $from === $to ) {
        return 0;
    }
    return (float) \number_format( ( $from - $to ) / $from * 100, 2 );
}

function num_byte_size( string|int|float $bytes ) : string
{
    $bytes = (float) ( \is_string( $bytes ) ? \mb_strlen( $bytes, '8bit' ) : $bytes );

    $unitDecimalsByFactor = [
        ['B', 0],  //     byte
        ['KiB', 0], // kibibyte
        ['MiB', 2], // mebibyte
        ['GiB', 2], // gigabyte
        ['TiB', 3], // mebibyte
        ['PiB', 3], // mebibyte
    ];

    $factor = $bytes ? \floor( \log( (int) $bytes, 1_024 ) ) : 0;
    $factor = (float) \min( $factor, \count( $unitDecimalsByFactor ) - 1 );

    $value = \round( $bytes / ( 1_024 ** $factor ), (int) $unitDecimalsByFactor[$factor][1] );
    $units = (string) $unitDecimalsByFactor[$factor][0];

    return $value.$units;
}
