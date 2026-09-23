<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 422 - Unprocessable Entity
 */
class UnprocessableEntityException extends HttpException
{
    protected int $statusCode = 422;
    protected string $statusText = 'Unprocessable Entity';

    public function __construct(
        string $message = 'Entidad no procesable',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
