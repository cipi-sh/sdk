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
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function update(string $app, array $options): array
    {
        return $this->client->put($this->appPath($app).'/deploy-config', $options);
    }
}
