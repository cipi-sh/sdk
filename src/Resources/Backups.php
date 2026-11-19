<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * Backup profiles: what is backed up, when, where to, and what the buckets hold.
 *
 * Reads need the ability backups-view, writes backups-manage. Requires
 * cipi/api ≥ 1.39.0 and Cipi CLI ≥ 5.9.0: on an older core every method here
 * answers 503 (ServerException), on an older panel 404 (NotFoundException).
 *
 * Destinations are read-only through the API. Adding or changing a bucket
 * takes an S3 secret key, which stays on the server: cipi backup configure and
 * cipi backup destination add|edit|remove. backup fetch, key and prune stay
 * there too.
 */
final class Backups extends Resource
{
    /**
     * Destinations (never their keys), profiles with what each covers right
     * now, its last run, overdue and running, and the apps and databases no
     * enabled profile takes (uncovered). Synchronous; changes nothing.
     *
     * data.configured is false on a server where cipi backup configure was
     * never run. data.host and data.config_file say where the answer comes
     * from (cipi/api ≥ 1.39.1).
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->client->get('/backups');
    }

    /**
     * The runs each destination holds, newest first, per profile:
     * {"data": {profiles: {<profile>: [{run, destinations}]}, errors: []}}.
     * Lists every bucket, so it can take a few seconds. Synchronous.
     *
     * @return array<string, mixed>
     */
    public function runs(?string $profile = null): array
    {
        return $this->client->get('/backups/runs', $this->withoutNulls([
            'profile' => $profile,
        ]));
    }

    /**
     * Start a backup run in the background: one profile, or every enabled one
     * when $profile is omitted. Not a job: the panel answers 202 with
     * {"data": {started: true, profile}} as soon as the run has started, and
     * the result shows in status() (running, then last).
     *
     * @return array<string, mixed>
     */
    public function run(?string $profile = null): array
    {
        return $this->client->post('/backups/run', $this->profileBody($profile));
    }

    /**
     * Read the newest run of a profile (or of each) back and check that every
     * archive in it opens. Returns a job (backup-verify): it downloads from
     * the bucket when there is no local copy.
     *
     * @return array<string, mixed>
     */
    public function verify(?string $profile = null): array
    {
        return $this->client->post('/backups/verify', $this->profileBody($profile));
    }

    /**
     * List each S3 destination with its own credentials, or the one named:
     * "s3" (the default destination) or a named one. Synchronous.
     *
     * @return array<string, mixed>
     */
    public function testDestinations(?string $name = null): array
    {
        return $this->client->post('/backups/destinations/test', $name === null ? null : ['name' => $name]);
    }

    /**
     * Create a profile. Synchronous: 201 with {"data": {profile, saved: true}}.
     *
     * $name is lowercase letters, digits and dashes, 2 to 32 characters,
     * starting with a letter. destinations is required: "local", "s3", or the
     * name of a destination created on the server.
     *
     * scope is all, files or db. apps, databases, exclude_databases and
     * exclude_tables are lists of names or globs (* ? [ ]). every (30m, 6h, 1d)
     * or cron (five fields), not both. keep, keep_days and keep_weeks are the
     * retention: give the three together, since sending one resets the other
     * two to 0, and at least one must be greater than 0. encrypt is a boolean.
     *
     * A field the panel refuses is a 422 (ValidationException, errors() keyed
     * by field); so is what the CLI itself refuses, with its message.
     *
     * @param  array{
     *     destinations: list<string>|string,
     *     scope?: string|null,
     *     apps?: list<string>|string|null,
     *     databases?: list<string>|string|null,
     *     exclude_databases?: list<string>|string|null,
     *     exclude_tables?: list<string>|string|null,
     *     every?: string|null,
     *     cron?: string|null,
     *     keep?: int|null,
     *     keep_days?: int|null,
     *     keep_weeks?: int|null,
     *     encrypt?: bool|null,
     * }|array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function createProfile(string $name, array $attributes): array
    {
        return $this->client->post('/backups/profiles', ['name' => $name] + $attributes);
    }

    /**
     * Change a profile: only the fields sent change (see createProfile()).
     * An empty list for exclude_databases or exclude_tables clears it. The
     * panel answers 422 when nothing would change. Synchronous.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateProfile(string $name, array $attributes): array
    {
        return $this->client->put($this->profilePath($name), $attributes);
    }

    /**
     * Remove a profile. Its archives stay where they are; data.message says
     * how many and for how long. Synchronous.
     *
     * @return array<string, mixed>
     */
    public function deleteProfile(string $name): array
    {
        return $this->client->delete($this->profilePath($name));
    }

    /**
     * @return array<string, mixed>
     */
    public function enableProfile(string $name): array
    {
        return $this->client->post($this->profilePath($name).'/enable');
    }

    /**
     * @return array<string, mixed>
     */
    public function disableProfile(string $name): array
    {
        return $this->client->post($this->profilePath($name).'/disable');
    }

    private function profilePath(string $name): string
    {
        return '/backups/profiles/'.$this->client->segment($name);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profileBody(?string $profile): ?array
    {
        return $profile === null ? null : ['profile' => $profile];
    }
}
