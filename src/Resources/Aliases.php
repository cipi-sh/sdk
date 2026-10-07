<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Aliases extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(string $app): array
    {
        return $this->client->get($this->appPath($app).'/aliases');
    }

    /**
     * @return array<string, mixed>
     */
    public function add(string $app, string $alias): array
    {
        return $this->client->post($this->appPath($app).'/aliases/'.$this->client->segment($alias));
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string $app, string $alias): array
    {
        return $this->client->delete($this->appPath($app).'/aliases/'.$this->client->segment($alias));
    }
}
