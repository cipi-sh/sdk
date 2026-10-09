<?php

declare(strict_types=1);

namespace Cipi\Sdk\Laravel\Facades;

use Cipi\Sdk\Cipi as CipiClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Cipi\Sdk\Resources\Apps apps()
 * @method static \Cipi\Sdk\Resources\Limits limits()
 * @method static \Cipi\Sdk\Resources\BasicAuth basicAuth()
 * @method static \Cipi\Sdk\Resources\Env env()
 * @method static \Cipi\Sdk\Resources\AuthJson authJson()
 * @method static \Cipi\Sdk\Resources\Artisan artisan()
 * @method static \Cipi\Sdk\Resources\Run run()
 * @method static \Cipi\Sdk\Resources\DeployConfig deployConfig()
 * @method static \Cipi\Sdk\Resources\Logs logs()
 * @method static \Cipi\Sdk\Resources\Aliases aliases()
 * @method static \Cipi\Sdk\Resources\Www www()
 * @method static \Cipi\Sdk\Resources\Redirects redirects()
 * @method static \Cipi\Sdk\Resources\Proxies proxies()
 * @method static \Cipi\Sdk\Resources\NodeApps node()
 * @method static \Cipi\Sdk\Resources\Deploys deploys()
 * @method static \Cipi\Sdk\Resources\Ssl ssl()
 * @method static \Cipi\Sdk\Resources\Databases databases()
 * @method static \Cipi\Sdk\Resources\PhpVersions php()
 * @method static \Cipi\Sdk\Resources\SshKeys ssh()
 * @method static \Cipi\Sdk\Resources\Services services()
 * @method static \Cipi\Sdk\Resources\Smtp smtp()
 * @method static \Cipi\Sdk\Resources\Health health()
 * @method static \Cipi\Sdk\Resources\Search search()
 * @method static \Cipi\Sdk\Resources\Packages packages()
 * @method static \Cipi\Sdk\Resources\Monitor monitor()
 * @method static \Cipi\Sdk\Resources\Disk disk()
 * @method static \Cipi\Sdk\Resources\ZeroTrust zeroTrust()
 * @method static \Cipi\Sdk\Resources\Jobs jobs()
 * @method static \Cipi\Sdk\Resources\Server server()
 * @method static \Cipi\Sdk\Resources\IpWhitelist ipWhitelist()
 * @method static array<string, mixed> wait(string|array $job, int $timeoutSeconds = 300, float $intervalSeconds = 1.0)
 * @method static array<string, mixed> request(string $method, string $path, ?array $json = null, array $query = [])
 *
 * @see CipiClient
 */
class Cipi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CipiClient::class;
    }
}
