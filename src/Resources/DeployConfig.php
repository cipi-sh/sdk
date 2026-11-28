<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class DeployConfig extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $app): array
    {
        return $this->client->get($this->appPath($app).'/deploy-config');
    }

    /**
     * Structured Deployer options. The panel regenerates deploy.php.
     *
     * keep_releases, migrate, optimize, storage_link, queue_restart,
     * horizon_terminate, predeploy_snapshot, node_build, extra_artisan — and,
     * with Cipi ≥ 5.18.0, the two clocks of a deploy in seconds: build_timeout
     * (the frontend build, 60–3600, 1800 unless set) and step_timeout (every
     * other command, 60–1800, 300 unless set). A Node app takes keep_releases,
     * predeploy_snapshot and the two clocks.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function update(string $app, array $options): array
    {
        return $this->client->put($this->appPath($app).'/deploy-config', $options);
    }
}
