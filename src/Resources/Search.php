<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Search extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->client->get('/search');
    }

    /**
     * Enable Meilisearch / Laravel Scout for a Laravel app.
     *
     * @return array<string, mixed>
     */
    public function enable(string $app): array
    {
        return $this->client->post($this->appPath($app).'/search/enable');
    }

    /**
     * @return array<string, mixed>
     */
    public function disable(string $app): array
    {
        return $this->client->post($this->appPath($app).'/search/disable');
    }
}
