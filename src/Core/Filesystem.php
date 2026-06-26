<?php

declare(strict_types=1);

namespace Northrook\Core;

use FilesystemIterator;
use Northrook\Contracts\Exceptions\FileNotFoundException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\Contracts\Interfaces\FilesystemInterface;
use Northrook\ErrorHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Traversable;

final class Filesystem implements FilesystemInterface
{
    public function copyFile(
        string $source,
        string $target,
        bool $alwaysOverwrite = false,
    ): void {
        $originIsLocal = \stream_is_local($source) || 0 === \stripos($source, 'file://');
        if ($originIsLocal && ! \is_file($source)) {
            throw new FileNotFoundException(
                \sprintf('Failed to copy "%s" because file does not exist.', $source),
                path: $source,
            );
        }

        $this->createDirectory(\dirname($target));

        $doCopy = true;
        if (! $alwaysOverwrite && ! \parse_url($source, \PHP_URL_HOST) && \is_file($target)) {
            $doCopy = \filemtime($source) > \filemtime($target);
        }

        if ($doCopy) {
            $sourceHandle = self::box('fopen', $source, 'r');
            if (! \is_resource($sourceHandle)) {
                throw new FilesystemException(
                    \sprintf(
                        'Failed to copy "%s" to "%s" because source file could not be opened for reading: ',
                        $source,
                        $target,
                    )
                        . ErrorHandler::get()->getLastError(),
                    path: $source,
                );
            }

            $targetHandle = self::box('fopen', $target, 'w', false, \stream_context_create(['ftp' => [
                'overwrite' => true,
            ]]));
            if (! \is_resource($targetHandle)) {
                throw new FilesystemException(
                    \sprintf(
                        'Failed to copy "%s" to "%s" because target file could not be opened for writing: ',
                        $source,
                        $target,
                    )
                        . ErrorHandler::get()->getLastError(),
                    path: $source,
                );
            }

            $bytesCopied = \stream_copy_to_stream($sourceHandle, $targetHandle);
            \fclose($sourceHandle);
            \fclose($targetHandle);
            unset($sourceHandle, $targetHandle);

            if (! \is_file($target)) {
                throw new FilesystemException(
                    \sprintf('Failed to copy "%s" to "%s".', $source, $target),
                    path: $source,
                );
            }

            if ($originIsLocal) {
                self::box('chmod', $target, \fileperms($source) & 0o777 & ~\umask());
                self::box('touch', $target, \filemtime($source));

                if ($bytesCopied !== ( $bytesOrigin = \filesize($source) )) {
                    throw new FilesystemException(
                        \sprintf(
                            'Failed to copy the whole content of "%s" to "%s" (%g of %g bytes copied).',
                            $source,
                            $target,
                            $bytesCopied,
                            $bytesOrigin,
                        ),
                        path: $source,
                    );
                }
            }
        }
    }

    public function pathsExist(
        string ...$paths,
    ): bool {
        return array_all(
            $paths,
            fn($path) => \file_exists($path),
        );
    }

    public function createDirectory(
        string|iterable $paths,
        int $mode = 0o777,
    ): void {
        foreach ($this->toIterable($paths) as $path) {
            if (\is_dir($path)) {
                continue;
            }

            if (! self::box('mkdir', $path, $mode, true) && ! \is_dir($path)) {
                throw new FilesystemException(
                    \sprintf('Failed to create "%s": ', $path) . ErrorHandler::get()->getLastError(),
                    path: $path,
                );
            }
        }
    }

    public function createParentDirectory(
        string|iterable $paths,
        int $mode = 0o777,
    ): void {
        foreach ($this->toIterable($paths) as $path) {
            $this->createDirectory(\dirname($path), $mode);
        }
    }

    public function touch(
        string|iterable $paths,
        null|int $modifiedTime = null,
        null|int $accessTime = null,
    ): void {
        foreach ($this->toIterable($paths) as $path) {
            $touched = $modifiedTime !== null
                ? self::box('touch', $path, $modifiedTime, $accessTime)
                : self::box('touch', $path);

            if (! $touched) {
                throw new FilesystemException(
                    \sprintf('Failed to touch "%s": ', $path) . ErrorHandler::get()->getLastError(),
                    path: $path,
                );
            }
        }
    }

    public function remove(
        string|iterable $paths,
    ): void {
        if ($paths instanceof Traversable) {
            $paths = \array_values(\iterator_to_array($paths, false));
        } elseif (\is_array($paths)) {
            $paths = \array_values($paths);
        } else {
            $paths = [$paths];
        }

        self::doRemove($paths, false);
    }

    public function setPermissions(
        string|iterable $paths,
        int $mode,
        int $umask = 0o000,
        bool $recursive = false,
    ): void {
        foreach ($this->toIterable($paths) as $path) {
            if (! self::box('chmod', $path, $mode & ~$umask)) {
                throw new FilesystemException(
                    \sprintf('Failed to chmod file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                    path: $path,
                );
            }
            if ($recursive && \is_dir($path) && ! \is_link($path)) {
                $this->setPermissions($this->directoryPaths($path), $mode, $umask, true);
            }
        }
    }

    public function setOwner(
        string|iterable $paths,
        string|int $owner,
        bool $recursive = false,
    ): void {
        foreach ($this->toIterable($paths) as $path) {
            if ($recursive && \is_dir($path) && ! \is_link($path)) {
                $this->setOwner($this->directoryPaths($path), $owner, true);
            }
            if (\is_link($path) && \function_exists('lchown')) {
                if (! self::box('lchown', $path, $owner)) {
                    throw new FilesystemException(
                        \sprintf('Failed to chown file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                        path: $path,
                    );
                }
            } elseif (! self::box('chown', $path, $owner)) {
                throw new FilesystemException(
                    \sprintf('Failed to chown file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                    path: $path,
                );
            }
        }
    }

    public function setGroup(
        string|iterable $paths,
        string|int $group,
        bool $recursive = false,
    ): void {
        foreach ($this->toIterable($paths) as $path) {
            if ($recursive && \is_dir($path) && ! \is_link($path)) {
                $this->setGroup($this->directoryPaths($path), $group, true);
            }
            if (\is_link($path) && \function_exists('lchgrp')) {
                if (! self::box('lchgrp', $path, $group)) {
                    throw new FilesystemException(
                        \sprintf('Failed to chgrp file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                        path: $path,
                    );
                }
            } elseif (! self::box('chgrp', $path, $group)) {
                throw new FilesystemException(
                    \sprintf('Failed to chgrp file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                    path: $path,
                );
            }
        }
    }

    public function move(
        string $source,
        string $target,
        bool $overwrite = false,
    ): void {
        if (! $overwrite && $this->pathsExist($target)) {
            throw new FilesystemException(
                \sprintf('Cannot rename because the target "%s" already exists.', $target),
                path: $target,
            );
        }

        if (! self::box('rename', $source, $target)) {
            if (\is_dir($source)) {
                $this->syncDirectory($source, $target, null, $overwrite, $overwrite);
                $this->remove($source);

                return;
            }
            throw new FilesystemException(
                \sprintf('Cannot rename "%s" to "%s": ', $source, $target) . ErrorHandler::get()->getLastError(),
                path: $target,
            );
        }
    }

    public function isReadable(
        string $path,
    ): bool {
        return \is_readable($path);
    }

    public function isWritable(
        string $path,
    ): bool {
        return \is_writable($path);
    }

    public function isFile(
        string $path,
    ): bool {
        return \is_file($path);
    }

    public function isDirectory(
        string $path,
    ): bool {
        return \is_dir($path);
    }

    public function isLink(
        string $path,
    ): bool {
        return \is_link($path);
    }

    public function isAbsolutePath(
        string $path,
    ): bool {
        if ('' === $path) {
            return false;
        }

        if (\str_contains($path, '://') && null !== \parse_url($path, \PHP_URL_SCHEME)) {
            return true;
        }

        if ('/' === $path[0]) {
            return true;
        }

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return false;
        }

        if ('\\' === $path[0]) {
            return true;
        }

        if (\strlen($path) > 1 && \ctype_alpha($path[0]) && ':' === $path[1]) {
            if (2 === \strlen($path)) {
                return true;
            }

            if ('/' === $path[2] || '\\' === $path[2]) {
                return true;
            }
        }

        return false;
    }

    public function createSymlink(
        string $source,
        string $target,
        bool $copyDirectoryOnWindows = false,
    ): void {
        self::assertFunctionExists('symlink');

        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::normalizeSlashes($source);
            self::normalizeSlashes($target);

            if ($copyDirectoryOnWindows) {
                $this->syncDirectory($source, $target);

                return;
            }
        }

        $this->createDirectory(\dirname($target));

        if (\is_link($target)) {
            if (\readlink($target) === $source) {
                return;
            }
            $this->remove($target);
        }

        if (! self::box('symlink', $source, $target)) {
            $this->linkException($source, $target, 'symbolic');
        }
    }

    public function createHardlink(
        string $source,
        string|iterable $targets,
    ): void {
        self::assertFunctionExists('link');

        if (! $this->assertPathExists($source)) {
            throw new FileNotFoundException(path: $source);
        }

        if (! \is_file($source)) {
            throw new FileNotFoundException(
                \sprintf('Origin file "%s" is not a file.', $source),
                path: $source,
            );
        }

        foreach ($this->toIterable($targets) as $target) {
            if (\is_file($target)) {
                if (\fileinode($source) === \fileinode($target)) {
                    continue;
                }
                $this->remove($target);
            }

            if (! self::box('link', $source, $target)) {
                $this->linkException($source, $target, 'hard');
            }
        }
    }

    public function readLinkTarget(
        string $path,
    ): null|string {
        if (! \is_link($path)) {
            return null;
        }

        return \readlink($path) ?: null;
    }

    public function resolvePath(
        string $path,
    ): null|string {
        if (! $this->assertPathExists($path)) {
            return null;
        }

        return \realpath($path) ?: null;
    }

    public function makeRelativePath(
        string $path,
        string $fromDirectory,
    ): string {
        if (! $this->isAbsolutePath($fromDirectory)) {
            throw new FilesystemException(
                \sprintf('The start path "%s" is not absolute.', $fromDirectory),
                path: $fromDirectory,
            );
        }

        if (! $this->isAbsolutePath($path)) {
            throw new FilesystemException(
                \sprintf('The end path "%s" is not absolute.', $path),
                path: $path,
            );
        }

        $originalEndPath = $path;

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $path          = normalize_slashes($path);
            $fromDirectory = normalize_slashes($fromDirectory);
        }

        $splitDriveLetter = static fn(string $segment) => (
            \strlen($segment) > 2 && ':' === $segment[1] && '/' === $segment[2] && \ctype_alpha($segment[0])
                ? [\substr($segment, 2), \strtoupper($segment[0])]
                : [$segment, null]
        );

        $splitPath = static function(string $segment): array {
            $result = [];

            foreach (\explode('/', \trim($segment, '/')) as $part) {
                if ('..' === $part) {
                    \array_pop($result);
                } elseif ('.' !== $part && '' !== $part) {
                    $result[] = $part;
                }
            }

            return $result;
        };

        [$path, $endDriveLetter] = $splitDriveLetter($path);
        [$fromDirectory, $startDriveLetter] = $splitDriveLetter($fromDirectory);

        $startPathArr = $splitPath($fromDirectory);
        $endPathArr   = $splitPath($path);

        if ($endDriveLetter && $startDriveLetter && $endDriveLetter !== $startDriveLetter) {
            return $endDriveLetter . ':/' . ( $endPathArr ? \implode('/', $endPathArr) . '/' : '' );
        }

        $index = 0;
        while (isset($startPathArr[$index], $endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            ++$index;
        }

        $depth = \count($startPathArr) - $index;

        $traverser        = \str_repeat('../', $depth);
        $endPathRemainder = \implode('/', \array_slice($endPathArr, $index));
        $relativePath     = $traverser . ( '' !== $endPathRemainder ? $endPathRemainder . '/' : '' );

        if (\str_ends_with($relativePath, '/') && \is_file($originalEndPath)) {
            $relativePath = \substr($relativePath, 0, -1);
        }

        return '' === $relativePath ? './' : $relativePath;
    }

    /**
     * @param Traversable<int, \SplFileInfo|string>|null $entries
     */
    public function syncDirectory(
        string $sourceDirectory,
        string $destinationDirectory,
        null|Traversable $entries = null,
        bool $alwaysOverwrite = false,
        bool $deleteMissingFiles = false,
        bool $copyLinksOnWindows = false,
    ): void {
        self::normalizeSlashes($destinationDirectory, trailing: true);
        self::normalizeSlashes($sourceDirectory, trailing: true);
        $sourceDirectoryLen = \strlen($sourceDirectory);

        if (! $this->assertPathExists($sourceDirectory)) {
            throw new FilesystemException(
                \sprintf('The origin directory specified "%s" was not found.', $sourceDirectory),
                path: $sourceDirectory,
            );
        }

        if ($this->assertPathExists($destinationDirectory) && $deleteMissingFiles) {
            $deleteIterator = $entries;
            if (null === $deleteIterator) {
                $flags          = FilesystemIterator::SKIP_DOTS;
                $deleteIterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($destinationDirectory, $flags),
                    RecursiveIteratorIterator::CHILD_FIRST,
                );
            }
            $destinationDirectoryLen = \strlen($destinationDirectory);
            foreach ($deleteIterator as $file) {
                $pathname = $file instanceof \SplFileInfo ? $file->getPathname() : (string) $file;
                self::normalizeSlashes($pathname);
                $origin = $sourceDirectory . \substr($pathname, $destinationDirectoryLen);
                if (! $this->assertPathExists($origin)) {
                    $this->remove($pathname);
                }
            }
        }

        if (null === $entries) {
            $flags = $copyLinksOnWindows
                ? FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
                : FilesystemIterator::SKIP_DOTS;
            $entries = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDirectory, $flags),
                RecursiveIteratorIterator::SELF_FIRST,
            );
        }

        $this->createDirectory($destinationDirectory);
        $destinationRealPath = \realpath($destinationDirectory) ?: false;
        if (\is_string($destinationRealPath)) {
            self::normalizeSlashes($destinationRealPath, trailing: true);
        }
        $destinationInsideSource =
            $destinationDirectory === $sourceDirectory
            || $this->isDescendantOf($destinationDirectory, $sourceDirectory);

        foreach ($entries as $file) {
            $pathname = $file instanceof \SplFileInfo ? $file->getPathname() : (string) $file;
            self::normalizeSlashes($pathname);
            $realPath = $file instanceof \SplFileInfo ? $file->getRealPath() : \realpath($pathname);
            if (\is_string($realPath)) {
                self::normalizeSlashes($realPath);
            }

            if ($destinationInsideSource) {
                if (
                    $this->isDescendantOf($pathname, $destinationDirectory)
                    || \is_string($realPath)
                    && \is_string($destinationRealPath)
                    && $this->isDescendantOf($realPath, $destinationRealPath)
                ) {
                    continue;
                }
            }

            if ($pathname !== $sourceDirectory && ! \str_starts_with($pathname, $sourceDirectory . \DIR_SEP)) {
                continue;
            }

            $relative = $pathname === $sourceDirectory
                ? ''
                : \substr($pathname, $sourceDirectoryLen);
            if ('' === $relative) {
                continue;
            }

            $target = $destinationDirectory . $relative;

            if (! $copyLinksOnWindows && \is_link($pathname)) {
                $linkTarget = $file instanceof \SplFileInfo ? $file->getLinkTarget() : \readlink($pathname);
                $this->createSymlink((string) $linkTarget, $target);
            } elseif (\is_dir($pathname)) {
                $this->createDirectory($target);
            } elseif (\is_file($pathname)) {
                $this->copyFile($pathname, $target, $alwaysOverwrite);
            } else {
                throw new FilesystemException(
                    \sprintf('Unable to guess "%s" file type.', $pathname),
                    path: $pathname,
                );
            }
        }
    }

    public function createTemporaryFile(
        string $directory,
        string $prefix,
        string $suffix = '',
    ): string {
        [$scheme, $hierarchy] = $this->getSchemeAndHierarchy($directory);

        if (( null === $scheme || 'file' === $scheme || 'gs' === $scheme ) && '' === $suffix) {
            $tmpFile = self::box('tempnam', $hierarchy, $prefix);
            if (\is_string($tmpFile) && '' !== $tmpFile) {
                if (null !== $scheme && 'gs' !== $scheme) {
                    return $scheme . '://' . $tmpFile;
                }

                return $tmpFile;
            }

            throw new FilesystemException(
                'A temporary file could not be created: ' . ErrorHandler::get()->getLastError(),
            );
        }

        $basename = $prefix . get_fast_hash(4) . $suffix;

        for ($i = 0; $i < 10; ++$i) {
            $tmpFile = match (true) {
                null === $scheme => self::joinLocalPath($hierarchy, $basename),
                'file' === $scheme => 'file://' . self::joinLocalPath($hierarchy, $basename),
                default => $directory . '/' . $basename,
            };

            if (! ( $handle = self::box('fopen', $tmpFile, 'x+') )) {
                continue;
            }

            self::box('fclose', $handle);

            return $tmpFile;
        }

        throw new FilesystemException('A temporary file could not be created: ' . ErrorHandler::get()->getLastError());
    }

    public function writeFileAtomically(
        string $path,
        mixed $content,
    ): void {
        if (\is_array($content)) {
            throw new \TypeError(\sprintf(
                'Argument 2 passed to "%s()" must be string or resource, array given.',
                __METHOD__,
            ));
        }

        $dir = \dirname($path);

        if (\is_link($path) && ( $linkTarget = $this->readLinkTarget($path) )) {
            $this->writeFileAtomically($this->makeAbsolute($linkTarget, $dir), $content);

            return;
        }

        if (! \is_dir($dir)) {
            $this->createDirectory($dir);
        }

        $tmpFile = $this->createTemporaryFile($dir, \basename($path));

        try {
            if (false === self::box('file_put_contents', $tmpFile, $content)) {
                throw new FilesystemException(
                    \sprintf('Failed to write file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                    path: $path,
                );
            }

            $existingPerms = self::box('fileperms', $path);
            self::box('chmod', $tmpFile, \is_int($existingPerms) ? $existingPerms : 0o666 & ~\umask());

            $this->move($tmpFile, $path, true);
        } finally {
            if (\file_exists($tmpFile)) {
                if ('\\' === \DIRECTORY_SEPARATOR && ! \is_writable($tmpFile)) {
                    $tmpPerms = self::box('fileperms', $tmpFile);
                    if (\is_int($tmpPerms)) {
                        self::box('chmod', $tmpFile, $tmpPerms | 0o200);
                    }
                }

                self::box('unlink', $tmpFile);
            }
        }
    }

    public function appendToFile(
        string $path,
        mixed $content,
        bool $lock = false,
    ): void {
        if (\is_array($content)) {
            throw new \TypeError(\sprintf(
                'Argument 2 passed to "%s()" must be string or resource, array given.',
                __METHOD__,
            ));
        }

        $dir = \dirname($path);

        if (! \is_dir($dir)) {
            $this->createDirectory($dir);
        }

        if (false === self::box('file_put_contents', $path, $content, \FILE_APPEND | ( $lock ? \LOCK_EX : 0 ))) {
            throw new FilesystemException(
                \sprintf('Failed to write file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                path: $path,
            );
        }
    }

    public function readFile(
        string $path,
    ): string {
        if (\is_dir($path)) {
            throw new FilesystemException(
                \sprintf('Failed to read file "%s": File is a directory.', $path),
                path: $path,
            );
        }

        $content = self::box('file_get_contents', $path);
        if (! \is_string($content)) {
            throw new FilesystemException(
                \sprintf('Failed to read file "%s": ', $path) . ErrorHandler::get()->getLastError(),
                path: $path,
            );
        }

        return $content;
    }

    public function fileSize(
        string $path,
    ): int {
        $size = self::box('filesize', $path);
        if (! \is_int($size)) {
            throw new FilesystemException(
                \sprintf('Failed to read file size of "%s": ', $path) . ErrorHandler::get()->getLastError(),
                path: $path,
            );
        }

        return $size;
    }

    public function modifiedTime(
        string $path,
    ): int {
        $time = self::box('filemtime', $path);
        if (! \is_int($time)) {
            throw new FilesystemException(
                \sprintf('Failed to read modification time of "%s": ', $path) . ErrorHandler::get()->getLastError(),
                path: $path,
            );
        }

        return $time;
    }

    public function createdTime(
        string $path,
    ): int {
        $time = self::box('filectime', $path);
        if (! \is_int($time)) {
            throw new FilesystemException(
                \sprintf('Failed to read creation time of "%s": ', $path) . ErrorHandler::get()->getLastError(),
                path: $path,
            );
        }

        return $time;
    }

    /**
     * @param list<string> $files
     */
    private static function doRemove(
        array $files,
        bool $isRecursive,
    ): void {
        $files = \array_reverse($files);
        foreach ($files as $file) {
            if (\is_link($file)) {
                if (
                    ! ( self::box('unlink', $file) || '\\' !== \DIRECTORY_SEPARATOR || self::box('rmdir', $file) )
                    && \file_exists($file)
                ) {
                    throw new FilesystemException(
                        \sprintf('Failed to remove symlink "%s": ', $file) . ErrorHandler::get()->getLastError(),
                    );
                }
            } elseif (\is_dir($file)) {
                $origFile = null;
                if (! $isRecursive) {
                    $tmpName = self::joinLocalPath(\dirname((string) \realpath($file)), '.!' . get_fast_hash(4));

                    if (\file_exists($tmpName)) {
                        try {
                            self::doRemove([$tmpName], true);
                        } catch (FilesystemException) {
                        }
                    }

                    if (! \file_exists($tmpName) && self::box('rename', $file, $tmpName)) {
                        $origFile = $file;
                        $file     = $tmpName;
                    }
                }

                $filesystemIterator = new FilesystemIterator(
                    $file,
                    FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS,
                );
                $childPaths = [];
                foreach ($filesystemIterator as $childPath) {
                    $childPaths[] = \is_string($childPath) ? $childPath : $childPath->getPathname();
                }
                self::doRemove($childPaths, true);

                if (! self::box('rmdir', $file) && \file_exists($file) && ! $isRecursive) {
                    $lastError = ErrorHandler::get()->getLastError();

                    if (null !== $origFile && self::box('rename', $file, $origFile)) {
                        $file = $origFile;
                    }

                    throw new FilesystemException(\sprintf('Failed to remove directory "%s": ', $file) . $lastError);
                }
            } elseif (
                ! self::box('unlink', $file)
                && (
                    ErrorHandler::get()->getLastError()
                    && \str_contains(ErrorHandler::get()->getLastError(), 'Permission denied')
                    || \file_exists($file)
                )
            ) {
                throw new FilesystemException(
                    \sprintf('Failed to remove file "%s": ', $file) . ErrorHandler::get()->getLastError(),
                );
            }
        }
    }

    private function linkException(
        string $origin,
        string $target,
        string $linkType,
    ): never {
        if (ErrorHandler::get()->getLastError()) {
            if (
                '\\' === \DIRECTORY_SEPARATOR
                && \str_contains(ErrorHandler::get()->getLastError(), 'error code(1314)')
            ) {
                throw new FilesystemException(
                    \sprintf(
                        'Unable to create "%s" link due to error code 1314: \'A required privilege is not held by the client\'. Do you have the required Administrator-rights?',
                        $linkType,
                    ),
                    path: $target,
                );
            }
        }
        throw new FilesystemException(
            \sprintf('Failed to create "%s" link from "%s" to "%s": ', $linkType, $origin, $target)
                . ErrorHandler::get()->getLastError(),
            path: $target,
        );
    }

    private function makeAbsolute(
        string $path,
        string $basePath,
    ): string {
        if ($this->isAbsolutePath($path)) {
            return normalize_path($path, traversal: true);
        }

        return normalize_path(
            path: [\rtrim($basePath, '/\\'), $path],
            traversal: true,
        );
    }

    private function assertPathExists(
        string $path,
    ): bool {
        $maxPathLength = \PHP_MAXPATHLEN - 2;

        if (\strlen($path) > $maxPathLength) {
            throw new FilesystemException(
                message: "Could not check if file exists because path length exceeds {$maxPathLength} characters.",
                path: $path,
            );
        }

        return \file_exists($path);
    }

    /**
     * @return list<string>
     */
    private function directoryPaths(
        string $directory,
    ): array {
        $paths = [];
        foreach (new FilesystemIterator(
            $directory,
            FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS,
        ) as $path) {
            $paths[] = \is_string($path) ? $path : $path->getPathname();
        }

        return $paths;
    }

    /**
     * @param string|iterable  $paths
     *
     * @return iterable<string>
     */
    private function toIterable(
        string|iterable $paths,
    ): iterable {
        return \is_string($paths) ? [$paths] : $paths;
    }

    private function isDescendantOf(
        string $path,
        string $ancestor,
    ): bool {
        self::normalizeSlashes($path, trailing: true);
        self::normalizeSlashes($ancestor, trailing: true);

        if ($path === $ancestor) {
            return true;
        }

        return \str_starts_with($path, $ancestor . \DIR_SEP);
    }

    /**
     * @return array{0: null|string, 1: string}
     */
    private function getSchemeAndHierarchy(
        string $filename,
    ): array {
        $components = \explode('://', $filename, 2);

        return 2 === \count($components) ? [$components[0], $components[1]] : [null, $components[0]];
    }

    private static function assertFunctionExists(
        string $func,
    ): void {
        if (! \function_exists($func)) {
            throw new FilesystemException(
                \sprintf(
                    'Unable to perform filesystem operation because the "%s()" function has been disabled.',
                    $func,
                ),
            );
        }
    }

    private static function joinLocalPath(
        string ...$segments,
    ): string {
        return normalize_path($segments);
    }

    /**
     * @param callable-string $func
     */
    private static function box(
        string $func,
        mixed ...$args,
    ): mixed {
        self::assertFunctionExists($func);

        return ErrorHandler::get()->box(
            static fn(): mixed => \call_user_func_array($func, $args),
        );
    }

    /**
     * Normalizes `/` and `\` to {@see \DIR_SEP} for path comparison and joining.
     *
     * Skips URL-like paths (`scheme://...`) so stream wrappers are left intact.
     */
    private static function normalizeSlashes(
        string &$path,
        bool $trailing = false,
    ): void {
        if (\str_contains($path, '://')) {
            return;
        }

        $path = \str_replace(['\\', '/'], \DIR_SEP, $path);

        if ($trailing) {
            $path = \rtrim($path, \DIR_SEP);
        }
    }
}
