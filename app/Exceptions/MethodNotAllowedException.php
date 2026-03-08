<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class MethodNotAllowedException extends RuntimeException
{
    public function __construct(private readonly string $allowedMethods)
    {
        parent::__construct("Method Not Allowed. Allowed: {$allowedMethods}");
    }

    public function getAllowedMethods(): string
    {
        return $this->allowedMethods;
    }
}
