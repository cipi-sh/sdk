<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

use Cipi\Sdk\Exception\ConfigurationException;
use Cipi\Sdk\Exception\JobFailedException;
use Cipi\Sdk\Exception\TimeoutException;

final class Jobs extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->get('/jobs/'.$this->client->segment($id));
    }

    /**
     * Poll until the job is completed or failed.
     * A failed job throws JobFailedException unless $throwOnFailure is false.
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
