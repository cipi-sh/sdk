<?php

declare(strict_types=1);

namespace Cipi\Sdk;

use Cipi\Sdk\Exception\ConfigurationException;

final class Options
{
    public const VERSION = '1.1.0';

    /**
     * @param  (callable(float): void)|null  $sleeper
     */
    public function __construct(
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
        public readonly bool $allowHttp = false,
        public readonly int $maxRetries = 2,
        public readonly string $userAgent = 'cipi-php-sdk/'.self::VERSION,
        public readonly int $maxResponseBytes = 16777216,
        public readonly mixed $sleeper = null,
        public readonly ?Transport $transport = null,
    ) {
        if ($this->timeout < 1 || $this->timeout > 300) {
            throw new ConfigurationException('Timeout must be between 1 and 300 seconds.');
        }
        if ($this->connectTimeout < 1 || $this->connectTimeout > 60) {
            throw new ConfigurationException('Connect timeout must be between 1 and 60 seconds.');
        }
        if ($this->maxRetries < 0 || $this->maxRetries > 5) {
            throw new ConfigurationException('max_retries must be between 0 and 5.');
        }
        if ($this->maxResponseBytes < 1024 || $this->maxResponseBytes > 67108864) {
            throw new ConfigurationException('max_response_bytes must be between 1 KiB and 64 MiB.');
        }
        if (preg_match('/[\r\n\x00]/', $this->userAgent) === 1 || trim($this->userAgent) === '') {
            throw new ConfigurationException('user_agent is empty or contains control characters.');
        }
        if ($this->sleeper !== null && ! is_callable($this->sleeper)) {
            throw new ConfigurationException('sleeper must be a callable.');
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            timeout: self::intOption($options, 'timeout', 30),
            connectTimeout: self::intOption($options, 'connect_timeout', 10),
            allowHttp: (bool) ($options['allow_http'] ?? false),
            maxRetries: self::intOption($options, 'max_retries', 2),
            userAgent: isset($options['user_agent']) ? (string) $options['user_agent'] : 'cipi-php-sdk/'.self::VERSION,
            maxResponseBytes: self::intOption($options, 'max_response_bytes', 16777216),
            sleeper: $options['sleeper'] ?? null,
            transport: isset($options['transport']) && $options['transport'] instanceof Transport
                ? $options['transport']
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function intOption(array $options, string $key, int $default): int
    {
        if (! array_key_exists($key, $options) || $options[$key] === null) {
            return $default;
        }
        if (is_int($options[$key])) {
            return $options[$key];
        }
        if (is_string($options[$key]) && preg_match('/^-?\d+$/', $options[$key]) === 1) {
            return (int) $options[$key];
        }

        throw new ConfigurationException($key.' must be an integer.');
    }
}
