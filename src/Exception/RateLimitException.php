<?php

declare(strict_types=1);

namespace Cipi\Sdk\Exception;

use Throwable;

class RateLimitException extends CipiException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        int $status = 429,
        array $body = [],
        ?string $method = null,
        ?string $path = null,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $body, $method, $path, $previous);
    }
}
