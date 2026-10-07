<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Packages extends Resource
{
    /**
     * Read-only catalog of optional host packages.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/packages');
    }
}
