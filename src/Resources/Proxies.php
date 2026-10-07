<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Proxies extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(string $app): array
    {
        return $this->client->get($this->appPath($app).'/proxies');
    }

    /**
     * The panel never bypasses the loopback guard.
     *
     * @return array<string, mixed>
     */
    public function add(
        string $app,
        string $prefix,
        string $upstream,
        ?bool $stripPrefix = null,
        ?bool $preserveHost = null,
        ?int $timeout = null,
        ?bool $buffering = null,
    ): array {
        return $this->client->post($this->appPath($app).'/proxies', $this->withoutNulls([
            'prefix' => $prefix,
            'upstream' => $upstream,
            'strip_prefix' => $stripPrefix,
            'preserve_host' => $preserveHost,
            'timeout' => $timeout,
            'buffering' => $buffering,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string $app, string $prefix): array
    {
        return $this->client->delete($this->appPath($app).'/proxies', [
            'prefix' => $prefix,
        ]);
    }
}
