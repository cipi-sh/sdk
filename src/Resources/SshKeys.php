<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class SshKeys extends Resource
{
    /**
     * Keys authorized for the cipi user.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/ssh/keys');
    }

    /**
     * @return array<string, mixed>
     */
    public function add(string $key): array
    {
        return $this->client->post('/ssh/keys', [
            'key' => $key,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(int $id): array
    {
        return $this->client->delete('/ssh/keys/'.$this->client->segment((string) $id));
    }
}
