<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Disk extends Resource
{
    /**
     * Read-only disk usage for the filesystem /home is on, then every app.
     * Requires Cipi CLI ≥ 5.5.2. Older hosts answer 503.
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        return $this->client->get('/disk');
    }

    /**
     * Read-only size of every database on every installed engine.
     * Requires Cipi CLI ≥ 5.5.2. Older hosts answer 503.
     *
     * @return array<string, mixed>
     */
    public function databases(): array
    {
        return $this->client->get('/disk/dbs');
    }
}
