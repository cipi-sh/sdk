<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Deploys extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function start(string $app): array
    {
        return $this->client->post($this->appPath($app).'/deploy');
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback(string $app): array
    {
        return $this->client->post($this->appPath($app).'/deploy/rollback');
    }

    /**
     * @return array<string, mixed>
     */
    public function unlock(string $app): array
    {
        return $this->client->post($this->appPath($app).'/deploy/unlock');
    }

    /**
     * Hash-chained deploy audit ledger. Synchronous.
     *
     * @return array<string, mixed>
     */
    public function audit(string $app, ?int $days = null): array
    {
        return $this->client->get($this->appPath($app).'/deploy/audit', $this->withoutNulls([
            'days' => $days,
        ]));
    }
}
