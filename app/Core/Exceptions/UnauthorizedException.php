<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 401 - Unauthorized
 */
class UnauthorizedException extends HttpException
{
    protected int $statusCode = 401;
    protected string $statusText = 'Unauthorized';

    public function __construct(
        string $message = 'No autorizado',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
