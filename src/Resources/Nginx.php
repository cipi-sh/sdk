<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Nginx extends Resource
{
    /**
     * The directives an app adds to its own nginx server block — headers,
     * caching, limits, rewrites, `location` blocks. Cipi keeps them and
     * includes them in the vhost, so they survive every change of it.
     *
     * `data`: `app`, `config` (the text; empty when there is none), `bytes`,
     * `max_bytes`, `active` (in the vhost right now — not while the app is
     * suspended) and `suspended`. Needs Cipi 5.14.0+ (503 before),
     * cipi/api 1.44+ and the `nginx-view` ability.
     *
     * @return array<string, mixed>
     */
    public function show(string $app): array
    {
        return $this->client->get($this->appPath($app).'/nginx');
    }

    /**
     * Replace the app's directives with `$config`; an empty text removes
     * them. Synchronous: `nginx -t` runs before nginx reloads.
     *
     * A text that is refused changes nothing and answers 422 — a
     * ValidationException whose message is the first reason; the others are
     * in the response body's `reasons` ("line 2: unknown directive "boom"").
     *
     * From the API a subset of nginx's language is taken: no `access_log` or
     * `error_log` with a path, `include`, `listen`, `server_name`, `*_pass`
     * or certificates; `root` and `alias` only under the app's home. Use
     * `proxies()` and `redirects()` for those, or `cipi nginx edit <app>` at
     * the server for the whole language. Ability: `nginx-manage`.
     *
     * @return array<string, mixed>
     */
    public function update(string $app, string $config): array
    {
        return $this->client->put($this->appPath($app).'/nginx', ['config' => $config]);
    }

    /**
     * Remove the app's directives. Ability: `nginx-manage`.
     *
     * @return array<string, mixed>
     */
    public function reset(string $app): array
    {
        return $this->client->delete($this->appPath($app).'/nginx');
    }
}
