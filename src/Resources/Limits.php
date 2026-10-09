<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Limits extends Resource
{
    /**
     * FPM, memory, worker, and soft disk limits. Null fields mean the CLI default.
     *
     * @return array<string, mixed>
     */
    public function get(string $app): array
    {
        return $this->client->get($this->appPath($app).'/limits');
    }

    /**
     * Queue a limits update. Poll the returned job_id.
     *
     * Keys: fpm_max_children, memory_limit, octane_workers, worker_procs, disk_limit_gb.
     * Pass disk_limit_gb as null to remove the soft disk limit. Node apps accept only that key.
     * Requires Cipi CLI ≥ 5.5.0 for disk_limit_gb.
     *
     * @param  array<string, mixed>  $limits
     * @return array<string, mixed>
     */
    public function update(string $app, array $limits): array
    {
        return $this->client->put($this->appPath($app).'/limits', $limits);
    }
}
