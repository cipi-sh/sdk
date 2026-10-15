<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Apps extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('/apps');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        return $this->client->get($this->appPath($name));
    }

    /**
     * Queue app creation. Poll the returned job_id.
     *
     * Required: user, domain. Optional: repository, branch, php, custom, docroot,
     * engine, octane; Node apps: node, framework, node_version, build, start,
     * output, health_path.
     *
     * node_build (Laravel apps only) is the asset build run inside every deploy
     * before the release goes live, for example "npm ci && npm run build". At most 200 characters, it starts with npm, npx,
     * yarn, pnpm, bun or node; && and ; are allowed, pipes, redirects, backticks
     * and $( ) are not. The panel validates it and answers 422 (ValidationException)
     * on a bad command or on custom and Node apps (Node apps take build).
     * Requires cipi/api ≥ 1.34.0 and Cipi CLI ≥ 5.6.0.
     *
     * The completed app-create job carries data.result: app, domain, php, home,
     * ssh, database, deploy_key, webhook, webhook_token, and on Cipi CLI ≥ 5.6.0
     * build and repository_access (see Jobs::get()). Either key may be absent.
     *
     * @param  array{
     *     user: string,
     *     domain: string,
     *     repository?: string|null,
     *     branch?: string|null,
     *     php?: string|null,
     *     custom?: bool|null,
     *     docroot?: string|null,
     *     engine?: string|null,
     *     octane?: string|bool|null,
     *     node_build?: string|null,
     *     node?: string|null,
     *     framework?: string|null,
     *     node_version?: string|null,
     *     build?: string|null,
     *     start?: string|null,
     *     output?: string|null,
     *     health_path?: string|null,
     * }|array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        return $this->client->post('/apps', $attributes);
    }

    /**
     * Queue an app edit. Poll the returned job_id.
     *
     * When the edit changes repository or branch, Cipi CLI ≥ 5.6.0 re-checks the
     * deploy key and the app-edit result carries repository_access (see Jobs::get()).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(string $name, array $attributes): array
    {
        return $this->client->put($this->appPath($name), $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $name): array
    {
        return $this->client->delete($this->appPath($name));
    }

    /**
     * @return array<string, mixed>
     */
    public function suspend(string $name): array
    {
        return $this->client->post($this->appPath($name).'/suspend');
    }

    /**
     * @return array<string, mixed>
     */
    public function unsuspend(string $name): array
    {
        return $this->client->post($this->appPath($name).'/unsuspend');
    }

    /**
     * @return array<string, mixed>
     */
    public function fixPermissions(string $name): array
    {
        return $this->client->post($this->appPath($name).'/fix-permissions');
    }

    /**
     * Recreate the Git webhook. Pass rotateSecret to rotate CIPI_WEBHOOK_TOKEN.
     *
     * @return array<string, mixed>
     */
    public function recreateWebhook(string $name, bool $rotateSecret = false): array
    {
        return $this->client->post($this->appPath($name).'/webhook/recreate', [
            'rotate_secret' => $rotateSecret,
        ]);
    }
}
