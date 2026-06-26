<?php

declare(strict_types=1);

namespace Northrook\Core;

use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Contracts\Http\CurlInterface;
use Northrook\Contracts\Interfaces\FilesystemInterface;
use Northrook\Core;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP facade over Symfony {@see CurlHttpClient}.
 *
 * - Stores default request options and connection limits
 * - Handles caching of successful responses
 */
final class Curl implements CurlInterface
{
    /** @var array<string, bool> */
    private static array $probeCache = [];

    /** @var array<string, mixed> */
    private readonly array $defaultOptions;

    private readonly int $maxHostConnections;

    private readonly int $maxPendingPushes;

    private readonly FilesystemInterface $filesystem;

    private readonly LoggerInterface $logger;

    private readonly string $cacheDirectory;

    private readonly null|HttpClientInterface $httpClient;

    /**
     * @param array<string, mixed>     $defaultOptions     Merged into every {@see client()} via `array_replace`
     * @param int                      $maxHostConnections Passed to {@see CurlHttpClient}
     * @param int                      $maxPendingPushes   Passed to {@see CurlHttpClient}
     * @param null|HttpClientInterface $httpClient         When set, returned from {@see client()} instead of creating a new client (intended for tests)
     */
    public function __construct(
        array $defaultOptions = [],
        int $maxHostConnections = 6,
        int $maxPendingPushes = 0,
        null|LoggerInterface $logger = null,
        null|FilesystemInterface $filesystem = null,
        null|string $cacheDirectory = null,
        null|HttpClientInterface $httpClient = null,
    ) {
        $this->defaultOptions     = $defaultOptions;
        $this->maxHostConnections = $maxHostConnections;
        $this->maxPendingPushes   = $maxPendingPushes;
        $this->logger             = $logger ?? new NullLogger();
        $this->filesystem         = $filesystem ?? new Filesystem();
        $this->cacheDirectory     = $cacheDirectory ?? Core::getCacheDirectory('curl');
        $this->httpClient         = $httpClient;
    }

    /**
     * Create an HTTP client using the configured defaults.
     *
     * When no `$httpClient` was injected at construction, a new
     * {@see CurlHttpClient} is created per call. Per-request options belong on
     * the verb methods, not here — unless you need client-wide overrides via
     * `withOptions()` on an injected client.
     *
     * @param array<string, mixed> $options Overrides merged into {@see $defaultOptions}
     */
    public function client(
        array $options = [],
    ): HttpClientInterface {
        if ($this->httpClient !== null) {
            return $options === []
                ? $this->httpClient
                : $this->httpClient->withOptions($options);
        }

        $client = new CurlHttpClient(
            array_replace($this->defaultOptions, $options),
            $this->maxHostConnections,
            $this->maxPendingPushes,
        );

        $client->setLogger($this->logger);

        return $client;
    }

    /**
     * Issue a GET request.
     *
     * HTTP error responses (4xx/5xx) are returned normally; only transport
     * failures throw {@see CurlException}.
     *
     * @param array<string, mixed> $query   Query parameters merged into the URL
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function get(
        string $url,
        array $query = [],
        array $options = [],
    ): ResponseInterface {
        if ($query !== []) {
            $existingQuery    = $options['query'] ?? [];
            $options['query'] = array_replace(
                \is_array($existingQuery) ? $existingQuery : [],
                $query,
            );
        }

        return $this->request('GET', $url, $options);
    }

    /**
     * Issue a POST request.
     *
     * Sets `body` from `$body` when neither `body` nor `json` is already present in `$options`.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function post(
        string $url,
        mixed $body = '',
        array $options = [],
    ): ResponseInterface {
        if ($body !== '' && ! \array_key_exists('body', $options) && ! \array_key_exists('json', $options)) {
            $options['body'] = $body;
        }

        return $this->request('POST', $url, $options);
    }

    /**
     * Issue a HEAD request.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function head(
        string $url,
        array $options = [],
    ): ResponseInterface {
        return $this->request('HEAD', $url, $options);
    }

    /**
     * Issue a PUT request.
     *
     * Sets `body` from `$body` when neither `body` nor `json` is already present in `$options`.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function put(
        string $url,
        mixed $body = '',
        array $options = [],
    ): ResponseInterface {
        if ($body !== '' && ! \array_key_exists('body', $options) && ! \array_key_exists('json', $options)) {
            $options['body'] = $body;
        }

        return $this->request('PUT', $url, $options);
    }

    /**
     * Issue a PATCH request.
     *
     * Sets `body` from `$body` when neither `body` nor `json` is already present in `$options`.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function patch(
        string $url,
        mixed $body = '',
        array $options = [],
    ): ResponseInterface {
        if ($body !== '' && ! \array_key_exists('body', $options) && ! \array_key_exists('json', $options)) {
            $options['body'] = $body;
        }

        return $this->request('PATCH', $url, $options);
    }

    /**
     * Issue a DELETE request.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function delete(
        string $url,
        array $options = [],
    ): ResponseInterface {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * Send a request with a JSON body and decode the response as JSON.
     *
     * When `$data` is not null it is sent via the `json` option (Symfony sets
     * `Content-Type` accordingly). The response body is decoded with
     * `JSON_THROW_ON_ERROR`.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure or non-JSON response handling errors
     * @throws \JsonException When the response body is not valid JSON
     */
    public function json(
        string $method,
        string $url,
        mixed $data = null,
        array $options = [],
    ): mixed {
        if ($data !== null) {
            $options['json'] = $data;
        }

        try {
            $content = $this->request($method, $url, $options)->getContent();
        } catch (\Throwable $e) {
            throw new CurlException(
                $url,
                previous: $e,
            );
        }

        return \json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Download a URL to a file path or writable stream callback.
     *
     * **String `$location`:** streams into a temporary file under
     * {@see $cacheDirectory}, then atomically copies to the destination via
     * {@see FilesystemInterface::copyFile()}. Resumes partial downloads when a
     * non-empty temp file already exists (`Range: bytes=N-`). The destination
     * basename is inferred from the URL when the path has no extension.
     *
     * **Callable `$location`:** streams into a `tmpfile()` and passes the
     * rewound handle to the callback after a successful response.
     */
    public function download(
        string $url,
        string|callable $location,
    ): bool {
        return \is_callable($location)
            ? $this->downloadToCallable($url, $location)
            : $this->downloadToFile($url, $location);
    }

    /**
     * Check whether `$url` responds with HTTP 2xx or 3xx.
     *
     * Sends HEAD with `timeout` 5 and `max_redirects` 20 unless overridden in
     * `$options`. When `$cached` is true, successful results are remembered for
     * the remainder of the process (failed checks are not short-circuited).
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException When `$throwOnError` is true and the request fails
     */
    public function probeUrl(
        string $url,
        bool $throwOnError = false,
        bool $cached = true,
        array $options = [],
    ): bool {
        if ($cached && ( self::$probeCache[$url] ?? false )) {
            return true;
        }

        $options = array_replace([
            'timeout'       => 5,
            'max_redirects' => 20,
        ], $options);

        try {
            $status = $this->head($url, $options)->getStatusCode();
        } catch (\Throwable $exception) {
            self::$probeCache[$url] = false;

            if ($throwOnError) {
                throw new CurlException($url, previous: $exception);
            }

            return false;
        }

        $success = self::isSuccessStatus($status);

        self::$probeCache[$url] = $success;

        if (! $success && $throwOnError) {
            throw new CurlException($url);
        }

        return $success;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws CurlException On transport failure
     */
    private function request(
        string $method,
        string $url,
        array $options = [],
    ): ResponseInterface {
        try {
            return $this->client()->request($method, $url, $options);
        } catch (\Throwable $exception) {
            throw new CurlException($url, previous: $exception);
        }
    }

    /**
     * Stream a download into a temporary file and deliver the handle to `$callback`.
     */
    private function downloadToCallable(
        string $url,
        callable $callback,
    ): bool {
        $handle = \tmpfile();

        if ($handle === false) {
            $this->logger->error('Failed to open temporary file for download.', ['url' => $url]);

            return false;
        }

        $status = $this->streamToHandle($url, $handle, []);

        if (! self::isSuccessStatus($status)) {
            \fclose($handle);

            return false;
        }

        \rewind($handle);
        $callback($handle);
        \fclose($handle);

        return true;
    }

    /**
     * Stream a download through a cache temp file, then copy atomically to `$location`.
     */
    private function downloadToFile(
        string $url,
        string $location,
    ): bool {
        $this->ensureCacheDirectory();

        $destination = $this->resolveDownloadPath($url, $location);
        $tempFile    = $this->tempFilePath($destination);

        $options = [];
        $mode    = 'wb';

        if ($this->filesystem->isReadable($tempFile)) {
            $filesize = $this->filesystem->fileSize($tempFile);

            if ($filesize > 0) {
                $options['headers'] = ['Range' => 'bytes=' . $filesize . '-'];
                $mode               = 'ab';
            }
        }

        $handle = \fopen($tempFile, $mode);

        if ($handle === false) {
            $this->logger->error('Failed to open temporary download file.', [
                'url'  => $url,
                'path' => $tempFile,
            ]);

            return false;
        }

        try {
            $status = $this->streamToHandle($url, $handle, $options);
        } finally {
            \fclose($handle);
        }

        if (! self::isSuccessStatus($status)) {
            $this->removeTempFile($tempFile);

            return false;
        }

        try {
            $this->filesystem->copyFile($tempFile, $destination, alwaysOverwrite: true);
            $this->removeTempFile($tempFile);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'url'         => $url,
                'destination' => $destination,
                'exception'   => $exception,
            ]);
            $this->removeTempFile($tempFile);

            return false;
        }

        return true;
    }

    /**
     * GET `$url` while writing the response body to `$handle` via Symfony's `buffer` option.
     *
     * @param array<string, mixed> $options
     *
     * @return int HTTP status code, or `0` on transport failure
     */
    private function streamToHandle(
        string $url,
        mixed $handle,
        array $options,
    ): int {
        $options['buffer'] = $handle;

        try {
            $response = $this->client()->request('GET', $url, $options);
            $status   = $response->getStatusCode();

            // Drain the response so buffered content is fully written.
            $response->getContent(false);

            return $status;
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'url'       => $url,
                'exception' => $exception,
            ]);

            return 0;
        }
    }

    /**
     * Normalize `$location` and, when it has no extension, append the URL basename.
     */
    private function resolveDownloadPath(
        string $url,
        string $location,
    ): string {
        $location = normalize_path($location);

        $urlBasename  = \strrchr($url, '/');
        $pathBasename = \strrchr($location, '/');

        if (
            $urlBasename !== false
            && $pathBasename !== false
            && $urlBasename !== $pathBasename
            && ! \str_contains($pathBasename, '.')
        ) {
            $location .= '/' . $urlBasename;
        }

        return normalize_path($location);
    }

    /** Path of the hashed temp file used while downloading to `$destination`. */
    private function tempFilePath(string $destination): string
    {
        return normalize_path([
            $this->cacheDirectory,
            \hash('xxh32', $destination) . '.tmp',
        ]);
    }

    private function ensureCacheDirectory(): void
    {
        $this->filesystem->createDirectory($this->cacheDirectory);
    }

    private function removeTempFile(string $path): void
    {
        if ($this->filesystem->isReadable($path)) {
            $this->filesystem->remove($path);
        }
    }

    private static function isSuccessStatus(int $status): bool
    {
        return $status >= 200 && $status < 400;
    }
}
