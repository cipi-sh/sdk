<?php

declare(strict_types=1);

namespace Cipi\Sdk;

use Cipi\Sdk\Exception\TransportException;

final class CurlTransport implements Transport
{
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        int $connectTimeout,
        int $maxResponseBytes,
        bool $allowHttp,
    ): array {
        if (! function_exists('curl_init')) {
            throw new TransportException('The curl extension is required.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Unable to initialize the HTTP client.');
        }

        $responseHeaders = [];
        $received = 0;
        $payload = '';

        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$payload, &$received, $maxResponseBytes): int {
                $size = strlen($chunk);
                $received += $size;
                if ($received > $maxResponseBytes) {
                    return 0;
                }
                $payload .= $chunk;

                return $size;
            },
        ];

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $curlOptions[CURLOPT_PROTOCOLS_STR] = $allowHttp ? 'http,https' : 'https';
            $curlOptions[CURLOPT_REDIR_PROTOCOLS_STR] = '';
        } else {
            $curlOptions[CURLOPT_PROTOCOLS] = $allowHttp ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : CURLPROTO_HTTPS;
            $curlOptions[CURLOPT_REDIR_PROTOCOLS] = 0;
        }

        if ($body !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $curlOptions);

        $ok = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($ok === false || $errno !== 0) {
            $safe = $error !== '' ? $error : 'HTTP request failed.';
            if ($received > $maxResponseBytes) {
                $safe = 'The API response exceeded the configured size limit.';
            }

            throw new TransportException($safe);
        }

        return [
            'status' => $status,
            'body' => $payload,
            'headers' => $responseHeaders,
        ];
    }
}
