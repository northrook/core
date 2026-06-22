<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Contracts\Exceptions\FileNotFoundException;
use Northrook\Contracts\Exceptions\FilesystemException;

use function Northrook\Core\file_copy;
use function Northrook\Core\file_remove;

final class FilesystemTest extends FilesystemTestCase
{
    public function testCopyFileCreatesTarget(): void
    {
        $source = $this->path('copy_source_file');
        $target = $this->path('copy_target_file');
        \file_put_contents($source, 'SOURCE FILE');

        $this->filesystem->copyFile($source, $target);

        self::assertFileExists($target);
        self::assertStringEqualsFile($target, 'SOURCE FILE');
    }

    public function testCopyFileThrowsWhenSourceMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->filesystem->copyFile($this->path('missing'), $this->path('out'));
    }

    public function testCopyFileSkipsWhenTargetIsNewer(): void
    {
        $source = $this->path('source.txt');
        $target = $this->path('target.txt');
        \file_put_contents($source, 'old');
        \file_put_contents($target, 'new');
        \touch($source, \time() - 3600);
        \touch($target, \time());

        $this->filesystem->copyFile($source, $target);

        self::assertSame('new', $this->filesystem->readFile($target));
    }

    public function testCopyFileOverwritesWhenAlwaysOverwrite(): void
    {
        $source = $this->path('source.txt');
        $target = $this->path('target.txt');
        \file_put_contents($source, 'updated');
        \file_put_contents($target, 'stale');
        \touch($source, \time() - 3600);
        \touch($target, \time());

        $this->filesystem->copyFile($source, $target, alwaysOverwrite: true);

        self::assertSame('updated', $this->filesystem->readFile($target));
    }

    public function testCopyFileDoesNotOverrideWhenSameMtime(): void
    {
        $source = $this->path('copy_source_file');
        $target = $this->path('copy_target_file');
        \file_put_contents($source, 'SOURCE FILE');
        \file_put_contents($target, 'TARGET FILE');

        $mtime = \time() - 1000;
        \touch($source, $mtime);
        \touch($target, $mtime);

        $this->filesystem->copyFile($source, $target);

        self::assertStringEqualsFile($target, 'TARGET FILE');
    }

    public function testCopyFileOverridesWhenSourceIsNewer(): void
    {
        $source = $this->path('copy_source_file');
        $target = $this->path('copy_target_file');
        \file_put_contents($source, 'SOURCE FILE');
        \file_put_contents($target, 'TARGET FILE');
        \touch($target, \time() - 1000);

        $this->filesystem->copyFile($source, $target);

        self::assertStringEqualsFile($target, 'SOURCE FILE');
    }

    public function testCopyFileCreatesTargetDirectoryIfItDoesNotExist(): void
    {
        $source = $this->path('copy_source_file');
        $target = $this->path('directory', 'copy_target_file');
        \file_put_contents($source, 'SOURCE FILE');

        $this->filesystem->copyFile($source, $target);

        self::assertDirectoryExists($this->path('directory'));
        self::assertStringEqualsFile($target, 'SOURCE FILE');
    }

    public function testPathsExist(): void
    {
        $file = $this->path('exists.txt');
        \file_put_contents($file, 'x');

        self::assertTrue($this->filesystem->pathsExist($file, $this->workspace));
        self::assertFalse($this->filesystem->pathsExist($file, $this->path('missing')));
    }

    public function testPathsExistWithTraversable(): void
    {
        $base = $this->path('exists-traversable');
        $this->filesystem->createDirectory($base);
        \file_put_contents($base . \DIR_SEP . 'file', 'x');

        $paths = new \ArrayObject([
            $base . \DIR_SEP . 'file',
            $base,
        ]);

        self::assertTrue($this->filesystem->pathsExist(...\iterator_to_array($paths)));
    }

    public function testCreateDirectoryIsIdempotent(): void
    {
        $directory = $this->path('nested', 'deep');
        $this->filesystem->createDirectory($directory);
        $this->filesystem->createDirectory($directory);

        self::assertDirectoryExists($directory);
    }

    public function testCreateDirectoryFromArray(): void
    {
        $directories = [
            $this->path('1'),
            $this->path('2'),
            $this->path('3'),
        ];

        $this->filesystem->createDirectory($directories);

        foreach ($directories as $directory) {
            self::assertDirectoryExists($directory);
        }
    }

    public function testCreateDirectoryFromTraversable(): void
    {
        $directories = new \ArrayObject([
            $this->path('1'),
            $this->path('2'),
            $this->path('3'),
        ]);

        $this->filesystem->createDirectory($directories);

        self::assertDirectoryExists($this->path('1'));
        self::assertDirectoryExists($this->path('2'));
        self::assertDirectoryExists($this->path('3'));
    }

    public function testTouchCreatesFilesFromTraversable(): void
    {
        $files = new \ArrayObject([
            $this->path('touch-1'),
            $this->path('touch-2'),
            $this->path('touch-3'),
        ]);

        $this->filesystem->touch($files);

        foreach ($files as $file) {
            self::assertFileExists($file);
        }
    }

    public function testCreateDirectoryFailsWhenPathIsFile(): void
    {
        $file = $this->path('2');
        \file_put_contents($file, '');

        $this->expectException(FilesystemException::class);
        $this->filesystem->createDirectory($file);
    }

    public function testCreateParentDirectory(): void
    {
        $file = $this->path('parents', 'child', 'file.txt');
        $this->filesystem->createParentDirectory($file);

        self::assertDirectoryExists(\dirname($file));
    }

    public function testTouchCreatesEmptyFile(): void
    {
        $file = $this->path('1');
        $this->filesystem->touch($file);

        self::assertFileExists($file);
    }

    public function testTouchCreatesFilesFromArray(): void
    {
        $files = [
            $this->path('1'),
            $this->path('2'),
            $this->path('3'),
        ];

        $this->filesystem->touch($files);

        foreach ($files as $file) {
            self::assertFileExists($file);
        }
    }

    public function testTouchFailsWhenParentIsFile(): void
    {
        $this->expectException(FilesystemException::class);
        $this->filesystem->touch($this->path('1', '2'));
    }

    public function testRemoveCleansDirectoryRecursively(): void
    {
        $base = $this->path('directory') . \DIR_SEP;
        \mkdir($base);
        \mkdir($base . 'dir');
        \touch($base . 'file');

        $this->filesystem->remove($base);

        self::assertDirectoryDoesNotExist($base);
    }

    public function testRemoveCleansArrayOfPaths(): void
    {
        \mkdir($this->path('dir'));
        \touch($this->path('file'));

        $this->filesystem->remove([
            $this->path('dir'),
            $this->path('file'),
        ]);

        self::assertDirectoryDoesNotExist($this->path('dir'));
        self::assertFileDoesNotExist($this->path('file'));
    }

    public function testRemoveCleansTraversable(): void
    {
        \mkdir($this->path('dir'));
        \touch($this->path('file'));

        $this->filesystem->remove(new \ArrayObject([
            $this->path('dir'),
            $this->path('file'),
        ]));

        self::assertDirectoryDoesNotExist($this->path('dir'));
        self::assertFileDoesNotExist($this->path('file'));
    }

    public function testRemoveIgnoresNonExistingFiles(): void
    {
        \mkdir($this->path('dir'));

        $this->filesystem->remove([
            $this->path('dir'),
            $this->path('file'),
        ]);

        self::assertDirectoryDoesNotExist($this->path('dir'));
    }

    public function testRemoveCleansInvalidSymlinks(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $base = $this->path('directory') . \DIR_SEP;
        \mkdir($base);
        \mkdir($base . 'dir');
        @\symlink($base . 'file', $base . 'file-link');
        $this->filesystem->createSymlink($base . 'dir' . \DIR_SEP, $base . 'dir-link');
        \rmdir($base . 'dir');

        $this->filesystem->remove($base);

        self::assertDirectoryDoesNotExist($base);
    }

    public function testMoveRenamesFile(): void
    {
        $source = $this->path('move-source.txt');
        $target = $this->path('move-target.txt');
        \file_put_contents($source, 'move');

        $this->filesystem->move($source, $target);

        self::assertFileDoesNotExist($source);
        self::assertSame('move', $this->filesystem->readFile($target));
    }

    public function testMoveRejectsExistingTargetUnlessOverwrite(): void
    {
        $source = $this->path('move-a.txt');
        $target = $this->path('move-b.txt');
        \file_put_contents($source, 'a');
        \file_put_contents($target, 'b');

        $this->expectException(FilesystemException::class);
        $this->filesystem->move($source, $target);
    }

    public function testMoveOverwritesWhenRequested(): void
    {
        $source = $this->path('move-a.txt');
        $target = $this->path('move-b.txt');
        \file_put_contents($source, 'a');
        \file_put_contents($target, 'b');

        $this->filesystem->move($source, $target, overwrite: true);

        self::assertFileDoesNotExist($source);
        self::assertSame('a', $this->filesystem->readFile($target));
    }

    public function testWriteReadAndAppendFile(): void
    {
        $file = $this->path('atomic.txt');
        $this->filesystem->writeFileAtomically($file, 'hello');
        $this->filesystem->appendToFile($file, ' world');

        self::assertSame('hello world', $this->filesystem->readFile($file));
    }

    public function testWriteFileAtomicallyOverwritesExistingFile(): void
    {
        $file = $this->path('dump.txt');
        \file_put_contents($file, 'OLD');

        $this->filesystem->writeFileAtomically($file, 'NEW');

        self::assertSame('NEW', $this->filesystem->readFile($file));
    }

    public function testAppendToFileCreatesFileWhenMissing(): void
    {
        $file = $this->path('append-create.txt');
        $this->filesystem->appendToFile($file, 'content');

        self::assertSame('content', $this->filesystem->readFile($file));
    }

    public function testReadFileThrowsForDirectory(): void
    {
        $this->expectException(FilesystemException::class);
        $this->filesystem->readFile($this->workspace);
    }

    public function testCreateTemporaryFile(): void
    {
        $path = $this->filesystem->createTemporaryFile($this->workspace, 'tmp_');

        self::assertFileExists($path);
        self::assertTrue(\str_starts_with($path, $this->workspace));
        $this->filesystem->remove($path);
    }

    public function testCreateTemporaryFileWithSuffix(): void
    {
        $path = $this->filesystem->createTemporaryFile($this->workspace, 'tmp_', '.dat');

        self::assertFileExists($path);
        self::assertStringEndsWith('.dat', $path);
        $this->filesystem->remove($path);
    }

    public function testCreateTemporaryFileWithFileScheme(): void
    {
        $path = $this->filesystem->createTemporaryFile('file://' . $this->workspace, 'tmp_');

        self::assertStringStartsWith('file://', $path);
        self::assertFileExists($path);
        $this->filesystem->remove($path);
    }

    public function testResolvePathAndIsAbsolutePath(): void
    {
        $file = $this->path('resolved.txt');
        \file_put_contents($file, 'x');

        self::assertTrue($this->filesystem->isAbsolutePath($file));
        self::assertFalse($this->filesystem->isAbsolutePath('relative/path'));
        self::assertSame(\realpath($file), $this->filesystem->resolvePath($file));
        self::assertNull($this->filesystem->resolvePath($this->path('missing')));
    }

    public function testSyncDirectoryCopiesTreeRecursively(): void
    {
        $source = $this->path('source') . \DIR_SEP;
        $target = $this->path('target') . \DIR_SEP;
        $file1  = $source . 'directory' . \DIR_SEP . 'file1';
        $file2  = $source . 'file2';

        \mkdir($source);
        \mkdir($source . 'directory');
        \file_put_contents($file1, 'FILE1');
        \file_put_contents($file2, 'FILE2');

        $this->filesystem->syncDirectory($source, $target);

        self::assertDirectoryExists($target);
        self::assertDirectoryExists($target . 'directory');
        self::assertFileContentsEqual($file1, $target . 'directory' . \DIR_SEP . 'file1');
        self::assertFileContentsEqual($file2, $target . 'file2');
    }

    public function testSyncDirectoryDeletesMissingFilesWhenRequested(): void
    {
        $source = $this->path('sync-source') . \DIR_SEP;
        $target = $this->path('sync-target') . \DIR_SEP;
        \mkdir($source);
        \mkdir($source . 'keep');
        \mkdir($target . 'stale', 0o777, true);
        \file_put_contents($source . 'keep' . \DIR_SEP . 'file.txt', 'keep');
        \file_put_contents($target . 'stale' . \DIR_SEP . 'file.txt', 'stale');

        $this->filesystem->syncDirectory($source, $target, deleteMissingFiles: true);

        self::assertFileExists($target . 'keep' . \DIR_SEP . 'file.txt');
        self::assertDirectoryDoesNotExist($target . 'stale');
    }

    public function testSyncDirectoryPreservesStaleFilesWithoutDeleteOption(): void
    {
        $source = $this->path('sync-source-2') . \DIR_SEP;
        $target = $this->path('sync-target-2') . \DIR_SEP;
        $file1  = $source . 'directory' . \DIR_SEP . 'file1';

        \mkdir($source);
        \mkdir($source . 'directory');
        \mkdir($target . 'directory', 0o777, true);
        \file_put_contents($file1, 'FILE1');
        \file_put_contents($target . 'directory' . \DIR_SEP . 'file1', 'STALE');

        \unlink($file1);

        $this->filesystem->syncDirectory($source, $target, deleteMissingFiles: false);

        self::assertFileExists($target . 'directory' . \DIR_SEP . 'file1');
    }

    public function testSyncDirectoryRemovesStaleFileWithDeleteOption(): void
    {
        $source = $this->path('sync-source-3') . \DIR_SEP;
        $target = $this->path('sync-target-3') . \DIR_SEP;
        $file1  = $source . 'directory' . \DIR_SEP . 'file1';

        \mkdir($source);
        \mkdir($source . 'directory');
        \mkdir($target . 'directory', 0o777, true);
        \file_put_contents($file1, 'FILE1');
        \file_put_contents($target . 'directory' . \DIR_SEP . 'file1', 'STALE');

        \unlink($file1);

        $this->filesystem->syncDirectory($source, $target, deleteMissingFiles: true);

        self::assertFileDoesNotExist($target . 'directory' . \DIR_SEP . 'file1');
    }

    public function testSyncDirectoryCreatesEmptyDirectory(): void
    {
        $source = $this->path('empty-source') . \DIR_SEP;
        $target = $this->path('empty-target') . \DIR_SEP;
        \mkdir($source);

        $this->filesystem->syncDirectory($source, $target);

        self::assertDirectoryExists($target);
    }

    public function testSyncDirectoryCopiesSymlinks(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $source = $this->path('symlink-source') . \DIR_SEP;
        $target = $this->path('symlink-target') . \DIR_SEP;
        \mkdir($source);
        \file_put_contents($source . 'file1', 'FILE1');
        \symlink($source . 'file1', $source . 'link1');

        $this->filesystem->syncDirectory($source, $target);

        self::assertFileContentsEqual($source . 'file1', $target . 'link1');
        self::assertTrue(\is_link($target . 'link1'));
    }

    public function testSyncDirectoryFollowsSymlinksWhenRequested(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $source = $this->path('follow-source') . \DIR_SEP;
        $target = $this->path('follow-target') . \DIR_SEP;
        \mkdir($source);
        \file_put_contents($source . 'file1', 'FILE1');
        \symlink($source . 'file1', $source . 'link1');

        $this->filesystem->syncDirectory($source, $target, copyLinksOnWindows: true);

        self::assertFileContentsEqual($source . 'file1', $target . 'link1');
        self::assertFalse(\is_link($target . 'link1'));
    }

    public function testSyncDirectoryCopiesLinkedDirectoryContents(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $source = $this->path('linked-dir-source') . \DIR_SEP;
        $target = $this->path('linked-dir-target') . \DIR_SEP;
        \mkdir($source . 'nested', 0o777, true);
        \file_put_contents($source . 'nested' . \DIR_SEP . 'file1.txt', 'FILE1');
        \symlink($source . 'nested', $source . 'link1');

        $this->filesystem->syncDirectory($source, $target);

        self::assertFileContentsEqual(
            $source . 'nested' . \DIR_SEP . 'file1.txt',
            $target . 'link1' . \DIR_SEP . 'file1.txt',
        );
        self::assertTrue(\is_link($target . 'link1'));
    }

    public function testSyncDirectoryAvoidsCopyingTargetInsideSource(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $source    = $this->path('nested-source') . \DIR_SEP;
        $directory = $source . 'directory' . \DIR_SEP;
        $file1     = $directory . 'file1';
        $file2     = $source . 'file2';
        $target    = $source . 'target' . \DIR_SEP;

        \mkdir($source);
        \mkdir($directory);
        \file_put_contents($file1, 'FILE1');
        \file_put_contents($file2, 'FILE2');

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->filesystem->createSymlink($target, $source . 'target_simlink');
        }

        $this->filesystem->syncDirectory($source, $target, deleteMissingFiles: true);

        self::assertTrue($this->pathExists($target));
        self::assertTrue($this->pathExists($target . 'directory'));
        self::assertFileContentsEqual($file1, $target . 'directory' . \DIR_SEP . 'file1');
        self::assertFileContentsEqual($file2, $target . 'file2');
        self::assertFalse($this->pathExists($target . 'target_simlink'));
        self::assertFalse($this->pathExists($target . 'target'));
    }

    public function testSyncDirectoryDoesNotMirrorPreExistingDestinationContentsIntoItself(): void
    {
        $source = $this->path('nested-source-2');
        $target = $source . \DIR_SEP . 'target';
        $this->filesystem->createDirectory([$source, $target]);
        \file_put_contents($source . \DIR_SEP . 'file.txt', 'DATA');
        \file_put_contents($target . \DIR_SEP . 'existing.txt', 'OLD');

        $this->filesystem->syncDirectory($source, $target);

        self::assertFileExists($target . \DIR_SEP . 'file.txt');
        self::assertSame('OLD', $this->filesystem->readFile($target . \DIR_SEP . 'existing.txt'));
        self::assertDirectoryDoesNotExist($target . \DIR_SEP . 'target');
    }

    public function testSyncDirectoryFromSubdirectoryIntoParent(): void
    {
        $target = $this->path('foo') . \DIR_SEP;
        $source = $target . 'bar' . \DIR_SEP;
        $file1  = $source . 'file1';
        $file2  = $source . 'file2';

        $this->filesystem->createDirectory($source);
        \file_put_contents($file1, 'FILE1');
        \file_put_contents($file2, 'FILE2');

        $this->filesystem->syncDirectory($source, $target);

        self::assertFileContentsEqual($file1, $target . 'file1');
        self::assertFileContentsEqual($file2, $target . 'file2');
    }

    public function testCreateSymlink(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->path('file');
        $link = $this->path('link');
        \touch($file);

        $this->filesystem->createSymlink($file, $link);

        self::assertTrue(\is_link($link));
        self::assertSame($file, \readlink($link));
    }

    public function testCreateSymlinkIsIdempotentForSameTarget(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->path('file');
        $link = $this->path('link');
        \touch($file);

        $this->filesystem->createSymlink($file, $link);
        $this->filesystem->createSymlink($file, $link);

        self::assertTrue(\is_link($link));
        self::assertSame($file, \readlink($link));
    }

    public function testCreateSymlinkOverwritesDifferentTarget(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file  = $this->path('file');
        $other = $this->path('other');
        $link  = $this->path('link');
        \touch($file);
        \touch($other);

        $this->filesystem->createSymlink($other, $link);
        $this->filesystem->createSymlink($file, $link);

        self::assertSame($file, \readlink($link));
    }

    public function testReadLinkTarget(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->path('file');
        $link = $this->path('link');
        \touch($file);
        \symlink($file, $link);

        self::assertSame($file, $this->filesystem->readLinkTarget($link));
        self::assertNull($this->filesystem->readLinkTarget($file));
        self::assertNull($this->filesystem->readLinkTarget($this->path('missing')));
    }

    public function testCreateHardlink(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $source = $this->path('origin');
        $target = $this->path('hardlink');
        \file_put_contents($source, 'content');

        $this->filesystem->createHardlink($source, $target);

        self::assertFileExists($target);
        self::assertSame(\fileinode($source), \fileinode($target));
    }

    public function testCreateHardlinkWithSeveralTargets(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $source  = $this->path('origin');
        $target1 = $this->path('hardlink1');
        $target2 = $this->path('hardlink2');
        \file_put_contents($source, 'content');

        $this->filesystem->createHardlink($source, [$target1, $target2]);

        self::assertSame(\fileinode($source), \fileinode($target1));
        self::assertSame(\fileinode($source), \fileinode($target2));
    }

    public function testCreateHardlinkSkipsExistingSameInode(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $source = $this->path('origin');
        $target = $this->path('hardlink');
        \file_put_contents($source, 'content');
        \link($source, $target);

        $this->filesystem->createHardlink($source, $target);

        self::assertSame(\fileinode($source), \fileinode($target));
    }

    public function testFileSizeModifiedTimeAndCreatedTime(): void
    {
        $file = $this->path('meta.txt');
        \file_put_contents($file, 'metadata');
        $now = \time();
        \touch($file, $now);

        self::assertSame(8, $this->filesystem->fileSize($file));
        self::assertSame($now, $this->filesystem->modifiedTime($file));
        self::assertGreaterThan(0, $this->filesystem->createdTime($file));
    }

    public function testSetPermissions(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $file = $this->path('chmod.txt');
        \touch($file);

        $this->filesystem->setPermissions($file, 0o640);

        $this->assertFilePermissions(640, $file);
    }

    public function testFileCopyHelperReturnsFalseOnMissingSource(): void
    {
        self::assertFalse(file_copy($this->path('missing.txt'), $this->path('out.txt')));
    }

    public function testFileRemoveHelper(): void
    {
        $file = $this->path('helper-remove.txt');
        \file_put_contents($file, 'x');

        self::assertTrue(file_remove($file));
        self::assertFileDoesNotExist($file);
        self::assertTrue(file_remove($this->path('missing.txt')));
    }

    public function testCreateTemporaryFileUsesNormalizePathOnWindowsStyleDirectory(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $directory = $this->workspace . '\\';
        } else {
            $directory = $this->workspace . '//';
        }

        $path = $this->filesystem->createTemporaryFile($directory, 'norm_', '.tmp');

        self::assertFileExists($path);
        self::assertStringEndsWith('.tmp', $path);
        self::assertFalse(\str_contains($path, '//'));
        $this->filesystem->remove($path);
    }

    public function testSyncDirectoryAcceptsBackslashSeparatedPathsOnUnix(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Unix-specific backslash separator normalization test.');
        }

        $base = $this->path('slash-normalize');
        $this->filesystem->createDirectory($base . '/source/nested');
        \file_put_contents($base . '/source/nested/file.txt', 'DATA');

        $this->filesystem->syncDirectory($base . '\\source', $base . '/target');

        self::assertFileExists($base . '/target/nested/file.txt');
        self::assertSame('DATA', $this->filesystem->readFile($base . '/target/nested/file.txt'));
    }

    public function testSyncDirectoryTreatsMixedTrailingSlashesAsSameDirectory(): void
    {
        $source = $this->path('mixed-trailing-source');
        $target = $source . \DIR_SEP . 'target' . \DIR_SEP;
        $this->filesystem->createDirectory($source);
        \file_put_contents($source . \DIR_SEP . 'file.txt', 'DATA');

        $this->filesystem->syncDirectory($source . \DIR_SEP, $target);

        self::assertSame('DATA', $this->filesystem->readFile($target . 'file.txt'));
        self::assertDirectoryDoesNotExist($target . 'target');
    }
}
