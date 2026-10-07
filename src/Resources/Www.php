<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Www extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function status(string $app): array
    {
        return $this->client->get($this->appPath($app).'/www');
    }

    /**
     * @return array<string, mixed>
     */
    public function add(string $app): array
    {
        return $this->client->post($this->appPath($app).'/www/add');
    }

    /**
     * 301 redirect www to the apex domain.
     *
     * @return array<string, mixed>
     */
    public function forceToRoot(string $app): array
    {
        return $this->client->post($this->appPath($app).'/www/force-to-root');
    }

    /**
     * 301 redirect the apex domain to www.
     *
     * @return array<string, mixed>
     */
    public function forceFromRoot(string $app): array
    {
        return $this->client->post($this->appPath($app).'/www/force-from-root');
    }

    /**
     * @return array<string, mixed>
     */
    public function clear(string $app): array
    {
        return $this->client->post($this->appPath($app).'/www/clear');
    }
}
