<?php

namespace App\Core\Exceptions;

/**
 * Excepción HTTP 500 - Database Error
 */
class DatabaseException extends HttpException
{
    protected int $statusCode = 500;
    protected string $statusText = 'Internal Server Error';

    /**
     * @var string|null Consulta SQL que causó el error
     */
    protected ?string $sqlQuery = null;

    /**
     * @var array Parámetros de la consulta
     */
    protected array $sqlParams = [];

    public function __construct(
        string $message = 'Error de base de datos',
        ?string $sqlQuery = null,
        array $sqlParams = [],
        ?\Throwable $previous = null,
        array $headers = [],
        bool $hideSensitiveDetails = false
    ) {
        $this->sqlQuery = $sqlQuery;
        $this->sqlParams = $sqlParams;

        // En producción, ocultar detalles sensibles
        if ($hideSensitiveDetails) {
            $message = 'Error de base de datos';
        }

        parent::__construct($message, $previous, $headers);
    }

    /**
     * Obtiene la consulta SQL.
     */
    public function getSqlQuery(): ?string
    {
        return $this->sqlQuery;
    }

    /**
     * Obtiene los parámetros de la consulta.
     *
     * @return array
     */
    public function getSqlParams(): array
    {
        return $this->sqlParams;
    }
}
