<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Ssl extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function install(string $app): array
    {
        return $this->client->post($this->appPath($app).'/ssl');
    }

    /**
     * Re-apply the HTTP to HTTPS redirect without a new certificate.
     *
     * @return array<string, mixed>
     */
    public function force(string $app): array
    {
        return $this->client->post($this->appPath($app).'/ssl/force');
    }
}
