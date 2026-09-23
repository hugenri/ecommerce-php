<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 429 - Too Many Requests
 */
class TooManyRequestsException extends HttpException
{
    protected int $statusCode = 429;
    protected string $statusText = 'Too Many Requests';

    public function __construct(
        string $message = 'Demasiadas solicitudes',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
