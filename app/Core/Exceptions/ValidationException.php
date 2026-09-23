<?php

namespace App\Core\Exceptions;

class ValidationException extends HttpException
{
    protected int $statusCode = 422;
    protected string $statusText = 'Unprocessable Entity';

    public function __construct(
        string $message = 'Error de validación',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
