<?php

declare(strict_types=1);

namespace Support;

use InvalidArgumentException;
use Stringable;
use RuntimeException;
use SplFileInfo;

/**
 * @param null|array<array-key,null|string|Stringable>|string|Stringable $path
 * @param bool                                                           $throwOnFault
 *
 * @return string
 */
function path(
    null|string|Stringable|array $path,
    bool                         $throwOnFault = false,
) : string {
    return normalize_path( $path, true, $throwOnFault );
}

/**
 * @param null|array<array-key,null|string|Stringable>|string|Stringable $path
 * @param bool                                                           $throwOnFault
 *
 * @return string
 */
function get_path(
    null|string|Stringable|array $path,
    bool                         $throwOnFault = false,
) : string {
    return normalize_path( $path, true, $throwOnFault );
}

/**
 * @param string $filename
 * @param mixed  $data
 * @param bool   $overwrite
 * @param bool   $append
 *
 * @return void
 */
function file_save(
    null|string|Stringable $filename,
    mixed                  $data,
    bool                   $overwrite = true,
    bool                   $append = false,
) : void {
    if ( ! $filename ) {
        throw new RuntimeException( 'No filename specified.' );
    }

    $path = new SplFileInfo( (string) $filename );

    if ( ! $overwrite && $path->isReadable() ) {
        return;
    }

    if ( ! \file_exists( $path->getPath() ) ) {
        \mkdir( $path->getPath(), 0777, true );
    }

    if ( ! \is_writable( $path->getPath() ) ) {
        throw new RuntimeException( message : 'The file '.$path->getPathname().' is not writable.' );
    }

    $mode = $append ? FILE_APPEND : LOCK_EX;

    $status = \file_put_contents( $path->getPathname(), $data, $mode );

    if ( $status === false ) {
        throw new RuntimeException( message : 'Unable to write to file '.$path->getPathname() );
    }
}

/**
 * Removes a file or directory, including nested files.
 *
 * @param string $path
 *
 * @return bool
 */
function file_purge( string $path ) : bool
{
    return \is_file( $path )
            ? @\unlink( $path )
            : \array_map( __FUNCTION__, \glob( $path.'/*' ) ?: [] ) == @\rmdir( $path );
}

// <editor-fold desc="Path">

/**
 * @param string                        $path
 * @param bool                          $throw
 * @param null|InvalidArgumentException $exception
 *
 * @return bool
 */
function path_valid(
    string                   $path,
    bool                     $throw = false,
    InvalidArgumentException & $exception = null,
) : bool {
    // Ensure we are not receiving any previously set exceptions
    $exception = null;

    // Check if $path exists and is readable
    $isReadable = \is_readable( $path );
    $exists     = \file_exists( $path ) && $isReadable;

    // Return early
    if ( $exists ) {
        return true;
    }

    // Determine $path type
    $type = \is_dir( $path ) ? 'dir' : ( \is_file( $path ) ? 'file' : false );

    // Handle non-existent paths
    if ( ! $type ) {
        $exception = new InvalidArgumentException( "The '{$path}' does not exist." );
        if ( $throw ) {
            throw $exception;
        }
        return false;
    }

    $isWritable = \is_writable( $path );

    $error = ( ! $isWritable && ! $isReadable ) ? ' is not readable nor writable.' : null;
    $error ??= ( ! $isReadable ) ? ' not writable.' : null;
    $error ??= ( ! $isReadable ) ? ' not unreadable.' : null;
    $error ??= ' encountered a filesystem error. The cause could not be determined.';

    // Create an exception message
    $exception = new InvalidArgumentException( "The path '{$path}' {$error}" );

    if ( $throw ) {
        throw $exception;
    }

    return false;
}

/**
 * @param string                        $path
 * @param bool                          $throw     [false]
 * @param null|InvalidArgumentException $exception
 *
 * @return bool
 */
function path_readable(
    string                   $path,
    bool                     $throw = false,
    InvalidArgumentException & $exception = null,
) : bool {
    $exception = null;

    if ( ! \file_exists( $path ) ) {
        $exception = new InvalidArgumentException(
            'The file at "'.$path.'" does not exist.',
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    if ( ! \is_readable( $path ) ) {
        $exception = new InvalidArgumentException(
            \sprintf( 'The "%s" "%s" is not readable.', \is_dir( $path ) ? 'directory' : 'file', $path ),
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    return ! $exception;
}

/**
 * @param string                        $path
 * @param bool                          $throw     [false]
 * @param null|InvalidArgumentException $exception
 *
 * @return bool
 */
function path_writable(
    string                   $path,
    bool                     $throw = false,
    InvalidArgumentException & $exception = null,
) : bool {
    $exception = null;

    if ( ! \file_exists( $path ) ) {
        $exception = new InvalidArgumentException(
            'The file at "'.$path.'" does not exist.',
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    if ( ! \is_writable( $path ) ) {
        $exception = new InvalidArgumentException(
            \sprintf( 'The "%s" "%s" is not writable.', \is_dir( $path ) ? 'directory' : 'file', $path ),
            500,
        );
        if ( $throw ) {
            throw $exception;
        }
    }

    return ! $exception;
}

/**
 * @param null|string|Stringable $filename
 * @param bool                   $traversal
 * @param bool                   $throwOnFault
 * @param bool                   $throw
 *
 * @return array{0: ?string, 1:string, 2: ?string}
 */
function path_info(
    null|string|Stringable $filename,
    bool                   $traversal = false,
    bool                   $throwOnFault = false,
) : array {
    $info = \pathinfo( normalize_path( $filename, $traversal, $throwOnFault ) );

    return [
        $info['dirname'] ?? null,
        $info['filename'],
        $info['extension'] ?? null,
    ];
}

// </editor-fold>
