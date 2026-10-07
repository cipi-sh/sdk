<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Health extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/health');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $app): array
    {
        return $this->client->get($this->appPath($app).'/health');
    }

    /**
     * @return array<string, mixed>
     */
    public function update(string $app, ?string $url = null, ?int $expect = null): array
    {
        return $this->client->put($this->appPath($app).'/health', $this->withoutNulls([
            'url' => $url,
            'expect' => $expect,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $app): array
    {
        return $this->client->delete($this->appPath($app).'/health');
    }

    /**
     * @return array<string, mixed>
     */
    public function check(string $app): array
    {
        return $this->client->post($this->appPath($app).'/health/check');
    }
}
