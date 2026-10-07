<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Databases extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function engines(): array
    {
        return $this->client->get('/dbs/engines');
    }

    /**
     * @return array<string, mixed>
     */
    public function installEngine(string $engine): array
    {
        return $this->client->post('/dbs/engines/install', [
            'engine' => $engine,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(?string $engine = null): array
    {
        return $this->client->get('/dbs', $this->withoutNulls([
            'engine' => $engine,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, ?string $engine = null): array
    {
        return $this->client->post('/dbs', $this->withoutNulls([
            'name' => $name,
            'engine' => $engine,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function backup(string $name, ?string $engine = null): array
    {
        return $this->client->post('/dbs/'.$this->client->segment($name).'/backup', $this->engineBody($engine));
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(string $name, string $file, ?string $engine = null): array
    {
        return $this->client->post('/dbs/'.$this->client->segment($name).'/restore', $this->withoutNulls([
            'file' => $file,
            'engine' => $engine,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function password(string $name, ?string $engine = null): array
    {
        return $this->client->post('/dbs/'.$this->client->segment($name).'/password', $this->engineBody($engine));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function engineBody(?string $engine): ?array
    {
        return $engine === null ? null : ['engine' => $engine];
    }
}
