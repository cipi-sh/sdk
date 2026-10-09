<?php

declare(strict_types=1);

namespace Cipi\Sdk\Resources;

final class Ssl extends Resource
{
    /**
     * Issue or reissue the app certificate.
     *
     * Omit the options to reissue as the certificate was last issued. Pass dns: "cloudflare"
     * for DNS-01, wildcard to add or drop *.<apex>, or http: true to go back to HTTP-01.
     * Requires Cipi CLI ≥ 5.5.0 for DNS-01 and ≥ 5.5.1 to reissue DNS-01 without a body.
     *
     * @return array<string, mixed>
     */
    public function install(
        string $app,
        ?string $dns = null,
        ?string $account = null,
        ?bool $wildcard = null,
        ?bool $http = null,
    ): array {
        $body = $this->withoutNulls([
            'dns' => $dns,
            'account' => $account,
            'wildcard' => $wildcard,
            'http' => $http,
        ]);

        return $this->client->post($this->appPath($app).'/ssl', $body === [] ? null : $body);
    }

    /**
     * Re-apply the HTTP to HTTPS redirect without a new certificate.
     *
     * @return array<string, mixed>
     */
    public function force(string $app): array
    {
        return $this->client->post($this->appPath($app).'/ssl/force');
    }

    /**
     * Cloudflare accounts for DNS-01 and the certificates that renew with each.
     * Tokens are never returned. Requires Cipi CLI ≥ 5.5.0.
     *
     * @return array<string, mixed>
     */
    public function dnsAccounts(): array
    {
        return $this->client->get('/ssl/dns');
    }

    /**
     * Add a Cloudflare account or rotate its token. Synchronous, so the token is not queued.
     * Omit $name to use the account named "default".
     *
     * @return array<string, mixed>
     */
    public function setDnsAccount(string $token, ?string $name = null): array
    {
        return $this->client->put('/ssl/dns', $this->withoutNulls([
            'name' => $name,
            'token' => $token,
        ]));
    }

    /**
     * Remove a Cloudflare account. The panel returns 409 while a certificate still renews with it.
     *
     * @return array<string, mixed>
     */
    public function removeDnsAccount(string $account): array
    {
        return $this->client->delete('/ssl/dns/'.$this->client->segment($account));
    }
}
