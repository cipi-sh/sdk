<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * The nginx page cache of an app (API 1.47+, Cipi 5.17+).
 *
 * nginx keeps the page PHP made and answers the next visitors with it,
 * without running PHP: `fastcgi_cache` for an app on PHP-FPM (Laravel,
 * WordPress, any PHP app), `proxy_cache` for one on Octane. It is not OPcache,
 * which is always on and keeps the compiled PHP files in memory — with OPcache
 * PHP still runs at every request.
 *
 * The cache respects what the app answers (`Cache-Control`, `Expires`,
 * `Set-Cookie`) and skips signed-in visitors, carts, admin and API paths and
 * everything that is not GET or HEAD. An app adds the paths of its own that
 * must never be kept with `exclude()`.
 */
final class PageCache extends Resource
{
    /**
     * Every app with its page cache. `data.apps`: one entry per app, as `show()`.
     * Ability: `nginx-view`.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/cache');
    }

    /**
     * `data`: `supported` (false for a Node app), `kind` (`fastcgi`, `proxy`),
     * `enabled`, `active`, `ttl`, `ttl_seconds`, `size`, `clear_on_deploy`,
     * `exclude` (the app's own paths never cached) and `exclude_max`,
     * `files` and `bytes` (on disk now), `path`, `suspended` and
     * `clear_command` — what a custom deploy recipe, a cron or the app's own
     * code runs to empty it (`sudo /usr/local/bin/cipi-cache-clear <app>`).
     * Ability: `nginx-view`.
     *
     * @return array<string, mixed>
     */
    public function show(string $app): array
    {
        return $this->client->get($this->appPath($app).'/cache');
    }

    /**
     * Turn the cache on, or change it when it is on already. What is not
     * given stays as it is (defaults: pages kept `10m`, `256m` on disk,
     * emptied after every deploy and rollback).
     *
     * Synchronous: `nginx -t` runs before nginx reloads; a refusal changes
     * nothing and is a ValidationException (422). Ability: `nginx-manage`.
     *
     * @param  ?string  $ttl  a number and s, m, h or d — "30s", "10m", "1h", "7d"
     * @param  ?string  $size  "256m", "2g" — 16m to 100g
     * @param  ?list<string>  $exclude  the whole list of the app's own paths never cached, as `exclude()`; `[]` for none, null and the list stays
     * @return array<string, mixed>
     */
    public function enable(string $app, ?string $ttl = null, ?string $size = null, ?bool $clearOnDeploy = null, ?array $exclude = null): array
    {
        return $this->client->put($this->appPath($app).'/cache', $this->withoutNulls([
            'enabled' => true,
            'ttl' => $ttl,
            'size' => $size,
            'clear_on_deploy' => $clearOnDeploy,
            'exclude' => $exclude === null ? null : array_values($exclude),
        ]));
    }

    /**
     * The paths of its own the app keeps out of its cache — the whole list,
     * on top of what is never cached for any app: a page that is different
     * for each visitor with no cookie or header saying so, a feed, a page
     * with a counter. `/cart*` is everything that starts so (`*` is anything,
     * slashes included); `/feed` is the path itself, with or without its last
     * slash and whatever its query string. A path is letters, digits,
     * `. _ ~ % - /` and `*`; fifty at most. `[]` keeps none.
     *
     * Works with the cache off too: the list waits for it to be turned on.
     * A path that is not one is a ValidationException (422), and nothing
     * changes. Ability: `nginx-manage`.
     *
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    public function exclude(string $app, array $paths): array
    {
        return $this->client->put($this->appPath($app).'/cache/exclude', ['exclude' => array_values($paths)]);
    }

    /**
     * Turn the cache off; the cached pages are deleted. Ability: `nginx-manage`.
     *
     * @return array<string, mixed>
     */
    public function disable(string $app): array
    {
        return $this->client->put($this->appPath($app).'/cache', ['enabled' => false]);
    }

    /**
     * Delete every cached page of the app; the next visit to each runs PHP
     * and keeps it again. `data.cleared` is the number of pages deleted.
     * Cipi does this by itself after every deploy and rollback.
     * Ability: `nginx-manage`.
     *
     * @return array<string, mixed>
     */
    public function clear(string $app): array
    {
        return $this->client->post($this->appPath($app).'/cache/clear');
    }
}
