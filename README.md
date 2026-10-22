# cipi/sdk

PHP client for the [Cipi](https://cipi.sh) panel API. One Sanctum token is enough to manage apps, deploys, domains, databases, and the server from PHP, Laravel, Symfony, or any other framework.

The package talks only to the REST API (`/api/*`). It does not open SSH and it does not shell out to `cipi` on the machine where your code runs.

Documentation: [cipi.sh/docs/php-sdk](https://cipi.sh/docs/php-sdk).

## Requirements

- PHP 8.2+
- `ext-curl` and `ext-json`
- A Cipi server with `cipi/api` installed and a token from `cipi api token create`

Laravel 11, 12, and 13 can use the service provider and facade. They are optional.

## Install

```bash
composer require cipi/sdk
```

Create a token on the server and grant only the abilities this application needs (`apps-view`, `deploy-manage`, and so on). The panel returns `403` when the token is missing an ability.

## Quick start

```php
use Cipi\Sdk\Cipi;

$cipi = Cipi::connect(
    baseUrl: 'https://api.example.com',
    token: getenv('CIPI_TOKEN') ?: '',
);

$apps = $cipi->apps()->list();

$job = $cipi->deploys()->start('shop');
$result = $cipi->wait($job);
```

`https://api.example.com` and `https://api.example.com/api` are both accepted. Plain `http://` is rejected unless you pass `allow_http: true` for a local panel.

Responses are the JSON body as an associative array. Writes that the panel queues return `job_id` and `status: pending`. `wait()` polls `GET /api/jobs/{id}` until the job is `completed` or `failed`.

## Laravel

The service provider is discovered automatically.

```dotenv
CIPI_BASE_URL=https://api.example.com
CIPI_TOKEN=
CIPI_TIMEOUT=30
CIPI_ALLOW_HTTP=false
```

```bash
php artisan vendor:publish --tag=cipi-config
```

```php
use Cipi\Sdk\Laravel\Facades\Cipi;

$apps = Cipi::apps()->list();
```

Or inject the client:

```php
public function __construct(private Cipi\Sdk\Cipi $cipi) {}
```

One container binding talks to one server. For several servers, call `Cipi::connect()` with each endpoint and token.

## Other frameworks

Symfony, Slim, WordPress, or a script: construct the client yourself and pass it through your container. There is no framework-specific bundle to install.

```php
$cipi = Cipi::connect($_ENV['CIPI_BASE_URL'], $_ENV['CIPI_TOKEN']);
```

## What you can call

| Client | Panel |
| --- | --- |
| `apps()` | list, get, create, update, delete, suspend, unsuspend, fix permissions, recreate webhook |
| `limits()` | FPM, memory, workers, and the soft disk limit |
| `basicAuth()` | HTTP Basic Auth status, enable, disable |
| `env()` | read and merge `.env` keys |
| `authJson()` | shared Composer `auth.json` (not Basic Auth) |
| `artisan()` | Artisan as an async job (`tinker` is rejected by the panel) |
| `run()` | whitelisted non-interactive commands |
| `deployConfig()` | structured Deployer options |
| `yml()` | the app's `cipi.yml`: status and plan, apply, auto-apply on / off |
| `logs()` | nginx, php, worker, deploy, laravel |
| `aliases()`, `www()`, `redirects()`, `proxies()` | domains, apex redirects, path redirects, prefix proxies |
| `node()` | Node runtimes, app status, blue/green restart |
| `deploys()` | deploy, rollback, unlock, audit ledger |
| `ssl()` | install a certificate (HTTP-01 or Cloudflare DNS-01), force HTTPS, DNS accounts |
| `databases()` | engines, create, backup, restore, password |
| `php()`, `ssh()`, `services()`, `smtp()` | PHP versions, cipi user keys, services, SMTP |
| `health()`, `search()` | HTTP healthchecks, Meilisearch / Scout |
| `packages()`, `monitor()`, `zeroTrust()`, `disk()` | read-only host insights |
| `server()->status()` | same snapshot as `cipi status` |
| `ipWhitelist()` | API client allowlist |
| `jobs()->get()` / `wait()` | async job status |
| `request()` | any `/api` path this release does not wrap yet |

A few examples:

```php
$cipi->apps()->create([
    'user' => 'shop',
    'domain' => 'shop.example.com',
    'repository' => 'git@github.com:acme/shop.git',
    'branch' => 'main',
    'php' => '8.4',
    'node_build' => 'npm ci && npm run build',
]);

$cipi->limits()->update('shop', ['fpm_max_children' => 10, 'disk_limit_gb' => 5]);

$cipi->env()->update('shop', ['APP_ENV' => 'production'], ['TELESCOPE_ENABLED']);

$cipi->databases()->create('shop', 'pgsql');

$cipi->redirects()->add('shop', '/blog/', 'https://blog.example.com', 301, true);

$cipi->ssl()->install('shop', dns: 'cloudflare', account: 'prod', wildcard: true);

$cipi->disk()->usage();

$cipi->logs()->get('shop', type: 'laravel', perPage: 100);
```

`node_build` (Laravel apps only) is the asset build Cipi runs inside every deploy, before the release goes live. It starts with `npm`, `npx`, `yarn`, `pnpm`, `bun`, or `node`, allows `&&` and `;`, refuses pipes, redirects, backticks, and `$( )`, and is at most 200 characters. The panel validates it and answers 422 (`ValidationException`) on a bad command or on a custom or Node app (Node apps take `build`). Needs cipi/api 1.34.0+ and Cipi CLI 5.6.0+.

The same versions report whether the app's deploy key can read its repository. The `app-create` job result, and the `app-edit` result when the repository or branch changed, carry `repository_access`; `app-create` also carries `build`. The check never fails the job, so look at it after `wait()`:

```php
$job = $cipi->wait($cipi->apps()->create([/* ... */]));
$access = $job['data']['result']['repository_access'] ?? null;

if ($access !== null && $access['status'] !== 'ok') {
    // 'nobranch': readable, but no such branch. 'denied': the key cannot read it.
    // $access['error'] is the git line, $access['hint'] the CLI fix Cipi suggests,
    // e.g. "cipi git github-token <token> && cipi git refresh shop".
}
```

`status` is `ok`, `nobranch`, or `denied`; `branch`, `error`, and `hint` are optional. On older servers, and on apps without a repository, the key is absent.

`limits()->update()` is an async job. Pass `disk_limit_gb: null` to clear the soft limit. DNS-01 and the disk limit need Cipi CLI 5.5.0+. `disk()` needs CLI 5.5.2 (older hosts return 503). `ssl()->setDnsAccount()` is synchronous, so the Cloudflare token is not queued, and the panel never returns it.

Installing host packages, changing monitor thresholds, and mutating Cloudflare Zero Trust stay on the server CLI. The API exposes those as read-only, and so does this SDK. Disk usage is read-only too; the per-app limit is `limits()`.

### cipi.yml

An app can ship a `cipi.yml` in its repository that describes the server state it expects: aliases, PHP version and settings, limits, workers, crons, deploy options, backups, and so on. `yml()` reads it and applies it from the panel:

```php
$plan = $cipi->yml()->status('shop')['data'];

foreach ($plan['changes'] as $change) {
    echo $change['summary'], PHP_EOL; // e.g. "PHP 8.3 → 8.4"
}

if ($plan['blockers'] === []) {
    $job = $cipi->jobs()->wait($cipi->yml()->apply('shop'), throwOnFailure: false);
    $outcome = $job['data']['result']['outcome']; // applied, unchanged, partial, blocked, invalid, no-file
}

$cipi->yml()->enableAuto('shop');
$cipi->yml()->disableAuto('shop');
```

`status()` is synchronous. `file` is `null` when the live release has no `cipi.yml`; `valid` is `false` when it does not parse (see `errors`); `in_sync` is `true` when nothing would change. `apply()` is an async job (`app-yml-apply`) and changes nothing while `blockers` is not empty. `applied` and `unchanged` end `completed`; `partial`, `blocked`, `invalid`, and `no-file` end `failed` but carry the same `result` (`applied`, `failed`, `failures`, `blockers`, `errors`), so `wait()` throws `JobFailedException` with the payload on `$e->job` unless you pass `throwOnFailure: false`.

`enableAuto()` and `disableAuto()` are synchronous and idempotent. With auto-apply on, Cipi applies the file after every successful deploy (CLI, panel, and Git webhook). That hands this app's server configuration to whoever can commit to the repository: aliases, PHP settings, limits, workers, the deploy recipe, scheduled commands, databases, and the backup schedule (for Node apps also mode, version, build, and start). Ask before you turn it on for someone.

Reads need the `yml-view` ability, writes `yml-manage`. Existing tokens do not get them automatically: a token without them gets `AuthorizationException` (403), so catch it and treat `cipi.yml` as unavailable. Needs cipi/api 1.35.0+ and Cipi CLI 5.6.4+. A host with an older CLI returns 503 (`ServerException`); a panel older than 1.35.0 has no such route (404). Before Cipi 5.6.4 a webhook deploy never applied `cipi.yml`, even with auto-apply on.

## Errors

Failed HTTP responses throw `Cipi\Sdk\Exception\CipiException` (or a subclass):

| Status | Exception |
| --- | --- |
| 401 | `AuthenticationException` |
| 403 | `AuthorizationException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 422 | `ValidationException` (`errors()` when Laravel returned a bag) |
| 429 | `RateLimitException` |
| 5xx | `ServerException` |

`wait()` throws `JobFailedException` when the job finishes with `failed`, and `TimeoutException` if it is still running when the timeout elapses (default 300 seconds).

```php
use Cipi\Sdk\Exception\JobFailedException;
use Cipi\Sdk\Exception\AuthorizationException;

try {
    $cipi->wait($cipi->deploys()->start('shop'));
} catch (JobFailedException $e) {
    $job = $e->job;
} catch (AuthorizationException $e) {
    // token is missing deploy-manage
}
```

## Security

- HTTPS and certificate verification are on by default. Redirects are not followed, so the Bearer token cannot be sent to another host.
- The token is sent only in the `Authorization` header. A token that contains whitespace or control characters is rejected, so it cannot inject a second header.
- The token is removed from exception messages, response arrays, and `var_dump` / `print_r` output.
- Reads (`GET`) may retry `429` and `503` when `Retry-After` is at most 10 seconds. Writes are never retried.
- Response bodies are capped (16 MiB by default).

Store `CIPI_TOKEN` in the environment of the machine that runs PHP. Do not commit it, and do not log the client. Create a separate token per application, with the smallest ability set that works.

```php
$cipi = Cipi::connect($url, $token, [
    'timeout' => 30,
    'connect_timeout' => 10,
    'max_retries' => 2,
    'allow_http' => false,
]);
```

## Tests

```bash
composer test
```

## License

MIT. Copyright (c) 2026 Andrea Pollastri.
