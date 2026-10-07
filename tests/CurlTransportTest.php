<?php

declare(strict_types=1);

namespace Cipi\Sdk\Tests;

use Cipi\Sdk\Cipi;
use Cipi\Sdk\Exception\CipiException;
use PHPUnit\Framework\TestCase;

final class CurlTransportTest extends TestCase
{
    private mixed $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $baseUrl = '';

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($this->process);
        }
    }

    public function test_curl_verifies_the_bearer_token_and_does_not_follow_redirects(): void
    {
        $this->boot();

        $cipi = Cipi::connect($this->baseUrl, 'test-token', ['allow_http' => true]);
        $status = $cipi->server()->status();
        $this->assertTrue($status['data']['ok']);

        try {
            $cipi->apps()->list();
            $this->fail('A redirect must not be followed.');
        } catch (CipiException $exception) {
            $this->assertSame(302, $exception->getCode());
            $this->assertArrayNotHasKey('followed', $exception->body);
        }
    }

    private function boot(): void
    {
        $command = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:0',
            __DIR__.'/fixtures/server.php',
        ];
        $this->process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $this->pipes);
        $this->assertIsResource($this->process);

        stream_set_blocking($this->pipes[2], false);
        $stderr = '';
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $stderr .= (string) stream_get_contents($this->pipes[2]);
            if (preg_match('/Development Server \(http:\/\/(127\.0\.0\.1:\d+)\)/', $stderr, $match) === 1) {
                $this->baseUrl = 'http://'.$match[1];

                return;
            }
            usleep(50_000);
        }

        $this->fail('The local API fixture did not start: '.$stderr);
    }
}
