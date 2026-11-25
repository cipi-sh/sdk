<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Packages extends Resource
{
    /**
     * The server's closed catalog of optional host packages and their state:
     * `data.packages` — `id`, `packages` (apt), `description`, `installed`,
     * `partial`, and since Cipi 5.14.0 `kind` (`tools` or `libraries`),
     * `binaries` (the commands an entry brings) and `missing` (apt packages
     * a partial install lacks).
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/packages');
    }

    /**
     * Install an entry of the catalog — `browsershot`, `ffmpeg`,
     * `libreoffice`, `tesseract`, … — or complete a partial one. An async
     * job (`package-install`): pass the answer to `$cipi->wait()`.
     *
     * Only ids of the server's own catalog are accepted (404 otherwise);
     * 409 when it is already installed. Needs Cipi 5.14.0+ (503 before),
     * cipi/api 1.44+ and the `packages-manage` ability.
     *
     * @return array<string, mixed>
     */
    public function install(string $id): array
    {
        return $this->client->post('/packages/'.$this->client->segment($id));
    }

    /**
     * Remove an entry of the catalog, and only what no other package still
     * needs: a library entry (`browsershot`, `fonts-cjk`) keeps whatever
     * other software uses. An async job (`package-remove`); its result says
     * `removed`, and `still_needed` when nothing could go.
     *
     * @return array<string, mixed>
     */
    public function remove(string $id): array
    {
        return $this->client->delete('/packages/'.$this->client->segment($id));
    }
}
