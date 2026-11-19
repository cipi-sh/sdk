<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class NodeApps extends Resource
{
    /**
     * Installed Node runtimes.
     *
     * @return array<string, mixed>
     */
    public function runtimes(): array
    {
        return $this->client->get('/node');
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $app): array
    {
        return $this->client->get($this->appPath($app).'/node');
    }

    /**
     * Blue/green restart of an SSR Node app. Returns a job.
     *
     * @return array<string, mixed>
     */
    public function restart(string $app): array
    {
        return $this->client->post($this->appPath($app).'/node/restart');
    }

    /**
     * Install a Node major next to the ones already there: the official build
     * from nodejs.org, checksum-verified. Returns a job (node-install).
     *
     * $major is an even (LTS) major: 22, 24, ... Anything else is a 422
     * (ValidationException).
     *
     * This and the three methods below need the ability node-runtimes-manage,
     * cipi/api ≥ 1.39.0 and Cipi CLI ≥ 5.9.0; on an older core the panel
     * answers 503 (ServerException) and queues nothing.
     *
     * @return array<string, mixed>
     */
    public function install(int|string $major): array
    {
        return $this->client->post('/node/install', [
            'major' => (string) $major,
        ]);
    }

    /**
     * Latest patch of one major, or of every installed one when $major is
     * omitted. Returns a job (node-upgrade).
     *
     * With $restart the SSR apps on that runtime move to the new binary
     * blue/green, with no downtime; without it they take it at their next deploy.
     *
     * @return array<string, mixed>
     */
    public function upgrade(int|string|null $major = null, bool $restart = false): array
    {
        $body = $this->withoutNulls([
            'major' => $major === null ? null : (string) $major,
            'restart' => $restart ? true : null,
        ]);

        return $this->client->post('/node/upgrade', $body === [] ? null : $body);
    }

    /**
     * The server-wide Node: what Laravel asset builds and run()->execute()
     * with npm use. Node apps and apps pinned to a major keep theirs. A major
     * that is not installed is installed first. Returns a job (node-default).
     *
     * $major is a major (22, 24, ...) or "system", the distribution's own Node.
     *
     * @return array<string, mixed>
     */
    public function setDefault(int|string $major): array
    {
        return $this->client->put('/node/default', [
            'major' => (string) $major,
        ]);
    }

    /**
     * Remove a Node major. Returns a job (node-remove); it ends failed, with
     * the CLI's message, when an app uses that major or it is the server default.
     *
     * @return array<string, mixed>
     */
    public function remove(int|string $major): array
    {
        return $this->client->delete('/node/'.$this->client->segment((string) $major));
    }
}
