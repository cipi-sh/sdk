<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Redirects extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(string $app): array
    {
        return $this->client->get($this->appPath($app).'/redirects');
    }

    /**
     * Redirect every hostname of the app to a URL.
     *
     * @return array<string, mixed>
     */
    public function set(string $app, string $to, ?int $code = null, ?bool $keepPath = null): array
    {
        return $this->client->put($this->appPath($app).'/redirect', $this->withoutNulls([
            'to' => $to,
            'code' => $code,
            'keep_path' => $keepPath,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function unset(string $app): array
    {
        return $this->client->delete($this->appPath($app).'/redirect');
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(string $app): array
    {
        return $this->client->post($this->appPath($app).'/redirect/enable');
    }

    /**
     * @return array<string, mixed>
     */
    public function disable(string $app): array
    {
        return $this->client->post($this->appPath($app).'/redirect/disable');
    }

    /**
     * Add or update a path redirect. A source ending in / is a prefix match.
     *
     * @return array<string, mixed>
     */
    public function add(string $app, string $from, string $to, ?int $code = null, ?bool $keepPath = null): array
    {
        return $this->client->post($this->appPath($app).'/redirects', $this->withoutNulls([
            'from' => $from,
            'to' => $to,
            'code' => $code,
            'keep_path' => $keepPath,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string $app, string $from): array
    {
        return $this->client->delete($this->appPath($app).'/redirects', [
            'from' => $from,
        ]);
    }
}
