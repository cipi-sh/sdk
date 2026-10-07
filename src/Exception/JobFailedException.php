<?php

declare(strict_types=1);

namespace Cipi\Sdk\Exception;

use Throwable;

class JobFailedException extends CipiException
{
    /**
     * @param  array<string, mixed>  $job
     */
    public function __construct(
        string $message,
        public readonly array $job,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $job, 'GET', null, $previous);
    }
}
