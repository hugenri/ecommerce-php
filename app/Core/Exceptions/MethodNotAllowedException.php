<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 405 - Method Not Allowed
 */
class MethodNotAllowedException extends HttpException
{
    protected int $statusCode = 405;
    protected string $statusText = 'Method Not Allowed';

    /**
     * @var array<string> Métodos HTTP permitidos para esta ruta
     */
    protected array $allowedMethods = [];

    public function __construct(
        array $allowedMethods,
        ?string $message = null,
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        $this->allowedMethods = array_map('strtoupper', $allowedMethods);

        $message ??= sprintf(
            'Método no permitido. Métodos permitidos: %s',
            implode(', ', $this->allowedMethods)
        );

        parent::__construct($message, $previous, $headers);
    }

    /**
     * Obtiene los métodos HTTP permitidos.
     *
     * @return array<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * Incluye la cabecera Allow automáticamente.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $headers = parent::getHeaders();
        $headers['Allow'] = implode(', ', $this->allowedMethods);
        return $headers;
    }
}
