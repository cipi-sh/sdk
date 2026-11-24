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
     * $order (cipi/api 1.43+, Cipi CLI 5.13+) puts the keys in that order,
     * after $set and $unset: every key the file has then, each once. Comments
     * above a key move with it. An older panel saves $set and $unset and
     * ignores the order — compare array_keys($result['data']['vars']).
     *
     * @param  array<string, scalar|null>  $set
     * @param  list<string>  $unset
     * @param  list<string>|null  $order
     * @return array<string, mixed>
     */
    public function update(string $app, array $set = [], array $unset = [], ?array $order = null): array
    {
        if ($set !== [] && array_is_list($set)) {
            throw new ConfigurationException('Env keys to set must be an associative array.');
        }

        $body = [
            'set' => $set === [] ? new \stdClass : $set,
            'unset' => array_values($unset),
        ];
        if ($order !== null) {
            if ($order === [] || ! array_is_list($order) || count(array_unique($order)) !== count($order)) {
                throw new ConfigurationException('The env order must be a list of keys, each once.');
            }
            $body['order'] = $order;
        }

        return $this->client->put($this->appPath($app).'/env', $body);
    }

    /**
     * Put the keys of the app .env in this order (cipi/api 1.43+, Cipi CLI
     * 5.13+): every key of the file, each once; comments above a key move with
     * it. A key missing or unknown is a 422 (ValidationException).
     *
     * @param  list<string>  $order
     * @return array<string, mixed>
     */
    public function reorder(string $app, array $order): array
    {
        return $this->update($app, [], [], $order);
    }
}
