<p align="center">
  <a href="https://cipi.sh">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://cipi.sh/brand/cipi-logo-dark.svg">
      <img src="https://cipi.sh/brand/cipi-logo.svg" alt="Cipi" height="56">
    </picture>
  </a>
</p>

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

Responses are the JSON body as an associative array. Writes that the panel queues return `job_id` and `status: pending`. `wait()` polls `GET /api/jobs/{id}` until the job is `completed` or `failed`. With cipi/api 1.36.1+ the token must hold the ability the job was created under (the one of the call that queued it: `deploy-manage` for a deploy, `dbs-manage` for a database password, …); without it `GET /api/jobs/{id}` answers 404, the same as an unknown id, and `wait()` throws `NotFoundException`.

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
| `env()` | read and merge `.env` keys; reorder them (API 1.43+, Cipi 5.13+) |
| `authJson()` | shared Composer `auth.json` (not Basic Auth) |
| `artisan()` | Artisan as an async job (`tinker` is rejected by the panel) |
| `run()` | whitelisted non-interactive commands |
| `deployConfig()` | structured Deployer options; with Cipi 5.18.0+ also `build_timeout` and `step_timeout` — seconds the frontend build and every other command of a deploy may run |
| `yml()` | the app's `cipi.yml`: status and plan, apply, auto-apply on / off |
| `logs()` | nginx, php, worker, deploy, laravel |
| `aliases()`, `www()`, `redirects()`, `proxies()` | domains, apex redirects, path redirects, prefix proxies |
| `node()` | Node runtimes (list, install, upgrade, default, remove), app status, blue/green restart |
| `deploys()` | deploy, rollback, unlock, live status, history, last run of every app, audit ledger |
| `ssl()` | install a certificate (HTTP-01 or Cloudflare DNS-01), force HTTPS, DNS accounts |
| `databases()` | engines, create, link to an app, backup, restore from the server or from a local dump, password |
| `backups()` | backup status, the setup (this server, an S3 bucket, an SFTP server), S3 and SFTP destinations, profiles, runs, run now, verify, the encryption key, the retention |
| `php()`, `ssh()`, `services()`, `smtp()` | PHP versions, cipi user keys, services, SMTP |
| `health()`, `search()` | HTTP healthchecks, Meilisearch / Scout |
| `nginx()` | an app's own nginx directives: show, replace, remove (API 1.44+, Cipi 5.14+; a subset of nginx's language from the API) |
| `cache()` | the nginx page cache of an app: status, on / off with ttl and size, the paths it never caches, empty it; every app's (API 1.47+, Cipi 5.17+) |
| `octane()` | the PHP of an Octane app: show, put it on a version by its number, restart; FrankenPHP builds and their upgrade (API 1.47+, Cipi 5.17+) |
| `valkey()` | Valkey per app: who is on the shared instance, give an app its own, move it back, restart, empty the shared one (API 1.47+, Cipi 5.17+) |
| `packages()` | optional host packages: the catalog, install and remove an entry (install / remove: API 1.44+, Cipi 5.14+) |
| `monitor()`, `disk()` | read-only host insights |
| `zeroTrust()` | Cloudflare Tunnel (read-only) and the mesh network — Tailscale or NetBird: status, join, leave, close and reopen SSH and 80/443, Tailscale SSH (API 1.48+, Cipi 5.18+, ability `zt-manage`) |
| `updates()` | how the server updates Cipi: only releases signed by a trusted key, a delay before a new release is installed — both off by default (API 1.48+, Cipi 5.18+, abilities `updates-view`, `updates-manage`) |
| `sync()` | apps sent to another server (`cipi sync`): this server's key, a check of the target, trust and untrust a key, the transfer as a job — no passphrase to handle (API 1.48+, Cipi 5.18+, ability `sync-manage`) |
| `metrics()` | metrics & health history: overview, series, uptime, events, retention, heartbeat (API 1.41+, Cipi 5.11+) |
| `notifications()` | every notification channel (SMTP, Resend, SES, Slack, Discord, Mattermost, Google Chat, Telegram, ntfy, Pushover, webhook) and trigger: add, update, test, enable/disable, remove; triggers on/off (API 1.42+, Cipi 5.12+); the time zone and the server name they use (API 1.47+, Cipi 5.17+) |
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
$cipi->env()->reorder('shop', ['APP_NAME', 'APP_ENV', 'APP_KEY', 'DB_HOST' /* … every key, once */]);

$cipi->databases()->create('shop', 'pgsql');

$cipi->redirects()->add('shop', '/blog/', 'https://blog.example.com', 301, true);

$cipi->ssl()->install('shop', dns: 'cloudflare', account: 'prod', wildcard: true);

$cipi->disk()->usage();

$cipi->logs()->get('shop', type: 'laravel', perPage: 100);
```

`db` (custom and Node apps, Cipi 5.18.0+) is the name of a new database to create with the app — `$cipi->apps()->create(['user' => 'blog', 'domain' => 'blog.example.com', 'custom' => true, 'docroot' => 'www', 'db' => 'wpblog'])` — linked to it, with its credentials in the job's `data.result.database`; `engine` picks its engine. A Laravel app is created with its own database.

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

`env` is the app's environment label, `production` by default: `staging`, `preview-42`, and so on. Lowercase letters, digits, `-` and `_`, 1 to 32 characters, starting with a letter or digit (`^[a-z0-9][a-z0-9_-]{0,31}$`); anything else is a 422 (`ValidationException`). Set it on create, change it with `update()`, and read it from `list()` and `get()`. It decides which `cipi.yml` the app reads (see below). Needs cipi/api 1.36.0+ and Cipi CLI 5.8.0+: an older panel ignores the attribute and omits the key, so treat a missing `env` as `production`.

```php
$cipi->apps()->create([
    'user' => 'shop',
    'domain' => 'staging.shop.example.com',
    'repository' => 'git@github.com:acme/shop.git',
    'branch' => 'develop',
    'env' => 'staging',
]);

$cipi->apps()->update('shop', ['env' => 'preview']);

$env = $cipi->apps()->get('shop')['data']['env'] ?? 'production';
```

`limits()->update()` is an async job. Pass `disk_limit_gb: null` to clear the soft limit. DNS-01 and the disk limit need Cipi CLI 5.5.0+. `disk()` needs CLI 5.5.2 (older hosts return 503). `ssl()->setDnsAccount()` is synchronous, so the Cloudflare token is not queued, and the panel never returns it.

Changing monitor thresholds and Cloudflare's side of Zero Trust (the tunnel, its hostnames, Access, its locks) stay on the server CLI. The API exposes those as read-only, and so does this SDK; the mesh network is managed from here too (below). Disk usage is read-only too; the per-app limit is `limits()`.

### Zero Trust: the tunnel and the mesh network

Zero Trust has two sides. Cloudflare Tunnel publishes the sites; a mesh network — Tailscale or NetBird — is how the people who run the server reach it: SSH, the panel and the API on an address only the devices of the network can route to. One call reads both:

```php
$zt = $cipi->zeroTrust()->status()['data'];

$zt['enabled'];     // Cloudflare's tunnel — says nothing of the mesh
$zt['mesh'];        // 'tailscale', 'netbird' or null
$zt['mesh_state'];  // 'connected' / 'disconnected'
$zt['mesh_ip'];     // 100.101.102.103: where SSH and the panel answer on the network

if ($mesh = $cipi->zeroTrust()->mesh()) {
    $mesh['lock']['ssh'];           // port 22 closed to the world: SSH from the network only
    $mesh['key_expires_in_days'];   // Tailscale; null when the node key does not expire
    $mesh['warnings'];              // what the server would tell its operator
}
```

`mesh()` is `details.mesh` of the same answer, or `null` while the server is on no mesh. It needs cipi/api 1.48+ and Cipi 5.18.0+; an older server says nothing of a mesh and `mesh()` is `null` there too.

#### Joining a network, closing ports

With a token that has `zt-manage` (as strong as `ssh-manage`: whoever holds it can put the server on a network of their own), the mesh is changed from here. Every call is synchronous and answers `data` — Zero Trust after the change, as `status()` — and `message`, what the CLI said.

```php
use Cipi\Sdk\Resources\ZeroTrust;

// A join installs the client and signs in: give the client a minute or two.
$cipi = new Cipi($url, $token, ['timeout' => 240]);

$cipi->zeroTrust()->joinMesh(ZeroTrust::TAILSCALE, 'tskey-auth-…', [
    'hostname' => 'web1',
    'tags' => ['cipi'],          // a tagged machine: its node key never expires
]);
$cipi->zeroTrust()->joinMesh(ZeroTrust::NETBIRD, $setupKey, ['control_url' => 'https://netbird.example.com']);

$cipi->zeroTrust()->lockMesh('ssh');     // port 22 open on the mesh only
$cipi->zeroTrust()->lockMesh('http');    // 80/443 open on the mesh only
$cipi->zeroTrust()->unlockMesh('ssh');
$cipi->zeroTrust()->tailscaleSsh(true);  // Tailscale only
$cipi->zeroTrust()->leaveMesh();         // reopens what the locks closed, then removes the client
```

Joining closes nothing. Options of `joinMesh()`: `hostname`, `control_url` (a Headscale, a self-hosted NetBird), `direct` (also open the client's UDP port), `accept_dns`, `accept_routes`, and for Tailscale `tags`, `ssh`, `reauth`. The key is sent once — the panel passes it to the CLI and neither stores nor returns it; left out, a client that is already installed and connected on the server is taken as it is.

**The server keeps a call from locking everyone out**, and there is no `--force` over the API. A refusal is a `ValidationException` (422) whose message is the reason:

- `lockMesh('ssh')` — until sshd has accepted a login that came over the mesh (sign in once: `ssh cipi@<mesh address>`), while the node key can expire, with the firewall off, or with Tailscale SSH on.
- `lockMesh('http')` — while an app renews its certificate over HTTP-01, and when your call reaches the API on the public address the lock would close. Call the API over the mesh, or through the Cloudflare tunnel, and it goes ahead.
- `leaveMesh()` — when your call reaches the server over the mesh itself.

`status()` says it before you ask:

```php
$panel = $cipi->zeroTrust()->status()['data']['details']['mesh']['panel'];

$panel['via'];                    // mesh, local, tunnel, public, unknown: how this call reached the server
$panel['blocked']['lock_ssh'];    // null, or why the server would refuse it
$panel['blocked']['lock_http'];
$panel['blocked']['disable'];
```

A panel that runs on the server it manages passes the address of the person at it — `status($browserIp)`, `lockMesh('http', $browserIp)` — and the server refuses to close 80/443 under the page they are looking at.

### Backups to SFTP

```php
$box = $cipi->backups()->addSftpDestination('box', 'u1234.your-storagebox.de', 'u1234', port: 23, path: 'cipi')['data'];
echo $box['public_key'];                              // install it on the remote: ssh-copy-id -p 23 -s … for a Storage Box
$cipi->backups()->testDestinations('box');           // a file written and removed there
$cipi->backups()->updateProfile('nightly', ['destinations' => ['local', 'box']]);
$cipi->backups()->updateSftpDestination('box', ['new_key' => true]);
$cipi->backups()->destinationKey('box')['data']['public_key'];
$cipi->backups()->removeDestination('box');          // no profile may use it; its archives stay
```

A server reached over SSH — a VPS with a backup user, a Hetzner Storage Box, a NAS — is a destination like the S3 ones. Cipi makes the key on the server: nothing secret is sent. Only SFTP is asked of the remote. cipi/api 1.48+, Cipi 5.18.0+.

### Backups set up from the panel: where they go, S3 buckets, the key, the retention

```php
// A server without backups: choose where they go. A nightly profile then takes every app and database there.
$cipi->backups()->configureS3('acme-backups', 'AKIA…', $secret, endpoint: 'https://fsn1.your-objectstorage.com');
$setup = $cipi->backups()->configureSftp('u1234.your-storagebox.de', 'u1234', port: 23, path: 'cipi');
echo $setup['destination']['public_key'];             // to install on the remote, then testDestinations('sftp')
$cipi->backups()->configureLocalOnly();               // this server only: lost with the server

// More buckets, as destinations of their own
$cipi->backups()->addS3Destination('offsite', 'acme-offsite', 'AKIA…', $secret, 'auto', 'https://<account-id>.r2.cloudflarestorage.com');
$cipi->backups()->updateS3Destination('offsite', ['region' => 'eu-west-1']);    // a key or a secret left out stays as it is
$cipi->backups()->removeDestination('offsite');

$key = $cipi->backups()->encryptionKey()['data']['key'];   // keep it off the server; logged and notified there
$cipi->backups()->rotateKey();                             // the old key stays on the server, for the old runs
$cipi->wait($cipi->backups()->prune(dryRun: true));        // what the retention would delete — a job
$cipi->wait($cipi->backups()->prune());                    // and apply it now
```

Every bucket is reached with its keys before it is saved: a wrong key is a `ValidationException` (422) and changes nothing. **A secret key never rides a command line**: the panel writes it into the CLI's standard input, never into a job, and no answer carries it back. The local and temp folders stay the server's to choose. Downloading a run stays on the server (`cipi backup fetch`). cipi/api 1.49+, Cipi 5.19.0+; ability `backups-manage`.

### How the server updates Cipi

```php
$cipi->updates()->policy()['data'];                          // verify, delay_hours, signers, pending, held
$cipi->updates()->setPolicy(delayHours: 12);                 // wait 12 hours before installing a new release
$cipi->updates()->setPolicy(verify: true, fingerprint: 'SHA256:…');   // only signed releases — the first trust
```

Both are off by default. `verify` installs a release only when its tag `v<version>` is signed by a key the server trusts; the first time, `fingerprint` names one of the keys shipped with the release, compared with the one published at cipi.sh. `delay_hours` (0 to 168) installs a new release once the server has seen it for that long. Starting an update is not in the API.

### Moving apps to another server

`sync()` is `cipi sync` over the API: the apps of one server sent to another — a move to a bigger machine, production copied onto staging, a second server kept ready. The server a client talks to is the **source** of a `push()`: it exports the apps (settings, `.env`, deploy recipe, workers, crons, and databases and uploaded files when asked), encrypts the archive, sends it over SSH and has the target import it. Apps that are not on the target are created there; apps that are, updated. Nothing is removed or stopped on the source.

```php
$source = Cipi::connect('https://prod.example.com', $prodToken, ['timeout' => 90]);
$target = Cipi::connect('https://staging.example.com', $stagingToken);

// 1. The target trusts the source's key.
$key = $source->sync()->key()['data']['key'];
$trusted = $target->sync()->trust($key)['data'];

try {
    // 2. Is the way open? $host is the address the SOURCE reaches the target on over SSH.
    $host = '203.0.113.77';
    if (! $source->sync()->ready($host)) {
        $why = $source->sync()->check($host)['data'];   // reason: not-trusted, unreachable, host-key-changed, no-cipi, no-key
        throw new RuntimeException($why['reason'].': '.$why['message']);
    }

    // 3. The transfer: a job, followed for as long as the panel gives a deploy.
    $job = $source->wait($source->sync()->push($host, ['shop', 'blog'], withDb: true, withStorage: true));
    echo $job['data']['output'];
} finally {
    // 4. Unless transfers repeat: the key the script added is taken back out.
    if ($trusted['changed']) {
        $target->sync()->untrust($key);
    }
}
```

- **`push($host, $apps = [], $withDb = false, $withStorage = false, $port = 22)`** — no apps: every app of the server. `withDb` dumps the databases on the source and restores them on the target, **replacing the database of an app that is already there**; `withStorage` adds each app's `shared/storage`. The job's type is `sync-push`; `wait()` follows it for two hours without being told (`Jobs::LONG_JOBS`).
- **`check($host, $port = 22)`** asks the source whether the target accepts its key and runs a Cipi that can take the transfer; nothing is sent. `ready()` is the same call as a boolean: `ok` **and** `auto_passphrase`. The server waits for SSH to answer — give the client a `timeout` of a minute or more.
- **`trust($key)` / `untrust($key)`** are called on the server that **receives**. `changed` says whether the call changed anything, which is how a script knows the key is its own to take back. A server that is not behind the API is prepared at its shell: `cipi sync trust`.
- **There is no passphrase to handle.** The archive is encrypted (AES-256) with a passphrase the source makes for that one transfer and hands to the target's import over the same SSH connection: it is never returned, and never passes through the API. The archive is removed from both servers when the import ends.
- **Laravel and custom apps travel; Node apps are skipped** — `cipi sync` does not recreate them yet: create them on the target with `apps()->create()`, the code comes from Git with the first deploy.
- DNS is yours to move: the apps answer on the target once their domains point at it, and certificates are issued there then.

`sync-manage` is as strong as `ssh-manage`: whoever holds a token with it can have the server send its apps — databases included — to a host of their choosing, and can let another server's key sign in as `cipi`. Tokens made with a list of abilities do not have it until they are created again. Exports to a file (`cipi sync export|import|list`) stay at the server's shell.

### Nginx directives of an app

```php
$cipi->nginx()->show('shop')['data']['config'];   // the text; '' when the app has none

$cipi->nginx()->update('shop', <<<'NGINX'
client_max_body_size 512M;
location ^~ /downloads/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
}
NGINX);

$cipi->nginx()->reset('shop');
```

Cipi keeps the directives and includes them in the app's server block, so they survive every change of the vhost. `update()` is synchronous: `nginx -t` runs before the reload, and a refused text changes nothing — a `ValidationException` (422) with the reason. From the API a subset of nginx's language is taken (no `access_log` path, `include`, `listen`, `server_name`, `*_pass`): reverse proxies are `proxies()`, redirects `redirects()`, and the whole language is `cipi nginx edit <app>` at the server.

### Page cache

```php
$cipi->cache()->enable('blog');                          // pages kept 10m, 256m on disk, emptied after a deploy
$cipi->cache()->enable('blog', ttl: '1h', size: '1g');   // or change it when it is on already
$cipi->cache()->show('blog')['data'];                    // enabled, ttl, files, bytes, clear_command, …
$cipi->cache()->exclude('blog', ['/cart*', '/feed']);    // its own paths never cached — the whole list; [] for none
$cipi->cache()->clear('blog')['data']['cleared'];        // pages deleted
$cipi->cache()->purge('blog', ['/blog/hello/', '/blog/*'])['data']['purged'];   // some pages only (API 1.48+)
$cipi->cache()->disable('blog');
```

nginx keeps the page PHP made and answers the next visitors with it, without running PHP — `fastcgi_cache` for an app on PHP-FPM (Laravel, WordPress, any PHP app), `proxy_cache` for one on Octane. It is not OPcache: OPcache is always on and keeps the compiled PHP in memory, so PHP still runs at every request; with the page cache it does not run at all for a page that is kept. The cache respects what the app answers (`Cache-Control`, `Expires`, `Set-Cookie`) and skips signed-in visitors, carts, admin and API paths.

`exclude()` is for what those rules cannot see: a page that is different for each visitor with no cookie or header saying so, a feed, a page with a counter. `/cart*` is everything that starts so (`*` is anything, slashes included); `/feed` is the path itself. `enable()` takes the same list as `exclude:`.

Cipi empties it after every deploy and rollback. Where content changes without a deploy, call `purge()` with the pages that changed — a path is that page on every domain of the app, with its `?utm_…` variants; `/blog/*` is every page under it; a URL is that domain only; up to 500 — or `clear()` for all of them. On the server the app's own code does the same without a token: `sudo /usr/local/bin/cipi-cache-clear <app> [<page>…]`, which the WordPress plugin [cipi-sh/cache-wp](https://github.com/cipi-sh/cache-wp) and the package `cipi/cache` ([cipi-sh/cache-php](https://github.com/cipi-sh/cache-php)) call.

### Octane

```php
$o = $cipi->octane()->show('shop')['data'];
// php: "8.5" (Composer, artisan, cron, workers), octane_php: "8.6.0" (what answers requests),
// state: "mismatch", available_php: ["8.5", "8.6"], fix: {command, why}

$cipi->wait($cipi->octane()->php('shop', '8.6'));   // both on PHP 8.6
$cipi->wait($cipi->octane()->restart('shop'));
$cipi->wait($cipi->octane()->upgrade('shop'));      // newest build on the same PHP
```

`php()` takes the version by its number, as for any other app: the FrankenPHP build that carries it is activated (and fetched when the server has none), Octane is restarted and checked — the previous build comes back when it does not answer — and then Composer, artisan, cron and the workers move. That PHP has to be installed on the server (`$cipi->php()->install('8.6')`).

### Valkey per app

```php
$v = $cipi->valkey()->list()['data'];     // apps: [{app, uses: own|shared|external|none, port, memory, keys}], shared: [...]

$cipi->wait($cipi->valkey()->isolate('shop'));   // its own instance; keys copied, workers restarted
$cipi->wait($cipi->valkey()->isolateAll());      // every app still on the shared instance
$cipi->valkey()->flushShared();                  // then: empty the shared one, change its password
```

On the shared instance every app holds the same password and can read the others' cache, sessions and queues. No password is ever in an answer.

### Notification settings

```php
$cipi->notifications()->setTimezone('Europe/Rome');   // the Time: line of every notification; null = the server's own
$cipi->notifications()->setServerName('production');  // "production (vmi12334)" instead of the hostname alone; null = hostname
$cipi->notifications()->settings()['data'];           // timezone, server_timezone, server_name, hostname, now
```

### Optional host packages

```php
$catalog = $cipi->packages()->list()['data']['packages'];
// id, packages (apt), description, installed, partial — and since Cipi 5.14.0
// kind (tools | libraries), binaries (the commands it brings), missing,
// source (ubuntu | upstream), version, installed_version, outdated

$job = $cipi->packages()->install('browsershot');   // async job: package-install
$cipi->wait($job);

$job = $cipi->wait($cipi->packages()->remove('ffmpeg'));
$job['data']['result'];   // ['package' => 'ffmpeg', 'removed' => true, 'apt' => [...]]
```

The catalog is closed and lives on the server: `image-optimizers`, `avif`, `ffmpeg`, `imagemagick`, `poppler-utils`, `browsershot` (the libraries and fonts the Chrome Puppeteer downloads needs — Chrome itself comes with `npm install puppeteer` in the app), `fonts-cjk`, `pdftk`, `libreoffice` (headless), `pandoc`, `tesseract`, and the security scanners [Hackly](https://github.com/andreapollastri/hackly) runs — `sec-recon`, `sec-gitleaks` from Ubuntu, and `sec-nuclei`, `sec-zap`, `sec-nikto`, `sec-wpscan`, `sec-trivy`, `sec-semgrep`, `sec-checkov`, upstream releases Cipi pins by version and SHA-256 (a download that does not match fails the job). Any other id answers 404, an installed one 409. `install()` also completes a partial install and updates an `outdated` upstream one. `remove()` takes only what no other package still needs: when nothing can go — the libraries of a library entry still used by other software — the job result says `removed: false, still_needed: true`. Install and remove need Cipi 5.14.0+ (503 before), cipi/api 1.44+ and the `packages-manage` ability.

### Deploy status and history

```php
$status = $cipi->deploys()->status('shop', tail: 50)['data'];
$status['state'];   // running, queued, interrupted or idle
$status['tail'];    // the last 50 lines of the deploy log

$cipi->deploys()->history('shop', limit: 10);
$cipi->deploys()->last(); // the last run of every app, null for one never deployed
```

All three are synchronous. `status()` needs the `deploy-manage` ability, cipi/api 1.37.0+ and Cipi CLI 5.8.4+; `tail` is 0 to 500 lines. With Cipi CLI 5.13+ it also returns `paused_commit`: the commit whose automatic deploy failed, which the webhook does not deploy again (the next push does). `unlock()` removes a stuck lock and resumes it; so does a deploy that succeeds. `history()` and `last()` need `apps-view` or `deploy-manage`, cipi/api 1.39.0+ and Cipi CLI 5.9.0+; `limit` is 1 to 500, 30 when omitted.

### Node runtimes

```php
$cipi->wait($cipi->node()->install(24));
$cipi->wait($cipi->node()->upgrade(24, restart: true)); // omit the major to upgrade every runtime
$cipi->wait($cipi->node()->setDefault(24));             // or 'system'
$cipi->wait($cipi->node()->remove(20));
```

Each is a job and needs the `node-runtimes-manage` ability, cipi/api 1.39.0+ and Cipi CLI 5.9.0+. Existing tokens do not get the ability automatically.

### Databases: link and restore a local dump

```php
$cipi->wait($cipi->databases()->create('shop_reports', 'pgsql', app: 'shop'));
$cipi->databases()->link('legacy', 'shop');
$cipi->databases()->unlink('legacy');

$cipi->wait($cipi->databases()->restoreLocal('shop', __DIR__.'/shop.sql.gz'));
```

A database linked to an app shows under that app (`GET /api/apps` returns its `databases`) and `list()` returns `app`, `link` and `size_mb` for each one. `link()` and `unlink()` are synchronous.

`restoreLocal()` sends a `.sql` or `.sql.gz` file from the machine the SDK runs on, in chunks, and starts the restore job on it. The file is read one chunk at a time, so its size is not bound by `memory_limit`. If a chunk or the restore call is refused, the partial upload is cancelled before the exception reaches you. The steps are available one by one:

```php
$cipi->databases()->uploadLimits();                       // max size, chunk size, what may wait
$upload = $cipi->databases()->upload('/tmp/shop.sql.gz'); // start + every chunk
$cipi->wait($cipi->databases()->restoreUpload('shop', $upload['data']['upload_id']));

$cipi->databases()->startUpload('shop.sql.gz', $bytes);
$cipi->databases()->uploadChunk($uploadId, 0, $firstChunk);
$cipi->databases()->cancelUpload($uploadId);
```

The panel deletes an uploaded dump when its restore job ends, or after a day. `restore()` still takes the path of a `.sql.gz` already on the server. Links and uploads need the `dbs-manage` ability, cipi/api 1.39.0+ and Cipi CLI 5.9.0+ (an older host returns 503).

`engines()` lists each engine with `engine`, `status`, `port` and `default`. With cipi/api 1.43+ it also says `server` and `label`: on a server where `cipi db switch mysql` replaced MariaDB with MySQL (Cipi CLI 5.13+, an explicit step on the server only, on a server without databases), the engine id stays `mariadb` and `server` is `mysql`. Everything that takes an engine keeps taking `mariadb`.

### Backups

```php
$backups = $cipi->backups()->status()['data'];
$backups['uncovered']; // apps and databases no enabled profile takes

$cipi->backups()->createProfile('nightly', [
    'scope' => 'all',            // all, files or db
    'every' => '1d',             // or 'cron' => '0 3 * * *'
    'destinations' => ['s3'],    // local, s3 or a named destination
    'keep_days' => 14,
    'encrypt' => true,
]);
$cipi->backups()->updateProfile('nightly', ['keep_days' => 30]);
$cipi->backups()->disableProfile('nightly');
$cipi->backups()->enableProfile('nightly');
$cipi->backups()->deleteProfile('nightly');

$cipi->backups()->run('nightly');             // omit the profile to run every enabled one
$cipi->backups()->runs('nightly');
$cipi->wait($cipi->backups()->verify('nightly'));
$cipi->backups()->testDestinations();
```

`run()` is not a job: the panel answers `202` with `started: true` as soon as the run has started, and the outcome shows in `status()` (`running`, then the profile's last run). Do not pass its answer to `wait()`. `verify()` is a job (`backup-verify`). Everything else is synchronous. A profile also takes `apps`, `databases`, `exclude_databases`, `exclude_tables`, `keep` and `keep_weeks`, and with cipi/api 1.43+ and Cipi CLI 5.13+ `exclude_paths`: paths left out of the app archives, relative to the app (`storage/app/uploads`, `*.mp4`, `shop:storage/app/uploads` for one app only) — a large uploads folder is better kept with rsync; destinations are set up with `configure*()` and `add*Destination()` (above) or on the server, and their credentials are never returned. Reads need the `backups-view` ability, writes `backups-manage`; both need cipi/api 1.39.0+ and Cipi CLI 5.9.0+. Existing tokens do not get them automatically.

### Notifications

Every channel and trigger of the server (API 1.42+, Cipi 5.12+). Secrets are never returned; an update that leaves one out keeps it.

```php
$cipi->notifications()->add('telegram', 'oncall', ['token' => $botToken, 'chat_id' => '-1001234', 'test' => true]);
$cipi->notifications()->add('slack', 'ops', ['url' => 'https://hooks.slack.com/services/…']);
$cipi->notifications()->update('oncall', ['chat_id' => '-1009999']);   // token kept
$cipi->notifications()->test('ops');                                     // ApiException (422) with the reason if refused
$cipi->notifications()->disable('ops');

$cipi->notifications()->setTriggers(['deploy_success' => false, 'ssh_login' => false]);
$cipi->notifications()->setAllTriggers(false, except: ['deploy_fail', 'backup_fail', 'health_fail']);
```

### cipi.yml

An app can ship a `cipi.yml` in its repository that describes the server state it expects: aliases, PHP version and settings, limits, workers, crons, deploy options, backups, and so on. `yml()` reads it and applies it from the panel:

```php
$plan = $cipi->yml()->status('shop')['data'];
$env = $plan['env'] ?? 'production'; // the file is cipi-<env>.yml, or cipi.yml

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

With cipi/api 1.36.0+ and Cipi CLI 5.8.0+ the file follows the app's `env` label: the server reads `cipi-<env>.yml` first (`cipi-staging.yml` for `'env' => 'staging'`) and falls back to `cipi.yml`, and `status()` returns `env` next to `file` so you can tell which one it picked. Older panels omit `env`; treat it as `production`. The same versions accept `deploy.branch` in the file, the Git branch the app deploys, validated like `cipi app edit --branch` (letters, digits, `.`, `_`, `/`, `-`, no `..`); a branch that differs from the app's is one of the `changes`.

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

`wait()` throws `JobFailedException` when the job finishes with `failed`, and `TimeoutException` if it is still running when the timeout elapses. Without `timeoutSeconds` that is **two hours for a deploy** — as long as the panel lets one run (cipi/api 1.48.0+), since the frontend build of a release alone has 30 minutes, and up to an hour where the app asks for it (Cipi 5.18.0+) — the same for a transfer of apps to another server (`sync()->push()`), and 300 seconds for every other job (`Jobs::DEPLOY_WAIT_SECONDS`, `Jobs::WAIT_SECONDS`). A `TimeoutException` ends the wait, not the job: `wait()` on the same id picks it up again. A job the panel itself stops ends as `failed`, with `exit_code` 124.

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
