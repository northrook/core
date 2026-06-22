<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Contracts\Exceptions\FilesystemException;
use PHPUnit\Framework\Attributes\DataProvider;

final class FilesystemPathTest extends FilesystemTestCase
{
    #[DataProvider('provideIsAbsolutePathTests')]
    public function testIsAbsolutePath(string $path, bool $expected): void
    {
        self::assertSame($expected, $this->filesystem->isAbsolutePath($path));
    }

  /**
   * @return iterable<string, array{0: string, 1: bool}>
   */
    public static function provideIsAbsolutePathTests(): iterable
    {
        yield '/var/lib' => ['/var/lib', true];
        yield '/' => ['/', true];
        yield 'var/lib' => ['var/lib', false];
        yield '../var/lib' => ['../var/lib', false];
        yield 'empty' => ['', false];
        yield 'phar' => ['phar:///css/style.css', true];
        yield 'http' => ['http://example.com', true];
        yield 'ftp' => ['ftp://user@server/path', true];
        yield 'vfs' => ['vfs://root/file.txt', true];
        yield 'file scheme' => ['file:///var/lib', true];

        if ('\\' === \DIRECTORY_SEPARATOR) {
            yield 'win backslash' => ['\\var\\lib', true];
            yield 'win drive' => ['C:\\var\\lib', true];
            yield 'win drive slash' => ['C:/css/style.css', true];
            yield 'win drive only' => ['C:', true];
            yield 'win relative drive' => ['C:css/style.css', false];
        }
    }

    #[DataProvider('provideMakeRelativePathTests')]
    public function testMakeRelativePath(string $endPath, string $startPath, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->filesystem->makeRelativePath($endPath, $startPath),
        );
    }

  /**
   * @return iterable<string, array{0: string, 1: string, 2: string}>
   */
    public static function provideMakeRelativePathTests(): iterable
    {
        $paths = [
            'same dir trailing' => ['/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component', '../'],
            'same dir both trailing' => ['/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component/', '../'],
            'cross tree up' => ['/usr/lib/symfony/', '/var/lib/symfony/src/Symfony/Component', '../../../../../../usr/lib/symfony/'],
            'child path' => ['/var/lib/symfony/src/Symfony/', '/var/lib/symfony/', 'src/Symfony/'],
            'identical' => ['/aa/bb', '/aa/bb', './'],
            'identical trailing start' => ['/aa/bb', '/aa/bb/', './'],
            'identical trailing end' => ['/aa/bb/', '/aa/bb', './'],
            'sibling depth' => ['/aa/bb/cc', '/aa/bb/cc/dd', '../'],
            'deeper child' => ['/aa/bb/cc', '/aa', 'bb/cc/'],
            'partial prefix' => ['/a/aab/bb', '/a/aa', '../aab/bb/'],
            'from root' => ['/a/aab/bb/', '/', 'a/aab/bb/'],
            'different branch' => ['/a/aab/bb/', '/b/aab', '../../a/aab/bb/'],
            'traversal in end' => ['/aa/bb/cc', '/aa/dd/..', 'bb/cc/'],
            'traversal both' => ['/aa/../bb/cc', '/aa/dd/..', '../bb/cc/'],
            'collapse segments' => ['/aa/bb/../../cc', '/aa/../dd/..', 'cc/'],
        ];

        foreach ($paths as $label => $case) {
            yield $label => $case;
        }

        if ('\\' === \DIRECTORY_SEPARATOR) {
            yield 'win traversal' => ['C:/aa/bb/cc', 'C:/aa/dd/..', 'bb/cc/'];
            yield 'win cross drive' => ['D:/aa/bb', 'C:/aa', 'D:/aa/bb/'];
            yield 'win mixed separators' => ['c:\var\lib/symfony/src/Symfony/', 'c:/var/lib/symfony/', 'src/Symfony/'];
        }
    }

    public function testMakeRelativePathRejectsRelativeStartPath(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('The start path "var/lib/symfony/src/Symfony/Component" is not absolute.');

        $this->filesystem->makeRelativePath('/var/lib/symfony/', 'var/lib/symfony/src/Symfony/Component');
    }

    public function testMakeRelativePathRejectsRelativeEndPath(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('The end path "var/lib/symfony/" is not absolute.');

        $this->filesystem->makeRelativePath('var/lib/symfony/', '/var/lib/symfony/src/Symfony/Component');
    }

    public function testMakeRelativePathWithExistingFile(): void
    {
        $dir  = $this->path('foo', 'bar');
        $file = $dir . \DIR_SEP . 'test.txt';
        $this->filesystem->createDirectory($dir);
        \touch($file);

        self::assertSame('foo/bar/test.txt', $this->filesystem->makeRelativePath($file, $this->workspace));
        self::assertSame('foo/bar/', $this->filesystem->makeRelativePath($dir, $this->workspace));
    }
}
