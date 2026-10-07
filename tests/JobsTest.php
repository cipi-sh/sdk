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
