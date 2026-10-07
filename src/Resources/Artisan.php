<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Artisan extends Resource
{
    /**
     * Run an Artisan command as an async job. tinker is rejected by the panel.
     *
     * @return array<string, mixed>
     */
    public function run(string $app, string $command): array
    {
        return $this->client->post($this->appPath($app).'/artisan', [
            'command' => $command,
        ]);
    }
}
