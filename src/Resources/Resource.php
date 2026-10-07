<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

use Cipi\Sdk\Client;

abstract class Resource
{
    public function __construct(protected readonly Client $client) {}

    protected function appPath(string $app): string
    {
        return '/apps/'.$this->client->segment($app);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withoutNulls(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }
}
