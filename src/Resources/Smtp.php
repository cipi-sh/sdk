<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Smtp extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->get('/smtp');
    }

    /**
     * Password is required only the first time SMTP is configured.
     *
     * @param  array{host: string, user: string, from: string, to: string, port?: int, password?: string, tls?: bool, enabled?: bool, test?: bool}  $config
     * @return array<string, mixed>
     */
    public function update(array $config): array
    {
        return $this->client->put('/smtp', $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(): array
    {
        return $this->client->post('/smtp/enable');
    }

    /**
     * @return array<string, mixed>
     */
    public function disable(): array
    {
        return $this->client->post('/smtp/disable');
    }

    /**
     * @return array<string, mixed>
     */
    public function test(): array
    {
        return $this->client->post('/smtp/test');
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(): array
    {
        return $this->client->delete('/smtp');
    }
}
