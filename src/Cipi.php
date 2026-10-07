<?php

declare(strict_types=1);

namespace Cipi\Sdk;

use Cipi\Sdk\Resources\Aliases;
use Cipi\Sdk\Resources\Apps;
use Cipi\Sdk\Resources\Artisan;
use Cipi\Sdk\Resources\AuthJson;
use Cipi\Sdk\Resources\BasicAuth;
use Cipi\Sdk\Resources\Databases;
use Cipi\Sdk\Resources\DeployConfig;
use Cipi\Sdk\Resources\Deploys;
use Cipi\Sdk\Resources\Env;
use Cipi\Sdk\Resources\Health;
use Cipi\Sdk\Resources\IpWhitelist;
use Cipi\Sdk\Resources\Jobs;
use Cipi\Sdk\Resources\Logs;
use Cipi\Sdk\Resources\Monitor;
use Cipi\Sdk\Resources\NodeApps;
use Cipi\Sdk\Resources\Packages;
use Cipi\Sdk\Resources\PhpVersions;
use Cipi\Sdk\Resources\Proxies;
use Cipi\Sdk\Resources\Redirects;
use Cipi\Sdk\Resources\Run;
use Cipi\Sdk\Resources\Search;
use Cipi\Sdk\Resources\Server;
use Cipi\Sdk\Resources\Services;
use Cipi\Sdk\Resources\Smtp;
use Cipi\Sdk\Resources\SshKeys;
use Cipi\Sdk\Resources\Ssl;
use Cipi\Sdk\Resources\Www;
use Cipi\Sdk\Resources\ZeroTrust;

final class Cipi
{
    public const VERSION = Options::VERSION;

    private readonly Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function connect(string $baseUrl, string $token, array $options = []): self
    {
        return new self(new Client($baseUrl, $token, Options::fromArray($options)));
    }

    public function apps(): Apps
    {
        return new Apps($this->client);
    }

    public function basicAuth(): BasicAuth
    {
        return new BasicAuth($this->client);
    }

    public function env(): Env
    {
        return new Env($this->client);
    }

    public function authJson(): AuthJson
    {
        return new AuthJson($this->client);
    }

    public function artisan(): Artisan
    {
        return new Artisan($this->client);
    }

    public function run(): Run
    {
        return new Run($this->client);
    }

    public function deployConfig(): DeployConfig
    {
        return new DeployConfig($this->client);
    }

    public function logs(): Logs
    {
        return new Logs($this->client);
    }

    public function aliases(): Aliases
    {
        return new Aliases($this->client);
    }

    public function www(): Www
    {
        return new Www($this->client);
    }

    public function redirects(): Redirects
    {
        return new Redirects($this->client);
    }

    public function proxies(): Proxies
    {
        return new Proxies($this->client);
    }

    public function node(): NodeApps
    {
        return new NodeApps($this->client);
    }

    public function deploys(): Deploys
    {
        return new Deploys($this->client);
    }

    public function ssl(): Ssl
    {
        return new Ssl($this->client);
    }

    public function databases(): Databases
    {
        return new Databases($this->client);
    }

    public function php(): PhpVersions
    {
        return new PhpVersions($this->client);
    }

    public function ssh(): SshKeys
    {
        return new SshKeys($this->client);
    }

    public function services(): Services
    {
        return new Services($this->client);
    }

    public function smtp(): Smtp
    {
        return new Smtp($this->client);
    }

    public function health(): Health
    {
        return new Health($this->client);
    }

    public function search(): Search
    {
        return new Search($this->client);
    }

    public function packages(): Packages
    {
        return new Packages($this->client);
    }

    public function monitor(): Monitor
    {
        return new Monitor($this->client);
    }

    public function zeroTrust(): ZeroTrust
    {
        return new ZeroTrust($this->client);
    }

    public function jobs(): Jobs
    {
        return new Jobs($this->client);
    }

    public function server(): Server
    {
        return new Server($this->client);
    }

    public function ipWhitelist(): IpWhitelist
    {
        return new IpWhitelist($this->client);
    }

    /**
     * Poll a 202 response, or a job id, until the job finishes.
     *
     * @param  string|array<string, mixed>  $job
     * @return array<string, mixed>
     */
    public function wait(string|array $job, int $timeoutSeconds = 300, float $intervalSeconds = 1.0): array
    {
        return $this->jobs()->wait($job, $timeoutSeconds, $intervalSeconds);
    }

    /**
     * Escape hatch for an endpoint this release does not wrap yet.
     * The path is relative to `/api`, for example `/apps`.
     *
     * @param  array<string, mixed>|null  $json
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $json = null, array $query = []): array
    {
        return $this->client->request($method, $path, $json, $query);
    }

    /**
     * @return array{version: string}
     */
    public function __debugInfo(): array
    {
        return ['version' => self::VERSION];
    }
}
