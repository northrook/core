<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Core\Filesystem;
use PHPUnit\Framework\TestCase;

abstract class FilesystemTestCase extends TestCase
{
    protected Filesystem $filesystem;

    protected string $workspace;

    private int $umask;

    private static ?bool $linkOnWindows = null;

    private static ?bool $symlinkOnWindows = null;

    public static function setUpBeforeClass(): void
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return;
        }

        self::$linkOnWindows = true;
        $originFile = \tempnam(\sys_get_temp_dir(), 'li');
        $targetFile = \tempnam(\sys_get_temp_dir(), 'li');
        if (true !== @\link($originFile, $targetFile)) {
            $report = \error_get_last();
            if (\is_array($report) && \str_contains($report['message'], 'error code(1314)')) {
                self::$linkOnWindows = false;
            }
        } else {
            @\unlink($targetFile);
        }

        self::$symlinkOnWindows = true;
        $originDir = \tempnam(\sys_get_temp_dir(), 'sl');
        $targetDir = \tempnam(\sys_get_temp_dir(), 'sl');
        if (true !== @\symlink($originDir, $targetDir)) {
            $report = \error_get_last();
            if (\is_array($report) && \str_contains($report['message'], 'error code(1314)')) {
                self::$symlinkOnWindows = false;
            }
        } else {
            @\unlink($targetDir);
        }
    }

    protected function setUp(): void
    {
        $this->umask        = \umask(0);
        $this->filesystem   = new Filesystem();
        $this->workspace    = \sys_get_temp_dir() . \DIR_SEP . 'northrook-filesystem-' . \bin2hex(\random_bytes(8));
        $this->filesystem->createDirectory($this->workspace);
        $resolved = \realpath($this->workspace);
        if (\is_string($resolved)) {
            $this->workspace = $resolved;
        }
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->workspace)) {
            $this->filesystem->remove($this->workspace);
        }
        \umask($this->umask);
    }

    protected function path(string ...$segments): string
    {
        return $this->workspace . \DIR_SEP . \implode(\DIR_SEP, $segments);
    }

    protected function pathExists(string $path): bool
    {
        return $this->filesystem->pathsExist($path);
    }

    protected function assertFileContentsEqual(string $expectedFile, string $actualFile): void
    {
        self::assertFileExists($expectedFile);
        self::assertFileExists($actualFile);
        self::assertSame(
            \file_get_contents($expectedFile),
            \file_get_contents($actualFile),
            \sprintf('File "%s" does not match "%s".', $actualFile, $expectedFile),
        );
    }

    protected function assertFilePermissions(int $expectedPermissions, string $path): void
    {
        $actual = (int) \substr(\sprintf('%o', \fileperms($path)), -3);
        self::assertSame(
            $expectedPermissions,
            $actual,
            \sprintf('Permissions for "%s" must be %03o, got %03o.', $path, $expectedPermissions, $actual),
        );
    }

    protected function markAsSkippedIfLinkIsMissing(): void
    {
        if (! \function_exists('link')) {
            $this->markTestSkipped('link is not supported');
        }

        if ('\\' === \DIRECTORY_SEPARATOR && false === self::$linkOnWindows) {
            $this->markTestSkipped('link requires "Create hard links" privilege on Windows');
        }
    }

    protected function markAsSkippedIfSymlinkIsMissing(bool $relative = false): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR && false === self::$symlinkOnWindows) {
            $this->markTestSkipped('symlink requires "Create symbolic links" privilege on Windows');
        }

        if ($relative && '\\' === \DIRECTORY_SEPARATOR && \PHP_ZTS) {
            $this->markTestSkipped('symlink does not support relative paths on thread-safe Windows PHP versions');
        }
    }

    protected function markAsSkippedIfChmodIsMissing(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('chmod is not supported on Windows');
        }
    }

    protected function markAsSkippedIfPosixIsMissing(): void
    {
        if (! \function_exists('posix_isatty')) {
            $this->markTestSkipped('Function posix_isatty is required.');
        }
    }
}
