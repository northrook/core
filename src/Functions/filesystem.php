<?php

declare(strict_types=1);

namespace Northrook\Core;

use InvalidArgumentException;
use Northrook\Contracts\Exceptions\FileNotFoundException;
use Northrook\Contracts\Exceptions\FilesystemException;
use RuntimeException;
use Stringable;

/**
 * Write or append data to a file via the Core filesystem service.
 *
 * Returns bytes written, or `false` when the file exists and `$overwrite` is false.
 *
 * @param null|string|Stringable $filename
 * @param resource|string        $data
 * @param bool                   $overwrite
 * @param bool                   $append
 *
 * @return false|int
 */
function file_save(
    null|string|Stringable $filename,
    mixed $data,
    bool $overwrite = true,
    bool $append = false,
): false|int {
    if (\is_array($data)) {
        throw new \TypeError(
            \sprintf(
                'Argument 2 passed to "%s()" must be string or resource, array given.',
                __FUNCTION__,
            ),
        );
    }

    if (! \is_string($data) && ! \is_resource($data)) {
        throw new \TypeError(
            \sprintf(
                'Argument 2 passed to "%s()" must be string or resource, %s given.',
                __FUNCTION__,
                \get_debug_type($data),
            ),
        );
    }

    if (! $filename) {
        throw new RuntimeException('No filename specified.');
    }

    $path = (string) $filename;

    if (! $overwrite && filesystem()->isReadable($path)) {
        return false;
    }

    $sizeBefore = $append && filesystem()->pathsExist($path)
        ? filesystem()->fileSize($path)
        : 0;

    try {
        if ($append) {
            filesystem()->appendToFile($path, $data);
        } else {
            filesystem()->writeFileAtomically($path, $data);
        }
    } catch (FilesystemException $e) {
        throw new RuntimeException($e->getMessage(), previous: $e);
    }

    return $append
        ? filesystem()->fileSize($path) - $sizeBefore
        : filesystem()->fileSize($path);
}

/**
 * Shared {@see Filesystem} instance for Core file helpers.
 */
function filesystem(): Filesystem
{
    static $instance = null;
    $instance ??= new Filesystem();

    return $instance;
}

/**
 * Copy `$source` to `$target`, returning false on failure instead of throwing.
 */
function file_copy(
    string $source,
    string $target,
    bool $alwaysOverwrite = false,
): bool {
    return (bool) get(static function () use ($source, $target, $alwaysOverwrite): true {
        filesystem()->copyFile($source, $target, $alwaysOverwrite);

        return true;
    }, false);
}

/**
 * Remove a file or directory, returning false on failure instead of throwing.
 */
function file_remove(string $path): bool
{
    return (bool) get(static function () use ($path): true {
        filesystem()->remove($path);

        return true;
    }, false);
}

// <editor-fold desc="Path">

/**
 * Check whether `$path` exists and is readable.
 *
 * Triggers `E_USER_WARNING` or throws when validation fails.
 *
 * @param string $path
 * @param bool   $throw
 *
 * @return bool
 */
function path_valid(
    string $path,
    bool $throw = false,
): bool {
    // Check if $path exists and is readable
    $isReadable = \is_readable($path);
    $exists     = \file_exists($path) && $isReadable;

    // Return early
    if ($exists) {
        return true;
    }

    // Determine $path type
    $type = \is_dir($path) ? 'dir' : ( \is_file($path) ? 'file' : false );

    // Handle non-existent paths
    if (! $type) {
        $message = "The '{$path}' does not exist.";

        if ($throw) {
            throw new FileNotFoundException($message);
        }

        @\trigger_error($message);

        return false;
    }

    $isWritable = \is_writable($path);

    $error = ! $isWritable && ! $isReadable ? ' is not readable nor writable.' : null;
    $error ??= ! $isWritable ? ' is not writable.' : null;
    $error ??= ! $isReadable ? ' is not readable.' : null;
    $error ??= ' encountered a filesystem error. The cause could not be determined.';

    $message = "The path '{$path}' {$error}";

    if ($throw) {
        throw new InvalidArgumentException($message);
    }

    @\trigger_error($message);

    return false;
}

/**
 * Check whether `$path` exists and is readable.
 *
 * @param string $path
 * @param bool   $throw [false]
 *
 * @return bool
 */
function path_readable(
    string $path,
    bool $throw = false,
): bool {
    if (! \file_exists($path)) {
        $message = "The file at '{$path}' does not exist.";

        if ($throw) {
            throw new FileNotFoundException($message);
        }

        @\trigger_error($message);

        return false;
    }

    if (! \is_readable($path)) {
        $message = \sprintf('The "%s" "%s" is not readable.', \is_dir($path) ? 'directory' : 'file', $path);

        if ($throw) {
            throw new FilesystemException($message);
        }

        @\trigger_error($message);
        return false;
    }

    return true;
}

/**
 * Check whether `$path` exists and is writable.
 *
 * @param string $path
 * @param bool   $throw [false]
 *
 * @return bool
 */
function path_writable(
    string $path,
    bool $throw = false,
): bool {
    if (! \file_exists($path)) {
        $message = "The path at '{$path}' does not exist.";

        if ($throw) {
            throw new FileNotFoundException($message);
        }

        @\trigger_error($message);

        return false;
    }

    if (! \is_writable($path)) {
        $message = \sprintf('The "%s" "%s" is not writable.', \is_dir($path) ? 'directory' : 'file', $path);

        if ($throw) {
            throw new FilesystemException($message);
        }

        @\trigger_error($message);
        return false;
    }

    return true;
}

/**
 * Split a path into `[dirname, filename, extension]` after {@see normalize_path()}.
 *
 * @param null|string|Stringable $filename
 * @param bool                   $traversal
 * @param bool                   $throwOnFault
 *
 * @return array{0: ?string, 1:string, 2: ?string}
 */
function path_info(
    null|string|Stringable $filename,
    bool $traversal = false,
    bool $throwOnFault = false,
): array {
    $info = \pathinfo(normalize_path($filename, $traversal, $throwOnFault));

    return [
        $info['dirname'] ?? null,
        $info['filename'],
        $info['extension'] ?? null,
    ];
}