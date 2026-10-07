<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class PhpVersions extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/php');
    }

    /**
     * @return array<string, mixed>
     */
    public function install(string $version): array
    {
        return $this->client->post('/php/install', [
            'version' => $version,
        ]);
    }
}
