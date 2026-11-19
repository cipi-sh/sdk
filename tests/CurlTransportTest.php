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

    public function test_a_json_body_reaches_the_server_on_every_write_method(): void
    {
        $this->boot();
        $cipi = Cipi::connect($this->baseUrl, 'test-token', ['allow_http' => true]);

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $answer = $cipi->request($method, '/echo', ['name' => 'shop', 'nested' => ['a' => 1]])['data'];

            $this->assertSame($method, $answer['method']);
            $this->assertSame('application/json', $answer['content_type']);
            $this->assertSame('{"name":"shop","nested":{"a":1}}', $answer['body'], $method.' arrived without its body.');
        }

        $this->assertSame('', $cipi->request('POST', '/echo')['data']['body']);
    }

    public function test_a_dump_chunk_arrives_as_a_file_upload_with_the_same_bytes(): void
    {
        $this->boot();

        // Every byte value, line endings and something shaped like a boundary:
        // 256 KiB of what a gzip stream can contain.
        $bytes = str_repeat(implode('', array_map('chr', range(0, 255)))."\r\n--cipi-sdk-0000\r\n", 1024);
        $bytes = substr($bytes, 0, 262144);

        $cipi = Cipi::connect($this->baseUrl, 'test-token', ['allow_http' => true]);
        $answer = $cipi->databases()->uploadChunk('0b9d6c1e-58f3-4a52-9c0e-3f6a5a1d2e44', 3, $bytes)['data'];

        $this->assertSame('3', $answer['index']);
        $this->assertSame(UPLOAD_ERR_OK, $answer['error']);
        $this->assertSame('chunk', $answer['filename']);
        $this->assertSame(262144, $answer['size']);
        $this->assertSame(sha1($bytes), $answer['sha1']);
        $this->assertStringStartsWith('multipart/form-data; boundary=cipi-sdk-', $answer['content_type']);
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
