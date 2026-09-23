<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 404 - Not Found
 */
class NotFoundException extends HttpException
{
    protected int $statusCode = 404;
    protected string $statusText = 'Not Found';

    public function __construct(
        string $message = 'Página no encontrada',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
