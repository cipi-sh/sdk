<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

/**
 * The cipi.yml an app ships in its repository: the server state it expects.
 * Requires cipi/api ≥ 1.35.0 and Cipi CLI ≥ 5.6.4. Older hosts answer 503.
 * Reads need the yml-view ability, writes yml-manage. Existing tokens do not
 * get them automatically, so a token without them answers 403.
 *
 * With cipi/api ≥ 1.36.0 and Cipi CLI ≥ 5.8.0 the file follows the app's
 * environment label (Apps::create() env): cipi-<env>.yml first, then cipi.yml.
 * The same versions accept deploy.branch in the file, the Git branch the app
 * deploys, validated like `cipi app edit --branch` (letters, digits, . _ / -,
 * no ".."); a branch that differs from the app's is one of the changes.
 */
final class Yml extends Resource
{
    /**
     * File, auto-apply state, validation, and the plan: what apply() would change.
     * Synchronous. Needs yml-view.
     *
     * data: app, file (null when the live release has none), env (the environment
     * label the file was chosen for: cipi-<env>.yml first, then cipi.yml; absent
     * before cipi/api 1.36.0, so read it as $data['env'] ?? 'production'), auto,
     * valid (null without a file; false → errors), in_sync (null without a file or
     * when invalid), errors, warnings, changes (list of {kind, summary}; show the
     * summary), blockers (apply() changes nothing while any are listed), notes.
     *
     * @return array<string, mixed>
     */
    public function status(string $app): array
    {
        return $this->client->get($this->appPath($app).'/yml');
    }

    /**
     * Queue an apply of the app's cipi.yml. Poll the returned job_id. Needs yml-manage.
     *
     * The job (type app-yml-apply) carries result.outcome: applied or unchanged end
     * completed; partial, blocked, invalid, and no-file end failed but keep the same
     * result (applied, failed, failures, blockers, errors). Pass throwOnFailure: false
     * to jobs()->wait() to read it without catching JobFailedException.
     *
     * @return array<string, mixed>
     */
    public function apply(string $app): array
    {
        return $this->client->post($this->appPath($app).'/yml/apply');
    }

    /**
     * Apply cipi.yml after every successful deploy (CLI, panel, and Git webhook).
     * Synchronous and idempotent. Needs yml-manage.
     *
     * Anyone who can commit to the repository can then change this app's aliases,
     * PHP settings, limits, workers, deploy recipe, scheduled commands, databases,
     * and backup schedule (for Node apps also mode, version, build, and start).
     * Ask for confirmation before calling this on a user's behalf.
     *
     * @return array<string, mixed>
     */
    public function enableAuto(string $app): array
    {
        return $this->client->post($this->appPath($app).'/yml/auto/enable');
    }

    /**
     * Stop applying cipi.yml after deploys. Synchronous and idempotent. Needs yml-manage.
     *
     * @return array<string, mixed>
     */
    public function disableAuto(string $app): array
    {
        return $this->client->post($this->appPath($app).'/yml/auto/disable');
    }
}
