<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * Zero Trust, read-only: Cloudflare Tunnel, and the mesh network the server
 * is on — Tailscale or NetBird. Every change stays on the server's CLI
 * (`cipi zt`, `cipi zt tailscale|netbird`): the panel API exposes the status
 * alone, and so does the SDK.
 */
final class ZeroTrust extends Resource
{
    /**
     * `GET /zt`. `data` has Cloudflare's summary (`enabled`, `cloudflared`,
     * `tunnel`, `real_ip`, `lock_http`, `lock_ssh`, `ssh_hostname`), the
     * mesh's (`mesh`: `tailscale`, `netbird` or null; `mesh_state`;
     * `mesh_ip` — cipi/api 1.48+, Cipi 5.18.0+) and, in `details`, the whole
     * of `cipi zt status --json` (cipi/api 1.46+, Cipi 5.16.0+).
     *
     * `enabled` is Cloudflare's alone: a server on a mesh with no tunnel
     * answers `enabled: false` and `mesh: "tailscale"`.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->client->get('/zt');
    }

    /**
     * The mesh network the server is on, as `details.mesh` describes it:
     * `provider` (`tailscale`, `netbird`), `connected`, `state`, `ip`,
     * `fqdn`, `network`, `peers`, `key_expiry`, `key_expires_in_days`,
     * `ssh`, `lock` (`ssh`, `http`), `warnings`. Null while the server is on
     * none — and on a server older than Cipi 5.18.0 or cipi/api 1.48, which
     * say nothing of a mesh.
     *
     * One call to `GET /zt`: to read both sides, call status() once and
     * take `data.details.mesh` from it.
     *
     * @return array<string, mixed>|null
     */
    public function mesh(): ?array
    {
        $mesh = $this->status()['data']['details']['mesh'] ?? null;

        return is_array($mesh) && ($mesh['enabled'] ?? false) === true ? $mesh : null;
    }
}
