<?php

declare(strict_types=1);

namespace Cipi\Sdk\Exception;

class ValidationException extends CipiException
{
    /**
     * Laravel validation bag when the panel returns `errors`.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        $errors = $this->body['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }
}
