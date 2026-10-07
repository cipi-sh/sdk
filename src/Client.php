<?php

declare(strict_types=1);

namespace Cipi\Sdk;

use Cipi\Sdk\Exception\AuthenticationException;
use Cipi\Sdk\Exception\AuthorizationException;
use Cipi\Sdk\Exception\CipiException;
use Cipi\Sdk\Exception\ConfigurationException;
use Cipi\Sdk\Exception\ConflictException;
use Cipi\Sdk\Exception\NotFoundException;
use Cipi\Sdk\Exception\RateLimitException;
use Cipi\Sdk\Exception\ServerException;
use Cipi\Sdk\Exception\TransportException;
use Cipi\Sdk\Exception\ValidationException;
use JsonException;

final class Client
{
    private readonly string $token;

    private readonly string $endpoint;

    private readonly Transport $transport;

    public function __construct(
        private readonly string $baseUrl,
        string $token,
        private readonly Options $options = new Options,
    ) {
        $this->token = $this->normalizeToken($token);
        $this->endpoint = $this->normalizeEndpoint($baseUrl, $options->allowHttp);
        $this->transport = $options->transport ?? new CurlTransport;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $json = null, array $query = []): array
    {
        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], true)) {
            throw new ConfigurationException('Unsupported HTTP method.');
        }
        if ($json !== null && in_array($method, ['GET', 'HEAD'], true)) {
            throw new ConfigurationException('GET and HEAD requests cannot include a JSON body.');
        }

        $url = $this->endpoint.$this->normalizePath($path).$this->queryString($query);
        $body = $json === null ? null : $this->encodeJson($json);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer '.$this->token,
            'User-Agent: '.$this->options->userAgent,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $attempt = 0;
        while (true) {
            try {
                $response = $this->transport->send(
                    $method,
                    $url,
                    $headers,
                    $body,
                    $this->options->timeout,
                    $this->options->connectTimeout,
                    $this->options->maxResponseBytes,
                    $this->options->allowHttp,
                );
            } catch (TransportException $exception) {
                throw new TransportException(
                    $this->scrub($exception->getMessage()),
                    0,
                    [],
                    $method,
                    $path,
                    $exception,
                );
            }

            $status = $response['status'];
            $decoded = $this->decodeBody($response['body'], $status, $method, $path);
            if ($status >= 200 && $status < 300) {
                return $decoded;
            }

            $retryable = in_array($method, ['GET', 'HEAD'], true)
                && in_array($status, [429, 503], true)
                && $attempt < $this->options->maxRetries;
            if ($retryable) {
                $delay = $this->retryDelay($response['headers'], $attempt);
                if ($delay !== null) {
                    $this->sleep($delay);
                    $attempt++;

                    continue;
                }
            }

            throw $this->exceptionFor($status, $decoded, $method, $path, $response['headers']);
        }
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, null, $query);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function post(string $path, ?array $json = null, array $query = []): array
    {
        return $this->request('POST', $path, $json, $query);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function put(string $path, ?array $json = null): array
    {
        return $this->request('PUT', $path, $json);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function delete(string $path, ?array $json = null): array
    {
        return $this->request('DELETE', $path, $json);
    }

    public function segment(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\/\\\\\x00-\x20\x7F]/', $value) === 1) {
            throw new ConfigurationException('A path segment is empty or contains characters that are not allowed in a URL path.');
        }

        return rawurlencode($value);
    }

    /**
     * @return array{base_url: string, token: string}
     */
    public function __debugInfo(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'token' => '[redacted]',
        ];
    }

    private function normalizeToken(string $token): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/[\s\x00-\x1F\x7F]/', $token) === 1) {
            throw new ConfigurationException('The API token is empty or contains whitespace or control characters.');
        }

        return $token;
    }

    private function normalizeEndpoint(string $baseUrl, bool $allowHttp): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '' || strlen($baseUrl) > 2048) {
            throw new ConfigurationException('The API base URL is empty or too long.');
        }

        $parts = parse_url($baseUrl);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new ConfigurationException('The API base URL is not a valid absolute URL.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConfigurationException('The API base URL cannot include credentials, a query string, or a fragment.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https' && ! ($scheme === 'http' && $allowHttp)) {
            throw new ConfigurationException('The API base URL must use HTTPS. Pass allow_http only for a local panel.');
        }

        $host = (string) $parts['host'];
        if ($host === '' || preg_match('/[\r\n\x00]/', $host) === 1) {
            throw new ConfigurationException('The API host is not valid.');
        }

        $origin = $scheme.'://'.$host;
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if ($path === '' || $path === '/api' || str_ends_with($path, '/api')) {
            return $origin.($path === '' ? '/api' : $path);
        }

        return $origin.$path.'/api';
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1 || str_starts_with($path, '//')) {
            throw new ConfigurationException('Request path must be a relative API path.');
        }
        if (str_contains($path, '..') || preg_match('/[\r\n\x00]/', $path) === 1) {
            throw new ConfigurationException('Request path is not allowed.');
        }

        return '/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    private function queryString(array $query): string
    {
        $filtered = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (! is_scalar($value)) {
                throw new ConfigurationException('Query values must be scalars.');
            }
            $filtered[$key] = $value;
        }
        if ($filtered === []) {
            return '';
        }

        return '?'.http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function encodeJson(array $json): string
    {
        try {
            if ($json === []) {
                return '{}';
            }

            return json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new ConfigurationException('The request body is not valid JSON.', 0, [], null, null, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body, int $status, string $method, string $path): array
    {
        if (strlen($body) > $this->options->maxResponseBytes) {
            throw new TransportException('The API response exceeded the configured size limit.', $status, [], $method, $path);
        }
        if (trim($body) === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CipiException('The API returned a non-JSON response.', $status, [], $method, $path, $exception);
        }

        if (! is_array($decoded)) {
            throw new CipiException('The API returned a JSON value that is not an object.', $status, [], $method, $path);
        }

        /** @var array<string, mixed> $decoded */
        return $this->scrubValue($decoded);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function retryDelay(array $headers, int $attempt): ?float
    {
        $retryAfter = $headers['retry-after'] ?? null;
        if ($retryAfter !== null && preg_match('/^\d+$/', $retryAfter) === 1) {
            $seconds = (int) $retryAfter;
            if ($seconds > 10) {
                return null;
            }

            return (float) $seconds;
        }

        return min(0.2 * (2 ** $attempt), 2.0);
    }

    private function sleep(float $seconds): void
    {
        $sleeper = $this->options->sleeper;
        if ($sleeper !== null) {
            $sleeper($seconds);

            return;
        }
        usleep((int) round($seconds * 1_000_000));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function exceptionFor(int $status, array $body, string $method, string $path, array $headers): CipiException
    {
        $message = $this->scrub($this->errorMessage($body, $status));
        $exception = match ($status) {
            401 => new AuthenticationException($message, $status, $body, $method, $path),
            403 => new AuthorizationException($message, $status, $body, $method, $path),
            404 => new NotFoundException($message, $status, $body, $method, $path),
            409 => new ConflictException($message, $status, $body, $method, $path),
            422 => new ValidationException($message, $status, $body, $method, $path),
            429 => new RateLimitException(
                $message,
                $status,
                $body,
                $method,
                $path,
                isset($headers['retry-after']) && preg_match('/^\d+$/', $headers['retry-after']) === 1
                    ? (int) $headers['retry-after']
                    : null,
            ),
            default => $status >= 500
                ? new ServerException($message, $status, $body, $method, $path)
                : new CipiException($message, $status, $body, $method, $path),
        };

        return $exception;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body, int $status): string
    {
        $error = $body['error'] ?? $body['message'] ?? null;
        if (is_string($error) && $error !== '') {
            return $error;
        }

        return 'Cipi API request failed with HTTP '.$status.'.';
    }

    private function scrub(string $message): string
    {
        return str_replace($this->token, '[redacted]', $message);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function scrubValue(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $value[$key] = $this->scrub($item);
            } elseif (is_array($item)) {
                $value[$key] = $this->scrubValue($item);
            }
        }

        return $value;
    }
}
