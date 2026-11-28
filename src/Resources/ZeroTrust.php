<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * Zero Trust: Cloudflare Tunnel, and the mesh network the server is on —
 * Tailscale or NetBird.
 *
 * Cloudflare's side is read-only: the tunnel, its hostnames, Access and its
 * locks are changed on the server's CLI (`cipi zt`). The mesh is managed from
 * here too (cipi/api 1.48+, Cipi 5.18.0+, ability `zt-manage`): join a
 * network, leave it, close and reopen the SSH port and 80/443 to the world,
 * Tailscale SSH on and off.
 *
 * Every change is synchronous and answers `data` (Zero Trust after it, as
 * status()) and `message` (what the CLI said). A refusal is a
 * ValidationException (422) with the CLI's words: the server itself keeps a
 * call from locking everyone out — see lockMesh().
 */
final class ZeroTrust extends Resource
{
    public const TAILSCALE = 'tailscale';

    public const NETBIRD = 'netbird';

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
     * `details.mesh.panel` says what this caller may do from where it calls:
     * `via` (`mesh`, `local`, `tunnel`, `public`, `unknown`) and `blocked`
     * (`lock_ssh`, `lock_http`, `disable` — each null, or the reason the
     * server would refuse it).
     *
     * @param  ?string  $client  for a panel that runs on the server itself: the address of the person
     *                           at it, so the answer also says whether closing 80/443 would close their page
     * @return array<string, mixed>
     */
    public function status(?string $client = null): array
    {
        return $this->client->get('/zt', $this->withoutNulls(['client' => $client]));
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

    /**
     * Join a mesh network: `POST /zt/mesh`. The server installs the client
     * from its vendor's package repository, signs in with the key and lets
     * the mesh interface through its firewall. Nothing is closed.
     *
     * It installs a package and signs in: allow it a minute or two — build
     * the client with a `timeout` to match (the default is 30 seconds).
     *
     * The key is sent once: the panel passes it to the CLI and neither
     * stores nor returns it. Without a key, a client that is already
     * installed and connected on the server is taken as it is.
     *
     * $options: `hostname` (the machine's name on the network), `control_url`
     * (a Headscale, a self-hosted NetBird), `direct` (also open the client's
     * UDP port, for direct connections), `accept_dns`, `accept_routes`; for
     * Tailscale `tags` (a list — a tagged machine has a node key that never
     * expires, which closing SSH asks for), `ssh` (Tailscale SSH on right
     * away), `reauth` (sign in again a machine already registered).
     *
     * @param  self::TAILSCALE|self::NETBIRD  $provider
     * @param  ?string  $key  a Tailscale auth key, a NetBird setup key
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function joinMesh(string $provider, ?string $key = null, array $options = []): array
    {
        return $this->client->post('/zt/mesh', $this->withoutNulls(['provider' => $provider, 'key' => $key] + $options));
    }

    /**
     * Leave the network: `DELETE /zt/mesh`. What its locks closed is reopened
     * first, then the client is removed. Refused (422) when this call itself
     * reaches the server over the mesh.
     *
     * @return array<string, mixed>
     */
    public function leaveMesh(): array
    {
        return $this->client->delete('/zt/mesh');
    }

    /**
     * Close a port to the world, open on the mesh only: `POST /zt/mesh/lock`.
     * $port is `ssh` (the SSH port) or `http` (80 and 443).
     *
     * The server refuses (422, with the reason) what would lock people out,
     * and `--force` does not exist here:
     *  - `ssh`: until sshd has accepted a login that came over the mesh, while
     *    the node key can expire, with the firewall off, with Tailscale SSH on;
     *  - `http`: while an app renews its certificate over HTTP-01, and when
     *    this call reaches the API on the public address the lock would close.
     * status() says it beforehand, in `details.mesh.panel.blocked`.
     *
     * @param  'ssh'|'http'  $port
     * @param  ?string  $client  as in status(): the address of the person at a panel on the server itself
     * @return array<string, mixed>
     */
    public function lockMesh(string $port, ?string $client = null): array
    {
        return $this->client->post('/zt/mesh/lock', $this->withoutNulls(['port' => $port, 'client' => $client]));
    }

    /**
     * Reopen it to the world: `DELETE /zt/mesh/lock`. A port Cloudflare's own
     * lock also holds stays closed, and `message` says so.
     *
     * @param  'ssh'|'http'  $port
     * @return array<string, mixed>
     */
    public function unlockMesh(string $port): array
    {
        return $this->client->delete('/zt/mesh/lock', ['port' => $port]);
    }

    /**
     * Tailscale SSH on or off: `PUT /zt/mesh/ssh`. On, tailscaled answers
     * port 22 of the tailnet address and the tailnet's policy says who signs
     * in. Tailscale only: over NetBird it is a 422.
     *
     * @return array<string, mixed>
     */
    public function tailscaleSsh(bool $enabled): array
    {
        return $this->client->put('/zt/mesh/ssh', ['enabled' => $enabled]);
    }
}
