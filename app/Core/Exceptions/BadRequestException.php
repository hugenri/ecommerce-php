<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 400 - Bad Request
 */
class BadRequestException extends HttpException
{
    protected int $statusCode = 400;
    protected string $statusText = 'Bad Request';

    public function __construct(
        string $message = 'Solicitud incorrecta',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
