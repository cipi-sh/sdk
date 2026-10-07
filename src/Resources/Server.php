<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Server extends Resource
{
    /**
     * Same snapshot as `cipi status`.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->client->get('/status');
    }
}
