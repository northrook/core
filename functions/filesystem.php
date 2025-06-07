<?php

declare(strict_types=1);

namespace Support;

use Core\Exception\{FileNotFoundException, FilesystemException};
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
 * Retrieves the project root directory.
 *
 * - This function assumes the Composer `vendor` directory is present in the project root.
 *
 * @param bool   $throwOnInvalidRoot [false]
 * @param string $composerDirectory  [vendor]
 *
 * @return string
 */
function get_project_directory(
    bool   $throwOnInvalidRoot = false,
    string $composerDirectory = 'vendor',
) : string {
    static $projectDirectory = null;

    return $projectDirectory ??= (
        static function() use ( $throwOnInvalidRoot, $composerDirectory ) : string {
            // Split the current directory into an array of directory segments
            $segments = \explode( DIRECTORY_SEPARATOR, __DIR__ );

            // Ensure the directory array has at least 5 segments and a valid vendor value
            if ( ( \count( $segments ) >= 5 && $segments[\count( $segments ) - 4] === $composerDirectory ) ) {
                // Remove the last 4 segments (vendor, package name, and Composer structure)
                $segments = \array_slice( $segments, 0, -4 );
            }
            elseif ( $throwOnInvalidRoot ) {
                throw new FilesystemException(
                    '\Support\get_project_directory() was unable to determine a valid root. Current path: '.__DIR__,
                );
            }

            $path = normalize_path( $segments );

            // return the project path
            return \realpath( $path ) ?: $path;
        }
    )();
}

/**
 * Retrieves the system temp directory for this project.
 *
 * - The `$subdirectory` is named using a hash based on the get_project_directory.
 *
 * @param ?string $subdirectory
 *
 * @return string
 */
function get_system_cache_directory( ?string $subdirectory = null ) : string
{
    static $cacheDirectory = null;
    return $cacheDirectory ??= (
        static function() use ( $subdirectory ) : string {
            $subdirectory ??= \hash( 'xxh32', get_project_directory() );
            $cacheDirectory = normalize_path( [\sys_get_temp_dir(), $subdirectory] );
            return \realpath( $cacheDirectory ) ?: $cacheDirectory;
        }
    )();
}

/**
 * @param string $filename
 * @param mixed  $data
 * @param bool   $overwrite
 * @param bool   $append
 *
 * @return false|int
 */
function file_save(
    null|string|Stringable $filename,
    mixed                  $data,
    bool                   $overwrite = true,
    bool                   $append = false,
) : false|int {
    if ( ! $filename ) {
        throw new RuntimeException( 'No filename specified.' );
    }

    $path = new SplFileInfo( (string) $filename );

    if ( ! $overwrite && $path->isReadable() ) {
        return false;
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

    return $status;
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
 * @param string $path
 * @param bool   $throw
 *
 * @return bool
 */
function path_valid(
    string $path,
    bool   $throw = false,
) : bool {
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
        $message = "The '{$path}' does not exist.";

        if ( $throw ) {
            throw new FileNotFoundException( $message );
        }

        @\trigger_error( $message );

        return false;
    }

    $isWritable = \is_writable( $path );

    $error = ( ! $isWritable && ! $isReadable ) ? ' is not readable nor writable.' : null;
    $error ??= ( ! $isReadable ) ? ' not writable.' : null;
    $error ??= ( ! $isReadable ) ? ' not unreadable.' : null;
    $error ??= ' encountered a filesystem error. The cause could not be determined.';

    $message = "The path '{$path}' {$error}";

    if ( $throw ) {
        throw new InvalidArgumentException( $message );
    }

    @\trigger_error( $message );

    return false;
}

/**
 * @param string $path
 * @param bool   $throw [false]
 *
 * @return bool
 */
function path_readable(
    string $path,
    bool   $throw = false,
) : bool {
    if ( ! \file_exists( $path ) ) {
        $message = "The file at '{$path}' does not exist.";

        if ( $throw ) {
            throw new FileNotFoundException( $message );
        }

        @\trigger_error( $message );
    }

    if ( ! \is_readable( $path ) ) {
        $message = \sprintf( 'The "%s" "%s" is not readable.', \is_dir( $path ) ? 'directory' : 'file', $path );

        if ( $throw ) {
            throw new FilesystemException( $message );
        }

        @\trigger_error( $message );
        return false;
    }

    return true;
}

/**
 * @param string $path
 * @param bool   $throw [false]
 *
 * @return bool
 */
function path_writable(
    string $path,
    bool   $throw = false,
) : bool {
    if ( ! \file_exists( $path ) ) {
        $message = "The at '{$path}' does not exist.";

        if ( $throw ) {
            throw new FileNotFoundException( $message );
        }

        @\trigger_error( $message );
    }

    if ( ! \is_writable( $path ) ) {
        $message = \sprintf( 'The "%s" "%s" is not writable.', \is_dir( $path ) ? 'directory' : 'file', $path );

        if ( $throw ) {
            throw new FilesystemException( $message );
        }

        @\trigger_error( $message );
        return false;
    }

    return true;
}

/**
 * @param null|string|Stringable $filename
 * @param bool                   $traversal
 * @param bool                   $throwOnFault
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
