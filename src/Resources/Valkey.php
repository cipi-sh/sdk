<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * Valkey per app, for the whole server (API 1.47+, Cipi 5.17+).
 *
 * A Laravel app keeps cache, sessions and queues in Valkey. On the server's
 * shared instance every app holds the same password, so each can read and
 * write the others' keys; an app with an instance of its own cannot. No
 * password is ever in an answer.
 */
final class Valkey extends Resource
{
    /**
     * `data`: `shared_port`, `apps` (`app`, `uses`: `own` / `shared` /
     * `external` / `none`, `port`, `instance`, `memory`, `keys`), `shared`
     * (the apps still on the shared instance) and `isolated`.
     * Ability: `services-view`.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/valkey');
    }

    /**
     * Give an app its own instance: its keys are copied with the app in
     * maintenance mode for the seconds that takes, its .env is switched and
     * its workers restart. `$copy` false starts it empty (no cache, no
     * sessions, no queued jobs). An async job (`valkey-isolate`).
     * Ability: `services-manage`.
     *
     * @return array<string, mixed>
     */
    public function isolate(string $app, bool $copy = true): array
    {
        return $this->client->post('/valkey/isolate', ['app' => $app] + ($copy ? [] : ['copy' => false]));
    }

    /**
     * The same for every app still on the shared instance. An async job
     * (`valkey-isolate`); its `result` has `moved`, `left` and
     * `shared_can_be_flushed`. Ability: `services-manage`.
     *
     * @return array<string, mixed>
     */
    public function isolateAll(bool $copy = true): array
    {
        return $this->client->post('/valkey/isolate', ['all' => true] + ($copy ? [] : ['copy' => false]));
    }

    /**
     * Move an app back to the shared instance and remove its own. On the
     * shared instance every other app can read and write its cache, sessions
     * and queues. An async job (`valkey-share`). Ability: `services-manage`.
     *
     * @return array<string, mixed>
     */
    public function share(string $app, bool $copy = true): array
    {
        return $this->client->post('/valkey/share', ['app' => $app] + ($copy ? [] : ['copy' => false]));
    }

    /**
     * Restart an app's own instance. Synchronous; `data` is the list after it.
     * Ability: `services-manage`.
     *
     * @return array<string, mixed>
     */
    public function restart(string $app): array
    {
        return $this->client->post('/valkey/'.$this->client->segment($app).'/restart');
    }

    /**
     * Empty the shared instance and change its password, once no app uses it
     * (a ConflictException, 409, while one does). The new password stays on
     * the server. Ability: `services-manage`.
     *
     * @return array<string, mixed>
     */
    public function flushShared(): array
    {
        return $this->client->post('/valkey/flush-shared');
    }
}
