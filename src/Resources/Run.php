<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Run extends Resource
{
    /**
     * Binaries the panel will execute as the app user.
     *
     * @return array<string, mixed>
     */
    public function commands(): array
    {
        return $this->client->get('/run-commands');
    }

    /**
     * Run one whitelisted non-interactive command as an async job.
     *
     * @return array<string, mixed>
     */
    public function execute(string $app, string $command): array
    {
        return $this->client->post($this->appPath($app).'/run', [
            'command' => $command,
        ]);
    }
}
