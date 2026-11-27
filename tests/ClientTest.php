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

    public function test_yml_status_is_refused_without_the_ability_or_on_an_older_cipi(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 403,
            'body' => '{"message":"Invalid ability provided."}',
            'headers' => [],
        ];

        try {
            $this->client($transport)->yml()->status('shop');
            $this->fail('Expected an authorization exception.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->getCode());
        }
        $this->assertCount(1, $transport->calls);

        // Cipi < 5.6.4 has no `cipi yml plan --json`: the panel answers 503.
        $transport = new FakeTransport;
        $transport->queue = array_fill(0, 3, [
            'status' => 503,
            'body' => '{"error":"cipi yml plan failed"}',
            'headers' => [],
        ]);

        try {
            $this->client($transport)->yml()->status('shop');
            $this->fail('Expected a server exception.');
        } catch (ServerException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertStringContainsString('cipi yml plan failed', $exception->getMessage());
        }
    }

    public function test_yml_status_returns_the_plan_as_sent(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"app":"shop","file":"/home/shop/current/cipi-staging.yml","env":"staging","auto":true,"valid":true,"in_sync":false,"errors":[],"warnings":[],"changes":[{"kind":"php","summary":"PHP 8.3 → 8.4"}],"blockers":["PHP 8.5 is not installed — run: cipi php install 8.5"],"notes":[]}}',
            'headers' => [],
        ];

        $data = $this->client($transport)->yml()->status('shop')['data'];

        // A staging app reads cipi-staging.yml first; env says which label the file was chosen for.
        $this->assertSame('staging', $data['env']);
        $this->assertSame('/home/shop/current/cipi-staging.yml', $data['file']);
        $this->assertFalse($data['in_sync']);
        $this->assertSame([['kind' => 'php', 'summary' => 'PHP 8.3 → 8.4']], $data['changes']);
        $this->assertSame(['PHP 8.5 is not installed — run: cipi php install 8.5'], $data['blockers']);

        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"app":"shop","file":null,"auto":false,"valid":null,"in_sync":null,"errors":[],"warnings":[],"changes":[],"blockers":[],"notes":[]}}',
            'headers' => [],
        ];

        $data = $this->client($transport)->yml()->status('shop')['data'];

        $this->assertNull($data['file']);
        $this->assertNull($data['valid']);
        $this->assertNull($data['in_sync']);
        // cipi/api < 1.36.0 sends no env: the app is production and the file is cipi.yml.
        $this->assertArrayNotHasKey('env', $data);
        $this->assertSame('production', $data['env'] ?? 'production');
    }

    public function test_apps_carry_their_environment_label_and_older_panels_omit_it(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":[{"app":"shop","domain":"staging.shop.test","php":"8.4","branch":"develop","repository":"git@github.com:acme/shop.git","env":"staging"}]}',
            'headers' => [],
        ];
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"app":"shop","domain":"staging.shop.test","php":"8.4","branch":"develop","repository":"git@github.com:acme/shop.git","env":"staging","suspended":false}}',
            'headers' => [],
        ];
        $cipi = $this->client($transport);

        $this->assertSame('staging', $cipi->apps()->list()['data'][0]['env']);
        $this->assertSame('staging', $cipi->apps()->get('shop')['data']['env']);

        // cipi/api < 1.36.0 has no environment labels: the key is absent and the app is production.
        $transport->queue[] = [
            'status' => 200,
            'body' => '{"data":{"app":"shop","domain":"shop.test","php":"8.4","branch":"main","repository":""}}',
            'headers' => [],
        ];

        $app = $cipi->apps()->get('shop')['data'];

        $this->assertArrayNotHasKey('env', $app);
        $this->assertSame('production', $app['env'] ?? 'production');
    }

    public function test_zero_trust_reads_the_mesh_network_next_to_the_tunnel(): void
    {
        $transport = new FakeTransport;
        $cipi = $this->client($transport);

        // A server on a tailnet with no Cloudflare tunnel: `enabled` is Cloudflare's alone.
        $body = '{"data":{"enabled":false,"tunnel":null,"mesh":"tailscale","mesh_state":"connected","mesh_ip":"100.101.102.103","raw":"",'
            .'"details":{"enabled":false,"mesh":{"enabled":true,"provider":"tailscale","connected":true,"ip":"100.101.102.103",'
            .'"key_expiry":null,"peers":{"total":3,"online":2},"lock":{"ssh":true,"http":false},"warnings":[]}}}}';
        $transport->queue[] = ['status' => 200, 'body' => $body, 'headers' => []];
        $transport->queue[] = ['status' => 200, 'body' => $body, 'headers' => []];

        $status = $cipi->zeroTrust()->status()['data'];
        $this->assertFalse($status['enabled']);
        $this->assertSame('tailscale', $status['mesh']);
        $this->assertSame('100.101.102.103', $status['mesh_ip']);

        $mesh = $cipi->zeroTrust()->mesh();
        $this->assertSame('tailscale', $mesh['provider']);
        $this->assertSame(['ssh' => true, 'http' => false], $mesh['lock']);
        $this->assertSame(2, $mesh['peers']['online']);
        $this->assertSame('GET', $transport->calls[1]['method']);
        $this->assertStringEndsWith('/zt', $transport->calls[1]['url']);

        // No mesh on, a server that says nothing of one, and an older panel with no details at all.
        foreach ([
            '{"data":{"enabled":true,"mesh":null,"details":{"enabled":true,"mesh":{"enabled":false,"provider":null,"active":null}}}}',
            '{"data":{"enabled":true,"details":{"enabled":true,"tunnel":{"name":"t"}}}}',
            '{"data":{"enabled":true,"raw":"Cloudflare Zero Trust","details":null}}',
        ] as $older) {
            $transport->queue[] = ['status' => 200, 'body' => $older, 'headers' => []];
            $this->assertNull($cipi->zeroTrust()->mesh());
        }
    }

    public function test_a_bad_environment_label_is_a_validation_error(): void
    {
        $transport = new FakeTransport;
        $transport->queue[] = [
            'status' => 422,
            'body' => '{"message":"The env field format is invalid.","errors":{"env":["The env field format is invalid."]}}',
            'headers' => [],
        ];

        try {
            $this->client($transport)->apps()->update('shop', ['env' => 'Staging!']);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['env' => ['The env field format is invalid.']], $exception->errors());
        }
        // The SDK does not pre-validate the label: the panel owns the rule.
        $this->assertSame('{"env":"Staging!"}', $transport->calls[0]['body']);
        $this->assertCount(1, $transport->calls);
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

    public function test_an_env_order_lists_each_key_once(): void
    {
        $transport = new FakeTransport;
        $cipi = $this->client($transport);

        foreach ([[], ['APP_NAME', 'APP_NAME'], ['a' => 'APP_NAME']] as $order) {
            try {
                $cipi->env()->reorder('shop', $order);
                $this->fail('refused: '.json_encode($order));
            } catch (ConfigurationException) {
                // nothing sent
            }
        }
        $this->assertSame([], $transport->calls);
    }

    public function test_it_refuses_to_send_the_token_to_an_absolute_path(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->client(new FakeTransport)->request('GET', 'https://evil.test/steal');
    }

    public function test_a_package_id_is_one_path_segment(): void
    {
        $transport = new FakeTransport;

        try {
            $this->client($transport)->packages()->install('../apps/shop');
            $this->fail('Expected a ConfigurationException');
        } catch (ConfigurationException) {
            $this->assertSame([], $transport->calls);
        }
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
            'apps create node_build' => [fn (Cipi $c) => $c->apps()->create(['user' => 'shop', 'domain' => 'shop.test', 'repository' => 'git@github.com:acme/shop.git', 'node_build' => 'npm ci && npm run build']), 'POST', $base.'/apps', '{"user":"shop","domain":"shop.test","repository":"git@github.com:acme/shop.git","node_build":"npm ci && npm run build"}'],
            'apps create env' => [fn (Cipi $c) => $c->apps()->create(['user' => 'shop', 'domain' => 'staging.shop.test', 'branch' => 'develop', 'env' => 'staging']), 'POST', $base.'/apps', '{"user":"shop","domain":"staging.shop.test","branch":"develop","env":"staging"}'],
            'apps update' => [fn (Cipi $c) => $c->apps()->update('shop', ['php' => '8.4']), 'PUT', $base.'/apps/shop', '{"php":"8.4"}'],
            'apps update env' => [fn (Cipi $c) => $c->apps()->update('shop', ['env' => 'preview-42']), 'PUT', $base.'/apps/shop', '{"env":"preview-42"}'],
            'apps webhook' => [fn (Cipi $c) => $c->apps()->recreateWebhook('shop', true), 'POST', $base.'/apps/shop/webhook/recreate', '{"rotate_secret":true}'],
            'limits get' => [fn (Cipi $c) => $c->limits()->get('shop'), 'GET', $base.'/apps/shop/limits', null],
            'limits update' => [fn (Cipi $c) => $c->limits()->update('shop', ['fpm_max_children' => 10, 'disk_limit_gb' => null]), 'PUT', $base.'/apps/shop/limits', '{"fpm_max_children":10,"disk_limit_gb":null}'],
            'yml status' => [fn (Cipi $c) => $c->yml()->status('shop'), 'GET', $base.'/apps/shop/yml', null],
            'yml apply' => [fn (Cipi $c) => $c->yml()->apply('shop'), 'POST', $base.'/apps/shop/yml/apply', null],
            'yml auto enable' => [fn (Cipi $c) => $c->yml()->enableAuto('shop'), 'POST', $base.'/apps/shop/yml/auto/enable', null],
            'yml auto disable' => [fn (Cipi $c) => $c->yml()->disableAuto('shop'), 'POST', $base.'/apps/shop/yml/auto/disable', null],
            'env merge' => [fn (Cipi $c) => $c->env()->update('shop', ['APP_ENV' => 'production'], ['DEBUG']), 'PUT', $base.'/apps/shop/env', '{"set":{"APP_ENV":"production"},"unset":["DEBUG"]}'],
            'env empty set' => [fn (Cipi $c) => $c->env()->update('shop'), 'PUT', $base.'/apps/shop/env', '{"set":{},"unset":[]}'],
            'env merge and order' => [fn (Cipi $c) => $c->env()->update('shop', ['MAIL_HOST' => 'smtp.test'], [], ['APP_NAME', 'MAIL_HOST', 'APP_ENV']), 'PUT', $base.'/apps/shop/env', '{"set":{"MAIL_HOST":"smtp.test"},"unset":[],"order":["APP_NAME","MAIL_HOST","APP_ENV"]}'],
            'env reorder' => [fn (Cipi $c) => $c->env()->reorder('shop', ['APP_ENV', 'APP_NAME']), 'PUT', $base.'/apps/shop/env', '{"set":{},"unset":[],"order":["APP_ENV","APP_NAME"]}'],
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
            'packages install' => [fn (Cipi $c) => $c->packages()->install('browsershot'), 'POST', $base.'/packages/browsershot', null],
            'packages remove' => [fn (Cipi $c) => $c->packages()->remove('fonts-cjk'), 'DELETE', $base.'/packages/fonts-cjk', null],
            'nginx show' => [fn (Cipi $c) => $c->nginx()->show('shop'), 'GET', $base.'/apps/shop/nginx', null],
            'nginx update' => [fn (Cipi $c) => $c->nginx()->update('shop', "expires 7d;\n"), 'PUT', $base.'/apps/shop/nginx', '{"config":"expires 7d;\n"}'],
            'nginx reset' => [fn (Cipi $c) => $c->nginx()->reset('shop'), 'DELETE', $base.'/apps/shop/nginx', null],
            'cache list' => [fn (Cipi $c) => $c->cache()->list(), 'GET', $base.'/cache', null],
            'cache show' => [fn (Cipi $c) => $c->cache()->show('shop'), 'GET', $base.'/apps/shop/cache', null],
            'cache enable' => [fn (Cipi $c) => $c->cache()->enable('shop'), 'PUT', $base.'/apps/shop/cache', '{"enabled":true}'],
            'cache enable with settings' => [fn (Cipi $c) => $c->cache()->enable('shop', '1h', '2g', false), 'PUT', $base.'/apps/shop/cache', '{"enabled":true,"ttl":"1h","size":"2g","clear_on_deploy":false}'],
            'cache enable with paths left out' => [fn (Cipi $c) => $c->cache()->enable('shop', ttl: '1h', exclude: ['/cart*', '/feed']), 'PUT', $base.'/apps/shop/cache', '{"enabled":true,"ttl":"1h","exclude":["/cart*","/feed"]}'],
            'cache enable with no path of its own' => [fn (Cipi $c) => $c->cache()->enable('shop', exclude: []), 'PUT', $base.'/apps/shop/cache', '{"enabled":true,"exclude":[]}'],
            'cache exclude' => [fn (Cipi $c) => $c->cache()->exclude('shop', ['/cart*', '/checkout*']), 'PUT', $base.'/apps/shop/cache/exclude', '{"exclude":["/cart*","/checkout*"]}'],
            'cache exclude none' => [fn (Cipi $c) => $c->cache()->exclude('shop', []), 'PUT', $base.'/apps/shop/cache/exclude', '{"exclude":[]}'],
            'cache exclude from a keyed array' => [fn (Cipi $c) => $c->cache()->exclude('shop', ['a' => '/cart*', 'b' => '/feed']), 'PUT', $base.'/apps/shop/cache/exclude', '{"exclude":["/cart*","/feed"]}'],
            'cache disable' => [fn (Cipi $c) => $c->cache()->disable('shop'), 'PUT', $base.'/apps/shop/cache', '{"enabled":false}'],
            'cache clear' => [fn (Cipi $c) => $c->cache()->clear('shop'), 'POST', $base.'/apps/shop/cache/clear', null],
            'octane show' => [fn (Cipi $c) => $c->octane()->show('shop'), 'GET', $base.'/apps/shop/octane', null],
            'octane php' => [fn (Cipi $c) => $c->octane()->php('shop', '8.5'), 'PUT', $base.'/apps/shop/octane/php', '{"php":"8.5"}'],
            'octane restart' => [fn (Cipi $c) => $c->octane()->restart('shop'), 'POST', $base.'/apps/shop/octane/restart', null],
            'octane builds' => [fn (Cipi $c) => $c->octane()->builds(), 'GET', $base.'/frankenphp', null],
            'octane upgrade one' => [fn (Cipi $c) => $c->octane()->upgrade('shop'), 'POST', $base.'/frankenphp/upgrade', '{"app":"shop"}'],
            'octane upgrade all' => [fn (Cipi $c) => $c->octane()->upgrade(), 'POST', $base.'/frankenphp/upgrade', null],
            'valkey list' => [fn (Cipi $c) => $c->valkey()->list(), 'GET', $base.'/valkey', null],
            'valkey isolate' => [fn (Cipi $c) => $c->valkey()->isolate('shop'), 'POST', $base.'/valkey/isolate', '{"app":"shop"}'],
            'valkey isolate empty' => [fn (Cipi $c) => $c->valkey()->isolate('shop', false), 'POST', $base.'/valkey/isolate', '{"app":"shop","copy":false}'],
            'valkey isolate all' => [fn (Cipi $c) => $c->valkey()->isolateAll(), 'POST', $base.'/valkey/isolate', '{"all":true}'],
            'valkey share' => [fn (Cipi $c) => $c->valkey()->share('shop'), 'POST', $base.'/valkey/share', '{"app":"shop"}'],
            'valkey restart' => [fn (Cipi $c) => $c->valkey()->restart('shop'), 'POST', $base.'/valkey/shop/restart', null],
            'valkey flush shared' => [fn (Cipi $c) => $c->valkey()->flushShared(), 'POST', $base.'/valkey/flush-shared', null],
            'monitor' => [fn (Cipi $c) => $c->monitor()->list(), 'GET', $base.'/monitor', null],
            'metrics overview' => [fn (Cipi $c) => $c->metrics()->overview(), 'GET', $base.'/metrics', null],
            'metrics host series' => [fn (Cipi $c) => $c->metrics()->series(metrics: ['cpu', 'mem'], range: '7d'), 'GET', $base.'/metrics/series?metrics=cpu%2Cmem&range=7d', null],
            'metrics app series' => [fn (Cipi $c) => $c->metrics()->series('shop', 'http_5xx', from: 1795000000, to: 1795086400), 'GET', $base.'/apps/shop/metrics?metrics=http_5xx&from=1795000000&to=1795086400', null],
            'metrics uptime' => [fn (Cipi $c) => $c->metrics()->uptime('shop', '30d'), 'GET', $base.'/metrics/uptime?app=shop&range=30d', null],
            'metrics events' => [fn (Cipi $c) => $c->metrics()->events(source: 'deploy', limit: 20), 'GET', $base.'/metrics/events?source=deploy&limit=20', null],
            'metrics settings' => [fn (Cipi $c) => $c->metrics()->settings(), 'GET', $base.'/metrics/settings', null],
            'metrics retention' => [fn (Cipi $c) => $c->metrics()->retention(rawDays: 3, rollupDays: 60), 'PUT', $base.'/metrics/settings', '{"raw_days":3,"rollup_days":60}'],
            'metrics probe off' => [fn (Cipi $c) => $c->metrics()->updateSettings(['probe' => false]), 'PUT', $base.'/metrics/settings', '{"probe":false}'],
            'metrics heartbeat' => [fn (Cipi $c) => $c->metrics()->setHeartbeat('https://hc-ping.com/x', 5), 'PUT', $base.'/metrics/heartbeat', '{"url":"https://hc-ping.com/x","every":5}'],
            'metrics heartbeat off' => [fn (Cipi $c) => $c->metrics()->removeHeartbeat(), 'DELETE', $base.'/metrics/heartbeat', null],
            'metrics heartbeat test' => [fn (Cipi $c) => $c->metrics()->testHeartbeat(), 'POST', $base.'/metrics/heartbeat/test', null],
            'notifications' => [fn (Cipi $c) => $c->notifications()->get(), 'GET', $base.'/notifications', null],
            'notification add slack' => [fn (Cipi $c) => $c->notifications()->add('slack', 'ops', ['url' => 'https://hooks.slack.com/services/x', 'test' => true]), 'POST', $base.'/notifications/channels', '{"type":"slack","id":"ops","url":"https://hooks.slack.com/services/x","test":true}'],
            'notification add smtp' => [fn (Cipi $c) => $c->notifications()->add('smtp', 'ignored', ['host' => 'smtp.test']), 'POST', $base.'/notifications/channels', '{"type":"smtp","host":"smtp.test"}'],
            'notification update' => [fn (Cipi $c) => $c->notifications()->update('oncall', ['chat_id' => '-2']), 'PUT', $base.'/notifications/channels/oncall', '{"chat_id":"-2"}'],
            'notification remove' => [fn (Cipi $c) => $c->notifications()->remove('ops'), 'DELETE', $base.'/notifications/channels/ops', null],
            'notification enable' => [fn (Cipi $c) => $c->notifications()->enable('ops'), 'POST', $base.'/notifications/channels/ops/enable', null],
            'notification disable' => [fn (Cipi $c) => $c->notifications()->disable('ops'), 'POST', $base.'/notifications/channels/ops/disable', null],
            'notification test all' => [fn (Cipi $c) => $c->notifications()->test(), 'POST', $base.'/notifications/channels/all/test', null],
            'notification triggers' => [fn (Cipi $c) => $c->notifications()->triggers(), 'GET', $base.'/notifications/triggers', null],
            'notification set triggers' => [fn (Cipi $c) => $c->notifications()->setTriggers(['deploy_success' => false]), 'PUT', $base.'/notifications/triggers', '{"triggers":{"deploy_success":false}}'],
            'notification all off but' => [fn (Cipi $c) => $c->notifications()->setAllTriggers(false, ['deploy_fail']), 'PUT', $base.'/notifications/triggers', '{"all":false,"on":["deploy_fail"]}'],
            'notification reset triggers' => [fn (Cipi $c) => $c->notifications()->resetTriggers(), 'POST', $base.'/notifications/triggers/reset', null],
            'notification settings' => [fn (Cipi $c) => $c->notifications()->settings(), 'GET', $base.'/notifications/settings', null],
            'notification timezone' => [fn (Cipi $c) => $c->notifications()->setTimezone('Europe/Rome'), 'PUT', $base.'/notifications/settings', '{"timezone":"Europe/Rome"}'],
            'notification timezone of the server' => [fn (Cipi $c) => $c->notifications()->setTimezone(null), 'PUT', $base.'/notifications/settings', '{"timezone":null}'],
            'notification server name' => [fn (Cipi $c) => $c->notifications()->setServerName('production'), 'PUT', $base.'/notifications/settings', '{"server_name":"production"}'],
            'basicauth agent' => [fn (Cipi $c) => $c->basicAuth()->agent('shop'), 'GET', $base.'/apps/shop/basicauth/agent', null],
            'basicauth agent set' => [fn (Cipi $c) => $c->basicAuth()->setAgent('shop', 'protected'), 'PUT', $base.'/apps/shop/basicauth/agent', '{"mode":"protected"}'],
            'disk usage' => [fn (Cipi $c) => $c->disk()->usage(), 'GET', $base.'/disk', null],
            'disk databases' => [fn (Cipi $c) => $c->disk()->databases(), 'GET', $base.'/disk/dbs', null],
            'zero trust' => [fn (Cipi $c) => $c->zeroTrust()->status(), 'GET', $base.'/zt', null],
            'zero trust mesh' => [fn (Cipi $c) => $c->zeroTrust()->mesh(), 'GET', $base.'/zt', null],
            'deploy status' => [fn (Cipi $c) => $c->deploys()->status('shop'), 'GET', $base.'/apps/shop/deploy/status', null],
            'deploy status tail' => [fn (Cipi $c) => $c->deploys()->status('shop', 50), 'GET', $base.'/apps/shop/deploy/status?tail=50', null],
            'deploy history' => [fn (Cipi $c) => $c->deploys()->history('shop'), 'GET', $base.'/apps/shop/deploy/history', null],
            'deploy history limit' => [fn (Cipi $c) => $c->deploys()->history('shop', 10), 'GET', $base.'/apps/shop/deploy/history?limit=10', null],
            'deploys last' => [fn (Cipi $c) => $c->deploys()->last(), 'GET', $base.'/deploys/last', null],
            'node install' => [fn (Cipi $c) => $c->node()->install(24), 'POST', $base.'/node/install', '{"major":"24"}'],
            'node upgrade all' => [fn (Cipi $c) => $c->node()->upgrade(), 'POST', $base.'/node/upgrade', null],
            'node upgrade one' => [fn (Cipi $c) => $c->node()->upgrade(22, restart: true), 'POST', $base.'/node/upgrade', '{"major":"22","restart":true}'],
            'node upgrade restart' => [fn (Cipi $c) => $c->node()->upgrade(restart: true), 'POST', $base.'/node/upgrade', '{"restart":true}'],
            'node default' => [fn (Cipi $c) => $c->node()->setDefault('system'), 'PUT', $base.'/node/default', '{"major":"system"}'],
            'node remove' => [fn (Cipi $c) => $c->node()->remove(20), 'DELETE', $base.'/node/20', null],
            'db create for app' => [fn (Cipi $c) => $c->databases()->create('shop_reports', app: 'shop'), 'POST', $base.'/dbs', '{"name":"shop_reports","app":"shop"}'],
            'db link' => [fn (Cipi $c) => $c->databases()->link('shop_reports', 'shop'), 'POST', $base.'/dbs/shop_reports/link', '{"app":"shop"}'],
            'db link engine' => [fn (Cipi $c) => $c->databases()->link('shop_reports', 'shop', 'pgsql'), 'POST', $base.'/dbs/shop_reports/link', '{"app":"shop","engine":"pgsql"}'],
            'db unlink' => [fn (Cipi $c) => $c->databases()->unlink('shop_reports'), 'DELETE', $base.'/dbs/shop_reports/link', null],
            'db unlink engine' => [fn (Cipi $c) => $c->databases()->unlink('shop_reports', 'pgsql'), 'DELETE', $base.'/dbs/shop_reports/link', '{"engine":"pgsql"}'],
            'db restore file' => [fn (Cipi $c) => $c->databases()->restore('shop', '/var/log/cipi/backups/shop.sql.gz'), 'POST', $base.'/dbs/shop/restore', '{"file":"/var/log/cipi/backups/shop.sql.gz"}'],
            'db restore upload' => [fn (Cipi $c) => $c->databases()->restoreUpload('shop', '0b9d6c1e-58f3-4a52-9c0e-3f6a5a1d2e44', 'pgsql'), 'POST', $base.'/dbs/shop/restore', '{"upload_id":"0b9d6c1e-58f3-4a52-9c0e-3f6a5a1d2e44","engine":"pgsql"}'],
            'db upload limits' => [fn (Cipi $c) => $c->databases()->uploadLimits(), 'GET', $base.'/dbs/uploads/limits', null],
            'db upload start' => [fn (Cipi $c) => $c->databases()->startUpload('shop.sql.gz', 1234), 'POST', $base.'/dbs/uploads', '{"filename":"shop.sql.gz","size":1234}'],
            'db upload cancel' => [fn (Cipi $c) => $c->databases()->cancelUpload('0b9d6c1e-58f3-4a52-9c0e-3f6a5a1d2e44'), 'DELETE', $base.'/dbs/uploads/0b9d6c1e-58f3-4a52-9c0e-3f6a5a1d2e44', null],
            'backups status' => [fn (Cipi $c) => $c->backups()->status(), 'GET', $base.'/backups', null],
            'backups runs' => [fn (Cipi $c) => $c->backups()->runs(), 'GET', $base.'/backups/runs', null],
            'backups runs profile' => [fn (Cipi $c) => $c->backups()->runs('nightly'), 'GET', $base.'/backups/runs?profile=nightly', null],
            'backups run all' => [fn (Cipi $c) => $c->backups()->run(), 'POST', $base.'/backups/run', null],
            'backups run profile' => [fn (Cipi $c) => $c->backups()->run('nightly'), 'POST', $base.'/backups/run', '{"profile":"nightly"}'],
            'backups verify' => [fn (Cipi $c) => $c->backups()->verify('nightly'), 'POST', $base.'/backups/verify', '{"profile":"nightly"}'],
            'backups test all' => [fn (Cipi $c) => $c->backups()->testDestinations(), 'POST', $base.'/backups/destinations/test', null],
            'backups test one' => [fn (Cipi $c) => $c->backups()->testDestinations('offsite'), 'POST', $base.'/backups/destinations/test', '{"name":"offsite"}'],
            'backups profile create' => [fn (Cipi $c) => $c->backups()->createProfile('nightly', ['destinations' => ['local', 's3'], 'scope' => 'db', 'every' => '1d', 'keep' => 7, 'keep_days' => 0, 'keep_weeks' => 4, 'encrypt' => true]), 'POST', $base.'/backups/profiles', '{"name":"nightly","destinations":["local","s3"],"scope":"db","every":"1d","keep":7,"keep_days":0,"keep_weeks":4,"encrypt":true}'],
            'backups profile update' => [fn (Cipi $c) => $c->backups()->updateProfile('nightly', ['cron' => '0 3 * * *', 'exclude_tables' => []]), 'PUT', $base.'/backups/profiles/nightly', '{"cron":"0 3 * * *","exclude_tables":[]}'],
            'backups profile exclude paths' => [fn (Cipi $c) => $c->backups()->updateProfile('nightly', ['exclude_paths' => ['storage/app/uploads', 'shop:*.mp4']]), 'PUT', $base.'/backups/profiles/nightly', '{"exclude_paths":["storage/app/uploads","shop:*.mp4"]}'],
            'backups profile delete' => [fn (Cipi $c) => $c->backups()->deleteProfile('nightly'), 'DELETE', $base.'/backups/profiles/nightly', null],
            'backups profile enable' => [fn (Cipi $c) => $c->backups()->enableProfile('nightly'), 'POST', $base.'/backups/profiles/nightly/enable', null],
            'backups profile disable' => [fn (Cipi $c) => $c->backups()->disableProfile('nightly'), 'POST', $base.'/backups/profiles/nightly/disable', null],
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
