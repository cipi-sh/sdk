<?php

declare(strict_types=1);

namespace Cipi\Sdk;

interface Transport
{
    /**
     * @param  list<string>  $headers
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        int $connectTimeout,
        int $maxResponseBytes,
        bool $allowHttp,
    ): array;
}
