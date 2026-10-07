<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class IpWhitelist extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->get('/ip-whitelist');
    }

    /**
     * Replace the allowlist. Use ["*"] to allow every client.
     * When $ensureClientIp is true, the panel keeps the caller if it would lock itself out.
     *
     * @param  list<string>  $entries
     * @return array<string, mixed>
     */
    public function replace(array $entries, ?bool $ensureClientIp = null): array
    {
        return $this->client->put('/ip-whitelist', $this->withoutNulls([
            'entries' => array_values($entries),
            'ensure_client_ip' => $ensureClientIp,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function add(string $ip): array
    {
        return $this->client->post('/ip-whitelist', [
            'ip' => $ip,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string $ip): array
    {
        return $this->client->delete('/ip-whitelist', [
            'ip' => $ip,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function allowAll(): array
    {
        return $this->client->post('/ip-whitelist/allow-all');
    }
}
