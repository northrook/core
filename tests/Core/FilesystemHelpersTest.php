<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Contracts\Exceptions\FileNotFoundException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\Core\FileInfo;
use Northrook\ErrorHandler;
use Northrook\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Northrook\Core\file_copy;
use function Northrook\Core\file_remove;
use function Northrook\Core\file_save;
use function Northrook\Core\filesystem;
use function Northrook\Core\normalize_path;
use function Northrook\Core\path_info;
use function Northrook\Core\path_readable;
use function Northrook\Core\path_valid;
use function Northrook\Core\path_writable;

final class FilesystemHelpersTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = \sys_get_temp_dir() . \DIR_SEP . 'northrook-fs-helpers-' . \bin2hex(\random_bytes(8));
        \mkdir($this->workspace, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->workspace)) {
            new Filesystem()->remove($this->workspace);
        }
    }

    public function testFileSaveWritesNewFile(): void
    {
        $path = $this->workspace . \DIR_SEP . 'new.txt';

        self::assertSame(5, file_save($path, 'hello'));
        self::assertSame('hello', \file_get_contents($path));
    }

    public function testFileSaveReturnsFalseWhenOverwriteDisabled(): void
    {
        $path = $this->workspace . \DIR_SEP . 'existing.txt';
        \file_put_contents($path, 'original');

        self::assertFalse(file_save($path, 'replacement', overwrite: false));
        self::assertSame('original', \file_get_contents($path));
    }

    public function testFileSaveAppendsAndReturnsBytesWritten(): void
    {
        $path = $this->workspace . \DIR_SEP . 'append.txt';
        file_save($path, 'ab');

        self::assertSame(2, file_save($path, 'cd', append: true));
        self::assertSame('abcd', \file_get_contents($path));
    }

    public function testFileSaveWrapsFilesystemExceptionAsRuntimeException(): void
    {
        $blocker = $this->workspace . \DIR_SEP . 'not-a-directory';
        \file_put_contents($blocker, 'x');

        $this->expectException(RuntimeException::class);

        try {
            file_save($blocker . \DIR_SEP . 'child.txt', 'data');
        } catch (RuntimeException $e) {
            self::assertInstanceOf(FilesystemException::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testFileCopyAndRemove(): void
    {
        $source = $this->workspace . \DIR_SEP . 'source.txt';
        $target = $this->workspace . \DIR_SEP . 'target.txt';
        \file_put_contents($source, 'payload');

        self::assertTrue(file_copy($source, $target));
        self::assertSame('payload', \file_get_contents($target));
        self::assertTrue(file_remove($target));
        self::assertFalse(\file_exists($target));
    }

    public function testFileCopyReturnsFalseForMissingSource(): void
    {
        self::assertFalse(
            file_copy(
                $this->workspace . \DIR_SEP . 'missing.txt',
                $this->workspace . \DIR_SEP . 'target.txt',
            ),
        );
    }

    public function testPathReadableAndWritable(): void
    {
        $path = $this->workspace . \DIR_SEP . 'readable.txt';
        \file_put_contents($path, 'x');

        self::assertTrue(path_readable($path));
        self::assertTrue(path_writable($path));
        self::assertTrue(path_valid($path));
    }

    public function testPathReadableThrowsForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        path_readable($this->workspace . \DIR_SEP . 'missing.txt', throw: true);
    }

    public function testPathWritableThrowsForMissingPath(): void
    {
        $this->expectException(FileNotFoundException::class);
        path_writable($this->workspace . \DIR_SEP . 'missing.txt', throw: true);
    }

    public function testPathInfoSplitsNormalizedPath(): void
    {
        $path = $this->workspace . \DIR_SEP . 'nested' . \DIR_SEP . 'file.txt';

        self::assertSame(
            [
                normalize_path($this->workspace . \DIR_SEP . 'nested'),
                'file',
                'txt',
            ],
            path_info($path),
        );
    }

    public function testFilesystemSingletonUsesRegisteredErrorHandler(): void
    {
        self::assertTrue(ErrorHandler::isRegistered());

        $filesystem = filesystem();
        $property   = new \ReflectionProperty($filesystem, 'errorHandler');

        self::assertSame(ErrorHandler::get(), $property->getValue($filesystem));
    }

    public function testFileInfoDelegatesToFilesystemHelpers(): void
    {
        $path = $this->workspace . \DIR_SEP . 'fileinfo.txt';
        $file = FileInfo::from($path);

        self::assertSame(3, $file->save('abc'));
        self::assertTrue($file->exists());
        self::assertSame('abc', $file->getContents());

        $dir = FileInfo::from($this->workspace . \DIR_SEP . 'child-dir');
        self::assertTrue($dir->mkdir());
        self::assertTrue($dir->isDir());
    }

    public function testFileInfoGetContentsReturnsNullForMissingFile(): void
    {
        $file = FileInfo::from($this->workspace . \DIR_SEP . 'absent.txt');

        self::assertNull($file->getContents());
    }

    public function testFileInfoGetContentsThrowsWhenRequested(): void
    {
        $file = FileInfo::from($this->workspace . \DIR_SEP . 'absent.txt');

        $this->expectException(RuntimeException::class);
        $file->getContents(throwOnError: true);
    }
}
