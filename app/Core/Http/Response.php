<?php

declare(strict_types=1);

namespace App\Core\Http;

class Response
{
    public function json(mixed $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function success(mixed $data, string $message = 'OK', int $statusCode = 200, array $meta = []): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'meta' => $meta,
        ], $statusCode);
    }

    public function error(string $message, int $statusCode = 400, mixed $errors = null): void
    {
        $this->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => [],
        ], $statusCode);
    }

    public function validationError(mixed $errors, string $message = 'Errores de validación.'): void
    {
        $this->error($message, 422, $errors);
    }

    public function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: $url", true, $statusCode);
        exit;
    }
}
