<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Apps extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/apps');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        return $this->client->get($this->appPath($name));
    }

    /**
     * Queue app creation. Poll the returned job_id.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        return $this->client->post('/apps', $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(string $name, array $attributes): array
    {
        return $this->client->put($this->appPath($name), $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $name): array
    {
        return $this->client->delete($this->appPath($name));
    }

    /**
     * @return array<string, mixed>
     */
    public function suspend(string $name): array
    {
        return $this->client->post($this->appPath($name).'/suspend');
    }

    /**
     * @return array<string, mixed>
     */
    public function unsuspend(string $name): array
    {
        return $this->client->post($this->appPath($name).'/unsuspend');
    }

    /**
     * @return array<string, mixed>
     */
    public function fixPermissions(string $name): array
    {
        return $this->client->post($this->appPath($name).'/fix-permissions');
    }

    /**
     * Recreate the Git webhook. Pass rotateSecret to rotate CIPI_WEBHOOK_TOKEN.
     *
     * @return array<string, mixed>
     */
    public function recreateWebhook(string $name, bool $rotateSecret = false): array
    {
        return $this->client->post($this->appPath($name).'/webhook/recreate', [
            'rotate_secret' => $rotateSecret,
        ]);
    }
}
