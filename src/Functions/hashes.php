<?php

declare(strict_types=1);

namespace Northrook\Core;

use Northrook\Hash;

/**
 * xxHash32 non-cryptographic checksum of an input value.
 */
#[\Deprecated(message: 'Use `Hash::checksum()` instead.')]
function get_checksum(
    string $input,
): string {
    return Hash::checksum($input);
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
#[\Deprecated(message: 'Use `Hash::time()` instead.')]
function get_time_hash(
    int $length = 10,
): string {
    return Hash::time($length);
}

/**
 * xxHash32 non-cryptographic checksum of an input value, encoded as Crockford Base32.
 *
 * Not appropriate for security-sensitive contexts.
 *
 * @param int<4,8> $length [8]
 */
#[\Deprecated(message: 'Use `Hash::value()` instead.')]
function get_hash(
    string $input,
    int $length = 8,
): string {
    return Hash::value($input, $length);
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
#[\Deprecated(message: 'Use `Hash::fast()` instead.')]
function get_fast_hash(
    int $length = 8,
): string {
    return Hash::fast($length);
}

/**
 * Generates a cryptographically secure random Crockford Base32 string.
 *
 * Bytes are unpacked 5 bits at a time to minimize wasted entropy across byte boundaries.
 *
 * @param int<8,32> $length [16]
 */
#[\Deprecated(message: 'Use `Hash::crypto()` instead.')]
function get_crypto_hash(
    int $length = 16,
): string {
    return Hash::crypto($length);
}
