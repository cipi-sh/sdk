<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * The PHP of an Octane app (API 1.47+, Cipi 5.17+).
 *
 * An Octane app runs two PHP: the one built into its FrankenPHP binary
 * answers the requests, the server's php<X.Y> runs Composer, artisan, cron
 * and the queue workers. A FrankenPHP release carries one PHP.
 */
final class Octane extends Resource
{
    /**
     * `data`: `php` (Composer, artisan, cron, workers), `octane_php` (what
     * answers requests), `build`, `frankenphp`, `state` (`ok`, `vulnerable`,
     * `mismatch`, `unpinned`, `missing`), `restart_pending`, `available_php`
     * (the minors a build on the server exists for) and, for a mismatch,
     * `fix` (`command`, `why`). 422 for an app that is not on Octane.
     * Ability: `php-view`.
     *
     * @return array<string, mixed>
     */
    public function show(string $app): array
    {
        return $this->client->get($this->appPath($app).'/octane');
    }

    /**
     * Put the app on one PHP version, by its number: the FrankenPHP build that
     * carries it (fetched when the server has none) — Octane restarted and
     * checked, the previous build back when it does not answer — then
     * Composer, artisan, cron and workers. That PHP must be installed on the
     * server. An async job (`octane-php`): pass the answer to `wait()`.
     * Ability: `php-manage`.
     *
     * @return array<string, mixed>
     */
    public function php(string $app, string $version): array
    {
        return $this->client->put($this->appPath($app).'/octane/php', ['php' => $version]);
    }

    /**
     * Restart Octane on its pinned build and check that it answers. An async
     * job (`octane-restart`). Ability: `php-manage`.
     *
     * @return array<string, mixed>
     */
    public function restart(string $app): array
    {
        return $this->client->post($this->appPath($app).'/octane/restart');
    }

    /**
     * The FrankenPHP build of every Octane app: the PHP inside each, patched
     * or not (API 1.40+, Cipi 5.10+). Ability: `php-view`.
     *
     * @return array<string, mixed>
     */
    public function builds(): array
    {
        return $this->client->get('/frankenphp');
    }

    /**
     * Move one Octane app — or all of them, without a name — to the newest
     * FrankenPHP build on its own PHP minor. Never another PHP: that is
     * `php()`. An async job (`frankenphp-upgrade`). Ability: `php-manage`.
     *
     * @return array<string, mixed>
     */
    public function upgrade(?string $app = null): array
    {
        return $this->client->post('/frankenphp/upgrade', $app === null ? null : ['app' => $app]);
    }
}
