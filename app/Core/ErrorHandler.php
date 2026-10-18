<?php

namespace App\Core;

use App\Core\Exceptions\HttpException;

class ErrorHandler
{
    private static bool $running = false;

    protected bool $debug;

    protected string $basePath;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
        $this->basePath = dirname(__DIR__, 2);
    }

    /**
     * Registra los manejadores globales de errores, excepciones y errores fatales.
     */
    public static function register(bool $debug = false): void
    {
        $handler = new self($debug);

        set_exception_handler([$handler, 'handleException']);
        set_error_handler([$handler, 'handleError']);
        register_shutdown_function([$handler, 'handleShutdown']);
    }

    /**
     * Maneja excepciones no capturadas.
     */
    public function handleException(\Throwable $exception): void
    {
        $this->render($exception);
    }

    /**
     * Convierte errores PHP en ErrorException y los lanza.
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        // Las deprecaciones no son fatales: se registran pero no se convierten
        // en excepciones. Convertirlas provocaba errores 500 (p. ej. los avisos
        // "implicitly nullable" al compilar clases en PHP 8.4+).
        if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            error_log(sprintf('[deprecated] %s en %s:%d', $message, $file, $line));
            return true;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Captura errores fatales al final del script.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [
            E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING,
            E_COMPILE_ERROR, E_COMPILE_WARNING,
        ], true)) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            $this->render($exception);
        }
    }

    protected function render(\Throwable $exception): void
    {
        if (self::$running) {
            $this->renderFallbackSafely($exception);
            return;
        }
        self::$running = true;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $statusCode = $this->getStatusCode($exception);
        $statusText = $this->getStatusText($statusCode);

        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        try {
            if ($this->debug) {
                (new ErrorRenderer())->renderDebug($exception, $statusCode, $statusText, $this->basePath);
            } else {
                (new ErrorRenderer())->renderProduction($statusCode, $statusText);
            }
        } catch (\Throwable $rendererError) {
            // Un fallo del renderizador nunca debe provocar una segunda
            // excepción fatal: se degrada a una respuesta autocontenida.
            $this->renderFallbackSafely($exception);
        }

        exit;
    }

    /**
     * Intenta el fallback enriquecido del renderizador y, si este tambien
     * falla (p. ej. porque la clase no puede cargarse), emite una respuesta
     * de emergencia que solo depende de funciones nativas.
     */
    protected function renderFallbackSafely(\Throwable $exception): void
    {
        try {
            (new ErrorRenderer())->renderFallback($exception);
        } catch (\Throwable $fallbackError) {
            $this->renderEmergency($exception);
        }

        exit;
    }

    /**
     * Respuesta 500 minima y autocontenida. No instancia clases de la
     * aplicacion, por lo que siempre puede emitirse aunque el renderizador
     * falle durante su carga o ejecucion. Los detalles solo van al log.
     */
    protected function renderEmergency(\Throwable $exception): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        error_log(
            '[ErrorHandler] ' . get_class($exception) . ': ' . $exception->getMessage()
            . ' en ' . $exception->getFile() . ':' . $exception->getLine()
        );

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>Error</title></head><body>'
            . '<h1>500 - Internal Server Error</h1></body></html>';
    }

    /**
     * Determina el código HTTP a partir de la excepción.
     *
     * Prioridad:
     *  1. getStatusCode() si el método existe (HttpException y subclases)
     *  2. getCode() si es un código HTTP válido (400-599)
     *  3. Reglas específicas para tipos conocidos
     *  4. 500 por defecto
     */
    protected function getStatusCode(\Throwable $exception): int
    {
        // 1. Excepciones HttpException u otras que implementen getStatusCode()
        if (method_exists($exception, 'getStatusCode')) {
            $code = $exception->getStatusCode();
            if ($code >= 100 && $code <= 599) {
                return $code;
            }
        }

        // 2. Código de la excepción si es un HTTP válido
        $code = $exception->getCode();
        if ($code >= 400 && $code <= 599) {
            return $code;
        }

        // 3. Reglas de fallback para tipos de excepción conocidos
        return match (true) {
            $exception instanceof \InvalidArgumentException,
            $exception instanceof \ValueError           => 400,

            $exception instanceof \PDOException,
            $exception instanceof \Error,
            $exception instanceof \TypeError            => 500,

            default                                      => 500,
        };
    }

    /**
     * Devuelve el texto estándar de un código HTTP.
     */
    protected function getStatusText(int $statusCode): string
    {
        return match ($statusCode) {
            // 2xx Success
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',

            // 3xx Redirection
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',

            // 4xx Client Error
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            418 => "I'm a Teapot",
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',

            // 5xx Server Error
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',

            // Fallback para códigos no listados
            default => 'Error',
        };
    }

}
