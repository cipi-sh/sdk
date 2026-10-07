<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

use Cipi\Sdk\Exception\ConfigurationException;

final class Env extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $app): array
    {
        return $this->client->get($this->appPath($app).'/env');
    }

    /**
     * Merge keys into the app .env. Values in $unset are removed.
     * Requires Cipi CLI 5.0.3+ and the apps-env ability.
     *
     * @param  array<string, scalar|null>  $set
     * @param  list<string>  $unset
     * @return array<string, mixed>
     */
    public function update(string $app, array $set = [], array $unset = []): array
    {
        if ($set !== [] && array_is_list($set)) {
            throw new ConfigurationException('Env keys to set must be an associative array.');
        }

        return $this->client->put($this->appPath($app).'/env', [
            'set' => $set === [] ? new \stdClass : $set,
            'unset' => array_values($unset),
        ]);
    }
}
