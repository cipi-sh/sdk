<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class ZeroTrust extends Resource
{
    /**
     * Read-only Cloudflare Zero Trust status.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->client->get('/zt');
    }
}
