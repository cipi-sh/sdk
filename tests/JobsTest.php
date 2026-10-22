<?php

declare(strict_types=1);

namespace Cipi\Sdk\Tests;

use Cipi\Sdk\Cipi;
use Cipi\Sdk\Exception\ConfigurationException;
use Cipi\Sdk\Exception\JobFailedException;
use Cipi\Sdk\Exception\TimeoutException;
use Cipi\Sdk\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase
{
    public function test_it_waits_until_a_job_completes(): void
    {
        $transport = new FakeTransport;
        $transport->queue = [
            ['status' => 200, 'body' => '{"data":{"id":"job-1","status":"running"}}', 'headers' => []],
            ['status' => 200, 'body' => '{"data":{"id":"job-1","status":"completed","exit_code":0}}', 'headers' => []],
        ];
        $cipi = $this->client($transport);

        $result = $cipi->wait(['job_id' => 'job-1'], 5, 0.1);

        $this->assertSame('completed', $result['data']['status']);
        $this->assertCount(2, $transport->calls);
        $this->assertSame('https://panel.test/api/jobs/job-1', $transport->calls[0]['url']);
    }

    public function test_an_app_create_result_reports_repository_access_and_build(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 200,
            'body' => json_encode(['data' => [
                'id' => 'job-1',
                'type' => 'app-create',
                'status' => 'completed',
                'result' => [
                    'app' => 'shop',
                    'build' => 'npm ci && npm run build',
                    'repository_access' => [
                        'status' => 'denied',
                        'error' => 'ERROR: Repository not found.',
                        'hint' => 'cipi git github-token <token> && cipi git refresh shop',
                    ],
                ],
            ]], JSON_THROW_ON_ERROR),
            'headers' => [],
        ];

        // A denied repository does not fail the job: wait() returns it.
        $job = $this->client($transport)->wait('job-1', 2, 0.1);
        $result = $job['data']['result'];

        $this->assertSame('npm ci && npm run build', $result['build']);
        $this->assertSame([
            'status' => 'denied',
            'error' => 'ERROR: Repository not found.',
            'hint' => 'cipi git github-token <token> && cipi git refresh shop',
        ], $result['repository_access']);
    }

    public function test_an_app_edit_result_reports_repository_access(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"id":"job-2","type":"app-edit","status":"completed","result":{"changes":{"branch":"develop"},"repository_access":{"status":"nobranch","branch":"develop"}}}}',
            'headers' => [],
        ];

        $result = $this->client($transport)->jobs()->get('job-2')['data']['result'];

        $this->assertSame(['status' => 'nobranch', 'branch' => 'develop'], $result['repository_access']);
    }

    public function test_a_result_from_an_older_server_has_no_repository_access(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"id":"job-3","type":"app-create","status":"completed","result":{"app":"shop","deploy_key":"ssh-ed25519 AAAA"}}}',
            'headers' => [],
        ];

        $result = $this->client($transport)->wait('job-3', 2, 0.1)['data']['result'];

        $this->assertSame('shop', $result['app']);
        $this->assertArrayNotHasKey('repository_access', $result);
        $this->assertArrayNotHasKey('build', $result);
        $this->assertNull($result['repository_access'] ?? null);
    }

    public function test_a_yml_apply_job_reports_what_it_applied(): void
    {
        $transport = new FakeTransport;
        $transport->queue = [
            ['status' => 202, 'body' => '{"job_id":"job-4","status":"pending"}', 'headers' => []],
            ['status' => 200, 'body' => '{"data":{"id":"job-4","type":"app-yml-apply","status":"completed","exit_code":0,"result":{"app":"shop","outcome":"applied","applied":3,"failed":0,"failures":[],"blockers":[],"errors":[]}}}', 'headers' => []],
        ];
        $cipi = $this->client($transport);

        $result = $cipi->wait($cipi->yml()->apply('shop'), 2, 0.1)['data']['result'];

        $this->assertSame('https://panel.test/api/apps/shop/yml/apply', $transport->calls[0]['url']);
        $this->assertSame('https://panel.test/api/jobs/job-4', $transport->calls[1]['url']);
        $this->assertSame('applied', $result['outcome']);
        $this->assertSame(3, $result['applied']);
    }

    public function test_a_blocked_yml_apply_fails_but_keeps_its_result(): void
    {
        $body = '{"data":{"id":"job-5","type":"app-yml-apply","status":"failed","exit_code":1,"output":"blocked","result":{"app":"shop","outcome":"blocked","applied":0,"failed":0,"failures":[],"blockers":["PHP 8.5 is not installed — run: cipi php install 8.5"],"errors":[]}}}';

        $transport = new FakeTransport;
        $transport->queue[] = ['status' => 200, 'body' => $body, 'headers' => []];

        $result = $this->client($transport)->jobs()->wait('job-5', 2, 0.1, throwOnFailure: false)['data']['result'];

        $this->assertSame('blocked', $result['outcome']);
        $this->assertSame(['PHP 8.5 is not installed — run: cipi php install 8.5'], $result['blockers']);

        $transport->queue[] = ['status' => 200, 'body' => $body, 'headers' => []];

        try {
            $this->client($transport)->wait('job-5', 2, 0.1);
            $this->fail('Expected a failed job.');
        } catch (JobFailedException $exception) {
            $this->assertSame('blocked', $exception->job['data']['result']['outcome']);
        }
    }

    public function test_a_failed_job_raises(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"id":"job-1","status":"failed","result":{"error":"deploy locked"}}}',
            'headers' => [],
        ];

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('deploy locked');
        $this->client($transport)->jobs()->wait('job-1', 2, 0.1);
    }

    public function test_it_times_out_while_a_job_is_still_running(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"status":"pending"}}',
            'headers' => [],
        ];

        $this->expectException(TimeoutException::class);
        $this->client($transport)->jobs()->wait('job-1', 1, 0.4);
    }

    public function test_env_set_must_be_an_associative_map(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->client(new FakeTransport)->env()->update('shop', ['NOT_A_KEY']);
    }

    private function client(FakeTransport $transport): Cipi
    {
        return Cipi::connect('https://panel.test', 'secret-token', [
            'transport' => $transport,
            'sleeper' => static function (): void {},
        ]);
    }
}
