<?php

declare(strict_types=1);

namespace Northrook\Core;

use InvalidArgumentException;
use Random\Randomizer;

/**
 * xxHash32 non-cryptographic checksum of an input value.
 */
function get_checksum(
    string $input,
): string {
    return \hash('xxh32', $input);
}

/**
 * Encodes the current timestamp as a Crockford Base32 string.
 *
 * Produces a time-sortable prefix suitable for use in ULID-style identifiers.
 *
 * Full millisecond sortability requires a length of `10`.
 *
 * @param int<1,16> $length [10]
 */
function get_time_hash(
    int $length = 10,
): string {
    if ($length < 1 || $length > 16) {
        throw new InvalidArgumentException(
            'length must be between 1 and 16',
        );
    }

    $shift  = (int) \floor(\microtime(true) * 1000);
    $output = \array_fill(0, $length, '');

    for ($i = $length - 1; $i >= 0; $i--) {
        $output[$i] = CROCKFORD_BASE32[$shift & 31];
        $shift      >>= 5;
    }

    return \implode('', $output);
}

/**
 * xxHash32 non-cryptographic checksum of an input value, encoded as Crockford Base32.
 *
 * Not appropriate for security-sensitive contexts.
 *
 * @param int<4,8> $length [8]
 */
function get_hash(
    string $input,
    int $length = 8,
): string {
    if ($length < 4 || $length > 8) {
        throw new InvalidArgumentException(
            'length must be between 4 and 8',
        );
    }

    $value  = \hexdec(\hash('xxh32', $input));
    $output = \array_fill(0, $length, '');

    for ($i = $length - 1; $i >= 0; $i--) {
        $output[$i] = CROCKFORD_BASE32[$value & 31];
        $value      >>= 5;
    }

    return \implode('', $output);
}

/**
 * Generates a fast, non-cryptographic random Crockford Base32 string.
 *
 * Suitable for low-stakes use cases such as UI keys or non-security-sensitive nonce.
 *
 * Not appropriate for security-sensitive contexts.
 *
 * @param int<1,32> $length [8]
 */
function get_fast_hash(
    int $length = 8,
): string {
    if ($length < 1 || $length > 32) {
        throw new InvalidArgumentException(
            'length must be between 1 and 32',
        );
    }

    $output = \array_fill(0, $length, '');
    $bits   = 0;
    $val    = 0;

    for ($i = 0; $i < $length; $i++) {
        if ($bits < 5) {
            $val  = \mt_rand(0, 0xFFFF_FFFF);
            $bits = 32;
        }

        $output[$i] = CROCKFORD_BASE32[( $val >> ( $bits - 5 ) ) & 31];
        $bits       -= 5;
    }

    return \implode('', $output);
}

/**
 * Generates a cryptographically secure random Crockford Base32 string.
 *
 * Bytes are unpacked 5 bits at a time to minimize wasted entropy across byte boundaries.
 *
 * @param int<8,32> $length [16]
 */
function get_crypto_hash(
    int $length = 16,
): string {
    if ($length < 8 || $length > 32) {
        throw new InvalidArgumentException(
            'length must be between 8 and 32',
        );
    }

    $buffer = new Randomizer()->getBytes(
        (int) \ceil(( $length * 5 ) / 8),
    );
    $output = \array_fill(0, $length, '');
    $idx    = 0;
    $bits   = 0;
    $val    = 0;

    for ($i = 0, $bufferLength = \strlen($buffer); $i < $bufferLength && $idx < $length; $i++) {
        $val  = ( $val << 8 ) | \ord($buffer[$i]);
        $bits += 8;

        while ($bits >= 5 && $idx < $length) {
            $output[$idx++] = CROCKFORD_BASE32[( $val >> ( $bits - 5 ) ) & 31];
            $bits           -= 5;
        }
    }

    return \implode('', $output);
}
