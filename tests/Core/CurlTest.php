<?php

declare(strict_types=1);

namespace Northrook\Tests\Core;

use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Core\Curl;
use Northrook\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CurlTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = \sys_get_temp_dir() . \DIR_SEP . 'northrook-curl-' . \bin2hex(\random_bytes(8));
        \mkdir($this->workspace, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testGetForwardsMethodUrlAndQuery(): void
    {
        $requests = [];
        $client   = new MockHttpClient(function(
            string $method,
            string $url,
            array $options,
        ) use (&$requests): MockResponse {
            $requests[] = compact('method', 'url', 'options');

            return new MockResponse('ok');
        });

        $curl = new Curl(httpClient: $client);
        $curl->get('https://example.com/items', ['page' => '2']);

        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('https://example.com/items?page=2', $requests[0]['url']);
    }

    public function testPostForwardsBody(): void
    {
        $requests = [];
        $client   = new MockHttpClient(function(
            string $method,
            string $url,
            array $options,
        ) use (&$requests): MockResponse {
            $requests[] = compact('method', 'url', 'options');

            return new MockResponse('created', ['http_code' => 201]);
        });

        $curl     = new Curl(httpClient: $client);
        $response = $curl->post('https://example.com/items', 'payload');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('payload', $requests[0]['options']['body']);
    }

    public function testJsonDecodesResponse(): void
    {
        $client = new MockHttpClient(static fn(): MockResponse => new MockResponse('{"id":1}'));
        $curl   = new Curl(httpClient: $client);

        self::assertSame(['id' => 1], $curl->json('GET', 'https://example.com/item'));
    }

    public function testProbeReturnsTrueForSuccessStatus(): void
    {
        $client = new MockHttpClient(static fn(): MockResponse => new MockResponse('', ['http_code' => 204]));
        $curl   = new Curl(httpClient: $client);

        self::assertTrue($curl->probeUrl('https://example.com/ok'));
    }

    public function testProbeReturnsFalseForErrorStatus(): void
    {
        $client = new MockHttpClient(static fn(): MockResponse => new MockResponse('', ['http_code' => 404]));
        $curl   = new Curl(httpClient: $client);

        self::assertFalse($curl->probeUrl('https://example.com/missing'));
    }

    public function testProbeCachesSuccessfulResults(): void
    {
        $count  = 0;
        $client = new MockHttpClient(function() use (&$count): MockResponse {
            ++$count;

            return new MockResponse('', ['http_code' => 200]);
        });
        $curl = new Curl(httpClient: $client);

        self::assertTrue($curl->probeUrl('https://example.com/cached', cached: true));
        self::assertTrue($curl->probeUrl('https://example.com/cached', cached: true));
        self::assertSame(1, $count);
    }

    public function testProbeThrowOnError(): void
    {
        $client = new MockHttpClient(static fn(): MockResponse => new MockResponse('', ['http_code' => 500]));
        $curl   = new Curl(httpClient: $client);

        $this->expectException(CurlException::class);
        $curl->probeUrl('https://example.com/error', throwOnError: true);
    }

    public function testDownloadWritesFile(): void
    {
        $client = new MockHttpClient(static fn(): MockResponse => new MockResponse('file-bytes'));
        $curl   = new Curl(
            filesystem: new Filesystem(),
            cacheDirectory: $this->workspace . \DIR_SEP . 'cache',
            httpClient: $client,
        );

        $target = $this->workspace . \DIR_SEP . 'download.bin';

        self::assertTrue($curl->download('https://example.com/file.bin', $target));
        self::assertSame('file-bytes', \file_get_contents($target));
        self::assertEmpty(\glob($this->workspace . \DIR_SEP . 'cache' . \DIR_SEP . '*.tmp') ?: []);
    }

    public function testDownloadCallableReceivesHandle(): void
    {
        $client = new MockHttpClient(static fn(): MockResponse => new MockResponse('streamed'));
        $curl   = new Curl(httpClient: $client);

        $received = null;
        $result   = $curl->download('https://example.com/file', function(
            $handle,
        ) use (&$received): void {
            $received = \stream_get_contents($handle);
        });

        self::assertTrue($result);
        self::assertSame('streamed', $received);
    }

    public function testDownloadResumesWithRangeHeader(): void
    {
        $requests = [];
        $client   = new MockHttpClient(function(
            string $method,
            string $url,
            array $options,
        ) use (&$requests): MockResponse {
            $requests[] = $options;

            return new MockResponse('-rest');
        });

        $cacheDirectory = $this->workspace . \DIR_SEP . 'cache';
        $filesystem     = new Filesystem();
        $curl           = new Curl(
            filesystem: $filesystem,
            cacheDirectory: $cacheDirectory,
            httpClient: $client,
        );

        $target   = $this->workspace . \DIR_SEP . 'resume.bin';
        $tempFile = $cacheDirectory . \DIR_SEP . \hash('xxh32', $target) . '.tmp';

        $filesystem->createDirectory($cacheDirectory);
        $filesystem->writeFileAtomically($tempFile, 'partial');

        self::assertTrue($curl->download('https://example.com/resume.bin', $target));
        self::assertSame('partial-rest', \file_get_contents($target));
        self::assertArrayHasKey('normalized_headers', $requests[0]);
        self::assertStringContainsString(
            'bytes=7-',
            $requests[0]['normalized_headers']['range'][0] ?? '',
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    #[DataProvider('verbProvider')]
    public function testVerbShortcuts(
        string $method,
        string $call,
        array $args,
    ): void {
        $requests = [];
        $client   = new MockHttpClient(function(
            string $requestMethod,
            string $url,
        ) use (&$requests): MockResponse {
            $requests[] = $requestMethod;

            return new MockResponse('');
        });

        $curl = new Curl(httpClient: $client);
        $curl->{$call}(...$args);

        self::assertSame($method, $requests[0]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, mixed>}>
     */
    public static function verbProvider(): array
    {
        return [
            'head'   => ['HEAD', 'head', ['https://example.com']],
            'put'    => ['PUT', 'put', ['https://example.com', 'body']],
            'patch'  => ['PATCH', 'patch', ['https://example.com', 'body']],
            'delete' => ['DELETE', 'delete', ['https://example.com']],
        ];
    }

    private function removeDirectory(
        string $directory,
    ): void {
        if (! \is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof \SplFileInfo) {
                continue;
            }

            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }

        \rmdir($directory);
    }
}
