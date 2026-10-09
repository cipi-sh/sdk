<?php

declare(strict_types=1);

namespace Cipi\Sdk\Tests;

use Cipi\Sdk\Cipi;
use Cipi\Sdk\Exception\AuthenticationException;
use Cipi\Sdk\Exception\AuthorizationException;
use Cipi\Sdk\Exception\ConfigurationException;
use Cipi\Sdk\Exception\ConflictException;
use Cipi\Sdk\Exception\NotFoundException;
use Cipi\Sdk\Exception\RateLimitException;
use Cipi\Sdk\Exception\ServerException;
use Cipi\Sdk\Exception\ValidationException;
use Cipi\Sdk\Tests\Support\FakeTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function test_it_sends_a_bearer_token_over_https_and_keeps_it_out_of_the_url(): void
    {
        $transport = new FakeTransport;
        $cipi = $this->client($transport);

        $cipi->server()->status();

        $call = $transport->calls[0];
        $this->assertSame('GET', $call['method']);
        $this->assertSame('https://panel.test/api/status', $call['url']);
        $this->assertContains('Authorization: Bearer secret-token', $call['headers']);
        $this->assertStringNotContainsString('secret-token', $call['url']);
        $this->assertFalse($call['allow_http']);
    }

    public function test_it_accepts_a_base_url_that_already_includes_api(): void
    {
        $transport = new FakeTransport;
        $cipi = Cipi::connect('https://panel.test/api/', 'secret-token', [
            'transport' => $transport,
            'sleeper' => static function (): void {},
        ]);

        $cipi->apps()->list();

        $this->assertSame('https://panel.test/api/apps', $transport->calls[0]['url']);
    }

    public function test_it_refuses_plaintext_http_unless_explicitly_allowed(): void
    {
        $this->expectException(ConfigurationException::class);
        Cipi::connect('http://panel.test', 'secret-token');
    }

    public function test_it_allows_http_for_a_local_panel_when_requested(): void
    {
        $transport = new FakeTransport;
        $cipi = Cipi::connect('http://127.0.0.1:8080', 'secret-token', [
            'allow_http' => true,
            'transport' => $transport,
            'sleeper' => static function (): void {},
        ]);

        $cipi->server()->status();

        $this->assertSame('http://127.0.0.1:8080/api/status', $transport->calls[0]['url']);
        $this->assertTrue($transport->calls[0]['allow_http']);
    }

    public function test_it_rejects_a_token_that_could_break_the_authorization_header(): void
    {
        $this->expectException(ConfigurationException::class);
        Cipi::connect('https://panel.test', "secret\r\nX-Injected: yes");
    }

    public function test_it_rejects_credentials_and_query_strings_in_the_base_url(): void
    {
        $this->expectException(ConfigurationException::class);
        Cipi::connect('https://user:secret-token@panel.test/api?token=secret-token', 'secret-token');
    }

    public function test_it_redacts_the_token_from_errors_and_debug_output(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 500,
            'body' => '{"error":"failed for secret-token"}',
            'headers' => [],
        ];
        $cipi = $this->client($transport);

        try {
            $cipi->server()->status();
            $this->fail('Expected a server exception.');
        } catch (ServerException $exception) {
            $this->assertStringNotContainsString('secret-token', $exception->getMessage());
            $this->assertStringContainsString('[redacted]', $exception->getMessage());
        }

        $debug = print_r($cipi, true);
        $this->assertStringNotContainsString('secret-token', $debug);
    }

    public function test_it_maps_http_errors(): void
    {
        $this->assertError(401, AuthenticationException::class);
        $this->assertError(403, AuthorizationException::class);
        $this->assertError(404, NotFoundException::class);
        $this->assertError(409, ConflictException::class);
        $this->assertError(422, ValidationException::class);
        $this->assertError(500, ServerException::class);
    }

    public function test_validation_errors_stay_on_the_exception(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 422,
            'body' => '{"message":"The name field is required.","errors":{"name":["The name field is required."]}}',
            'headers' => [],
        ];

        try {
            $this->client($transport)->databases()->create('bad name');
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['name' => ['The name field is required.']], $exception->errors());
        }
    }

    public function test_safe_reads_retry_a_short_rate_limit_and_writes_do_not(): void
    {
        $transport = new FakeTransport;
        $slept = [];
        $transport->queue = [
            ['status' => 429, 'body' => '{"error":"slow down"}', 'headers' => ['retry-after' => '0']],
            ['status' => 200, 'body' => '{"data":[]}', 'headers' => []],
        ];
        $cipi = $this->client($transport, static function (float $seconds) use (&$slept): void {
            $slept[] = $seconds;
        });

        $this->assertSame(['data' => []], $cipi->apps()->list());
        $this->assertCount(2, $transport->calls);
        $this->assertSame([0.0], $slept);

        $transport->calls = [];
        $transport->queue = [
            ['status' => 429, 'body' => '{"error":"slow down"}', 'headers' => ['retry-after' => '1']],
        ];

        try {
            $cipi->apps()->delete('shop');
            $this->fail('Expected a rate limit exception.');
        } catch (RateLimitException $exception) {
            $this->assertSame(1, $exception->retryAfter);
        }
        $this->assertCount(1, $transport->calls);
    }

    public function test_it_refuses_to_send_the_token_to_an_absolute_path(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->client(new FakeTransport)->request('GET', 'https://evil.test/steal');
    }

    #[DataProvider('endpoints')]
    public function test_resources_call_the_panel_routes(callable $call, string $method, string $url, ?string $body): void
    {
        $transport = new FakeTransport;
        $cipi = $this->client($transport);

        $call($cipi);

        $this->assertSame($method, $transport->calls[0]['method']);
        $this->assertSame($url, $transport->calls[0]['url']);
        $this->assertSame($body, $transport->calls[0]['body']);
    }

    /**
     * @return array<string, array{0: callable, 1: string, 2: string, 3: ?string}>
     */
    public static function endpoints(): array
    {
        $base = 'https://panel.test/api';

        return [
            'apps list' => [fn (Cipi $c) => $c->apps()->list(), 'GET', $base.'/apps', null],
            'apps create' => [fn (Cipi $c) => $c->apps()->create(['user' => 'shop', 'domain' => 'shop.test']), 'POST', $base.'/apps', '{"user":"shop","domain":"shop.test"}'],
            'apps webhook' => [fn (Cipi $c) => $c->apps()->recreateWebhook('shop', true), 'POST', $base.'/apps/shop/webhook/recreate', '{"rotate_secret":true}'],
            'limits get' => [fn (Cipi $c) => $c->limits()->get('shop'), 'GET', $base.'/apps/shop/limits', null],
            'limits update' => [fn (Cipi $c) => $c->limits()->update('shop', ['fpm_max_children' => 10, 'disk_limit_gb' => null]), 'PUT', $base.'/apps/shop/limits', '{"fpm_max_children":10,"disk_limit_gb":null}'],
            'env merge' => [fn (Cipi $c) => $c->env()->update('shop', ['APP_ENV' => 'production'], ['DEBUG']), 'PUT', $base.'/apps/shop/env', '{"set":{"APP_ENV":"production"},"unset":["DEBUG"]}'],
            'env empty set' => [fn (Cipi $c) => $c->env()->update('shop'), 'PUT', $base.'/apps/shop/env', '{"set":{},"unset":[]}'],
            'auth replace' => [fn (Cipi $c) => $c->authJson()->replace('shop', ['http-basic' => ['example.com' => ['user' => 'a']]]), 'PUT', $base.'/apps/shop/auth', '{"http-basic":{"example.com":{"user":"a"}}}'],
            'logs' => [fn (Cipi $c) => $c->logs()->get('shop', 'laravel', 2, 50), 'GET', $base.'/apps/shop/logs?type=laravel&page=2&per_page=50', null],
            'alias' => [fn (Cipi $c) => $c->aliases()->add('shop', 'www.shop.test'), 'POST', $base.'/apps/shop/aliases/www.shop.test', null],
            'redirect remove' => [fn (Cipi $c) => $c->redirects()->remove('shop', '/blog/'), 'DELETE', $base.'/apps/shop/redirects', '{"from":"/blog/"}'],
            'proxy add' => [fn (Cipi $c) => $c->proxies()->add('shop', '/api/', 'http://127.0.0.1:3000', true), 'POST', $base.'/apps/shop/proxies', '{"prefix":"/api/","upstream":"http://127.0.0.1:3000","strip_prefix":true}'],
            'deploy audit' => [fn (Cipi $c) => $c->deploys()->audit('shop', 7), 'GET', $base.'/apps/shop/deploy/audit?days=7', null],
            'db create' => [fn (Cipi $c) => $c->databases()->create('shop', 'pgsql'), 'POST', $base.'/dbs', '{"name":"shop","engine":"pgsql"}'],
            'db backup' => [fn (Cipi $c) => $c->databases()->backup('shop'), 'POST', $base.'/dbs/shop/backup', null],
            'ssh remove' => [fn (Cipi $c) => $c->ssh()->remove(3), 'DELETE', $base.'/ssh/keys/3', null],
            'service restart' => [fn (Cipi $c) => $c->services()->restart('php8.3-fpm'), 'POST', $base.'/services/php8.3-fpm/restart', null],
            'ip replace' => [fn (Cipi $c) => $c->ipWhitelist()->replace(['203.0.113.4'], true), 'PUT', $base.'/ip-whitelist', '{"entries":["203.0.113.4"],"ensure_client_ip":true}'],
            'node runtimes' => [fn (Cipi $c) => $c->node()->runtimes(), 'GET', $base.'/node', null],
            'search' => [fn (Cipi $c) => $c->search()->enable('shop'), 'POST', $base.'/apps/shop/search/enable', null],
            'ssl install' => [fn (Cipi $c) => $c->ssl()->install('shop'), 'POST', $base.'/apps/shop/ssl', null],
            'ssl install dns' => [fn (Cipi $c) => $c->ssl()->install('shop', dns: 'cloudflare', account: 'prod', wildcard: false), 'POST', $base.'/apps/shop/ssl', '{"dns":"cloudflare","account":"prod","wildcard":false}'],
            'ssl dns list' => [fn (Cipi $c) => $c->ssl()->dnsAccounts(), 'GET', $base.'/ssl/dns', null],
            'ssl dns set' => [fn (Cipi $c) => $c->ssl()->setDnsAccount('cf-token-value-xxxxxxxx', 'prod'), 'PUT', $base.'/ssl/dns', '{"name":"prod","token":"cf-token-value-xxxxxxxx"}'],
            'ssl dns remove' => [fn (Cipi $c) => $c->ssl()->removeDnsAccount('prod'), 'DELETE', $base.'/ssl/dns/prod', null],
            'packages' => [fn (Cipi $c) => $c->packages()->list(), 'GET', $base.'/packages', null],
            'monitor' => [fn (Cipi $c) => $c->monitor()->list(), 'GET', $base.'/monitor', null],
            'disk usage' => [fn (Cipi $c) => $c->disk()->usage(), 'GET', $base.'/disk', null],
            'disk databases' => [fn (Cipi $c) => $c->disk()->databases(), 'GET', $base.'/disk/dbs', null],
            'zero trust' => [fn (Cipi $c) => $c->zeroTrust()->status(), 'GET', $base.'/zt', null],
            'raw request' => [fn (Cipi $c) => $c->request('GET', '/apps/shop'), 'GET', $base.'/apps/shop', null],
        ];
    }

    private function assertError(int $status, string $class): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => $status,
            'body' => '{"error":"nope"}',
            'headers' => [],
        ];

        try {
            $this->client($transport)->server()->status();
            $this->fail('Expected '.$class);
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($class, $exception);
            $this->assertSame($status, $exception->getCode());
        }
    }

    /**
     * @param  (callable(float): void)|null  $sleeper
     */
    private function client(FakeTransport $transport, ?callable $sleeper = null): Cipi
    {
        return Cipi::connect('https://panel.test', 'secret-token', [
            'transport' => $transport,
            'sleeper' => $sleeper ?? static function (): void {},
        ]);
    }
}
