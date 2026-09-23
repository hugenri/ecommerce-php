<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 403 - Forbidden
 */
class ForbiddenException extends HttpException
{
    protected int $statusCode = 403;
    protected string $statusText = 'Forbidden';

    public function __construct(
        string $message = 'Prohibido',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $previous, $headers);
    }
}
