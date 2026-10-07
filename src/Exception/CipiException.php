<?php

declare(strict_types=1);

namespace Cipi\Sdk\Exception;

use RuntimeException;
use Throwable;

class CipiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $body = [],
        public readonly ?string $method = null,
        public readonly ?string $path = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
