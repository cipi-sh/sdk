<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class NodeApps extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function runtimes(): array
    {
        return $this->client->get('/node');
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $app): array
    {
        return $this->client->get($this->appPath($app).'/node');
    }

    /**
     * Blue/green restart of an SSR Node app. Returns a job.
     *
     * @return array<string, mixed>
     */
    public function restart(string $app): array
    {
        return $this->client->post($this->appPath($app).'/node/restart');
    }
}
