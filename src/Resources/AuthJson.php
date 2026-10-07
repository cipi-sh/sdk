<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class AuthJson extends Resource
{
    /**
     * Shared Composer auth.json. This is not HTTP Basic Auth.
     *
     * @return array<string, mixed>
     */
    public function get(string $app): array
    {
        return $this->client->get($this->appPath($app).'/auth');
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $app, bool $force = false): array
    {
        return $this->client->post($this->appPath($app).'/auth', [
            'force' => $force,
        ]);
    }

    /**
     * Replace the auth.json document. The array is sent as the JSON body.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function replace(string $app, array $document): array
    {
        return $this->client->put($this->appPath($app).'/auth', $document);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $app): array
    {
        return $this->client->delete($this->appPath($app).'/auth');
    }
}
