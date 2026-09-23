<?php

namespace App\Core\Exceptions;

/**
 * Clase abstracta base para excepciones HTTP.
 *
 * Cada subclase debe definir sus propios $statusCode y $statusText.
 */
abstract class HttpException extends \Exception
{
    /**
     * @var int Código de estado HTTP
     */
    protected int $statusCode = 500;

    /**
     * @var string Texto descriptivo del estado HTTP
     */
    protected string $statusText = 'Internal Server Error';

    /**
     * @var array Cabeceras HTTP
     */
    protected array $headers = [];

    /**
     * @param string         $message  Mensaje de error
     * @param \Throwable|null $previous Excepción previa
     * @param array          $headers  Cabeceras HTTP
     * @param int            $code     Código de error PHP
     */
    public function __construct(
        string $message = '',
        \Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        $this->headers = $headers;

        parent::__construct($message ?: $this->statusText, $code, $previous);
    }

    /**
     * Obtiene el código de estado HTTP.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtiene el texto descriptivo del estado HTTP.
     */
    public function getStatusText(): string
    {
        return $this->statusText;
    }

    /**
     * Obtiene las cabeceras HTTP.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    // ── Fábricas estáticas ──────────────────────────────────────────

    /**
     * 400 Bad Request
     */
    public static function badRequest(
        string $message = 'Solicitud incorrecta',
        \Throwable $previous = null,
        array $headers = []
    ): BadRequestException {
        return new BadRequestException($message, $previous, $headers);
    }

    /**
     * 401 Unauthorized
     */
    public static function unauthorized(
        string $message = 'No autorizado',
        \Throwable $previous = null,
        array $headers = []
    ): UnauthorizedException {
        return new UnauthorizedException($message, $previous, $headers);
    }

    /**
     * 403 Forbidden
     */
    public static function forbidden(
        string $message = 'Prohibido',
        \Throwable $previous = null,
        array $headers = []
    ): ForbiddenException {
        return new ForbiddenException($message, $previous, $headers);
    }

    /**
     * 404 Not Found
     */
    public static function notFound(
        ?string $uri = null,
        ?string $message = null,
        \Throwable $previous = null,
        array $headers = []
    ): NotFoundException {
        $message ??= sprintf('Ruta no encontrada: %s', $uri ?? '/');
        return new NotFoundException($message, $previous, $headers);
    }

    /**
     * 405 Method Not Allowed
     */
    public static function methodNotAllowed(
        array $allowedMethods,
        ?string $message = null,
        \Throwable $previous = null,
        array $headers = []
    ): MethodNotAllowedException {
        return new MethodNotAllowedException($allowedMethods, $message, $previous, $headers);
    }

    /**
     * 409 Conflict
     */
    public static function conflict(
        string $message = 'Conflicto',
        \Throwable $previous = null,
        array $headers = []
    ): ConflictException {
        return new ConflictException($message, $previous, $headers);
    }

    /**
     * 422 Unprocessable Entity
     */
    public static function unprocessableEntity(
        string $message = 'Entidad no procesable',
        \Throwable $previous = null,
        array $headers = []
    ): UnprocessableEntityException {
        return new UnprocessableEntityException($message, $previous, $headers);
    }

    /**
     * 429 Too Many Requests
     */
    public static function tooManyRequests(
        string $message = 'Demasiadas solicitudes',
        \Throwable $previous = null,
        array $headers = []
    ): TooManyRequestsException {
        return new TooManyRequestsException($message, $previous, $headers);
    }

    /**
     * 500 Internal Server Error
     */
    public static function internalServerError(
        string $message = 'Error interno del servidor',
        \Throwable $previous = null,
        array $headers = []
    ): static {
        return new static($message, $previous, $headers);
    }

    /**
     * 503 Service Unavailable
     */
    public static function serviceUnavailable(
        string $message = 'Servicio no disponible',
        \Throwable $previous = null,
        array $headers = []
    ): static {
        return new static($message, $previous, $headers);
    }
}
