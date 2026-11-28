<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * How the server updates Cipi itself (`cipi self-update policy`): every night
 * it installs the latest Cipi from GitHub. Two safeguards can be added — both
 * off unless turned on:
 *
 * - `verify`: a release is installed only when its tag v<version> is signed by
 *   a key the server trusts and points at what was downloaded. The first time,
 *   the keys shipped with the release are trusted when `fingerprint` matches
 *   one of them — compare it with the one published at cipi.sh/docs/self-update.
 * - `delay_hours`: a new release is installed once the server has seen it for
 *   that long (0 to 168). The clock is the server's own.
 *
 * Starting an update is not in the API. cipi/api 1.48+, Cipi 5.18.0+.
 * Abilities: updates-view, updates-manage.
 */
final class Updates extends Resource
{
    /**
     * `data`: verify, delay_hours, delay_default_hours, signers, signers_ready,
     * shipped_signers, pending (a release waiting for its delay), held (why the
     * last update was not installed), installed_sha.
     *
     * @return array<string, mixed>
     */
    public function policy(): array
    {
        return $this->client->get('/self-update/policy');
    }

    /**
     * @param  ?bool  $verify  only signed releases; null leaves it
     * @param  ?int  $delayHours  0 (none) to 168; null leaves it
     * @param  ?string  $fingerprint  SHA256:… of a shipped key, the first time verify goes on
     * @return array<string, mixed>
     */
    public function setPolicy(?bool $verify = null, ?int $delayHours = null, ?string $fingerprint = null): array
    {
        $body = array_filter(['verify' => $verify, 'delay_hours' => $delayHours, 'fingerprint' => $fingerprint], fn ($v) => $v !== null);

        return $this->client->put('/self-update/policy', $body);
    }
}
