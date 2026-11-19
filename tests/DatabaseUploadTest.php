<?php

declare(strict_types=1);

namespace Cipi\Sdk\Tests;

use Cipi\Sdk\Cipi;
use Cipi\Sdk\Client;
use Cipi\Sdk\Exception\CipiException;
use Cipi\Sdk\Exception\ConfigurationException;
use Cipi\Sdk\Exception\ConflictException;
use Cipi\Sdk\Exception\ValidationException;
use Cipi\Sdk\Options;
use Cipi\Sdk\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

final class DatabaseUploadTest extends TestCase
{
    private const UPLOAD = '0b9d6c1e-58f3-4a52-9c0e-3f6a5a1d2e44';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function test_a_chunk_is_sent_as_multipart_with_its_bytes_untouched(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'received' => 9, 'size' => 9, 'next' => 1, 'complete' => true]]);
        // Bytes a text encoding or a careless boundary would break.
        $bytes = "\x1f\x8b\x00\r\n--x\xff\x00";

        $answer = $this->client($transport)->databases()->uploadChunk(self::UPLOAD, 0, $bytes);

        $this->assertTrue($answer['data']['complete']);
        $call = $transport->calls[0];
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://panel.test/api/dbs/uploads/'.self::UPLOAD.'/chunks', $call['url']);
        $this->assertContains('Authorization: Bearer secret-token', $call['headers']);
        $this->assertNotContains('Content-Type: application/json', $call['headers']);

        $boundary = $this->boundary($call['headers']);
        $this->assertSame(
            '--'.$boundary."\r\n"
            .'Content-Disposition: form-data; name="index"'."\r\n\r\n"
            ."0\r\n"
            .'--'.$boundary."\r\n"
            .'Content-Disposition: form-data; name="chunk"; filename="chunk"'."\r\n"
            .'Content-Type: application/octet-stream'."\r\n\r\n"
            .$bytes."\r\n"
            .'--'.$boundary."--\r\n",
            $call['body'],
        );
    }

    public function test_every_request_gets_its_own_boundary(): void
    {
        $transport = new FakeTransport;
        $databases = $this->client($transport)->databases();

        $databases->uploadChunk(self::UPLOAD, 0, 'a');
        $databases->uploadChunk(self::UPLOAD, 1, 'b');

        $this->assertNotSame($this->boundary($transport->calls[0]['headers']), $this->boundary($transport->calls[1]['headers']));
    }

    public function test_upload_sends_a_local_dump_in_the_chunks_the_panel_asks_for(): void
    {
        $path = $this->dump('0123456789');
        $transport = new FakeTransport;
        $transport->queue = [
            $this->json(201, ['data' => ['upload_id' => self::UPLOAD, 'size' => 10, 'chunk_size' => 4, 'chunks' => 3, 'max_size' => 1000]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'received' => 4, 'size' => 10, 'next' => 1, 'complete' => false]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'received' => 8, 'size' => 10, 'next' => 2, 'complete' => false]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'received' => 10, 'size' => 10, 'next' => 3, 'complete' => true]]),
        ];

        $answer = $this->client($transport)->databases()->upload($path, 'shop.sql');

        $this->assertSame(self::UPLOAD, $answer['data']['upload_id']);
        $this->assertTrue($answer['data']['complete']);
        $this->assertCount(4, $transport->calls);
        $this->assertSame('https://panel.test/api/dbs/uploads', $transport->calls[0]['url']);
        $this->assertSame('{"filename":"shop.sql","size":10}', $transport->calls[0]['body']);
        $this->assertSame([[0, '0123'], [1, '4567'], [2, '89']], [
            $this->chunk($transport->calls[1]),
            $this->chunk($transport->calls[2]),
            $this->chunk($transport->calls[3]),
        ]);
    }

    public function test_upload_names_the_dump_after_the_file_by_default(): void
    {
        $path = $this->dump('select 1;', '.sql.gz');
        $transport = new FakeTransport;
        $transport->queue = [
            $this->json(201, ['data' => ['upload_id' => self::UPLOAD, 'size' => 9, 'chunk_size' => 1024]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'complete' => true]]),
        ];

        $this->client($transport)->databases()->upload($path);

        $this->assertSame(json_encode(['filename' => basename($path), 'size' => 9]), $transport->calls[0]['body']);
    }

    public function test_restore_local_uploads_the_dump_and_queues_the_restore(): void
    {
        $path = $this->dump('abcdef');
        $transport = new FakeTransport;
        $transport->queue = [
            $this->json(201, ['data' => ['upload_id' => self::UPLOAD, 'size' => 6, 'chunk_size' => 6]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'complete' => true]]),
            $this->json(202, ['job_id' => 'job-9', 'status' => 'pending']),
        ];

        $job = $this->client($transport)->databases()->restoreLocal('shop', $path, 'pgsql');

        $this->assertSame('job-9', $job['job_id']);
        $this->assertSame('https://panel.test/api/dbs/shop/restore', $transport->calls[2]['url']);
        $this->assertSame('{"upload_id":"'.self::UPLOAD.'","engine":"pgsql"}', $transport->calls[2]['body']);
    }

    public function test_a_restore_the_panel_refuses_does_not_leave_the_dump_behind(): void
    {
        $path = $this->dump('abcdef');
        $transport = new FakeTransport;
        $transport->queue = [
            $this->json(201, ['data' => ['upload_id' => self::UPLOAD, 'size' => 6, 'chunk_size' => 6]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'complete' => true]]),
            $this->json(422, ['error' => 'The file is named .sql.gz but is not gzip-compressed']),
        ];

        try {
            $this->client($transport)->databases()->restoreLocal('shop', $path);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('not gzip-compressed', $exception->getMessage());
        }
        $this->assertSame('DELETE', $transport->calls[3]['method']);
        $this->assertSame('https://panel.test/api/dbs/uploads/'.self::UPLOAD, $transport->calls[3]['url']);
    }

    public function test_a_chunk_the_panel_refuses_cancels_the_upload_and_is_not_retried(): void
    {
        $path = $this->dump('0123456789');
        $transport = new FakeTransport;
        $transport->queue = [
            $this->json(201, ['data' => ['upload_id' => self::UPLOAD, 'size' => 10, 'chunk_size' => 4]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'complete' => false]]),
            $this->json(409, ['error' => 'Chunk 1 is out of order: the next one is 0']),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'cancelled' => true]]),
        ];

        try {
            $this->client($transport)->databases()->upload($path);
            $this->fail('Expected a conflict exception.');
        } catch (ConflictException $exception) {
            $this->assertSame('Chunk 1 is out of order: the next one is 0', $exception->getMessage());
        }

        $this->assertCount(4, $transport->calls);
        $this->assertSame('DELETE', $transport->calls[3]['method']);
        $this->assertSame('https://panel.test/api/dbs/uploads/'.self::UPLOAD, $transport->calls[3]['url']);
    }

    public function test_a_failing_cancel_does_not_hide_the_error_that_caused_it(): void
    {
        $path = $this->dump('0123456789');
        $transport = new FakeTransport;
        $transport->queue = [
            $this->json(201, ['data' => ['upload_id' => self::UPLOAD, 'size' => 10, 'chunk_size' => 4]]),
            $this->json(422, ['error' => 'More data than the size declared when the upload started']),
            $this->json(500, ['error' => 'cancel failed']),
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('More data than the size declared');
        $this->client($transport)->databases()->upload($path);
    }

    public function test_an_upload_the_panel_does_not_call_complete_is_an_error(): void
    {
        $path = $this->dump('abcdef');
        $transport = new FakeTransport;
        $transport->queue = [
            $this->json(201, ['data' => ['upload_id' => self::UPLOAD, 'size' => 9, 'chunk_size' => 6]]),
            $this->json(200, ['data' => ['upload_id' => self::UPLOAD, 'received' => 6, 'size' => 9, 'next' => 1, 'complete' => false]]),
        ];

        try {
            $this->client($transport)->databases()->upload($path);
            $this->fail('Expected an exception.');
        } catch (CipiException $exception) {
            $this->assertStringContainsString('before the panel had the whole dump', $exception->getMessage());
        }
        $this->assertSame('DELETE', $transport->calls[2]['method']);
    }

    public function test_a_start_answer_without_an_upload_id_stops_before_any_chunk(): void
    {
        $path = $this->dump('abcdef');
        $transport = new FakeTransport;

        try {
            $this->client($transport)->databases()->upload($path);
            $this->fail('Expected an exception.');
        } catch (CipiException $exception) {
            $this->assertStringContainsString('upload id', $exception->getMessage());
        }
        $this->assertCount(1, $transport->calls);
    }

    public function test_a_missing_or_empty_dump_is_refused_before_any_request(): void
    {
        $transport = new FakeTransport;
        $databases = $this->client($transport)->databases();

        foreach ([sys_get_temp_dir().'/cipi-sdk-no-such-dump.sql', $this->dump('')] as $path) {
            try {
                $databases->upload($path);
                $this->fail('Expected a configuration exception.');
            } catch (ConfigurationException) {
            }
        }
        $this->assertSame([], $transport->calls);
    }

    public function test_multipart_names_cannot_break_out_of_their_header(): void
    {
        $transport = new FakeTransport;
        $client = new Client('https://panel.test', 'secret-token', new Options(transport: $transport));

        foreach ([
            [['in"dex' => 0], []],
            [["index\r\nX-Injected: 1" => 0], []],
            [[], ['chunk' => ['contents' => 'a', 'filename' => "a\"\r\nb"]]],
            [[], ['chunk' => ['contents' => 'a', 'filename' => '../etc/passwd']]],
        ] as [$fields, $files]) {
            try {
                $client->postMultipart('/dbs/uploads/'.self::UPLOAD.'/chunks', $fields, $files);
                $this->fail('Expected a configuration exception.');
            } catch (ConfigurationException) {
            }
        }
        $this->assertSame([], $transport->calls);
    }

    private function dump(string $contents, string $extension = '.sql'): string
    {
        $path = sys_get_temp_dir().'/cipi-sdk-test-'.bin2hex(random_bytes(6)).$extension;
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    /**
     * @param  list<string>  $headers
     */
    private function boundary(array $headers): string
    {
        foreach ($headers as $header) {
            if (preg_match('#^Content-Type: multipart/form-data; boundary=(cipi-sdk-[0-9a-f]{32})$#', $header, $match) === 1) {
                return $match[1];
            }
        }
        $this->fail('The request has no multipart Content-Type: '.implode(' | ', $headers));
    }

    /**
     * The index and the bytes of a recorded chunk request.
     *
     * @param  array{headers: list<string>, body: ?string}  $call
     * @return array{0: int, 1: string}
     */
    private function chunk(array $call): array
    {
        $boundary = preg_quote($this->boundary($call['headers']), '#');
        $matched = preg_match(
            '#^--'.$boundary.'\r\nContent-Disposition: form-data; name="index"\r\n\r\n(\d+)\r\n--'.$boundary
            .'\r\nContent-Disposition: form-data; name="chunk"; filename="chunk"\r\nContent-Type: application/octet-stream\r\n\r\n(.*)\r\n--'
            .$boundary.'--\r\n$#s',
            (string) $call['body'],
            $match,
        );
        $this->assertSame(1, $matched, 'Not the multipart body of a chunk.');

        return [(int) $match[1], $match[2]];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private function json(int $status, array $body): array
    {
        return ['status' => $status, 'body' => (string) json_encode($body), 'headers' => []];
    }

    private function client(FakeTransport $transport): Cipi
    {
        return Cipi::connect('https://panel.test', 'secret-token', [
            'transport' => $transport,
            'sleeper' => static function (): void {},
        ]);
    }
}
