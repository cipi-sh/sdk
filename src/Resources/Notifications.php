<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * Notifications (API 1.42+, Cipi 5.12+): every channel — smtp, resend, ses,
 * slack, discord, mattermost, googlechat, telegram, ntfy, pushover, webhook —
 * and the triggers. Every enabled trigger reaches every enabled channel.
 * Synchronous. Secrets are never returned (token_set, api_key_set, …); an
 * update that leaves a secret out keeps the stored one.
 */
final class Notifications extends Resource
{
    /**
     * Channels (SMTP first), how many triggers are on, the channel types.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->get('/notifications');
    }

    /**
     * Add a channel. Fields per type — smtp: host, port, user, password, from, to, tls;
     * resend: api_key, from, to; ses: region, access_key, secret_key, from, to;
     * slack|discord|mattermost|googlechat: url; telegram: token, chat_id;
     * ntfy: url, priority; pushover: token, user; webhook: url, secret.
     * Also enabled and test. $id is ignored for smtp.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed> {channel, test}
     */
    public function add(string $type, ?string $id, array $fields = []): array
    {
        return $this->client->post('/notifications/channels', $this->withoutNulls(['type' => $type, 'id' => $type === 'smtp' ? null : $id] + $fields));
    }

    /**
     * Change a channel: only the fields given; a secret left out is kept.
     * clear_secret => true drops a webhook's signing secret.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed> {channel, test}
     */
    public function update(string $id, array $fields): array
    {
        return $this->client->put('/notifications/channels/'.$this->client->segment($id), $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string $id): array
    {
        return $this->client->delete('/notifications/channels/'.$this->client->segment($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(string $id): array
    {
        return $this->client->post('/notifications/channels/'.$this->client->segment($id).'/enable');
    }

    /**
     * @return array<string, mixed>
     */
    public function disable(string $id): array
    {
        return $this->client->post('/notifications/channels/'.$this->client->segment($id).'/disable');
    }

    /**
     * Send a test to one channel, or 'all'. A refused test is an ApiException (422) carrying the reason.
     *
     * @return array<string, mixed> {ok, message}
     */
    public function test(string $id = 'all'): array
    {
        return $this->client->post('/notifications/channels/'.$this->client->segment($id).'/test');
    }

    /**
     * Every trigger with id, category, label, enabled.
     *
     * @return array<string, mixed>
     */
    public function triggers(): array
    {
        return $this->client->get('/notifications/triggers');
    }

    /**
     * Turn triggers on and off in one write: ['deploy_success' => false, 'deploy_fail' => true].
     *
     * @param  array<string, bool>  $triggers
     * @return array<string, mixed>
     */
    public function setTriggers(array $triggers): array
    {
        return $this->client->put('/notifications/triggers', ['triggers' => $triggers]);
    }

    /**
     * Every trigger on (true) or off (false); $except are set the other way.
     *
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    public function setAllTriggers(bool $on, array $except = []): array
    {
        return $this->client->put('/notifications/triggers', ['all' => $on] + ($except === [] ? [] : [$on ? 'off' : 'on' => $except]));
    }

    /**
     * Every trigger back on.
     *
     * @return array<string, mixed>
     */
    public function resetTriggers(): array
    {
        return $this->client->post('/notifications/triggers/reset');
    }

    /**
     * How notifications read (API 1.47+, Cipi 5.17+): `timezone` (null: the
     * server's own, `server_timezone`), `server_name` (null: its `hostname`)
     * and `now`, the time as a notification would write it at this moment.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->client->get('/notifications/settings');
    }

    /**
     * The time zone of the `Time:` line of every notification: an IANA name
     * ("Europe/Rome"), or null for the server's own. For notifications only —
     * the server's clock, cron and logs do not change.
     *
     * @return array<string, mixed>
     */
    public function setTimezone(?string $timezone): array
    {
        return $this->client->put('/notifications/settings', ['timezone' => $timezone]);
    }

    /**
     * The name notifications give the server ("production"), or null for its
     * hostname. Subjects carry the name, bodies `name (hostname)`.
     *
     * @return array<string, mixed>
     */
    public function setServerName(?string $name): array
    {
        return $this->client->put('/notifications/settings', ['server_name' => $name]);
    }
}
