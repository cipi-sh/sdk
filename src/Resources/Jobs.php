<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

use Cipi\Sdk\Exception\ConfigurationException;
use Cipi\Sdk\Exception\JobFailedException;
use Cipi\Sdk\Exception\TimeoutException;

final class Jobs extends Resource
{
    /**
     * Job status: {"data": {"id", "type", "status", "result", ...}}.
     *
     * data.result is the parsed CLI output and depends on the job type. With
     * cipi/api ≥ 1.34.0 and Cipi CLI ≥ 5.6.0 the app-create result, and the
     * app-edit result when the repository or branch changed, include repository_access:
     * whether the app's deploy key can read its repository. The check never fails
     * the job, so a completed app-create can still report "denied". The key is
     * absent on older servers and on apps without a repository; app-create also
     * gains build (the Laravel node_build or the Node build command).
     *
     *     $access = $job['data']['result']['repository_access'] ?? null;
     *     if ($access !== null && $access['status'] !== 'ok') {
     *         // $access['hint'], e.g. "cipi git github-token <token> && cipi git refresh shop"
     *     }
     *
     * repository_access shape: array{status: 'ok'|'nobranch'|'denied', branch?: string,
     * error?: string, hint?: string}. ok = readable and the branch exists; nobranch =
     * readable, no such branch; denied = the key cannot read it (error is the git line).
     *
     * app-yml-apply (cipi/api ≥ 1.35.0, Cipi CLI ≥ 5.6.4) carries array{app: string,
     * outcome: 'applied'|'unchanged'|'partial'|'blocked'|'invalid'|'no-file', applied: int,
     * failed: int, failures: list<string>, blockers: list<string>, errors: list<string>}.
     * Only applied and unchanged end completed; the others end failed with the same result.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->get('/jobs/'.$this->client->segment($id));
    }

    /**
     * Poll until the job is completed or failed.
     * A failed job throws JobFailedException unless $throwOnFailure is false.
     * Returns the same payload as get(), including data.result.
     *
     * @param  string|array<string, mixed>  $job
     * @return array<string, mixed>
     */
    public function wait(
        string|array $job,
        int $timeoutSeconds = 300,
        float $intervalSeconds = 1.0,
        bool $throwOnFailure = true,
    ): array {
        $id = $this->id($job);
        if ($timeoutSeconds < 1 || $timeoutSeconds > 3600) {
            throw new ConfigurationException('Job timeout must be between 1 and 3600 seconds.');
        }
        if ($intervalSeconds < 0.1 || $intervalSeconds > 30) {
            throw new ConfigurationException('Job poll interval must be between 0.1 and 30 seconds.');
        }

        $deadline = microtime(true) + $timeoutSeconds;
        $sleep = $intervalSeconds;

        while (true) {
            $payload = $this->get($id);
            $status = $this->statusOf($payload);
            if ($status === 'completed') {
                return $payload;
            }
            if ($status === 'failed') {
                if ($throwOnFailure) {
                    throw new JobFailedException($this->failureMessage($payload), $payload);
                }

                return $payload;
            }
            if (microtime(true) >= $deadline) {
                throw new TimeoutException('Timed out waiting for Cipi job '.$id.'.', 0, $payload, 'GET', '/jobs/'.$id);
            }

            $remaining = $deadline - microtime(true);
            usleep((int) round(min($sleep, max($remaining, 0)) * 1_000_000));
            $sleep = min($sleep * 1.5, 5.0);
        }
    }

    /**
     * @param  string|array<string, mixed>  $job
     */
    private function id(string|array $job): string
    {
        if (is_string($job)) {
            return $job;
        }

        $id = $job['job_id'] ?? $job['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new ConfigurationException('A job id or a response containing job_id is required.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function statusOf(array $payload): ?string
    {
        $data = $payload['data'] ?? null;
        if (is_array($data) && isset($data['status']) && is_string($data['status'])) {
            return $data['status'];
        }
        $status = $payload['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function failureMessage(array $payload): string
    {
        $data = $payload['data'] ?? null;
        if (is_array($data)) {
            $result = $data['result'] ?? null;
            if (is_array($result) && isset($result['error']) && is_string($result['error']) && $result['error'] !== '') {
                return $result['error'];
            }
            if (isset($data['output']) && is_string($data['output']) && trim($data['output']) !== '') {
                $output = trim($data['output']);
                if (strlen($output) > 500) {
                    $output = substr($output, -500);
                }

                return $output;
            }
        }

        return 'Cipi job failed.';
    }
}
