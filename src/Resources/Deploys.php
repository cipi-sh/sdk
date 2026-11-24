<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Deploys extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function start(string $app): array
    {
        return $this->client->post($this->appPath($app).'/deploy');
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback(string $app): array
    {
        return $this->client->post($this->appPath($app).'/deploy/rollback');
    }

    /**
     * Remove a deploy lock left behind, and — Cipi CLI 5.13+ — resume the
     * automatic deploys of a commit whose deploy failed (status()'s
     * paused_commit). A job (app-deploy-unlock): its result says unlocked,
     * resumed and resumed_commit (cipi/api 1.43+).
     *
     * @return array<string, mixed>
     */
    public function unlock(string $app): array
    {
        return $this->client->post($this->appPath($app).'/deploy/unlock');
    }

    /**
     * Hash-chained deploy audit ledger. Synchronous.
     *
     * @return array<string, mixed>
     */
    public function audit(string $app, ?int $days = null): array
    {
        return $this->client->get($this->appPath($app).'/deploy/audit', $this->withoutNulls([
            'days' => $days,
        ]));
    }

    /**
     * Is a deploy running for this app right now, whoever started it: a Git
     * push through the webhook, cipi deploy on the server, the panel, a
     * rollback. Synchronous.
     *
     * {"data": {app, state, running, phase, queued, locked, paused_commit, current, last, log}}.
     * state is running, queued (a webhook push the per-minute cron has not
     * picked up yet), interrupted (a run that never wrote its end and that
     * nothing is running: the deploy lock may be stuck, see unlock()) or idle.
     * phase is deploy or post-deploy while running. current is the run in
     * flight: trigger (webhook, cli, panel, rollback, ...), branch,
     * from_release, started_at, elapsed (seconds), pids. last is how the
     * previous run ended: result (ok or failed), kind (deploy or rollback),
     * release, duration, exit, trigger and times. log is the deploy log's path.
     * paused_commit (Cipi CLI 5.13+, null otherwise) is the commit whose
     * automatic deploy failed: the webhook does not deploy it again, the next
     * push does, and unlock() or a deploy that succeeds resumes it.
     *
     * $tail adds the last N lines of the deploy log as data.tail (0 to 500).
     *
     * Ability deploy-manage. Requires cipi/api ≥ 1.37.0 and Cipi CLI ≥ 5.8.4;
     * on an older core the panel answers 503 (ServerException) and starts nothing.
     *
     * @return array<string, mixed>
     */
    public function status(string $app, ?int $tail = null): array
    {
        return $this->client->get($this->appPath($app).'/deploy/status', $this->withoutNulls([
            'tail' => $tail,
        ]));
    }

    /**
     * Past deploys and rollbacks of an app, newest first. Read from the deploy
     * log and its rotated files, so last week's deploy still has its duration.
     * Synchronous.
     *
     * {"data": {app, count, runs: [{result, kind, release, duration, exit,
     * trigger, branch, from_release, started_at, finished_at}]}}.
     *
     * $limit is 1 to 500; the panel returns 30 runs when it is omitted.
     *
     * Ability apps-view or deploy-manage. Requires cipi/api ≥ 1.39.0 and Cipi
     * CLI ≥ 5.9.0 (503 on an older core).
     *
     * @return array<string, mixed>
     */
    public function history(string $app, ?int $limit = null): array
    {
        return $this->client->get($this->appPath($app).'/deploy/history', $this->withoutNulls([
            'limit' => $limit,
        ]));
    }

    /**
     * The last finished deploy of every app in one call: {"data": {<app>: run|null}},
     * the run shaped as in history(), null for an app that was never deployed.
     * Synchronous.
     *
     * Ability apps-view or deploy-manage. Requires cipi/api ≥ 1.39.0 and Cipi
     * CLI ≥ 5.9.0 (503 on an older core).
     *
     * @return array<string, mixed>
     */
    public function last(): array
    {
        return $this->client->get('/deploys/last');
    }
}
