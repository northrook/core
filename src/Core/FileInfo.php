<?php

declare(strict_types=1);

namespace Northrook\Core;

use Override;
use RuntimeException;
use SplFileInfo;
use Stringable;

class FileInfo extends SplFileInfo
{
    public function __construct(
        string|SplFileInfo|Stringable $filename,
    ) {
        $string = (string) $filename;
        if (! \str_contains($string, '://')) {
            $string = normalize_path($string);
        }
        parent::__construct($string);
    }

    final public function append(
        string|Stringable $string,
    ): FileInfo {
        $path = \str_ends_with($this->getPathname(), DIRECTORY_SEPARATOR)
            ? $this->getPathname() . $string
            : $this->getPathname() . DIRECTORY_SEPARATOR . $string;
        return new self($path);
    }

    /**
     * Returns the `filename` without the extension.
     *
     * @return string
     */
    #[Override]
    final public function getFilename(): string
    {
        return \strrchr(parent::getFilename(), '.', true) ?: parent::getFilename();
    }

    final public function isDotFile(): bool
    {
        return (
            \str_starts_with(
                $this->getBasename(),
                '.',
            )
            && $this->isFile()
        );
    }

    final public function isDotDirectory(): bool
    {
        return \str_contains(
            $this->getPath(),
            DIRECTORY_SEPARATOR . '.',
        );
    }

    final public function getContents(
        bool $throwOnError = false,
    ): null|string {
        $contents = \file_get_contents($this->getPathname());

        if (false === $contents && $throwOnError) {
            throw new RuntimeException(
                'Unable to read file: ' . $this->getPathname(),
            );
        }

        return $contents ?: null;
    }

    final public function exists(
        bool $throwOnError = false,
    ): bool {
        $exists = \file_exists($this->getPathname());

        if (false === $exists && $throwOnError) {
            throw new RuntimeException(
                'The file does not exist: ' . $this->getPathname(),
            );
        }

        return $exists;
    }

    /**
     * Atomically dumps content into a file.
     *
     * - {@see IOException} will be caught and logged as an error, returning false
     *
     * @param resource|string $content The data to write into the file
     *
     * @return false|int The number of bytes that were written into the file, or false on failure.
     */
    final public function save(
        mixed $content,
        bool $overwrite = true,
        bool $append = false,
    ): false|int {
        return file_save(
            $this->getPathname(),
            $content,
            $overwrite,
            $append,
        );
    }

    final public function mkdir(
        int $mode = 0777,
    ): bool {
        return \mkdir(
            directory: $this->getPathname(),
            permissions: $mode,
            recursive: true,
        );
    }

    /**
     * Perform one or more `glob(..)` patterns on {@see self::getPathname()}.
     *
     * Each matched result is `normalized`.
     *
     * @param string|string[] $pattern
     * @param null|int             $flags
     * @param bool            $asFileInfo
     *
     * @return ($asFileInfo is true ? list<FileInfo> : list<string>)
     */
    final public function glob(
        string|array $pattern,
        null|int $flags = null,
        bool $asFileInfo = false,
    ): array {
        $flags ??= GLOB_NOSORT | GLOB_BRACE;
        $path  = \rtrim($this->getPathname(), '\\/');
        $glob  = [];

        foreach ((array) $pattern as $match) {
            $match  = \DIRECTORY_SEPARATOR . \ltrim($match, '\\/');
            $glob[] = \glob($path . $match, $flags) ?: [];
        }

        $matches = \array_merge(...$glob);

        if ($asFileInfo) {
            return \array_map(
                static fn(string $filename): FileInfo => FileInfo::from($filename),
                $matches,
            );
        }

        return \array_map(
            static fn(string $path): string => normalize_path($path),
            $matches,
        );
    }

    /**
     * Sets access and modification time of file.
     *
     * @param ?int $time  The touch time as a Unix timestamp, if not supplied the current system time is used
     * @param ?int $atime The access time as a Unix timestamp, if not supplied the current system time is used
     *
     * @return bool
     */
    final public function touch(
        null|int $time = null,
        null|int $atime = null,
    ): bool {
        return \touch(
            $this->getPathname(),
            $time,
            $atime,
        );
    }

    /**
     * Copies {@see self::getRealPath()} to {@see $targetFile}.
     *
     * - If the target file is automatically overwritten when this file is newer.
     * - If the target is newer, $overwriteNewerFiles decides whether to overwrite.
     * - {@see IOException}s will be caught and logged as an error, returning false
     *
     * @param string $targetFile
     * @param bool   $overwriteNewerFiles
     *
     * @return bool True if the file was written to, false if it already existed or an error occurred
     */
    final public function copy(
        string $targetFile,
        bool $overwriteNewerFiles = false,
    ): bool {
        return file_copy($this->getPathname(), $targetFile, $overwriteNewerFiles);
    }

    /**
     * Remove {@see self}.
     *
     * @return bool
     */
    final public function remove(): bool
    {
        return file_remove($this->getPathname());
    }

    final public static function from(
        string|SplFileInfo|Stringable $filename,
    ): self {
        return new self($filename);
    }
}
