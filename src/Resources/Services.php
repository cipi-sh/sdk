<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Services extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(?string $service = null): array
    {
        return $this->client->get('/services', $this->withoutNulls([
            'service' => $service,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function restart(string $name): array
    {
        return $this->client->post('/services/'.$this->client->segment($name).'/restart');
    }
}
