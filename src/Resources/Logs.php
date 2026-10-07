<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Logs extends Resource
{
    /**
     * @param  'all'|'nginx'|'php'|'worker'|'deploy'|'laravel'|null  $type
     * @return array<string, mixed>
     */
    public function get(string $app, ?string $type = null, ?int $page = null, ?int $perPage = null): array
    {
        return $this->client->get($this->appPath($app).'/logs', $this->withoutNulls([
            'type' => $type,
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }
}
