<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 409 - Conflict
 */
class ConflictException extends HttpException
{
    protected int $statusCode = 409;
    protected string $statusText = 'Conflict';

    public function __construct(
        string $message = 'Conflicto',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
