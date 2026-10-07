<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Monitor extends Resource
{
    /**
     * Read-only system monitor checks.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/monitor');
    }
}
