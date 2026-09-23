<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Conekta;

use RuntimeException;

/**
 * Error técnico originado por la API REST de Conekta.
 */
class ConektaException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
