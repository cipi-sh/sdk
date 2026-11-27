<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * Apps sent from one server to another (`cipi sync`): a move to a bigger
 * machine, production copied onto staging, a second server kept ready.
 *
 * The server this client talks to is the source of a push(): it exports the
 * apps — settings, `.env`, deploy recipe, workers, crons, and databases and
 * uploaded files when asked — into one archive, encrypts it, sends it over
 * SSH and has the target import it. Apps that are not on the target are
 * created there; apps that are, updated. Nothing is removed or stopped on
 * the source.
 *
 * The target has to trust the source's key first: key() on the source,
 * trust() on the target — with a client for each — or `cipi sync trust` at
 * the target's shell. check() says whether the way is open before anything
 * moves.
 *
 * There is no passphrase here: the source makes one for the transfer and
 * hands it to the target's import over the same SSH connection. It is never
 * returned, and never passes through the API.
 *
 * cipi/api 1.48+, Cipi 5.18.0+ on both servers, ability `sync-manage` — as
 * strong as `ssh-manage`: its holder can have the server send its apps,
 * databases included, to a host of their choosing, and can let another
 * server's key sign in as `cipi`.
 */
final class Sync extends Resource
{
    /**
     * `GET /sync/key`: this server's sync key — what a target has to trust.
     * `data`: `key` (the public key, one line), `type`, `fingerprint`
     * (`SHA256:…`), `comment`.
     *
     * @return array<string, mixed>
     */
    public function key(): array
    {
        return $this->client->get('/sync/key');
    }

    /**
     * `POST /sync/check`: can this server send to that one. Nothing is sent.
     * $host is the address *this server* reaches the target on over SSH — a
     * public IP, or a private or mesh one.
     *
     * `data`: `ok`, `host`, `port`, `user`, `cipi` (the target's version),
     * `auto_passphrase` (the target can take the passphrase from the source:
     * what push() needs), `reason` and `message` when `ok` is false —
     * `not-trusted` (the target does not accept this server's key),
     * `unreachable` (no SSH answer), `host-key-changed`, `no-cipi` (SSH
     * answers, Cipi does not), `no-key` (this server has no sync key).
     *
     * The server waits for SSH to answer: allow the call a minute — build
     * the client with a `timeout` to match (the default is 30 seconds).
     *
     * @return array<string, mixed>
     */
    public function check(string $host, int $port = 22): array
    {
        return $this->client->post('/sync/check', ['host' => $host, 'port' => $port]);
    }

    /**
     * Is the way open for a push(): the target accepts this server's key and
     * runs a Cipi that takes the passphrase from the source. One call to
     * check().
     */
    public function ready(string $host, int $port = 22): bool
    {
        $data = $this->check($host, $port)['data'] ?? [];

        return is_array($data) && ($data['ok'] ?? false) === true && ($data['auto_passphrase'] ?? false) === true;
    }

    /**
     * `POST /sync/trust`, on the server that receives: let the server this
     * key belongs to send apps here. That server's `cipi` user can then sign
     * in here over SSH — only the key of a server of yours.
     *
     * `data`: `key`, `type`, `fingerprint`, `comment`, and `changed` — false
     * when the key was trusted already. Anything that is not one public key
     * on one line is a ValidationException (422).
     *
     * @param  string  $key  `data.key` of the source's key()
     * @return array<string, mixed>
     */
    public function trust(string $key): array
    {
        return $this->client->post('/sync/trust', ['key' => $key]);
    }

    /**
     * `DELETE /sync/trust`: take that key back out. `changed` is false when
     * it was not trusted.
     *
     * @return array<string, mixed>
     */
    public function untrust(string $key): array
    {
        return $this->client->delete('/sync/trust', ['key' => $key]);
    }

    /**
     * `POST /sync/push`: send apps to the target and import them there. An
     * async job of type `sync-push` — follow it with jobs()->wait(), which
     * gives it the time the panel gives a deploy.
     *
     * $apps: the apps to send; none, every app of the server. $withDb dumps
     * the databases here and restores them there, replacing the database of
     * an app that is already on the target; $withStorage adds each app's
     * `shared/storage`. The archive is removed from both servers when the
     * import ends. A failed import fails the job.
     *
     * Laravel and custom apps travel; Node apps are skipped — sync does not
     * recreate them yet.
     *
     * The target must trust this server's key and run Cipi 5.18.0+: call
     * ready() first, or the job fails at its first step.
     *
     * @param  list<string>  $apps
     * @return array<string, mixed>
     */
    public function push(string $host, array $apps = [], bool $withDb = false, bool $withStorage = false, int $port = 22): array
    {
        return $this->client->post('/sync/push', [
            'host' => $host,
            'port' => $port,
            'apps' => array_values($apps),
            'with_db' => $withDb,
            'with_storage' => $withStorage,
        ]);
    }
}
