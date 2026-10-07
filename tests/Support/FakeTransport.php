<?php

declare(strict_types=1);

namespace Cipi\Sdk\Tests\Support;

use Cipi\Sdk\Transport;

final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: ?string}> */
    public array $calls = [];

    /** @var list<array{status: int, body: string, headers: array<string, string>}> */
    public array $queue = [];

    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        int $connectTimeout,
        int $maxResponseBytes,
        bool $allowHttp,
    ): array {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'allow_http' => $allowHttp,
        ];

        return array_shift($this->queue) ?? [
            'status' => 200,
            'body' => '{}',
            'headers' => [],
        ];
    }
}
