<?php

declare(strict_types=1);

namespace Cipi\Sdk\Laravel;

use Cipi\Sdk\Cipi;
use Illuminate\Support\ServiceProvider;

class CipiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/cipi.php', 'cipi');

        $this->app->singleton(Cipi::class, function ($app): Cipi {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('cipi', []);

            return Cipi::connect(
                (string) ($config['base_url'] ?? ''),
                (string) ($config['token'] ?? ''),
                [
                    'timeout' => $config['timeout'] ?? 30,
                    'connect_timeout' => $config['connect_timeout'] ?? 10,
                    'allow_http' => filter_var($config['allow_http'] ?? false, FILTER_VALIDATE_BOOL),
                    'max_retries' => $config['max_retries'] ?? 2,
                ],
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__, 2).'/config/cipi.php' => config_path('cipi.php'),
            ], 'cipi-config');
        }
    }
}
